# Tenancy del POS

Guía operativa de multiempresa y contexto de sucursal. Complementa el
[ADR-001](./ADR-001-Multiempresa-y-contexto-de-sucursal.md).

## Modelo

- Cada empresa (tenant) vive en `platform_tenants` y tiene su propia base de
  datos operativa registrada en `platform_tenant_connections`.
- Las tablas operativas (pedidos, ventas, cocina, caja…) **no** llevan
  `tenant_id`: el aislamiento es por conexión de base de datos.
- Dentro de una empresa hay varias sucursales (`establecimientos`). El
  personal operativo se relaciona con ellas mediante la tabla pivote
  `establecimiento_usuario`.
- El subdominio identifica el `slug`: `acme.pos.localhost` → tenant `acme`.

## Modos

| Modo | Activación | Comportamiento |
| --- | --- | --- |
| `single` | `TENANT_DATABASE_MODE=single` (default en tests) | Todas las filas comparten una base. El subdominio resuelve el tenant y llena `TenantContext`, pero **no** hay aislamiento real de datos. Solo para desarrollo/pruebas. |
| `database` | `TENANT_DATABASE_MODE=database` (producción) | El subdominio resuelve el tenant y `TenantConnectionResolver` cambia la conexión `tenant` a la base operativa de la empresa. Host desconocido → `404`. |

Reglas de host en `TenantConnectionResolver::slugFromHost()`:

- `localhost` / `127.0.0.1` → `tenancy.default_slug`.
- `X.pos.localhost` → `X`.
- Otro host: `default_slug` en modo `single`, `null` en modo `database`.

## Orden de middleware

`ResolveTenant` está prependido al grupo `web` (antes de `EncryptCookies`) y
aplicado al grupo `api/fiscal/v1`:

- Web: `[ResolveTenant, EncryptCookies, AddQueuedCookies, StartSession,
  ShareErrorsFromSession, PreventRequestForgery, SubstituteBindings]`
- Panel Filament (`AdminPanelProvider`): `ResolveTenant` antes de
  `SubstituteBindings` para que el route-model binding resuelva dentro de la
  base del tenant.

`ResolveTenant` ejecuta `useTenant` antes de `$next()` y `reset()` en el
`finally`: la conexión por defecto y `EstablishmentContext` vuelven al estado
de respaldo al terminar el request. No arrastrar contexto entre peticiones.

## Firma de datos (fiscal)

- Los endpoints `api/fiscal/v1/*` exigen `ResolveTenant` y HMAC
  (`X-Fiscal-Key`, `X-Fiscal-Timestamp`, `X-Fiscal-Hmac`) con
  `HmacSigner::sign($content, config('fiscal.mock.secret'))`.
- La idempotencia del mock se califica por tenant:
  `fiscal.mock.venta.{slug}.{clave}`. Sin el slug, dos empresas con la misma
  clave colisionarían con un falso `409 CLAVE_REUTILIZADA`.
- En modo `single` el webhook atribuye el evento por `fiscal_sale_id` global;
  el aislamiento real se prueba y garantiza en modo `database`.

## Sucursal activa

- La sesión guarda la sucursal activa; `EstablishmentContext` la expone y
  valida (`set()` → `AuthorizationException` si no hay acceso).
- Páginas operativas (cocina, entrega) usan `GuardsEstablishment` en `mount()`:
  varias sucursales sin selección → redirige a `EstablishmentSelection`;
  ninguna → `403`.
- `ReprintTicket` verifica que el pedido pertenezca a la sucursal activa y
  lanza `AuthorizationException` antes de encolar impresión.

## Probar tenancy

```bash
# Modo single (suite completa)
php artisan test

# Modo database (aislamiento real)
TENANT_DATABASE_MODE=database php artisan test --filter \
  "FiscalTenantIsolationTest|EstablishmentBindingTest|ReprintTicketIsolationTest|GatesEstablecimientoTest"
```

- `tests/Feature/Traits/TenantDatabaseHarness.php` crea tres SQLite en archivo
  (`platform`, `acme`, `beta`) y migra el esquema operativo por la conexión
  dinámica llamada `tenant`.
- **Trampa**: las migraciones `000003`, `000004` y `000007` omiten tablas de
  plataforma con `Schema::getConnection()->getName() === 'tenant'`. Migra
  siempre con `--database=tenant`, nunca con `tenantA`/`tenantB`.
- **Trampa**: en los tests, el host del tenant se pasa en la URL absoluta
  (`http://acme.pos.localhost/...`). Symfony `Request::create` sobrescribe
  `HTTP_HOST` con el host del URI.
- **Trampa**: las bases de tenant deben ser archivos, no `:memory:`
  (las conexiones purgadas pierden el esquema).

## Operación

- Registro central: `php artisan platform:migrate`.
- Esquema operativo por empresa: `php artisan tenants:migrate`.
- Registrar una nueva empresa: crear `platform_tenants` activo + su conexión en
  `platform_tenant_connections`, luego migrar el esquema operativo en su base.
