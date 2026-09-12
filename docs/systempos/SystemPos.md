# SystemPos
## Definición del producto y especificación funcional

Base de trabajo 1.0 · 5 de septiembre de 2026

Este documento define la idea completa de SystemPos antes de programarla. Explica el negocio, el alcance de la primera entrega, los actores, los recorridos, las reglas y las condiciones de aceptación. Su propósito es que el propietario y el equipo de desarrollo describan el mismo producto y puedan verificarlo con los mismos ejemplos.

SystemPos será un sistema de punto de venta táctil para pupuserías. Permitirá registrar pedidos, comunicar a cocina lo que debe preparar, cobrar, controlar caja y consultar resultados. Cada empresa podrá operar varias sucursales. La cocina de una sucursal pequeña trabajará con comandas impresas; una sucursal que lo necesite podrá habilitar pantalla de cocina sin cambiar de producto.

La primera entrega se concentra en una operación de venta completa y controlada. Las ampliaciones también se describen para preparar su convivencia, pero no se confunden con compromisos de la primera entrega. Tener una función descrita como ampliación no significa que deba estar implementada en el núcleo inicial.

### Cómo interpretar esta definición

Los requisitos identificados como RF y las reglas RN son la propuesta funcional que debe guiar el desarrollo. Los puntos expresamente solicitados son: nombre SystemPos, uso táctil, varias sucursales de la misma empresa, comanda impresa como base, cocina con pantalla opcional, opciones habilitables y una definición común para los programadores. Las reglas detalladas de este documento completan esa idea como propuesta del analista; no representan una aprobación contractual ya otorgada.

El registro de decisiones al final concentra lo que requiere validación de negocio. Hasta resolver un punto, se conserva la conducta base indicada y no se promete su alternativa. Esta definición no certifica que exista código, integración o servicio funcionando.

## 01. El negocio que queremos resolver

Una pupusería necesita atender pedidos rápidos sin perder cantidades, sabores, observaciones ni dinero. En las horas de mayor actividad, una misma persona puede recibir clientes, registrar cambios, cobrar y avisar a cocina. Si la información se reparte entre memoria, papeles y mensajes, resulta difícil saber cuál es el pedido vigente, qué se pagó y quién autorizó una corrección.

SystemPos organizará esa operación alrededor de un pedido identificable. El cajero registrará los productos y sus particularidades; cocina recibirá instrucciones legibles; el encargado controlará los cambios sensibles y la caja; el propietario revisará resultados de cada sucursal y de su empresa. El sistema debe explicar los problemas al usuario y conservar las operaciones pendientes para que pueda resolverlas.

El producto se dirige a pupuserías pequeñas y medianas que venden en mostrador, para consumo en el local o para llevar. El consumo en local no obliga a implementar un mapa de mesas. La primera entrega permite una referencia de mesa o nombre de retiro; la atención completa por meseros, división de cuentas y mapa de salón se considera una ampliación.

### Resultado esperado

Una jornada estará controlada cuando sea posible reconstruir qué se pidió, qué instrucciones llegaron a cocina, cuánto se cobró, qué comprobante se entregó, quién realizó las acciones y cómo terminó la caja. Una segunda sucursal deberá usar esas mismas reglas con sus propios usuarios, cajas, impresoras y configuración.

El éxito no se expresará con porcentajes de ahorro inventados. Se comprobará mediante pedidos sin pérdida de información, cobros sin duplicación, cierres explicables, aislamiento entre empresas y sucursales, y personal capaz de completar sus tareas en una prueba operativa.

## 02. Alcance y prioridades de construcción

Se utilizarán tres categorías. BASE es lo necesario para aceptar la primera entrega. OPCIONAL es una capacidad prevista cuya entrega se aprueba por separado y que, una vez implementada, se habilita mediante configuración. FUTURO es una extensión sin compromiso de entrega inicial. Una función opcional todavía no construida debe aparecer como no disponible, nunca como un interruptor que simule funcionar.

| Área | Primera entrega BASE | Extensión definida |
| Empresa y sucursales | Separación de empresas, sucursales y acceso por asignación | Altas comerciales automáticas y facturación de suscripciones: FUTURO |
| Usuarios | Cuentas individuales, perfiles, permisos y bitácora | Asistencia, planilla y recursos humanos: FUTURO |
| Catálogo | Categorías, productos, variantes, extras y disponibilidad | Combos complejos y campañas: OPCIONAL |
| Venta | Mostrador, consumo local y para llevar; pedidos y correcciones | Meseros, plano de mesas y división de cuenta: OPCIONAL |
| Cocina | Comanda impresa por sucursal, incidencias y reimpresión | Pantalla KDS o modo mixto: OPCIONAL |
| Caja y cobro | Apertura, efectivo, registro de otros medios, arqueo y cierre | Integración bancaria: OPCIONAL; pagos divididos: fuera de BASE |
| Administración | Ventas, cierres, medios de pago, productos y auditoría | Rentabilidad y analítica avanzada: OPCIONAL |
| Inventario | Disponibilidad manual del menú | Existencias, recetas, compras y transferencias: OPCIONAL |
| Comprobantes | Ticket operativo y registro de venta | Documentos fiscales: entrega condicionada a país e integración |
| Canales | Venta atendida por personal | QR, delivery, kiosco y fidelización: FUTURO |

La separación de empresas es una capacidad de plataforma: una empresa cliente no administra ni consulta otra empresa. Para el propietario de una pupusería, el flujo habitual será entrar a su empresa y escoger entre sus sucursales autorizadas. No se introduce una consolidación contable entre empresas independientes.

### Qué significa terminar la primera entrega

El núcleo termina cuando una empresa con dos sucursales puede configurar su menú, abrir cajas, vender de forma táctil, imprimir comandas en el local correcto, registrar cobros, resolver incidencias, cerrar turnos y consultar reportes consistentes. Los requisitos BASE y sus pruebas deben pasar; las ampliaciones no bloquean la aceptación si están identificadas como fuera de esa entrega.

