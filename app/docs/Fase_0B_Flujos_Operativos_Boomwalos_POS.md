# Fase 0B — Flujos Operativos de POS

Este documento especifica los flujos operativos oficiales de **POS** alineados estrictamente con la secuencia macro del Master Técnico Ejecutivo:

> **Registro (Mesa o para llevar) → Preparación (Envío a cocina) → Cobro (Efectivo o tarjeta) → Ticket (Número y QR opcional) → Entrega (Pedido finalizado)**

Se detallan los tres flujos fundamentales del sistema, sus implicaciones de diseño de datos, auditoría y las decisiones o zonas grises identificadas.

---

## Secuencia Macro Oficial

A diferencia de un modelo de restaurante tradicional (cobro al final tras consumir) o un modelo de comida rápida estricto (cobro previo a la comanda), **POS** opera en un modelo intermedio:

1. **Creación del pedido y envío inicial a cocina.**
2. **Procesamiento de pago en paralelo** mientras la cocina prepara la comanda.
3. **Entrega final del producto** al cliente para proceder al cierre comercial y liberación del recurso (mesa).

---

## Flujo 1 — Venta en Mesa

### Diagrama de Estados y Pasos

```
[Mesa libre]
    │
    ▼
[1] REGISTRO ──► Cajero selecciona mesa (Mesa pasa a OCUPADA).
    │            Se crea Pedido (tipo=Mesa, mesa_id, establecimiento_id, usuario_id).
    │            Estado comercial: ABIERTO.
    │            Estado de cocina: Sin tandas asignadas.
    ▼
[2] CONSTRUCCIÓN ──► Adición de productos, combos (selección de slots) y notas por línea.
    │                Sin límite de tiempo en estado ABIERTO.
    ▼
[3] ENVÍO A COCINA (Tanda 1) ──► Líneas actuales asignadas a tanda=1.
    │                            Estado de cocina de Tanda 1: PENDIENTE.
    │                            Auditoría: evento 'pedido_enviado_cocina'.
    │                            Impresión automática de comanda térmica (Tanda 1).
    ▼
[4] COBRO ──► Registro de pago (Efectivo / Tarjeta).
    │         Ocurre en paralelo con la preparación en cocina.
    │         Estado comercial: COBRADO.
    │         Generación de entidad Pago (método, monto, cambio).
    │         Auditoría: evento 'pedido_cobrado'.
    ▼
[5] TICKET ──► Impresión de ticket cliente (tracking, desglose, total, pago, QR fiscal).
    │
    ▼
[6] COCINA AVANZA ──► Tanda 1 pasa de PENDIENTE ──► EN PREPARACIÓN ──► LISTA.
    │                 (Independiente del cobro).
    ▼
[7] ENTREGA ──► Tanda 1 se marca como ENTREGADA.
                Si no hay más tandas pendientes:
                - Estado comercial pasa a CERRADO.
                - Mesa se libera automáticamente (pasa a LIBRE).
                Auditoría: evento 'pedido_entregado'.
```

### Decisión de Diseño: Liberación de Mesa
* **Punto de liberación:** La mesa cambia de `Ocupada` a `Libre` estrictamente en el **Paso 7 (Entrega del pedido / Estado comercial CERRADO)**.
* **Justificación:** Al no gestionar ocupación física por conteo de comensales, la entidad `Mesa` mide únicamente si existe un *pedido activo en curso*. Mantener la mesa ocupada hasta la entrega previene la sobreescritura o asignación errónea de un nuevo pedido mientras el cliente sigue esperando sus alimentos.

---

## Flujo 2 — Venta Para Llevar

### Diagrama de Estados y Pasos

```
[Inicio Venta]
    │
    ▼
[1] REGISTRO ──► Se crea Pedido (tipo=Para llevar, mesa_id=NULL).
    │            Estado comercial: ABIERTO.
    ▼
[2] CONSTRUCCIÓN ──► Adición de productos, combos y notas por línea.
    │
    ▼
[3] ENVÍO A COCINA (Tanda 1) ──► Líneas marcadas con tanda=1.
    │                            Estado cocina Tanda 1: PENDIENTE.
    │                            Impresión de comanda de cocina.
    │                            Auditoría: evento 'pedido_enviado_cocina'.
    ▼
[4] COBRO ──► Registro de Pago (Efectivo / Tarjeta).
    │         Estado comercial: COBRADO.
    ▼
[5] TICKET ──► Impresión de ticket con número de tracking interno (identificador único).
    │
    ▼
[6] COCINA AVANZA ──► Tanda 1 pasa a EN PREPARACIÓN ──► LISTA.
    │
    ▼
[7] ENTREGA ──► Tanda 1 pasa a ENTREGADA.
                Estado comercial pasa a CERRADO.
                Auditoría: evento 'pedido_entregado'.
```

