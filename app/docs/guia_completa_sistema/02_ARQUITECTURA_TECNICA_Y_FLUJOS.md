# 02. Arquitectura Técnica y Flujos Operativos

## 1. Arquitectura General del Sistema

Boomwalos POS sigue el patrón **Service Layer** en Laravel, separando la lógica de negocio de los controladores y componentes visuales de Filament.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        CAPAS DE LA ARQUITECTURA                        │
├────────────────────────────────────────────────────────────────────────┤
│  1. Capa de Presentación (Filament 5.7 / Livewire 3 / Blade)           │
│     • Admin Panel (/admin) & Platform Panel (/platform)                │
│     • Pantallas Táctiles: ServiceSelection, TableSelection, KDS       │
│                                                                        │
│  2. Capa de Servicios de Aplicación (Service Layer)                    │
│     • PedidoService (Apertura, agregados, tandas de cocina)            │
│     • CobroService (Validación de pagos, propinas, vuelto, tickets)    │
│     • KitchenService (KDS en tiempo real por tandas)                   │
│     • CierreCajaService (Apertura, arqueo ciego, cuadre)               │
│                                                                        │
│  3. Capa de Integración & Salida Asíncrona                             │
│     • FiscalOutboxService & FiscalClient (Firma HMAC y API Fiscal)     │
│     • EscPosPrintService & PdfTicketService (Impresión térmica/PDF)    │
│                                                                        │
│  4. Capa de Persistencia y Multi-Tenancy                               │
│     • Multi-BD por conexión (`platform` vs `tenant_connection`)        │
│     • EstablecimientoContext (Aislamiento por sucursal activa)         │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Flujo Operativo del Restaurante (Paso a Paso)

```
[1] TIPO SERVICIO ──► [2] SELECCIÓN MESA ──► [3] TOMA DE PEDIDO ──► [4] ENVÍO COCINA (TANDA)
    • En local (Mesa)     • Grilla táctil        • Catálogo/Combos     • Notifica KDS cocina
    • Para llevar         • Zonas: Salón/Terraza • Slots de sabores    • Imprime comanda

[5] CONSUMO / REORDEN (TANDAS NUEVAS) ──► [6] COBRO Y CAJA ──► [7] TICKET & FISCAL
    • Cliente pide más pupusas                • Efectivo/Tarjeta    • Ticket PDF/Térmico
    • Genera Tanda #2 a cocina                • Arqueo ciego        • Outbox API Fiscal
```

---

## 3. Características Técnicas Destacadas

### A. Combos Dinámicos por Slots (`ComboSelectionValidator.php`)
Permite definir promociones agrupadas (ej. *Combo de 10 pupusas* o *Combo Familiar de 20 pupusas*) donde el usuario puede combinar diferentes cantidades de cada especialidad (ej. 4 revueltas, 3 queso, 3 frijol con queso) respetando exactamente el límite configurado en el slot.

### B. KDS de Cocina por Tandas (`KitchenService.php` / `KitchenDisplay.php`)
Los pedidos se dividen en **Tandas cronológicas** (`TandaPedido`). Cada vez que la mesa ordena algo nuevo:
1. Se crea una nueva tanda (`Tanda #1`, `Tanda #2`, etc.).
2. La cocina solo prepara lo nuevo, sin duplicar los platos ya entregados.
3. Se actualiza en tiempo real mediante Livewire polling.

### C. Arqueo Ciego de Caja (`CierreCajaService.php`)
Al finalizar el turno, el cajero debe ingresar el dinero físico contado en efectivo sin que el sistema le revele el total esperado. El sistema calcula las diferencias automáticamente en el reporte administrativo para evitar hurtos o alteraciones.

### D. Multi-Tenancy y Multi-Sucursal (`EstablecimientoContext.php`)
- Cada usuario inicia sesión y opera bajo el contexto de una sucursal específica (`establecimiento_id`).
- Todas las consultas de mesas, productos, pedidos, sesiones de caja e impresoras quedan filtradas estrictamente a su sucursal.