No se incluyen por defecto contabilidad completa, nómina, comercio electrónico, reparto geolocalizado, funcionamiento sin servidor, sincronización offline ni compatibilidad con cualquier impresora del mercado. Cada inclusión posterior necesita una definición de funcionamiento y aceptación.

## 03. Empresa, sucursal y propiedad de los datos

Una empresa representa al negocio cliente. Una sucursal es un local de esa empresa. Una caja es un punto de cobro de una sucursal. Una sesión de caja es el período durante el cual un responsable opera esa caja desde su apertura hasta el cierre. Estas cuatro nociones no son intercambiables.

Ejemplo: la empresa Pupusería El Comal tiene Centro y Norte. Centro posee Caja 1 e impresora Cocina Centro; Norte posee Caja 1 e impresora Cocina Norte. Las dos cajas pueden llamarse igual, pero pertenecen a sucursales diferentes. Los tickets y las comandas deben identificar el local para evitar ambigüedad.

| Información | Propiedad y regla |
| Categorías, productos, variantes y extras | Catálogo de la empresa, reutilizable en sus sucursales. |
| Precio | Precio base de empresa; excepción por sucursal autorizada y registrada. |
| Disponibilidad | Cada sucursal puede pausar productos sin eliminarlos del catálogo común. |
| Pedido, pago y devolución | Pertenecen a la empresa y a la sucursal de origen; no se trasladan después del cobro. |
| Caja, sesión, movimiento e impresora | Pertenecen a una sola sucursal. |
| Usuario | Identidad con acceso a una empresa y sucursales asignadas; sin acceso implícito a las demás. |
| Reporte consolidado | Agrega únicamente sucursales autorizadas de la misma empresa. |
| Configuración de cocina | Independiente por sucursal; Centro puede imprimir y Norte utilizar KDS. |

### Requisitos de organización

RF-01. El administrador técnico debe poder registrar una empresa y su estado operativo. El alta requiere nombre, identificador interno único, zona horaria, moneda y administrador inicial. Los datos fiscales se solicitan únicamente cuando corresponda activar esa capacidad.

RF-02. El administrador de empresa debe poder crear y actualizar sucursales, asignar responsables y definir sus dispositivos. Inactivar una sucursal impide nuevas operaciones; no elimina el historial. El sistema debe exigir resolver sesiones abiertas antes de completar la inactivación.

RF-03. El usuario debe ver permanentemente la empresa, sucursal y caja activas. Cambiar de sucursal requiere permiso. Si tiene un borrador en edición, deberá guardarlo o descartarlo expresamente antes de cambiar; nunca migrarlo silenciosamente a otra sucursal.

RN-01. Toda consulta, impresión, descarga, reporte y modificación debe verificar el alcance autorizado. Cambiar un identificador en una dirección o solicitud no concede acceso. Las búsquedas por número de pedido también respetan la empresa y sucursal.

RN-02. Un cambio de precio o nombre del catálogo afecta ventas nuevas. Los pedidos confirmados conservan la descripción, cantidad, precio, descuentos e impuestos utilizados en el momento de su confirmación.

## 04. Personas, permisos y responsabilidades

El permiso determina qué puede hacer una persona; la asignación determina dónde puede hacerlo. Habilitar un módulo no concede permisos automáticamente. Cada usuario debe iniciar sesión con una cuenta propia, y las autorizaciones sensibles deben registrar al operador y a la persona que las aprueba.

| Perfil inicial | Responsabilidades | Límites |
| Administrador técnico | Alta de empresas, capacidades disponibles, diagnóstico y soporte autorizado | No obtiene acceso comercial indiscriminado por defecto. |
| Administrador de empresa | Sucursales, catálogo, usuarios, opciones autorizadas y reportes consolidados | No puede actuar fuera de su empresa. |
| Encargado de sucursal | Jornada, disponibilidad, incidencias, autorizaciones y cierre | Solo sucursales asignadas. |
| Cajero | Pedidos, envío de comanda, cobro y consulta operativa | No cambia permisos ni borra ventas. |
| Cocina con KDS | Consultar instrucciones y registrar preparación | No cobra ni consulta información financiera innecesaria. |
| Consulta o auditoría | Leer reportes, cierres y bitácora autorizados | No modifica operación ni configuración. |

RF-04. La gestión de usuarios permitirá crear, inactivar, restablecer acceso y asignar perfil y sucursales. Inactivar una cuenta revoca su acceso vigente sin eliminar sus acciones históricas. Los permisos iniciales serán plantillas editables por una persona autorizada.

RF-05. Los permisos sensibles deben distinguir al menos: cambiar precios, aplicar descuentos, cancelar pedidos enviados, devolver dinero, registrar retiros, cerrar caja, consultar consolidado, exportar datos, modificar dispositivos y habilitar capacidades. Ocultar un botón es parte de la interfaz; el servidor debe rechazar igualmente una acción no autorizada.

RF-06. El sistema debe permitir bloqueo de la sesión en el dispositivo compartido y reautenticación. Al cambiar de operador, la operación siguiente se atribuye a la persona autenticada. Las contraseñas no se muestran en registros ni documentos.

RN-03. Una solicitud de soporte que requiera entrar a datos del cliente tendrá alcance, responsable y duración registrados. El administrador técnico gestionará la plataforma; las operaciones del negocio exigirán acceso explícito y auditable.

## 05. Menú, productos y precios

El menú será la base del POS. Una categoría agrupa productos; un producto es algo vendible; una variante identifica una opción que cambia lo vendido, como masa de maíz o arroz; un extra es un agregado con precio propio. Una nota comunica una instrucción libre y no debe convertirse en un cargo escondido.

Ejemplo: Pupusa de queso tiene precio base, variantes de masa y un extra de queso adicional. El cajero elige cantidades y opciones permitidas. Si el negocio vende maíz y arroz como productos separados, se utilizará ese modelo y no se duplicará simultáneamente como variante. La configuración del menú deberá mantener una sola representación para cada artículo vendible.

RF-07. El administrador podrá registrar productos con nombre, categoría, precio, estado, imagen opcional y destino de preparación. No se permiten nombres vacíos, cantidades no válidas ni precios negativos. La ausencia de imagen muestra una tarjeta con texto legible.

