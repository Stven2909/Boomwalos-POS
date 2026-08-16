# Fase 0A — Dominio de POS

Este documento define la arquitectura y especificación detallada del dominio de **POS** para la Fase 0A. Refleja las decisiones operativas, las reglas de negocio estrictas, las entidades de datos y los pendientes explícitos identificados en las iteraciones con el cliente y los stakeholders.

---

## 1. Usuarios y Roles

### Entidades y Atributos
* **Usuario:**
  * `id`: Identificador único.
  * `nombre`: Nombre completo del usuario.
  * `credenciales`: Nombre de usuario / correo y contraseña (hash).
  * `rol_id`: Referencia al rol asignado.
* **Rol:**
  * Exclusivamente dos roles globales predefinidos: **Administrador** y **Cajero**.

### Reglas de Permisos e Independencia Fiscal
* **Acceso Fiscal Ortogonal:** El acceso al módulo fiscal es un permiso independiente del rol y no es heredado automáticamente. Ni siquiera el rol de **Administrador** tiene acceso por defecto a la gestión o solicitud de documentos fiscales.
  * Se requiere un permiso explícito (ej. `gestionar_solicitudes_fiscales`), desacoplado de la jerarquía de roles tradicionales.
* **Permisos Granulares Futuros:**
  * El modelo de seguridad debe permitir la adición de permisos específicos a nivel de acción (ej. `cancelar_pedido`, `aplicar_descuento`).
  * *Pendiente explícito:* Actualmente no se han definido las reglas de negocio sobre qué rol o bajo qué condiciones se pueden cancelar pedidos o aplicar descuentos. El sistema debe permitir la verificación del permiso sin hardcodear el rol que lo posee.

---

## 2. Productos y Categorías

### Categorías de Catálogo
Las categorías del sistema corresponden únicamente al menú actual del restaurante:
1. `Pupusas Normales`
2. `Pupusas Especiales`
3. `Combos`
4. `Bebidas Frías`
5. `Bebidas Calientes`

### Entidad Producto
* `id`: Identificador único.
* `categoria_id`: Categoría a la que pertenece.
* `nombre`: Nombre del producto.
* `precio`: Precio base.
* `imagen_url`: Ruta/URL de la imagen (opcional).
* `disponibilidad`: Enum con **3 estados explícitos** (no booleano):
  * `Disponible`: Disponible para venta activa.
  * `Agotado`: Agotado temporalmente en la jornada (ej. se acabaron los ingredientes hoy).
  * `Temporalmente no disponible`: Fuera de temporada o retirado del menú por decisión del negocio.

### Delimitación del Alcance
* Sin control de inventarios ni deducción de stock por receta.
* Sin campos ni contadores de cantidades en el producto base.
* Sin modificadores o "extras" con costo genérico asociados directamente al producto.
* **Gestión de Catálogo:** Operaciones CRUD reservadas de forma exclusiva para el rol con permisos de **Administrador**.

---

## 3. Combos

Los combos constituyen la estructura más compleja del catálogo al representar selecciones dinámicas en lugar de un ítem estático con precio fijo.

### Estructura de Entidades
* **Combo:**
  * `id`: Identificador único.
  * `nombre`: Nombre comercial del combo (ej. "Combo Familiar").
  * `precio_fijo`: Precio global de venta del combo.
* **Slot del Combo:**
  * `id`: Identificador único.
  * `combo_id`: Combo al que pertenece.
  * `nombre`: Identificador de la agrupación (ej. "Pupusas", "Bebida").
  * `cantidad_requerida`: Cantidad exacta de ítems que deben elegirse para completar el slot.
  * `es_obligatorio`: Booleano que determina si el slot debe completarse obligatoriamente.
* **Opciones Elegibles por Slot:**
  * Tabla pivote/relación que asocia `slot_id` con `producto_id` de los productos permitidos para ese slot (ej. el slot "Pupusas" permite elegir entre "Revuelta", "Queso" y "Frijol con Queso").

