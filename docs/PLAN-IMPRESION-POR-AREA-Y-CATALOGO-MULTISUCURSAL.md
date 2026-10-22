# Plan — Impresión por área + Catálogo por sucursal

- Fecha: 2026-10-22
- App: `boomwalos-pos` (repositorio `C:\laragon\www\boomwalos-pos`) · terminal en `C:\pos\boomwalos-pos`
- Estado: **Fase 1 implementada (`56466bd` + soporte Windows compartida `8dfdbb0`); Fase 2 implementada en código el 2026-10-22, pendiente cargar el catálogo de la sucursal nueva**
- Alcance: 2 fases ortogonales — Fase 1 (impresión por área), Fase 2 (catálogo por sucursal)

## Objetivo

1. Imprimir **comandas segmentadas por área de preparación** (COCINA, BAR, PUPUSAS) en sus
   respectivas ticketeras, y **tickets a la ticketera de CAJA**.
2. Separar el **catálogo por sucursal**: 2 sucursales (la actual + una nueva) con categorías,
   productos y precios distintos.

## Decisiones cerradas

- **Áreas fijas** (enum): `COCINA | BAR | PUPUSAS`.
- **Mapeo por categoría**, con herencia del grupo padre.
- **"Para compartir" es una categoría** (no un área) → se enruta a **COCINA**. No hay combos
  reales en uso; cualquier combo cae a **COCINA**.
- **Fallback de impresora: SOLO dentro de la misma sucursal** (nunca otra sucursal ni global).
- **Caja imprime solo tickets.** **No** hay reimpresión por área.
- **Catálogo multi-sucursal:** cada categoría/producto/combo se **asigna a una sucursal**;
  **no se comparte** catálogo entre sucursales (los precios y productos son diferentes).
- **`TandaPedido` / `QueueKitchenBatch` / `KitchenService` NO se tocan**: son código muerto
  (ver Anexo A). El camino real de comandas es `PedidoService::sendPendingBatch` y
  `CobroService::createComandaJob`.
- **Orden de ejecución sugerido:** Fase 1 primero (no requiere hardware nuevo para desplegarse).

---

## Contexto del código actual

### Impresión (solo 2 tipos, 1 comanda por envío)

- `Impresora.tipo` ∈ {`TICKET`, `COMANDA`}; **sin concepto de área**.
- `Impresora::buscar($tipo, $establecimientoId)` (`app/Models/Impresora.php:65-83`): solo
  `activa=true`, prioriza establecimiento y cae a global (`establecimiento_id = null`).
- Puntos de despacho de **comanda** (a adaptar):
  1. `PedidoService::sendPendingBatch` (`app/Services/PedidoService.php:504-535`)
     ← `OrderEntry::sendToKitchen` (`app/Filament/Pages/Pos/OrderEntry.php:544-565`).
  2. `CobroService::createComandaJob` (`app/Services/CobroService.php:204-230`).
  3. `QueueKitchenBatch::handle` (legado, sin llamadores; ver Anexo A).
- Puntos de **ticket** (a CAJA; sin cambios funcionales salvo un fix):
  `QueueCustomerTicket.php:28`, `CobroService.php:235`, `FiscalOutboxService.php:245`,
  `ReprintTicket.php:29`.
- `ChargeAndSend` retorna `[$pago, $comandaJob, $ticketJob]` (`CobroService.php:199`).
- `RenderKitchenComanda` ya acepta un subconjunto `$detalles`.
- Cola de impresión: `ProcessPrintJob` (`tries=3`, `backoff=5`) → `EscPosPrintService::print()`
  marca `IMPRESO` tras `close()`.
- Panel: `/admin/monitor-impresion` (`PrintMonitor.php`); dependencias en
  `PosOperationSettings.php:166-167`.
- CLI de prueba: `php artisan pos:ticket-demo --pedido=ID [--solo-texto]`.

### Catálogo (hoy global)

- Modelos `Categoria` (jerarquía `parent_id`/`children`, `activa`, `icono`), `Producto`
  (precio único en `productos.precio`), `Combo` (sin `categoria_id`). **Ninguno tiene
  `establecimiento_id`.**
- Consumidores principales: `OrderEntry::getCategoriesProperty` /
  `getRootCategoriesProperty` / `getProductsProperty` (`OrderEntry.php:770-830`) y las
  resources de admin (`CategoriaResource`, `ProductoResource`, `ComboResource`, tree view).
- Contexto de sucursal ya existe: `EstablishmentContext`/`EstablishmentSelection`,
  `config/pos.php` (`require_explicit_establishment`).

---

## FASE 1 — Impresión por área

### 1. Migraciones (SQLite-safe: `string` nullable, NO `enum`)