RF-08. Las variantes y extras definirán opciones permitidas, obligatoriedad, mínimo y máximo de selección y diferencia de precio. El sistema bloqueará combinaciones inválidas antes de agregar el artículo. Las opciones se conservarán en el detalle de pedido y en la comanda correspondiente.

RF-09. El encargado podrá marcar un producto como agotado temporalmente en su sucursal. El POS lo mostrará deshabilitado y explicará la causa. Un cambio de disponibilidad no cancela automáticamente pedidos previamente confirmados.

RF-10. El catálogo permitirá un precio base y una excepción por sucursal. La excepción debe ser visible al administrador y quedar registrada. El cajero no editará libremente el precio de una línea salvo que tenga un permiso específico y registre motivo.

RN-04. Los importes se almacenan y calculan con precisión monetaria, sin errores acumulados de redondeo. El detalle mostrará cantidad, precio unitario, extras, descuento y total. La suma visible debe coincidir con lo cobrado y con el reporte. El tratamiento de impuestos se configurará antes del piloto y se mantendrá uniforme en toda la empresa.

RN-05. Un producto utilizado en ventas se inactiva, no se elimina de forma que destruya el historial. La misma regla aplica a categorías, medios de pago y usuarios ya utilizados.

## 06. Jornada y recorrido principal del POS

Al iniciar la jornada, el encargado verifica sucursal, caja, menú disponible y dispositivos. El cajero abre una sesión registrando el fondo inicial y recibe confirmación de que puede vender. Si la impresión no está disponible, aparece una incidencia y se aplica el procedimiento de contingencia definido; el sistema no informa falsamente que cocina recibió el pedido.

El cajero selecciona consumo local o para llevar, agrega productos, variantes, cantidades y notas, revisa el resumen y cobra una sola vez. El pedido recibe una identificación única y se comunica a cocina únicamente después de que el pago ha sido aprobado. Si el pago falla, el pedido permanece como borrador y no se imprime ni se muestra a cocina. No existe cobro posterior, crédito ni pago pendiente.

RF-11. La pantalla principal mostrará categorías, búsqueda, productos, detalle del pedido, importe total, tipo de servicio y las acciones de revisar, cobrar y consultar pedidos. El flujo no permite confirmar antes de cobrar: cuando el pago único es aprobado, el sistema confirma automáticamente la orden y envía la comanda. Los controles más frecuentes deben poder usarse por toque sin depender de pasar el cursor sobre ellos.

RF-12. Se permitirá incrementar o disminuir cantidades, quitar líneas del borrador y editar notas antes de confirmar. Descartar un borrador requiere confirmación si contiene productos. Un borrador guardado debe poder recuperarse desde la misma sucursal y no implica una venta ni una comanda.

RF-13. Confirmar requiere al menos un artículo válido y todas las opciones obligatorias. El sistema recalcula el pedido con las reglas vigentes y muestra cualquier cambio antes de aceptarlo. La confirmación repetida por doble toque debe devolver el mismo pedido, no crear dos.

RF-14. El pedido debe mostrar su identificador, origen, sucursal, operador, fecha, tipo de servicio, detalle, estado de pago y estado operativo. Puede incluir nombre corto o referencia de mesa; la venta de mostrador no exige datos personales del comprador.

RF-15. El listado de pedidos permitirá buscar por identificador y filtrar por fecha, servicio, pago y situación operativa. Abrir un pedido desde ese listado conserva el alcance de la sucursal y muestra su historial de cambios.

### Ejemplo conductor

Un cliente pide cuatro pupusas revueltas a $1.00 y dos de queso a $1.25 para llevar. El pedido suma $6.50. El cajero registra una nota: las dos de queso sin curtido. El cliente entrega $10.00 y recibe $3.50 de cambio. El sistema confirma el cobro y envía a Cocina Centro una comanda de seis pupusas con la nota asociada a las de queso. Estos valores son datos ilustrativos para las pruebas, no una lista de precios comercial.

## 07. El pago ocurre una sola vez antes de cocina

El cajero cobra el pedido una sola vez, en el momento de tomarlo y antes de enviarlo a cocina. El pago aprobado es una condición para confirmar la venta y generar la comanda. La preparación puede continuar después del cobro y tener sus propios estados, pero nunca puede existir una orden enviada a cocina con pago pendiente.

| Dimensión | Estados definidos | Regla |
| Pedido | Borrador, confirmado, cancelado, finalizado | Confirmar fija el detalle inicial; cancelar conserva el historial. |
| Pago | No iniciado, rechazado, pagado, reembolsado | BASE exige un único pago completo aprobado por pedido; no hay crédito, pagos parciales ni cobro posterior. |
| Preparación | No aplica, pendiente de envío, enviado, en preparación, listo | Estos estados aparecen después del pago aprobado; en papel solo se registran estados que un operador confirma. |
| Entrega | Pendiente, entregado | Requiere pago completo y comanda confirmada; no hay excepción de crédito. |
| Impresión | En cola, procesando, enviada al dispositivo, error, resultado incierto | Envío al dispositivo no prueba por sí solo que el papel salió. |

RF-16. El sistema debe exigir el pago completo antes de confirmar la venta y enviar la comanda. Un pedido sin pago aprobado no puede pasar a cocina ni entregarse. Para finalizar requiere pago registrado, entrega confirmada y ninguna incidencia crítica abierta. La ausencia de pantalla en cocina no impide operar: el cajero o encargado puede registrar listo y entregado cuando lo confirma físicamente.

RF-17. Una cancelación exige motivo y permiso según el avance del pedido. Cancelar una orden pagada no devuelve dinero automáticamente; debe completar el procedimiento de reembolso. Si había instrucciones enviadas, se genera un aviso de cancelación para cocina.

RF-18. Cuando dos usuarios abran el mismo pedido, una modificación concurrente no debe sobrescribir silenciosamente la anterior. El segundo usuario recibe el detalle actualizado y revisa su cambio antes de aplicarlo. Los cobros y las entregas repetidos deben devolver el resultado existente.

RN-06. Los tiempos de cocina solo se calculan con eventos registrados. Una sucursal que usa papel y no marca inicio ni listo no tendrá tiempos de preparación inventados ni comparaciones de productividad basadas únicamente en la impresión.