### Persistencia en Pedidos
* Al agregar un combo a una línea de pedido, la base de datos no solo registra la compra del combo, sino la resolución concreta de las opciones seleccionadas (sabores de pupusas, tipo de bebida seleccionada).
* Esta información detallada es indispensable para el flujo operativo y de preparación en cocina.

---

## 4. Mesas

### Entidad y Reglas de Operación
* **Mesa:**
  * `id`: Identificador único.
  * `numero`: Identificador físico de la mesa.
  * `estado`: Enum (`Libre`, `Ocupada`).
* **Regla Dura de Negocio:**
  * **Una mesa = Máximo un pedido activo a la vez.**
  * No existen funcionalidades para reasignación/traslado de pedidos entre mesas.
  * No existe módulo de reservas de mesas.
* **Ciclo de Vida del Estado de Mesa:**
  * Pasa a `Ocupada` inmediatamente al crear un pedido asociado a esa mesa.
  * Regresa a `Libre` de manera automática únicamente cuando el pedido asociado pasa al estado comercial `Cerrado` (cobrado).

---

## 5. Pedidos

### Entidad Pedido
* `id`: Identificador interno de base de datos.
* `tracking_number`: Número secuencial/interno de seguimiento operativo (independiente del `numeroControl` fiscal).
* `tipo`: Enum (`Mesa`, `Para llevar`).
* `mesa_id`: Referencia a la mesa (campo `NULL` si el pedido es `Para llevar`).
* `establecimiento_id`: Referencia obligatoria al establecimiento (presente desde el día 1).
* `usuario_id`: Usuario/Cajero que creó el pedido.

### Ciclos de Vida Independientes (Dos Dimensiones)
Para evitar la mezcla de la lógica comercial con la operativa de cocina, se separan explícitamente dos estados:

1. **Estado Comercial (`estado_comercial`):**
   * `Abierto`: Pedido creado, acepta adición de ítems.
   * `Cobrado`: Pago registrado exitosamente.
   * `Cerrado`: Pedido completado y finalizado administrativamente.
2. **Estado de Cocina (`estado_cocina`):**
   * `Pendiente` → `En preparación` → `Lista` → `Entregada` | `Cancelada`.

> *Nota:* Un pedido puede encontrarse en estado comercial `Cobrado` mientras su estado operativo en cocina continúa en `En preparación` (ej. pago por adelantado en modalidad para llevar o caja central).

### Operaciones Especiales
* **División de Cuenta:** Cálculo dinámico puramente en interfaz de usuario (`Total / Número de Personas`). No se persiste en base de datos ni requiere entidades específicas.
* **Modificación de Pedidos Enviados:**
  * Un pedido en estado `Abierto` permite agregar nuevas líneas de pedido después de haber sido enviado parcialmente a cocina.
  * Cada incremento/adición se maneja mediante el concepto de **Tandas de Envío**.

---

## 6. Detalles del Pedido (Líneas y Tandas)

### Entidad Línea de Pedido
* `id`: Identificador único.
* `pedido_id`: Referencia al pedido contenedor.
* `producto_id` / `combo_id`: Referencia al ítem vendido.
* `selecciones_combo`: Registro estructurado (JSON/Relación) de las opciones elegidas en slots de combo (si aplica).
* `cantidad`: Cantidad de ítems solicitados.
* `precio_unitario`: Captura del precio al momento exacto de la venta (inmutable ante futuros cambios de precio en el catálogo).
* `tanda_envio`: Identificador/Número secuencial del lote de envío a cocina.

### Mecanismo Técnico de Tandas
* Cuando un pedido es enviado por primera vez a cocina, todas sus líneas asociadas se asignan a la `Tanda 1`.
* Si posteriormente el cliente solicita productos adicionales en el mismo pedido, las nuevas líneas agregadas se marcan con la `Tanda 2`.
* La pantalla de cocina e impresoras de comanda identifican y diferencian los nuevos ítems por su número de tanda, evitando duplicar impresiones o re-preparar ítems previos.

---

## 7. Notas de Cocina