| Migración | Cambio |
|---|---|
| `..._add_area_to_impresoras_table.php` | `impresoras.area` (solo aplica a `COMANDA`) |
| `..._add_area_to_categorias_table.php` | `categorias.area` |
| `..._add_area_to_trabajo_impresion_table.php` | `trabajo_impresion.area` (trazabilidad/monitor) |

- **Backfill:** la impresora COMANDA actual (SEWOO `192.168.0.205`) queda `area = null`,
  es decir, **default de su sucursal**.
- `down()` revierte cada columna.

### 2. Enum

- `app/Enums/AreaPreparacion.php`: `COCINA | BAR | PUPUSAS`, con `label()` (mismo estilo que
  `TipoImpresora`).

### 3. Modelos

- `app/Models/Impresora.php`
  - `$fillable` + cast `'area' => AreaPreparacion::class`.
  - `scopePorArea(Builder, ?AreaPreparacion)`.
  - `buscarPorArea(TipoImpresora $tipo, ?AreaPreparacion $area, ?int $establecimientoId): ?self`
    con orden **estrictamente dentro de la misma sucursal**:
    1. `area` + sucursal
    2. `area = null` + sucursal (default de la sucursal)
    3. cualquier COMANDA activa de la sucursal ("a la disponible")
    4. `null` (el caller marca `ERROR`)
  - **Dejar `buscar()` intacto** para tickets.
- `app/Models/Categoria.php`
  - cast `'area' => AreaPreparacion::class`.
  - `areaResuelta(): ?AreaPreparacion` = `area ?? parent?->areaResuelta()`.
- `app/Models/TrabajoImpresion.php`
  - `$fillable` + cast `area`.

### 4. Servicio nuevo: `app/Services/Printing/ComandaDispatcher.php`

- `dispatch(Pedido $pedido, Collection $lineas): Collection<TrabajoImpresion>`
- Pasos:
  1. Agrupar líneas por `areaResuelta()` (combo/`null` → `COCINA`).
  2. Por área: `Impresora::buscarPorArea(COMANDA, $area, $pedido->establecimiento_id)`.
  3. `render($pedido, $subset, $area)`.
  4. `firstOrCreate(['original_uid' => hash('sha256', "$id|COMANDA|$area|$lineIds")], [...])`
     — el área entra en el `uid` para evitar colisiones.
  5. `DetallePedido.tanda_id` = job de su área.
  6. `ProcessPrintJob::dispatch` solo si hay impresora; si no → `estado = ERROR`,
     `ultimo_error = "No hay impresora de comanda (área X) en esta sucursal."`.

### 5. Renderer

- `RenderKitchenComanda::render(Pedido $pedido, ?iterable $detalles = null, ?AreaPreparacion $area = null)`:
  encabezado `COMANDA` o `COMANDA – BAR` según área.

### 6. Callers a ajustar

- `PedidoService::sendPendingBatch` (`PedidoService.php:504`) → delega en `ComandaDispatcher`,
  **retorna colección** de jobs.
  - `OrderEntry::sendToKitchen` (`OrderEntry.php:544-565`) hoy usa `$job->estado` → cambiar a
    mensaje "Comanda enviada a N área(s) (M en cola)".
- `CobroService::createComandaJob` (`CobroService.php:204-230`) → delega en `ComandaDispatcher`;
  `chargeAndSend` retorna `[$pago, Collection<int, TrabajoImpresion> $comandaJobs, $ticketJob]`.
  - **Consumidores que rompen y hay que actualizar:**
    `app/Filament/Pages/Pos/ChargeOrder.php:139` (`[, $comandaJob, $ticketResult]`) y
    `tests/Feature/PosFlowConfigurationTest.php:208,231`.
  - **No rompen** (omiten la comanda): `OrderEntry.php:665`, `tests/Feature/CardPaymentTest.php:82`.
- Fix puntual: `CobroService.php:235` llama `Impresora::buscar(TipoImpresora::TICKET)` sin
  establecimiento → pasar `$pedido->establecimiento_id` para consistencia.

### 7. UI (Filament)

- `ImpresoraResource`: `Select::make('area')` visible/obligatorio si `tipo = COMANDA`; columna
  Área. Validación de unicidad sugerida `(establecimiento_id, tipo, area)`.
- `CategoriaResource`: `Select::make('area')` (nullable, leyenda "Hereda del grupo"); columna Área.
- `PrintMonitor`: columna **Área** (+ filtro).
- `PosOperationSettings` (`:166-167`): reemplazar el check de comanda único por estado **por área**.

### 8. Tests

- Nuevos: `ComandaAreaSplitTest` (3 productos/3 áreas → 3 jobs, contenido y `tanda_id` correctos),
  `ComandaFallbackTest` (sin BAR → default de la sucursal; sin ninguna → `ERROR`),
  `ComandaSucursalAislamientoTest` (no usa impresora de otra sucursal), `CategoriaAreaTest`
  (herencia grupo → categoría).
- Ajustar: `PosFlowConfigurationTest`, `CashCloseFlowTest` (destructura del retorno).