## 08. Comanda impresa: operación base de cocina

La comanda es una instrucción operativa para preparar alimentos. El ticket de venta es un comprobante para el cliente. Se diseñan y enrutan por separado aunque eventualmente compartan un dispositivo aprobado. Cocina recibe los productos que le corresponden, cantidades, variantes, extras y notas; no necesita datos personales ni montos financieros para preparar.

RF-19. Cada sucursal tendrá una impresora de cocina configurada y categorías o productos asignados a preparación. Un producto de entrega inmediata, como una bebida embotellada servida en mostrador, puede quedar fuera de la comanda de cocina. Una configuración sin destino válido debe bloquearse o informar el fallo antes de confirmar el envío.

RF-20. La comanda incluirá empresa o sucursal, número del pedido, número del envío, fecha y hora, tipo de servicio, referencia opcional, cantidades, nombres, variantes, extras y observaciones. Los cambios y las anulaciones deberán distinguirse visualmente del envío inicial.

RF-21. Cada envío agrupa solo las instrucciones nuevas desde el envío anterior. El sistema conservará la relación entre pedido, envío y trabajo de impresión. Reimprimir una comanda no crea una nueva orden de cocina ni vuelve a cobrar la venta.

RF-22. El operador verá si la comanda está en cola, enviada, fallida o con resultado incierto. Si hay un fallo, podrá reintentar con permiso o pedir intervención. Cada intento conserva fecha, dispositivo y resultado. Un error de impresora no borra el pedido ni revierte un pago ya confirmado.

RF-23. Toda copia solicitada por el operador llevará la marca REIMPRESIÓN y el mismo identificador de envío. Cuando la comunicación se interrumpa después de enviar, se mostrará resultado incierto y se pedirá verificar el papel antes de imprimir otra copia que pueda duplicar la preparación.

### Cambios después de enviar

Si el cliente agrega una pupusa, el sistema crea una corrección del pedido y un envío adicional con esa pupusa; no vuelve a mandar las seis anteriores como si fueran nuevas. Si quita una línea, se envía una cancelación identificada. Si cocina ya está trabajando, el encargado debe resolver la preparación y la posible merma, además del ajuste monetario.

RN-07. Después de aprobar el pago y enviar la comanda, el pedido queda cerrado para modificaciones de productos, cantidades, variantes, extras y precio. Si el cliente solicita otro producto, se registra como una nueva orden con su propio pago. Una cancelación o devolución requiere autorización y deja historial; nunca se altera el pago original.

RN-08. Durante una contingencia de impresión, el encargado puede autorizar una comanda manual vinculada al mismo pedido y registrar que fue comunicada a cocina. Al recuperarse el dispositivo, no debe imprimirse automáticamente como una nueva instrucción. La regularización conserva el incidente y evita duplicar preparación.

## 09. Pantalla de cocina opcional por sucursal

El KDS es una pantalla que representa las instrucciones de preparación y permite registrar su avance. Su incorporación no reemplaza el concepto de pedido ni crea un segundo flujo de ventas. La sucursal puede usar IMPRESIÓN, KDS o MIXTO cuando la capacidad esté implementada y autorizada. En MIXTO, papel y pantalla corresponden al mismo envío, no a dos trabajos de preparación.

RF-24. Al habilitar KDS se deben definir sucursal, estación, categorías visibles y usuarios autorizados. Las tarjetas mostrarán pedido, servicio, hora, productos, notas y tiempo transcurrido. Los importes de venta se omiten por defecto.

RF-25. Cocina podrá pasar de pendiente a en preparación y a listo. Marcar una línea no debe marcar automáticamente otras no preparadas. El pedido estará listo cuando todas sus líneas activas que requieran preparación estén listas. Una nueva orden adicional genera su propio trabajo y no vuelve a preparar ni modifica el pedido original.

RF-26. Las correcciones y cancelaciones deben destacarse y conservarse hasta que el operador las reconozca. La pérdida de conexión se muestra de forma visible. Al reconectar, la pantalla recupera el estado vigente; no repite una transición ya registrada.

RF-27. Los umbrales de demora se configurarán por sucursal o estación. El color se acompañará de texto y tiempo. Una devolución de estado requiere permiso y motivo; no debe desaparecer del historial.

RN-09. Desactivar KDS o cambiar de modo exige revisar instrucciones activas y confirmar su destino de continuidad. El sistema bloqueará el cambio si deja pedidos sin responsable. El cambio de modo no elimina tiempos ni historial previo.

### Dos locales, una definición

Centro trabaja con una impresora: cocina recibe papel y el cajero confirma la entrega. Norte activa KDS: cocina marca sus estados y caja visualiza cuándo está listo. Ambos locales conservan iguales reglas de precios, pagos, cancelaciones, permisos y cierre. La diferencia es el medio de coordinación y la información de preparación que cada local registra.

## 10. Cobros, descuentos y devoluciones

La regla única es cobrar una sola vez antes de enviar la orden a cocina. El sistema no permitirá configurar cobro posterior, crédito, cuenta abierta ni órdenes enviadas con pago pendiente. Un pedido cuyo pago sea rechazado permanece como borrador y no genera comanda.

RF-28. El cobro muestra total, medio seleccionado y resultado. En efectivo se ingresa lo recibido y se calcula el cambio; no se acepta un recibido menor al total. En otros medios se registra el importe y la referencia cuando corresponda. Un pago aprobado confirma la orden y dispara el envío a cocina; un pago rechazado no confirma ni imprime. Registrar tarjeta o transferencia no significa que SystemPos haya ejecutado una transacción bancaria.

RF-29. Cada pedido admite un solo cobro completo. La confirmación debe ser indivisible: queda registrada una vez o se informa que no se completó. Si se interrumpe la comunicación, el operador consulta el estado antes de repetir. El sistema debe identificar y rechazar un segundo intento del mismo cobro, aunque el usuario toque nuevamente el botón.