### Definición y Reglas
* **Catálogo Cerrado:** Colección predefinida de modificaciones permitidas (ej. "Sin curtido", "Salsa extra", "Bien tostada").
* **Sin Texto Libre:** No se permite el ingreso de texto arbitrario por parte del cajero.
* **Asociación Granular:** Cada nota de cocina se vincula directamente a una **línea específica del pedido** (o selección de combo), no al pedido de forma global.
* **Administración:** El catálogo de notas predefinidas es gestionado exclusivamente por el rol con permisos de **Administrador**.

---

## 8. Cocina / KDS (Kitchen Display System)

### Flujo Operativo y Hardware
* **Estados de Cocina Permitidos:** `Pendiente`, `En preparación`, `Lista`, `Entregada`, `Cancelada`.
* **Sin Reimpresión de Comanda:** No se soporta la reimpresión manual o automática de comandas operativas en la versión 1.0.
* **Arquitectura KDS:**
  * Basado en la especificación de hardware de 2 pantallas totales en el negocio (no 4 pantallas dedicadas).
  * El KDS se implementa como una **vista interactiva/pestaña dentro de los mismos dispositivos compartidos** utilizados por el cajero/personal.
  * Estrategia de sincronización simple mediante sondeo (*Livewire polling*) en sustitución de arquitecturas complejas de WebSockets o dispositivos independientes.
* **Gestión por Tanda:** El progreso de la orden en cocina se gestiona granularmente por tanda de envío. Una `Tanda 2` agregada recientemente ingresará en estado `Pendiente`, mientras las líneas de la `Tanda 1` pueden estar en estado `Lista`.

---

## 9. Pagos

### Entidad Pago
* `id`: Identificador único.
* `pedido_id`: Referencia al pedido que liquida.
* `metodo_pago`: Enum exclusivamente con dos valores (`Efectivo`, `Tarjeta`).
* `monto_recibido`: Requerido si el método es `Efectivo`.
* `cambio_devuelto`: Calculado automáticamente (`monto_recibido - total_pedido`).

### Restricciones
* **Un Solo Método por Pedido:** No se aceptan pagos divididos o combinados entre tarjeta y efectivo para un mismo pedido en esta fase.

---

## 10. Caja

### Sesión de Caja
* `id`: Identificador de la sesión.
* `establecimiento_id`: Referencia al establecimiento.
* `usuario_apertura_id`: Usuario que abre la caja.
* `usuario_cierre_id`: Usuario que realiza el cierre.
* `monto_inicial`: Saldo inicial con el que se abre la caja.
* `efectivo_esperado`: Total calculado del sistema (`monto_inicial + ventas_efectivo_sesion`).
* `efectivo_contado`: Arqueo físico ingresado por el cajero al cerrar.
* `diferencia`: Desfase calculado (`efectivo_contado - efectivo_esperado`).
* `fecha_apertura` / `fecha_cierre`.

### Delimitación
* Únicamente existirá **una caja activa** a la vez en la operación actual.
* Quedan fuera del alcance los módulos de caja chica, registro de gastos operacionales, retiros parciales de efectivo y compras a proveedores.

---

## 11. Eventos / Auditoría

### Registro Centralizado de Auditoría
Se especifica una estructura genérica y unificada (`order_events` / `audit_logs`) para la trazabilidad completa del sistema:

* `id`: Identificador del evento.
* `entidad_tipo`: Objeto afectado (ej. `Pedido`, `Caja`, `SolicitudFiscal`).
* `entidad_id`: ID del registro afectado.
* `usuario_id`: Usuario responsable de la acción.
* `tipo_evento`: Enum de acciones registradas:
  * `CreacionPedido`
  * `ModificacionPedido` (adición de líneas/tandas)
  * `EnvioCocina`
  * `CobroPedido`
  * `CancelacionPedido`
  * `AperturaCaja` / `CierreCaja`
  * `ConsultaSolicitudFiscal` / `GestionSolicitudFiscal`
