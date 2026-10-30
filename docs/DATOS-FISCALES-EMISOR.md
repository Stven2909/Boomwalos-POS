# Ficha fiscal del emisor — Los Boomwalos

**Documento preparado para el contador**
Fecha de elaboración: 29 de octubre de 2026
Sistema: Boomwalos POS + ApiFact (facturación electrónica DTE)

> Este documento contiene datos fiscales y de configuración. **No incluye**
> contraseñas, certificados `.p12`, llaves de API ni credenciales del portal MH.

---

## 1. Identificación del emisor

| Campo | Valor |
|---|---|
| Razón social | INVERSIONES TICAS S. A. DE C. V. |
| NIT | 06141601141064 |
| NRC | 2310805 |
| Nombre comercial | Los Boomwalos Restaurante |
| Actividad económica | 56106 — Pupusería |
| Teléfono | 77447362 |
| Correo electrónico | los_boomwalos@hotmail.com |
| Ambiente de facturación | 01 — Producción |
| Tipo de establecimiento | 02 — Casa Matriz (CAT-009) |
| Modalidad | Facturación electrónica (DTE), transmisión en línea |

### Dirección fiscal

| Campo | Código | Descripción |
|---|---|---|
| Departamento | 06 | San Salvador |
| Municipio | 23 | San Salvador Centro |
| Distrito | 14 | San Salvador |
| Complemento | — | Carretera a Panchimalco 1/2 km, Los Planes de Renderos, San Salvador |

---

## 2. Establecimientos y puntos de venta

| Código | Nombre | Tipo | Estado |
|---|---|---|---|
| M001 | Boomwalos Los Planes de Renderos | Casa Matriz | Activo |
| P001 | Caja Principal | Punto de venta | Activo |

**Notas:**
- Al MH se envía el código `M001` y `P001`; el nombre del local no viaja en el DTE.
- No aplica CAI: en DTE la numeración se controla por establecimiento y punto de venta.

---

## 3. Documentos emitidos

### 3.1 Identificación

| # | Número de control | Código de generación | Tipo | Ambiente | Estado |
|---|---|---|---|---|---|
| 1 | `DTE-01-M001P001-000000000000001` | `BF78BD58-1ABD-494B-986A-4BA4EC4ED296` | Factura (01) | 01 | SEALED |
| 2 | `DTE-01-M001P001-000000000000002` | `13DFBDFD-6C8E-4B70-9F11-B90E5DC436C3` | Factura (01) | 01 | SEALED |

### 3.2 Montos y sello

| # | Fecha | Hora | Total | IVA | Receptor | Sello de recepción |
|---|---|---|---|---|---|---|
| 1 | 2026-10-18 | 16:52:00 | $1.00 | $0.12 | Consumidor Final | `202622548D808AAF4EB5955EECF8C960DAD9Q2KA` |
| 2 | 2026-10-18 | 18:32:45 | $1.00 | $0.12 | Consumidor Final | `20265470981A418946E18EADE5E264B8A26AKL6N` |

---

## 4. Correlativos (establecimiento M001 · punto de venta P001)

| Tipo DTE | Descripción | Último número usado | Siguiente número |
|---|---|---|---|
| 01 | Factura | 2 | 3 |
| 03 | Comprobante de Crédito Fiscal | 0 | 1 |
| 05 | Nota de Crédito | 0 | 1 |
| 14 | Sujeto Excluido | 0 | 1 |
| 15 | Comprobante de Donación | 0 | 1 |

> Existen registros históricos de correlativos en 0 correspondientes a
> configuraciones anteriores; no afectan la numeración vigente. También hay un
> punto de venta adicional creado el 22/10/2026 que nunca se usó y no tiene
> correlativos asignados.

---

## 5. Configuración técnica de facturación

| Concepto | Detalle |
|---|---|
| Plataforma | ApiFact (servicio propio de integración con Hacienda) |
| Integración POS | API REST autenticada con firma HMAC |
| Esquema Factura | MH `fe-f-v2` (DTE 01) |
| Esquema Crédito Fiscal | MH `fe-ccf-v4` (DTE 03) |
| Esquema Sujeto Excluido | MH `fe-fse-v2` (DTE 14) |
| Validación previa | Cada DTE se valida contra el JSON Schema oficial antes de firmar |
| Firma | Certificado `.p12` registrado en el MH |
| Contingencia | Emisión diferida + evento de contingencia ante el MH |

---

## 6. Información disponible en el sistema

Si el contador necesita respaldo documental, el sistema puede entregar:

| Recurso | Contenido | Formato |
|---|---|---|
| JSON firmado del DTE | Documento completo tal como se envió al MH (emisor, receptor, ítems, resumen, sello) | JSON |
| Representación gráfica | PDF del DTE sellado | PDF |
| Registro de ventas fiscales | Referencia, establecimiento, estado, monto, método de pago, ID fiscal, fecha de sincronización | Pantalla |
| Informe de ventas | Resumen por período, métodos de pago, productos más vendidos, ventas por sucursal | Pantalla / impresión |
| Informe de caja | Sesiones de caja y detalle de pagos | Pantalla / impresión |

**Notas:**
- El POS no genera todavía una exportación directa a CSV/Excel; se puede agregar
  si el contador la necesita.
- No existe un reporte llamado "libro de ventas". Se puede armar a partir del
  registro de ventas fiscales y de los DTE sellados.
- Solo los DTE sellados por Hacienda tienen PDF disponible.

---

## 7. Aclaraciones relevantes

1. **Dirección anterior.** Los dos DTE ya emitidos (18/10/2026) conservan la
   dirección anterior (“Plaza Universitaria, Centro Histórico de San Salvador”)
   dentro del JSON firmado. Son válidos e inmutables; la dirección no afecta
   montos, IVA ni el sello. El cambio aplica a partir de la próxima emisión.
2. **Casa Matriz.** El código `02` quedó configurado en el emisor. El esquema
   oficial de la Factura Electrónica (DTE 01) no incluye el campo
   `tipoEstablecimiento`, por lo que no se imprime en la factura. Se usa en los
   eventos de contingencia.
3. **Anulaciones.** No hay anulaciones ni reemisiones pendientes.
4. **Correlativos.** No se reiniciaron ni se alteraron con estos cambios.

---

## 8. Pendiente de confirmar

- Representante legal y documento de identidad.
- Fecha de inicio de actividades.
- Régimen tributario / calidad de contribuyente.
- Si la dirección registrada en el RUC ya coincide con la del local.

---

## 9. Información que no se comparte

Por seguridad, no se incluyen en este documento:

- Usuario y contraseña del portal MH.
- Certificado `.p12` ni su contraseña.
- `PROVISIONING_TOKEN`, `client_secret` ni llaves HMAC.
- Credenciales de base de datos o del servidor.