RF-30. Los descuentos BASE se aplican antes del cobro como importe o porcentaje autorizado, con motivo y responsable. No pueden producir un total negativo. Se muestran en el detalle y en reportes. Después del pago no se cambia el descuento ni el total. Las promociones automáticas y acumulación de beneficios requieren una extensión específica.

RF-31. La devolución se vincula a una venta y a sus líneas originales, identifica importe, medio, motivo y aprobador. La devolución no es un nuevo cobro ni permite volver a editar la venta. No se puede devolver un importe superior al pago original. El pago y la devolución permanecen consultables.

RF-32. Un pedido cobrado con tarjeta no se registra como reembolsado al banco por el simple hecho de crear una devolución local. Si la devolución externa es manual, debe registrarse su referencia y confirmación. No se promete conciliación bancaria automática en BASE.

RN-10. La reversión de un pedido no equivale a un reembolso y un reembolso no prueba que un alimento volvió al inventario. El retorno físico y la merma se registrarán por separado cuando exista el módulo de inventario.

RN-11. Un producto solicitado después de pagar no se agrega a la venta original. Se registra como una nueva orden con su propio pago único y su propia comanda. La venta original conserva su importe y la suma de cada orden se consulta por separado.

## 11. Apertura, movimientos y cierre de caja

Cada caja física tendrá una única sesión abierta. El operador registra el fondo inicial y asume responsabilidad por esa sesión. Para BASE se propone un responsable por sesión; si otro cajero recibe la caja, se realiza un cierre y una nueva apertura con conteo. El uso simultáneo de un mismo cajón por varios responsables requiere otro procedimiento explícito.

RF-33. Abrir caja requiere usuario autorizado, sucursal, caja y monto inicial no negativo. No se permite abrir una segunda sesión en una caja ocupada ni cobrar sin una sesión válida. Los intentos concurrentes deben resolver una sola apertura.

RF-34. Se podrán registrar ingresos y retiros de efectivo distintos de las ventas, con concepto, monto, usuario y autorización correspondiente. Esos movimientos no se contabilizan como ventas. Un error se corrige con un movimiento compensatorio, no borrando el original.

RF-35. Al iniciar el cierre se bloquearán nuevos cobros sobre esa sesión. No deben existir pedidos confirmados pendientes de pago: cualquier pedido no cobrado es un borrador y no forma parte de las ventas ni del cierre. Las devoluciones pendientes se identificarán y resolverán según autorización. Pedidos ya pagados pueden seguir en preparación y su entrega posterior no modifica el cierre monetario.

RF-36. El cajero registra el efectivo contado; el sistema calcula esperado y diferencia. La diferencia no se convierte automáticamente en ingreso o retiro. Si no coincide, se exige observación y revisión del encargado. El cierre almacena conteo, esperado, diferencia, operador, revisión y fecha.

RF-37. Una sesión cerrada no se vuelve a editar para cuadrarla. Las correcciones posteriores dejan un ajuste vinculado y autorización. Una devolución de una venta de una sesión cerrada afecta el efectivo de la sesión actual donde se paga y conserva referencia a la venta original.

### Fórmula y ejemplo de aceptación

Efectivo esperado = fondo inicial + cobros netos en efectivo + ingresos adicionales - retiros - devoluciones en efectivo. Cobro neto significa dinero recibido menos cambio entregado; los pagos con tarjeta no incrementan el cajón.

Con fondo de $50.00, ventas en efectivo de $120.00, ingreso de $10.00, retiro de $20.00 y devolución de $5.00, el esperado es $155.00. Si se cuentan $153.00, la diferencia es -$2.00. El cierre debe mostrar ambos valores y la explicación, no alterar las ventas para hacerlos coincidir.

## 12. Reportes y trazabilidad

La gerencia necesita saber cuánto vendió y cobró, por qué medio, en qué sucursal y con qué ajustes. El sistema diferenciará borradores, ventas cobradas, dinero recibido y devoluciones. Un borrador no se presenta como ingreso efectivo y no puede haber una venta confirmada pendiente de pago.

RF-38. Los reportes BASE incluyen ventas cobradas, medios de pago, productos vendidos, descuentos, cancelaciones, devoluciones, movimientos y cierres de caja. Se filtrarán por rango de fecha, sucursal, caja y operador según corresponda al reporte y a los permisos.

RF-39. El consolidado de empresa suma solo sucursales autorizadas y muestra su desglose. Cada reporte identifica filtros, fecha de generación, zona horaria, moneda y criterio temporal. El período de ventas cobradas usa la fecha del cobro; las devoluciones se muestran por fecha de devolución, con referencia a la venta original.

RF-40. Las exportaciones conservarán los filtros y el alcance de la vista. Los totales exportados deben coincidir con los consultados. El acceso de solo consulta no concede automáticamente permiso para exportar información completa.

RF-41. La bitácora registrará acciones sensibles: cambios de precio, descuento, cancelación, devolución, retiro, cierre con diferencia, cambio de permisos, activación de módulos y modificación de dispositivos. Mostrará quién, cuándo, dónde, motivo y valores anterior y nuevo cuando aplique.

RN-12. No se reportará utilidad real sin costos definidos. No se reportará tiempo real de preparación sin eventos de cocina. Los indicadores que dependan de módulos desactivados se identificarán como no disponibles; no mostrarán ceros que parezcan mediciones.

## 13. Inventario y recetas: ampliación delimitada

En BASE, disponibilidad significa que el encargado puede marcar un producto como vendible o agotado. No significa que se lleven cantidades de masa, queso o frijoles. El inventario es un módulo opcional con operaciones y validaciones propias; habilitarlo exige preparar unidades, existencias iniciales y responsables.

RF-42. Cuando se implemente inventario, las existencias pertenecerán a una sucursal. Las entradas, salidas, ajustes y mermas tendrán documento, cantidad, unidad, fecha y responsable. La corrección de una operación confirmada se realizará mediante reversión trazable.

RF-43. Una receta definirá el consumo de ingredientes por unidad de producto y su vigencia. Como regla propuesta, el consumo se registra al iniciar preparación; en modo papel se utilizará la confirmación de envío como aproximación operativa explícita. Cambiar una receta no recalcula consumos históricos.