* `timestamp`: Fecha y hora exacta.
* `detalle_payload`: Estructura JSON con el detalle del cambio realizado.

*El gancho de auditoría registra las cancelaciones de forma agnóstica a las reglas definitivas de permisos que se establezcan a futuro.*

---

## 12. Impresión

El sistema soporta dos salidas de impresión independientes bajo especificación **ESC/POS** directa (sin dependencia de las implementaciones o fallos de librerías tipo `printer.js` de Lakasir):

1. **Ticket (Comprobante Cliente):**
   * Encabezado del negocio.
   * Número de tracking.
   * Detalle de productos y total.
   * Método de pago y desglose de cambio.
   * Código QR dinámico (activo cuando el módulo fiscal esté integrado).
2. **Comanda (Impresión Cocina):**
   * Número de tracking.
   * Tipo de pedido (`Mesa N° X` o `Para llevar`).
   * Tanda de envío correspondiente.
   * Lista de productos con sus selecciones de combo y notas de cocina predefinidas.

---

## 13. Configuración

Parámetros globales a nivel del establecimiento almacenados en sistema:
* `caja_habilitada`: Booleano para activar/desactivar flujo de caja.
* `catalogo_notas_predefinidas`: Administración del listado de notas para cocina.
* `datos_ticket`: Dirección, teléfono, mensaje de agradecimiento e información comercial primaria para la impresión.

---

## 14. Establecimiento

### Entidad Establecimiento
* `id`: Identificador único.
* `nombre`: Nombre comercial del local.
* `direccion`: Dirección física.
* `cod_establecimiento`: Código asignado por el Ministerio de Hacienda (nulo en etapa inicial).
* `cod_punto_venta`: Código de punto de venta asignado por Hacienda (nulo en etapa inicial).

Todos los pedidos y sesiones de caja registran obligatoriamente el `establecimiento_id` desde el primer día.

---

## 15. Preparación para Fiscalización

### Entidad Documento Fiscal (Desacoplada)
Estructura completamente separada de la tabla de pedidos:

* `id`: Identificador único.
* `pedido_id`: Referencia al pedido comercial origen.
* `tipo_documento`: Enum (`Factura`, `Comprobante de Crédito Fiscal - CCF`).
* `numero_control`: Número de control fiscal asignado.
* `codigo_generacion`: UUID / Código de generación fiscal.
* `sello_recepcion`: Sello de recepción devuelto por la API fiscal.
* `estado`: Enum (`Pendiente`, `Emitido`, `Rechazado`).
* `datos_solicitante`: JSON con Nombre/Razón Social, NIT/DUI, Correo Electrónico.

### Reglas de Integración Fiscal
* **Restricción de Unicidad:** Clave única sobre la combinación `(pedido_id, tipo_documento)` para prevenir estructuralmente solicitudes duplicadas a nivel de base de datos.
* **Consulta Externa de Estado:** El modelo expone un endpoint o método de consulta del estado de pago del pedido (`es_pagado`), garantizando que la plataforma externa de facturación en Vercel valide que el pedido exista y esté completamente cobrado antes de procesar cualquier DTE.

---

## Pendientes Explícitos del Dominio

Las siguientes reglas se mantienen deliberadamente abiertas a la espera de confirmación formal por parte del cliente y stakeholders:

1. **Permisos de Cancelación y Descuento:** Matriz definitiva de qué roles o usuarios específicos pueden ejecutar cancelaciones de pedidos o aplicar descuentos en caja.
2. **Ventana de Tiempo para Facturación:** Período límite (en horas/días) permitido para que un cliente solicite un documento fiscal post-venta.
3. **Manejo Interactivo de Solicitudes Duplicadas:** Definición de la experiencia de usuario y mensajes del sistema cuando un cliente intenta solicitar un documento fiscal para un pedido que ya posee una solicitud en proceso o emitida.
4. **Validación de Pantalla KDS:** Confirmación final en sitio sobre si la vista de cocina operará en pantalla compartida interactiva mediante sondeo o requerirá alguna visualización alternativa.