---

## FASE 2 — Catálogo por sucursal (sin compartir)

### 1. Migraciones

- `categorias.establecimiento_id` (FK `nullOnDelete`, indexado).
- `productos.establecimiento_id` (FK `nullOnDelete`, indexado).
- `combos.establecimiento_id` (FK `nullOnDelete`, indexado).
- Las columnas quedan `nullable()` a nivel esquema solo para no romper migración/seeders, pero
  **la UI las exige**.

### 2. Datos (backfill)

- Asignar el catálogo actual a la **sucursal existente**.
- Cargar categorías, productos y precios de la **sucursal nueva** (precio distinto = fila propia).

### 3. Filtrado en el POS (estricto por sucursal)

- `OrderEntry::getCategoriesProperty / getRootCategoriesProperty / getProductsProperty`
  (`OrderEntry.php:770-830`): `where('establecimiento_id', $ctxId)`.
- **Incluir la sucursal en la cache key** de `cachedProducts` y afines.
- `ListCategories` (tree view) y `ComboResource` (opciones de productos): mismo filtro.

### 4. UI admin

- `CategoriaResource` / `ProductoResource` / `ComboResource`: `Select establecimiento_id`
  (requerido), columna y filtro Sucursal.

### 5. Tests

- Catálogo filtrado por sucursal; producto presente en una sola sucursal; mismo producto con
  precios distintos = dos filas.

---

## Despliegue al monitor

1. `git pull` en `C:\pos\boomwalos-pos`.
2. `php artisan migrate --force` (migraciones portables SQLite; el monitor usa
   `DB_CONNECTION=sqlite`).
3. Reiniciar el stack: `Stop-ScheduledTask \pos-watchdog` → matar procesos `php` →
   `Start-ScheduledTask \pos-startup` → `Start-ScheduledTask \pos-watchdog`.
4. Verificar **4 procesos** (`artisan serve`, `queue:work`, `schedule:work`, `php -S`) y
   `php -m` con `pdo_sqlite`/`sqlite3`.
5. Configurar áreas en `/admin/impresoras` y en las categorías; probar "Probar" → comanda → ticket.

## Riesgos

- **SQLite del monitor:** usar `string`, nunca `enum` en migraciones.
- **Duplicados activos por área:** `buscarPorArea` toma el primero → validar unicidad en la UI.
- **Jobs viejos** `PENDIENTE`/`ERROR` con impresoras obsoletas → reintentar/ignorar en el monitor.
- **Fase 1 desplegable sin Fase 2** (ortogonales); Fase 2 sin Fase 1 no rompe impresión.
- **Pendiente de hardware:** las 4 ticketeras (COCINA y CAJA aún no conectadas); la Fase 1 es
  desplegable y queda inerte hasta configurar las áreas.

---

## Anexo A — `TandaPedido` es código muerto

Diseño original (tabla `tandas_pedido`): "tanda/ronda de cocina" (KDS) que agrupaba las líneas
enviadas a cocina, con estado `PENDIENTE → EN_PREPARACION → LISTA → ENTREGADA` (`CANCELADA`),
`KitchenService::transition()` y `QueueKitchenBatch` (imprimía `COMANDA – TANDA N`).

Evidencia de que hoy no se usa:

- **Nadie crea tandas:** no existe `TandaPedido::create(...)` en el código de la app.
- **`KitchenService` sin llamadores:** solo su propia definición; ningún `app(KitchenService::class)`.
- **`QueueKitchenBatch` nunca se invoca:** `KitchenDispatcherInterface` solo está *bindeada* en
  `AppServiceProvider.php:36` y afirmada por `tests/Feature/SolidContractsTest.php:19`.
- **La FK cambió de dueño:** `detalles_pedido.tanda_id` fue redirigida de `tandas_pedido` a
  `trabajo_impresion` (`2026_08_17_000003_redirect_tanda_fk_to_trabajo_impresion.php`); hoy
  `tanda_id` significa "lote de impresión".
- `CocinaReport` (`ReportesService.php:269`) lee `tandas_pedido` → siempre vacío en la práctica.

**Consecuencia:** no se adapta en este plan; el modelo de comandas vigente es
`trabajo_impresion`.

## Anexo B — Pendientes ajenos a este plan

- Impresoras COCINA y CAJA ausentes de la red (`192.168.0.204/205`); conectar/fijar IPs.
- Colas de Windows en desorden (`PendingDeletion`, puerto inválido `192.168.0..205`,
  `192.168.1.16`, `LPT1:`) + "Modo de impresión protegida" ON.
- FK `restrictOnDelete` en `trabajo_impresion.impresora_id` impide borrar impresoras →
  decidir `DeleteAction` deshabilitada vs `SoftDeletes`.
- Apertura de gaveta por red (falta protocolo del hardware) y `AnyDesk ID` del monitor.