RF-44. Cancelar antes de comenzar permitirá revertir reservas. Si el producto ya se preparó, los ingredientes no reaparecen automáticamente: se registra merma o recuperación autorizada. La política de venta con existencias insuficientes se configura antes de habilitar el módulo y debe quedar visible al operador.

RF-45. Una transferencia entre sucursales tendrá solicitud, despacho y recepción. El material en tránsito no estará simultáneamente disponible en origen y destino. Las diferencias de recepción exigen motivo y revisión; una transferencia no es una venta.

RF-46. Compras y recepción, cuando se contraten, permitirán proveedor, documento y recepción de cantidades reales. Ordenar una compra no aumenta existencias; confirmar su recepción sí. No se incluyen cuentas por pagar ni contabilidad completa dentro de esta ampliación sin una definición adicional.

## 14. Centro de funcionalidades y administración técnica

Existirá un Centro de funcionalidades para conocer qué capacidades están disponibles y decidir dónde se utilizan. El administrador técnico controlará la disponibilidad de la plataforma; el administrador de empresa activará las capacidades que tenga autorizadas; el encargado configurará únicamente parámetros de su sucursal que le hayan sido delegados.

RF-47. Cada capacidad mostrará nombre, finalidad, alcance, disponibilidad, requisitos, estado y responsable del último cambio. Los estados serán: no disponible, disponible sin configurar, lista para activar, activa y desactivada. Solo una capacidad construida y validada puede llegar a activa.

RF-48. La activación de KDS comprobará estación y usuarios; inventario comprobará unidades y saldos iniciales; una integración fiscal comprobará ambiente y credenciales mediante su procedimiento específico. Un fallo deja la capacidad sin activar y explica qué falta.

RF-49. Una capacidad activa a nivel de empresa puede habilitarse en sucursales autorizadas. Desactivarla en una sucursal no afecta a las otras. Una función deshabilitada se bloquea tanto en las pantallas como en las operaciones del servidor.

RF-50. La desactivación debe mostrar dependencias y trabajo pendiente. Preservará datos históricos y permitirá su consulta autorizada. Si genera pedidos sin destino, procesos inconsistentes o información imposible de cerrar, deberá bloquearse hasta resolverlos. Reactivar recupera la configuración conservada tras validarla.

### Ejemplo de configuración completa

El administrador técnico marca KDS como disponible para la empresa. Su administrador asigna la capacidad a Norte. El encargado configura estación Cocina Norte y categorías de pupusas, y asigna acceso a sus cocineros. Solo entonces se activa. Centro sigue imprimiendo. Un cajero de Centro no puede abrir KDS Norte por conocer su dirección.

RN-13. Los cambios de configuración no deben requerir que un programador edite código para cada cliente. La instalación de una capacidad nueva sí puede requerir una publicación técnica; su uso posterior depende del Centro de funcionalidades y de permisos.

## 15. Comprobantes, dispositivos y continuidad

El comprobante operativo confirma el registro de la venta y muestra sus importes. No se presentará como documento tributario autorizado sin una integración y reglas fiscales definidas. El documento funcional no fija un país, régimen, proveedor fiscal ni equipo específico que el negocio todavía no haya elegido.

RF-51. El ticket BASE incluirá identificación comercial, sucursal, número de venta, fecha, líneas, descuentos, total, medio de pago, efectivo recibido y cambio cuando aplique. Una reimpresión se identifica como copia y no genera otra venta. Los datos personales se incluyen solo cuando sean necesarios.

RF-52. La configuración de impresoras mostrará sucursal, nombre, función, conexión y prueba de impresión. La instalación deberá contar con una lista de modelos y conexiones efectivamente probados. No se seleccionará una impresora de otra sucursal como sustitución silenciosa.

RF-53. El sistema informará pérdida de conexión, errores de impresión y operaciones pendientes. Mientras no pueda confirmar una escritura en el servidor, no mostrará cobro o venta como completados. Al recuperarse, consultará el resultado de los intentos inciertos antes de reenviarlos.

RF-54. La integración fiscal, si se incorpora, diferenciará venta registrada de documento pendiente, aceptado o rechazado. Un rechazo fiscal no elimina una venta ya cobrada. El reintento conservará la identidad del documento; los estados simulados quedarán limitados a pruebas y nunca se presentarán como autorización real.

RN-14. El funcionamiento offline no forma parte de BASE. Si se cae el servicio, se aplica el procedimiento manual acordado y se registra la incidencia. La incorporación posterior de operaciones manuales requiere identificar su origen para no confundirlas con nuevas ventas ni duplicar efectivo.

## 16. Calidad de uso, seguridad y operación

Los requisitos de calidad también forman parte del producto. Se proponen los siguientes objetivos de aceptación para el piloto; el equipo registrará hardware, red y volumen usados para comprobarlos. No son garantías de capacidad ilimitada.

| ID | Exigencia comprobable |
| RNF-01 | Diseño táctil desde 1024 × 768 en orientación horizontal, controles principales de al menos 44 × 44 píxeles y separación suficiente para evitar toques accidentales. |
| RNF-02 | Ninguna acción necesaria depende solo del color o de pasar el cursor. Texto legible, foco visible y mensajes que expliquen cómo continuar. |
| RNF-03 | Agregar un artículo muestra respuesta visual en menos de 300 ms; confirmar pedido y cobro devuelve respuesta en menos de 2 s en el percentil 95 del piloto, excluyendo servicios externos e impresión física. |
| RNF-04 | Prueba base propuesta: dos sucursales, dos terminales por sucursal, cuatro operadores concurrentes y catálogo de 500 artículos. Si el cliente requiere más, se amplía la prueba antes de comprometer capacidad. |
| RNF-05 | Fallar o repetir una solicitud no duplica pedidos, cobros, devoluciones, movimientos ni instrucciones de cocina. Se prueba desconexión antes y después de la confirmación. |
| RNF-06 | Toda operación verifica identidad, permiso y alcance. Las comunicaciones se protegen y los secretos no se muestran en pantallas, exportaciones ni bitácoras. |
| RNF-07 | Respaldo automatizado diario y prueba de restauración antes del piloto; objetivo inicial propuesto de recuperación de servicio en 4 h y pérdida máxima de 24 h, sujeto a infraestructura elegida. |
| RNF-08 | El historial conserva fechas consistentes, zona horaria del negocio y referencia de operaciones relacionadas. Los reportes no cambian su criterio de fecha entre pantalla y exportación. |
| RNF-09 | Las pantallas admiten datos vacíos, nombres largos, importes grandes y errores sin ocultar botones ni superponer texto. |

