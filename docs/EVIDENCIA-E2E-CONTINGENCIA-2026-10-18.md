# Evidencia E2E — Contingencia Fiscal (Fase OFFLINE)

- Fecha: 2026-10-18
- Hora local: 14:51 (America/El_Salvador) · UTC 20:51
- Terminal: kiosko POS (host `DESKTOP-668SAI0`), sesión 1 (`console`), usuario `pos-terminal`
- App: `boomwalos-pos` en `C:\pos\boomwalos-pos`
- Config relevante: `APP_ENV=production`, `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database`,
  `FISCAL_GATEWAY=http`, `FISCAL_API_URL=http://127.0.0.1:1` (simulación de corte), sin
  `FISCAL_PROBE_TIMEOUT` (default 3 s)
- Ejecutor de verificación: `php artisan tinker` (PHP 8.3)
- Resultado global: **PASS**

## Escenario

Corte de conectividad hacia la API fiscal. El puerto destino está cerrado (`127.0.0.1:1`),
por lo que la sonda `fsockopen` (`FiscalOutboxService::hayConexion()`) falla y el
`EnviarVentasFiscalesJob` lanza `Illuminate\Http\Client\ConnectionException`. La venta se marca
como contingencia, se difiere (backoff) y se emite un comprobante transitorio no-fiscal que el
cliente conserva hasta que el DTE firmado se entregue al restablecerse la transmisión.

## Pasos ejecutados

1. Cobrar una venta de prueba (efectivo) en el kiosko.
2. Consultar `cola_ventas_fiscales`, `ventas_fiscales_pos` y `trabajo_impresion` vía `tinker`.

## Evidencia 1 — `cola_ventas_fiscales` (último registro)

```
[estado]                => PENDIENTE
[contingencia]          => 1
[contingencia_desde]    => 2026-10-18T20:51:26.000000Z
[proximo_intento_at]    => 2026-10-18T20:52:28.000000Z
[intentos]              => 1
```

## Evidencia 2 — `ventas_fiscales_pos` (último registro)

```
[estado]            => App\Enums\EstadoVentaFiscal::NO
[codigo_generacion] => 804E1EBA-24FE-44C9-B066-EE24E5ED9D76
[fecha_emision]     => 2026-10-18 14:51:26 (America/El_Salvador)
```

## Evidencia 3 — Ticket de cliente (`trabajo_impresion`, `tipo_trabajo=TICKET`)

```
BOOMWALOS CENTRO HISTÓRICO
TICKET DE CLIENTE
MESA 2
Pedido: 1
Fecha: 18/10/2026 14:51
--------------------------------
1 x Cafe  $1.00
--------------------------------
TOTAL  $1.00
PAGO   Efectivo
RECIBIDO $1.00
CAMBIO  $0.00

ATENDIDO POR: Administrador General
--------------------------------
COMPROBANTE TRANSITORIO
MODELO DE FACTURACIÓN: DIFERIDO
TIPO DE TRANSMISIÓN: POR CONTINGENCIA
Código de generación: 804E1EBA-24FE-44C9-B066-EE24E5ED9D76
Fecha y hora de emisión: 18/10/2026 14:51
Este comprobante no sustituye al Documento
Tributario Electrónico. Conserve este ticket;
el DTE firmado se le entregará al restablecerse
el servicio de transmisión.
--------------------------------
¿DESEA FACTURA O CCF?
Escanea el QR o ingresa a:
https://boomwalos.vercel.app
Tracking: POS-261018143626-5484
QR_URL: https://boomwalos.vercel.app/?tracking=POS-261018143626-5484
--------------------------------
¡GRACIAS POR SU PREFERENCIA!
```

## Criterios de aceptación

| # | Criterio | Esperado | Obtenido | Estado |
|---|----------|----------|----------|--------|
| 1 | Venta diferida en contingencia | `cola.contingencia=1` | `1` | PASS |
| 2 | `fecInicio` de contingencia registrada | timestamp con hora | `20:51:26Z` | PASS |
| 3 | Backoff programado | `proximo_intento_at = +1 min` | `20:52:28Z` (+1 min) | PASS |
| 4 | Intento contabilizado | `intentos=1` | `1` | PASS |
| 5 | Estado de venta | `NO` (no sincronizada) | `NO` | PASS |
| 6 | UUID de generación | UUID v4 persistido | `804E1EBA-…` | PASS |
| 7 | `fecha_emision` = hora local | 14:51 SV | `14:51:26` | PASS |
| 8 | Leyenda transitoria en ticket | texto DIFERIDO / CONTINGENCIA | presente | PASS |
| 9 | UUID impreso coincide con la venta | `804E1EBA-…` | coincide | PASS |