### Equivalencia Estructural
No existe bifurcación de código entre el flujo de Mesa y Para Llevar. Ambas rutas ejecutan exactamente el mismo ciclo de vida; la única variación lógica es la ausencia de `mesa_id` (`NULL`) y la omisión de la actualización del estado de mesa en el paso final.

---

## Flujo 3 — Modificación Posterior al Envío a Cocina

Representa el flujo de adición de productos a un pedido ya enviado.

### Diagrama de Estados y Pasos

```
[Pedido en curso] ──► Tanda 1 previamente enviada (Estado cocina Tanda 1: EN PREPARACIÓN / LISTA).
                      Estado comercial: ABIERTO o COBRADO.
    │
    ▼
[A] SOLICITUD DE ADICIÓN ──► Cajero reabre el pedido existente en pantalla.
    │
    ▼
[B] AGREGAR ÍTEMS ──► Se incorporan nuevas líneas de productos/combos/notas.
    │                 Las líneas previas (Tanda 1) permanecen inmutables.
    ▼
[C] ENVÍO A COCINA (Tanda 2) ──► Nuevas líneas asignadas a tanda=2.
    │                            Estado de cocina de Tanda 2: PENDIENTE.
    │                            Auditoría: 'pedido_modificado' (incluye payload explícito).
    │                            Impresión de NUEVA comanda exclusiva para Tanda 2.
    │                            (Prohibida la reimpresión de Tanda 1).
    ▼
[D] VISUALIZACIÓN EN KDS ──► La pantalla de cocina muestra Tanda 1 y Tanda 2 agrupadas
    │                        bajo el mismo tracking_number del pedido,
    │                        pero con estados de preparación independientes.
    ▼
[E] RESOLUCIÓN Y ENTREGA ──► El pedido alcanza estado comercial CERRADO únicamente cuando
                             TODAS las tandas asociadas (Tanda 1, Tanda 2, ... N)
                             se encuentran en estado ENTREGADA.
```

---

## Implicaciones Técnicas y de Modelado Derived

### 1. Granularidad del Estado de Cocina
* **Regla Estructural:** El estado de cocina **no puede ser un campo simple en la tabla de pedidos**.
* **Modelo de Datos:** El estado operativo debe almacenarse por **Tanda de Envío** (o a nivel de línea agrupada por tanda). Esto permite que un mismo pedido mantenga de forma simultánea:
  * `Tanda 1`: `Lista` o `Entregada`.
  * `Tanda 2`: `Pendiente` o `En preparación`.

### 2. Control de Impresión
* Queda prohibida la reimpresión automática o completa del pedido al agregar nuevas líneas.
* Cada envío adicional genera únicamente una comanda física/digital con las líneas pertenecientes al número de tanda recién creada (`tanda_id`).

---

## Pendientes y Zonas Grises a Confirmar con el Cliente

1. **Gestión Monetaria de Adiciones Post-Cobro:**
   * *Escenario:* Un cliente realiza un pedido, se envía la `Tanda 1`, el cajero procesa el **Cobro** (Estado comercial: `COBRADO`) y posteriormente el cliente solicita un producto adicional (`Tanda 2`).
   * *Pregunta para el negocio:* ¿El sistema debe requerir un **segundo cobro independiente** inmediatamente al enviar la `Tanda 2`? ¿O se debe permitir la adición acumulada generando un saldo pendiente que fuerce un cobro complementario antes de cerrar el pedido?
2. **Confirmación de Liberación de Mesa:**
   * Confirmar si el negocio aprueba que la mesa se mantenga como `Ocupada` hasta el momento de la entrega de la comida (Paso 7), o si prefieren liberarla inmediatamente tras el registro del cobro (Paso 4) aunque los clientes continúen sentados.