### Lenguaje visual común

La interfaz deberá priorizar empresa, sucursal, pedido y total. Se propone conservar azul oscuro para estructura, verde azulado para acciones principales y ámbar para atención. Éxito, pendiente y error siempre tendrán etiqueta. La paleta de este documento organiza la lectura; no sustituye una validación de las pantallas reales.

El color no decidirá permisos ni estados por sí solo. Una devolución, cancelación o cierre necesita texto de confirmación con el pedido o caja afectados. Los mensajes técnicos extensos se reservan al diagnóstico; al cajero se le indicará el problema y la siguiente acción disponible.

## 17. Escenarios completos que deben funcionar

### Escenario A · Venta de mostrador con comanda

Centro abre Caja 1 con $50.00. El cajero registra el ejemplo de seis pupusas por $6.50, para llevar. El cliente entrega $10.00; el sistema calcula $3.50 de cambio. Se registra un cobro por $6.50 y un envío a Cocina Centro. El cajero entrega un ticket; cocina recibe sus instrucciones. Cuando el cliente recoge, el cajero registra entrega. El pedido termina pagado, entregado y consultable. Caja contiene $56.50 atribuibles al fondo y esa venta.

### Escenario B · Solicitud de otro producto después de pagar

Sobre el pedido anterior, el cliente solicita una revuelta adicional de $1.00. El sistema no modifica el pedido ya pagado ni crea un cobro adicional dentro de esa orden. El cajero registra una nueva orden por $1.00, cobra una sola vez esa nueva orden y envía una comanda adicional identificada. La primera comanda permanece intacta.

### Escenario C · Impresión con resultado incierto

El servidor envía una comanda y pierde confirmación del dispositivo. El pedido sigue existiendo y el pago sigue registrado. El operador ve resultado incierto, verifica si salió papel y decide confirmar comunicación o imprimir una copia identificada. El sistema no reenvía ciegamente como una nueva instrucción de cocina.

### Escenario D · Pago rechazado antes de cocina

Norte registra una orden de $8.00. El medio de pago es rechazado. El sistema conserva el borrador para corregir el medio o cancelarlo, pero no genera comanda, no descuenta inventario y no permite entregar. Cuando el cliente paga correctamente, se registra un único cobro aprobado y recién entonces se confirma y se envía la orden.

### Escenario E · Cancelación y devolución

Un cliente cancela una orden de $6.50 ya pagada. El encargado registra motivo, revisa si cocina inició y comunica cancelación. Si autoriza devolución total en efectivo, se registra un egreso por $6.50 vinculado al pago original. La orden conserva pago y devolución; no desaparece. Si ya se preparó, no se restituyen ingredientes automáticamente.

### Escenario F · Dos sucursales con cocina diferente

Centro opera con comanda y Norte con KDS. Cada pedido llega a la cocina de su local. El gerente puede consultar el total de ambas; el cajero Centro solo consulta su alcance. Desactivar KDS Norte obliga a resolver instrucciones activas y configurar impresión antes de continuar. Centro no cambia su modo.

### Escenario G · Cobro concurrente y recuperación

Dos dispositivos intentan cobrar la misma orden. Solo uno confirma un cobro válido; el otro recibe el estado pagado y no registra un segundo ingreso ni una segunda comanda. Si el primero perdió conexión después de confirmar, al reconectarse obtiene el cobro existente y puede reimprimir el ticket como copia.

### Escenario H · Cierre con diferencia

El responsable inicia cierre, verifica que no existan borradores presentados como ventas y cuenta $153.00 ante un esperado de $155.00. Registra la diferencia de -$2.00 con observación y revisión del encargado. El reporte conserva el faltante. Una venta posterior requiere nueva sesión; no modifica el cierre anterior.

## 18. Aceptación y trazabilidad del alcance

Cada prueba se ejecutará con usuarios y sucursales reales de un ambiente de prueba. La evidencia debe identificar datos utilizados, resultado, responsable y fecha. Una captura de pantalla por sí sola no demuestra aislamiento, ausencia de duplicados o corrección monetaria: se revisan también los registros resultantes.

| Prueba | Requisitos | Resultado exigido |
| AC-01 · Aislamiento | RF-01 a RF-06; RN-01 | Un usuario de Empresa A no consulta ni modifica Empresa B; un cajero no opera sucursales no asignadas, incluso mediante solicitudes directas. |
| AC-02 · Catálogo | RF-07 a RF-10; RN-02 a RN-05 | Variantes y extras se validan; disponibilidad es local; cambiar precio no altera pedidos confirmados. |
| AC-03 · Pedido táctil | RF-11 a RF-15 | El cajero registra y recupera un pedido completo con notas; doble toque confirma una sola orden. |
| AC-04 · Estados | RF-16 a RF-18; RN-06 | El pago aprobado es requisito para confirmar y enviar; preparación avanza después del pago; concurrencia no duplica cobro ni comanda. |
| AC-05 · Comanda | RF-19 a RF-23; RN-07 y RN-08 | Original, adicional, cancelación y copia se distinguen; ningún envío cruza sucursales ni duplica preparación silenciosamente. |
| AC-06 · KDS opcional | RF-24 a RF-27; RN-09 | Adicionales y cancelaciones se reflejan una vez; desconexión se informa; desactivar no deja instrucciones huérfanas. |
| AC-07 · Dinero | RF-28 a RF-32; RN-10 y RN-11 | Cada orden se cobra una sola vez; adicionales son nuevas órdenes; cambios y reembolsos no duplican dinero. |
| AC-08 · Caja | RF-33 a RF-37 | Solo una sesión por caja; cierre aplica fórmula; diferencia se conserva; devolución posterior no reescribe cierre. |
| AC-09 · Reportes | RF-38 a RF-41; RN-12 | Detalle, totales, consolidado y exportación concuerdan con fechas y alcance visibles. |
| AC-10 · Inventario opcional | RF-42 a RF-46 | Receta consume una vez; merma no devuelve stock; transferencia requiere recepción; compra sin recepción no aumenta existencias. |
| AC-11 · Capacidades | RF-47 a RF-50; RN-13 | Función no construida no se activa; requisitos se validan; desactivación conserva historial y verifica dependencias. |
| AC-12 · Continuidad | RF-51 a RF-54; RN-14 | Ticket y fiscal se distinguen; errores conservan pedido; impresión respeta sucursal; reconexión no duplica cobro. |
| AC-13 · Calidad | RNF-01 a RNF-09 | Prueba táctil, rendimiento, concurrencia, autorización, restauración y límites visuales aprobados en ambiente documentado. |

