# Flujos operativos oficiales de Boomwalos POS

Este documento describe el comportamiento vigente del POS. El sistema pertenece a una sola empresa y separa la operación por sucursal. No existe multiempresa en este alcance.

## Configuración TI por sucursal

La página `TI → Operación del POS` permite habilitar dos capacidades independientes:

- `MOSTRADOR_PREPAGO`: tomar el pedido, cobrar y después generar la comanda pendiente.
- `MESA_POSTPAGO`: tomar la orden en mesa, enviar a cocina y cobrar al finalizar el consumo.

Ambos flujos pueden convivir. Siempre debe quedar al menos uno activo y el predeterminado debe estar habilitado. El permiso `gestionar_configuracion_pos` controla el acceso sin depender directamente del nombre de un rol.

La desactivación se bloquea cuando existen operaciones reales incompatibles. Los borradores abiertos sin líneas activas ni pago se cancelan como borradores, conservan auditoría y no bloquean el cambio.

## Flujo A — Mostrador, cobro previo

```text
Abrir turno
→ elegir Mostrador
→ agregar productos, masas y combos
→ revisar total
→ cobrar en efectivo o tarjeta
→ registrar pago
→ generar comanda solo con líneas no enviadas
→ generar ticket y registro fiscal si corresponde
→ cerrar pedido
→ volver al inicio
```

El cobro no crea automáticamente otro pedido. Una nueva orden nace únicamente cuando el operador la solicita.

## Flujo B — Mesa, cobro posterior

```text
Abrir turno
→ elegir Comer aquí
→ seleccionar mesa libre
→ agregar productos, masas y combos
→ enviar líneas nuevas a cocina
→ mantener pedido abierto y mesa ocupada
→ permitir adiciones y enviar solo lo nuevo
→ solicitar cuenta
→ pasar a PENDIENTE_COBRO
→ cobrar desde caja
→ generar ticket y registro fiscal si corresponde
→ cerrar pedido y liberar mesa
```

Una mesa no puede cobrarse mientras siga `ABIERTO`, ni solicitar cuenta si tiene líneas pendientes de cocina. El cobro no vuelve a imprimir líneas que ya fueron enviadas.

## Comandas incrementales

`detalles_pedido.tanda_id` referencia el trabajo de impresión que comunicó cada línea. El nombre histórico “tanda” se conserva por compatibilidad, pero la unidad operativa vigente es el trabajo de impresión.

Cada envío:

1. Bloquea el pedido y sus líneas pendientes.
2. Renderiza solamente esas líneas.
3. Crea una identidad determinista con pedido e identificadores de línea.
4. Asocia las líneas al trabajo dentro de la misma transacción.
5. Despacha la impresión después del commit.

Los modelos históricos de `TandaPedido` y KDS no se eliminan en esta fase. La cocina base funciona mediante comanda impresa; KDS permanece como ampliación futura.

## Masa y combos

Las pupusas que requieren masa deben guardar `MAIZ` o `ARROZ`. Los combos permiten distribuir cada producto seleccionable entre ambas masas. La misma descripción se presenta en carrito, pantalla de cobro, ticket y comanda.

## Estados

- `ABIERTO`: pedido editable.
- `PENDIENTE_COBRO`: cuenta de mesa solicitada; ya no admite cambios desde entrada de pedido.
- `COBRADO`: estado transitorio del pago dentro de la transacción.
- `CERRADO`: pago y cierre completados.
- `CANCELADO`: operación que no continúa, con historial conservado.
- Mesa `LIBRE`: sin cuenta activa.
- Mesa `OCUPADA`: pedido de mesa abierto o pendiente de cobro.

## Atomicidad y seguridad

- La sucursal activa delimita pedidos, mesas, caja, impresoras y configuración.
- Crear pedidos y cambiar flujos bloquea la misma fila de establecimiento para evitar carreras.
- Pago, cierre, trabajos de impresión y asociaciones de línea se escriben transaccionalmente.
- Impresión y salida fiscal ocurren después del commit o mediante sus colas existentes.
- Un cambio TI registra usuario, sucursal, valores anterior/nuevo y fecha.
- Los accesos directos también validan permisos, sucursal y política del flujo.

## Criterios de aceptación

- Mostrador cobra una sola vez, imprime lo pendiente y no deja pedidos vacíos.
- Mesa permanece ocupada después de comandar y se libera únicamente al finalizar el pago.
- Una adición genera otra comanda que no contiene productos anteriores.
- Maíz y arroz son visibles en producto individual y dentro de combos.
- Borradores vacíos no aparecen como pedidos operativos ni bloquean el cierre.
- Una sucursal no puede leer ni modificar datos operativos de otra.
