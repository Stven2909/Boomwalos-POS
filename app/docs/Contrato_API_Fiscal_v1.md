# Contrato API Fiscal v1

Contrato de integración del POS **Los Boomwalos** con la plataforma de emisión de
comprobantes (DTE) del proveedor fiscal.

> **Fase 0+1 (esta entrega):** contrato + simulador `ENV-ONLY`.
> El simulador solo existe para desarrollo/QA y **nunca** puede activarse en
> `APP_ENV=production` (el guard se valida en el controlador).
> La operación real queda determinada por la configuración por establecimiento
> (`configuraciones_fiscales`, `fiscal_habilitada`), pendiente en Fase 2.

---

## 1. Identidad del cliente

Cada establecimiento autentica sus llamadas con firma **HMAC-SHA256** sobre el
cuerpo crudo de la petición. Cabeceras requeridas en todas las llamadas salientes:

| Cabecera              | Ejemplo                              | Descripción                                  |
|-----------------------|--------------------------------------|----------------------------------------------|
| `Content-Type`        | `application/json`                   | Cuerpo JSON.                                 |
| `X-Fiscal-Key`        | `est-0001`                           | Clave pública del establecimiento.           |
| `X-Fiscal-Timestamp`  | `1786802400`                         | Segundos Unix (UTC). Tolerancia ±300 s.      |
| `X-Fiscal-Hmac`       | `sha256=<hex64>`                     | `hex(hmac_sha256(cuerpo_crudo, cliente_secret))`. |

El servidor rechaza con `401` la firma ausente, vencida o inválida.

## 2. Envío de venta

`POST /api/fiscal/v1/ventas`

Cuerpo:

```json
{
  "clave_reintento": "v-2026-08-15-0001",
  "referencia": "P-000123",
  "fecha_emision": "2026-08-15T12:00:00-06:00",
  "monto_total": "4.00",
  "metodo_pago": "EFECTIVO",
  "receptor": null
}
```

| Campo           | Tipo    | Reglas                                        |
|-----------------|---------|-----------------------------------------------|
| `clave_reintento` | string | Obligatorio, 1..100. Idempotencia.            |
| `referencia`    | string  | Obligatorio, 1..50. Número de seguimiento POS.|
| `fecha_emision` | date    | Obligatorio (RFC 3339).                       |
| `monto_total`   | decimal | Obligatorio, ≥ 0, 2 decimales.                |
| `metodo_pago`   | string  | Opcional. `EFECTIVO` / `TARJETA`.             |
| `receptor`      | object  | Opcional. Datos del receptor (factura/CCF).   |

Respuestas:

| Código | Cuerpo (ejemplo)                                           | Significado                                      |
|--------|------------------------------------------------------------|--------------------------------------------------|
| `202`  | `{"fiscal_sale_id":"MOCK-AB12CD34EF56","estado":"RECIBIDA","qr_url":null}` | Aceptada. **RECIBIDA = la API recibió la venta; el DTE aún no está emitido.** |
| `409`  | `{"error":"CLAVE_REUTILIZADA","mensaje":"..."}`            | Misma `clave_reintento` con payload distinto.    |
| `401`  | `{"error":"FIRMA_INVALIDA"}`                               | Firma ausente/vencida/incorrecta.                |
| `422`  | Errores de validación Laravel                              | Cuerpo inválido.                                 |
| `404`  | —                                                            | Mock desactivado (solo cuando no es ambiente).   |

### 2.1 Idempotencia

- Misma `clave_reintento` + **mismo** payload → `202` con el mismo
  `fiscal_sale_id` (seguro de reintentar).
- Misma `clave_reintento` + payload **distinto** → `409` (nunca se contesta).

## 3. Semántica de estados (cliente POS)

| Estado POS         | Significado                                              |
|--------------------|----------------------------------------------------------|
| `SINCRONIZADO`     | La API **recibió** la venta (`fiscal_sale_id` asignado). |
| `NO`               | El DTE **no** está emitido (receptor no asignado).       |
| `ENVIO_FALLIDO`    | El último intento de envío falló (queda en cola).        |

El **receptor** de un documento se conserva mientras el DTE no se emite y se
**borra al sincronizar** la venta.

## 4. Solicitud de documento

La solicitud de un documento (factura/CCF) al receptor está limitada a
**48 horas** desde la emisión. Pasado `expires_at`, la solicitud se rechaza;
la venta permanece sincronizada.

## 5. Webhooks (orden y reconciliación)

- El proveedor notifica con `secuencia` y `tipo` (`DTE_EMITIDO`, `DTE_RECHAZADO`, ...).
- Si un webhook llega **desordenado** (secuencia menor a la esperada), se
  almacena en `PENDIENTE` y se reconcilia por `ultima_secuencia_webhook`.
- La venta pasa a `NO` (DTE emitido) cuando el tipo lo indica.

## 6. Reintento manual

El operador con permiso puede reintentar el envío de una venta en `ENVIO_FALLIDO`
reutilizando la **misma `clave_reintento`** y el **mismo `payload_envio`**
almacenados en `cola_ventas_fiscales`.