AC-06 y AC-10 solo son obligatorias cuando se entregue su módulo. En AC-12, RF-54 solo aplica si se incorpora emisión fiscal. En la primera entrega, esos módulos permanecen identificados como no disponibles y su ausencia no se oculta al usuario.

### Condición de salida del piloto

Se requiere completar los escenarios A, B, C, D, E, G y H, además de F en su parte de aislamiento y modos disponibles. No deben quedar defectos que permitan una orden en cocina sin pago aprobado, dupliquen dinero o comandas, mezclen empresas, pierdan pedidos o impidan cerrar caja. Las incidencias menores restantes tendrán responsable, impacto y fecha acordada antes de aceptar la entrega.

## 19. Entregables para construir y operar

El equipo de producto utilizará este documento como fuente funcional. Antes de implementar cada área, asociará sus tareas a los identificadores RF, RN y AC correspondientes. Los diseños de pantalla y decisiones técnicas detallan cómo se realiza el requisito; no cambian su comportamiento sin registrar el cambio.

| Entregable | Contenido mínimo | Evidencia de cierre |
| Definición funcional | Este documento, alcance y decisiones de negocio | Revisión de responsables y decisiones registradas. |
| Diseño de pantallas | POS, pedidos, cobro, caja, configuración y reportes | Recorridos completos, incluidos estados vacíos y errores. |
| Construcción BASE | Requisitos obligatorios y permisos | Demostración de escenarios y registros consistentes. |
| Instalación piloto | Empresa, dos sucursales de prueba, cajas y dispositivos | Impresión correcta, acceso y configuración verificados. |
| Formación | Guía de cajero, encargado y administrador | Personal completa venta, incidencia y cierre. |
| Continuidad | Respaldos, recuperación, contactos y fallos habituales | Restauración comprobada y procedimiento disponible. |
| Aceptación | Matriz AC, incidencias y alcance entregado | Acta con resultados y excepciones expresas. |

No se fijan fechas, precios ni un número de horas de soporte sin estimación y acuerdo. Esos compromisos comerciales se completan en la contratación. El documento sí exige que se definan responsables de soporte, horario de atención, escalamiento y procedimiento de recuperación antes de operar con clientes reales.

## 20. Decisiones de negocio y control de cambios

La intención de mantener una sola línea se concreta con un documento de nombre estable, requisitos identificables y una lista central de decisiones. La existencia de una propuesta escrita evita asumir funciones por comentarios aislados; la validación de negocio evita presentar como acordado algo que todavía necesita una elección.

| ID | Decisión | Conducta base propuesta y efecto |
| D-01 | Momento de cobro | Un solo cobro completo por pedido, realizado al tomar la orden y aprobado antes de enviar a cocina. No existe cobro posterior ni crédito. |
| D-02 | Atención en local | Mostrador con referencia opcional de mesa. Meseros, plano y cuenta dividida requieren alcance adicional. |
| D-03 | País, moneda e impuestos | Se configuran antes del piloto. Los importes en dólares del documento son ejemplos. Fiscal requiere definición legal y proveedor. |
| D-04 | Medios de pago | Efectivo y registro manual de medios autorizados; un medio por cobro BASE. Integración bancaria o pago dividido requieren extensión. |
| D-05 | Inventario | BASE usa disponibilidad manual. Existencias y recetas requieren alta de módulo y preparación de datos. |
| D-06 | Infraestructura y equipos | Operación conectada. Seleccionar servidor, conectividad y modelos de impresora antes de probar capacidad y recuperación. |
| D-07 | Responsabilidad de caja | Un responsable por sesión, relevo mediante cierre y apertura. Otro manejo necesita regla aprobada. |
| D-08 | Soporte y conservación | Definir responsables, horarios, retención histórica y política de datos antes de producción; no se borran registros automáticamente por omisión. |

### Procedimiento para una solicitud nueva

Quien solicite un cambio deberá describir el problema y un ejemplo. El analista identificará los requisitos afectados, su efecto sobre otras áreas y la prueba de aceptación. El equipo estimará el esfuerzo y el responsable de producto decidirá si entra en la entrega actual o queda en una ampliación. Una vez decidido, se actualiza esta misma definición y el registro de cambios.

Una corrección editorial que no modifica comportamiento mantiene la línea funcional. Un cambio que agrega una capacidad o altera reglas exige actualizar el alcance y avisar al equipo. Se conservará historial en el control del proyecto, pero se distribuirá un solo PDF vigente llamado SystemPos.pdf, evitando copias ambiguas como final2 o definitivo_nuevo.

### Acuerdo que debe quedar al aprobar

El propietario y el equipo deben reconocer el mismo escenario: una pupusería registra pedidos táctiles, cobra según la política elegida, comunica instrucciones a su cocina, entrega, cierra caja y consulta resultados. Varias sucursales comparten la definición del negocio y conservan su operación local. Las capacidades opcionales se incorporan con reglas de activación y pruebas propias.

La aprobación de esta base confirma qué se construye y cómo se acepta. No convierte las ampliaciones en entregables implícitos ni acredita software existente. Con las decisiones aplicables resueltas y los requisitos BASE aceptados, el equipo tendrá un punto de cierre verificable para SystemPos.
