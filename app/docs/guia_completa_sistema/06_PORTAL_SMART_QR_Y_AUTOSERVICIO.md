# 06. Portal Smart QR y Autoservicio

## 1. Concepto del Flujo Smart QR

El módulo **Smart QR** es la característica comercial diferenciadora del **Plan Smart QR ($49/mes)** y **Plan Pro ($69/mes)**.

Permite al restaurante emitir un ticket de venta tradicional con un **código QR dinámico** que traslada la carga operativa de la facturación electrónica hacia el propio comensal, reduciendo tiempos de espera en caja y optimizando el consumo de DTEs ante el Ministerio de Hacienda.

---

## 2. Flujo de Experiencia del Cliente

```
┌────────────────────────────────────────────────────────────────────────┐
│                        FLUJO COMERCIAL SMART QR                        │
├────────────────────────────────────────────────────────────────────────┤
│ 1. El cliente consume en el local o pide para llevar.                  │
│ 2. El cajero realiza el cobro en el POS y entrega el ticket.           │
│ 3. El ticket contiene un código QR único con token criptográfico.      │
│ 4. El cliente escanea el QR desde su smartphone.                       │
│ 5. Se abre el Portal Web del restaurante (https://tenant.pos.com/qr/..)│
│ 6. El cliente puede:                                                   │
│    • Consultar el desglose de su cuenta.                               │
│    • Solicitar Factura de Consumidor Final (CF) ingresando su DUI/NIT. │
│    • Solicitar Comprobante de Crédito Fiscal (CCF) con datos empresa.  │
│    • Ver el menú digital para ordenar una nueva tanda de comida.       │
│ 7. La API Fiscal genera el DTE y le entrega el PDF/JSON en su móvil.   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. ¿Por qué el QR no aparece en el Plan Básico?

- **Plan Básico ($29/mes):** Diseñado para clientes que solo buscan operación local (mesas, comandas y caja). El método `qrLine()` retorna `null` y el ticket muestra el texto de comprobante interno de caja.
- **Plan Smart QR ($49/mes):** El sistema detecta `plan_code === 'smart_qr'` en la base de datos del tenant, genera el token opaco único y renderiza el código QR en la parte inferior del ticket térmico o PDF.

---

## 4. Arquitectura de Endpoints WebFact (API REST)

El sistema expone dos capas de endpoints dedicados bajo `routes/api.php`:

### A. Endpoints Públicos para Clientes (`/api/v1/portal-qr`)
- **`GET /api/v1/portal-qr/orden/{tracking}`:** Consulta el desglose de pupusas, combos, notas de cocina, montos y estado de solicitud por número de seguimiento o código corto.
- **`GET /api/v1/portal-qr/estado?trackingPOS={tracking}`:** Consulta rápida compatible con WebFact retornando `estadoDTE`, `codigoGeneracion` y `selloRecepcion`.
- **`POST /api/v1/portal-qr/solicitar`:** Recibe la petición del cliente (Factura 01 o CCF 03 con NIT, NRC, Razón Social, Giro, Dirección, Teléfono, Correo).

### B. Endpoints Administrativos Protegidos (`/api/v1/portal-admin`)
Protegidos mediante Bearer Token (`AuthenticatePortalAdmin`):
- **`POST /api/v1/portal-admin/login`:** Login con usuario/email y password de administrador. Retorna Bearer Token válido por 24 horas.
- **`GET /api/v1/portal-admin/solicitudes`:** Listado paginado con filtros por estado (`PENDIENTE`, `EMITIDO`, `RECHAZADO`), buscador y conteo estadístico.
- **`PUT /api/v1/portal-admin/solicitudes/{id}`:** Modificación de datos de facturación antes de la emisión.
- **`POST /api/v1/portal-admin/solicitudes/{id}/generar`:** Disparo manual de emisión del DTE hacia el Ministerio de Hacienda.
- **`POST /api/v1/portal-admin/solicitudes/{id}/rechazar`:** Rechazo con registro de motivo.
- **`GET /api/v1/portal-admin/configuracion` & `PUT /api/v1/portal-admin/configuracion`:** Consulta y guardado del modo de emisión activo.

---

## 5. Modos de Emisión Fiscal Configurables

Configurables en `Configuracion` (`clave: modo_emision_portal`):

| Modo | Comportamiento |
|---|---|
| **`AUTOMATICO`** | Todas las solicitudes enviadas por clientes se emiten y transmiten al instante al Ministerio de Hacienda. |
| **`MANUAL`** | Todas las solicitudes quedan en estado `PENDIENTE` para que un administrador las revise y apruebe manualmente. |
| **`HIBRIDO` *(Por defecto / Recomendado)*** | **Facturas de Consumidor Final (01)** se emiten al instante de forma automática; **Comprobantes de Crédito Fiscal (03 / CCF)** pasan a validación manual para verificar NRC y Giro. |

---

## 6. Generación Gráfica del QR y Dominio WebFact

- **URL de Producción:** `https://boomwalos.vercel.app/?tracking={NUMERO_SEGUIMIENTO}` (configurable vía `WEBFACT_URL` o `FRONTEND_URL` en `.env`).
- **Generación Local Offline:** Motor `chillerlan/php-qrcode` produciendo imágenes Base64 PNG a escala 4x incrustadas directamente en los PDFs térmicos.
- **Fallback Automático:** Si la extensión gráfica local no está disponible, el sistema conmuta automáticamente a la API de contingencia `api.qrserver.com`.
- **Impresoras Físicas:** Comando ESC/POS nativo `Printer::qrCode()` para impresión instantánea en rollos de 80mm.