Payload enviado al aceptar la contingencia (por diseño):

```
tipoModelo = 2 (diferido)
tipoOperacion = 2 (contingencia)
tipoContingencia = 3 (falla de internet del emisor, CAT-005)
```

## Fase ONLINE — Reconciliación por pull

- Config de la prueba: `FISCAL_GATEWAY=mock` (el mock no usa red; `FISCAL_API_URL` sin cambios)
- Scheduler `schedule:work` detenido durante la prueba para evitar que `pos:enviar-fiscales-pendientes`
  ganara la carrera a la reconciliación
- Resultado global: **PASS**

### Comando y salida

```
php artisan pos:reconciliar-fiscales
Ventas selladas reconciliadas: 1
```

### Evidencia — `ventas_fiscales_pos` (último registro)

```
[estado]           => App\Enums\EstadoVentaFiscal::SINCRONIZADO
[numero_control]   => MOCK-CONTROL
[sello_recepcion]  => MOCK-SELLO
[sincronizado_at]  => 2026-10-18 15:02:20 (America/El_Salvador)
```

### Criterios de aceptación

| # | Criterio | Esperado | Obtenido | Estado |
|---|----------|----------|----------|--------|
| 1 | Reconciliación por consulta sella la venta | `selladas = 1` | `1` | PASS |
| 2 | Transición de estado | `NO → SINCRONIZADO` | `SINCRONIZADO` | PASS |
| 3 | Sello persistido | valor recibido | `MOCK-SELLO` | PASS |
| 4 | Número de control persistido | valor recibido | `MOCK-CONTROL` | PASS |
| 5 | Fecha de sincronización registrada | timestamp | `15:02:20` | PASS |
| 6 | Cola marcada como enviada | `cola.estado=ENVIADO` | `ENVIADO` | PASS |

### Nota

La **reimpresión del DTE final** no se ejecutó: sin impresora `TICKET` configurada,
`encolarReimpresionDteFinal()` hace `return` y no crea el job. Pendiente para la entrega
de la impresora física.

## Incidencias y correcciones durante la sesión

- **Kiosko invisible (causa raíz):** la tarea programada `\pos-watchdog` corría en la
  **sesión 0 (services)** y, al relanzar `kiosk.cmd`, creaba el Chrome del kiosko en sesión 0
  (invisible; sin ventana ni icono). Evidencia: procesos Chrome del perfil `C:\pos-kiosk-profile`
  con `SessionId=0` mientras `pos-terminal`/`explorer` estaban en `SessionId=1`.
  **Corrección aplicada:**

  ```powershell
  $t = Get-ScheduledTask -TaskName 'pos-watchdog'
  $p = $t.Principal
  $p.LogonType = 'Interactive'
  Set-ScheduledTask -TaskName 'pos-watchdog' -Principal $p
  ```

  Tras relanzar con `schtasks /run /tn "\pos-startup"`, los procesos `php` y el Chrome del
  kiosko quedaron en **sesión 1** (visible) y la ventana abrió correctamente.
- **Watchdog desactivado durante la prueba** (`schtasks /change /tn "\pos-watchdog" /disable`)
  para evitar que reiniciara el stack a mitad de la E2E. Se rehabilita al finalizar con
  `schtasks /change /tn "\pos-watchdog" /enable`.
- **Comillas en PowerShell:** al pasar código PHP a un ejecutable nativo (`php.exe`), usar
  comillas dobles fuera con `$` escapado por backtick (`` `$v ``) y comillas simples para los
  literales PHP. Ejemplo:

  ```powershell
  php artisan tinker --execute="`$v=App\Models\VentaFiscalPos::latest('id')->first(); print_r(`$v->only(['estado','codigo_generacion','fecha_emision']));"
  ```

## Configuración usada para reproducir

`.env` (fragmento, estado de la prueba):

```
FISCAL_GATEWAY=http
FISCAL_API_URL=http://127.0.0.1:1
FISCAL_API_TIMEOUT=15
FISCAL_MOCK_ENABLED=false
```

## Pendiente (no cubierto en esta evidencia)

- **Fase ONLINE** (reconciliación por pull → `SINCRONIZADO` + sello + reimpresión del DTE final).
- Prueba con ApiFact real (staging) y/o impresora física.
- Brecha detectada: `FiscalOutboxService::enviar()` marca `SINCRONIZADO` sin sello si el envío
  tiene éxito; el scheduler (`pos:enviar-fiscales-pendientes`) podría dejar la reimpresión del
  DTE final sin disparar. Anotado como pendiente post-entrega.
