# 03. Seguridad HMAC y Cliente HTTP Fiscal

## 1. Contexto de la Integración Fiscal

El sistema POS se integra con un servidor API Fiscal externo alojado en Cloudways:
- **URL Base:** `https://phplaravel-1581457-6620216.cloudwaysapps.com`
- **Endpoint de Emisión:** `/api/v1/pos/emitir`
- **Mecanismo de Autenticación:** Firma HMAC SHA256 Canónica Multielemento.

---

## 2. Esquema de Firma Canónica Multielemento

Para evitar ataques de repetición (*Replay Attacks*), alteración de montos (*Body Tampering*) o manipulación de rutas (*Path Tampering*), cada solicitud enviada por el POS debe construirse y firmarse siguiendo la norma canónica:

```
┌────────────────────────────────────────────────────────────────────────┐
│                      ESTRUCTURA DE LA CADENA CANÓNICA                  │
├────────────────────────────────────────────────────────────────────────┤
│                                                                        │
│   METHOD     (ej. POST)                                                │
│   \n                                                                   │
│   PATH       (ej. /api/v1/pos/emitir)                                  │
│   \n                                                                   │
│   TIMESTAMP  (ej. 1724117000 - Segundos Unix UTC)                      │
│   \n                                                                   │
│   NONCE      (ej. 4b2f8a10-2b15-4c0e-9f0a-112233445566 - UUIDv4)       │
│   \n                                                                   │
│   SHA256     (hash('sha256', JSON_BODY))                               │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

### Cálculo de la Firma Criptográfica:
```php
$bodyHash = hash('sha256', $body);
$canonicalString = strtoupper($method) . "\n" .
                   $path . "\n" .
                   $timestamp . "\n" .
                   $nonce . "\n" .
                   $bodyHash;

$signature = 'sha256=' . hash_hmac('sha256', $canonicalString, $secret);
```

---

## 3. Cabeceras HTTP Requeridas

Toda petición enviada a la API Fiscal debe incluir las siguientes 5 cabeceras obligatorias:

| Cabecera HTTP | Variable en `config/fiscal.php` | Propósito |
|---|---|---|
| **`X-Signature`** | `fiscal.hmac.header` | Firma criptográfica `sha256=<hex>` generada con el secret. |
| **`X-Timestamp`** | `fiscal.hmac.timestamp_header` | Marca de tiempo Unix en segundos UTC. Tolerancia máxima: $\pm 300$ segundos. |
| **`X-Client-Id`** | `fiscal.hmac.key_header` | Identificador público del cliente/establecimiento (`cliente_key`). |
| **`X-Nonce`** | `fiscal.hmac.nonce_header` | Identificador único de petición (UUID) que impide reenvíos duplicados. |
| **`Idempotency-Key`** | N/A | Clave de idempotencia (`v-{est}-{ped}-{pago}`) que previene dobles emisiones fiscales. |

---

## 4. Aislamiento de Variables en el `.env`

La URL y credenciales de la API fiscal **nunca se escriben en código duro**:

En `.env` local:
```dotenv
FISCAL_API_URL=https://phplaravel-1581457-6620216.cloudwaysapps.com
FISCAL_GATEWAY=http
FISCAL_API_TIMEOUT=15
FISCAL_MOCK_ENABLED=false
```

En `.env.example` (lo que se sube a GitHub):
```dotenv
FISCAL_API_URL=https://tu-api-fiscal.ejemplo.com
FISCAL_GATEWAY=http
FISCAL_API_TIMEOUT=15
FISCAL_MOCK_ENABLED=false
```

### Cifrado en Reposo en Base de Datos:
En el modelo [`ConfiguracionFiscal.php`](file:///f:/POSYSTEM/Boomwalos-POS/app/Models/ConfiguracionFiscal.php), el secreto del cliente se almacena cifrado con **AES-256-CBC**:
```php
protected function casts(): array
{
    return [
        'cliente_secret' => 'encrypted',
    ];
}
```
Incluso si la base de datos es extraída, el secreto permanece inaccesible sin la `APP_KEY`.
