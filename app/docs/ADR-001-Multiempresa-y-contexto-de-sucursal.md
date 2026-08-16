# ADR-001 — Multiempresa y contexto de sucursal

## Estado

Aceptada.

## Decisión

POS se implementa como un SaaS con un registro central de empresas y una base
de datos operativa independiente por empresa. El subdominio identifica el
`slug` de la empresa; `TenantConnectionResolver` obtiene la conexión y cambia
el contexto antes de ejecutar la operación.

Las tablas operativas no reciben `tenant_id`. El aislamiento principal ocurre
en la conexión de base de datos. El modo `single` existe únicamente para
desarrollo y pruebas con SQLite; producción debe usar
`TENANT_DATABASE_MODE=database` y una conexión registrada por empresa.

Cada empresa puede tener varias filas de `establecimientos`. El usuario
administrador tiene acceso a todas; el personal operativo se relaciona con las
sucursales permitidas mediante `establecimiento_usuario`. Las operaciones de
pedido, cobro, cocina y configuración consultan `EstablishmentContext` y no
eligen automáticamente una sucursal arbitraria cuando el contexto exige una
selección explícita.

La administración global usa el panel `/platform`, el guard `platform` y la
tabla central `platform_users`. El panel `/admin` queda para los usuarios de
una empresa.

La operación de despliegue se separa en dos comandos: `php artisan
platform:migrate` para el registro central y `php artisan tenants:migrate` para
el esquema operativo de cada empresa ya provisionada.

## Consecuencias

- El producto visible es `POS`; la marca comercial vive en `platform_tenants`.
- La información legal/fiscal vive en `configuraciones_fiscales`, separada de
  los colores, logos y textos de la marca.
- Los adaptadores externos se consumen mediante interfaces pequeñas. El flujo
  de cobro no conoce el cliente HTTP fiscal ni el driver de impresión.
- La migración de una instalación existente debe registrarla como el primer
  tenant y ejecutar el mismo esquema operativo en su base de datos.

## Seguridad

Las credenciales de conexiones de tenant y los secretos fiscales se almacenan
cifrados. El subdominio no concede autorización por sí solo: la solicitud
debe pasar por el contexto de empresa, usuario y sucursal.

## Reforzamiento de aislamiento (v5)

La revisión de seguridad del POS introdujo seis refuerzos que quedan
asentados como decisiones de diseño:

- **F1 — Resolución temprana de tenant en fiscal.** `ResolveTenant` se aplica
  al grupo `api/fiscal/v1` (ventas y webhooks). En modo `single` esto
  garantiza que `TenantContext` esté disponible durante el request y que la
  clave de idempotencia del mock se califique por tenant:
  `fiscal.mock.venta.{slug}.{clave}`. Sin el `slug`, dos empresas con la
  misma `clave_reintento` producían un falso `409 CLAVE_REUTILIZADA`.
- **F2 — Resolución temprana de tenant en web.** `ResolveTenant` se prepende
  al grupo `web` (antes de `EncryptCookies`) y se mueve en el panel Filament
  antes de `SubstituteBindings`. Así, el route-model binding de
  `establishment.context` (`{establecimiento}`) se resuelve dentro de la base
  operativa de la empresa del host.
- **F3 — Reimpresión protegida por sucursal.** `ReprintTicket` exige que
  `pedido.establecimiento_id` coincida con `EstablishmentContext::id()` y
  lanza `AuthorizationException` en caso contrario, antes de consultar la
  impresora o encolar `trabajo_impresion`. Las páginas que listan pedidos
  filtran por `establecimiento_id`.
- **F4 — Selección de sucursal forzada en páginas operativas.** El trait
  `GuardsEstablishment` se aplica en `mount()` de cocina y entrega: sin
  sucursal activa y con varias accesibles redirige a `EstablishmentSelection`;
  con ninguna lanza `403`. `EstablishmentContext::set()` falla con
  `AuthorizationException` si el usuario no puede acceder a la sucursal y
  `id()` con `ValidationException` si no hay sucursal seleccionada.
- **F5 — Moneda por sucursal sin crash.** `ServiceSelection` devuelve `$`
  cuando no hay sucursal activa en lugar de depender de una configuración
  inexistente.
- **F6 — Documentación y contrato.** Este ADR, `app/docs/TENANCY.md` y el
  phpdoc de `EstablishmentContextInterface` describen el modelo, los modos y
  las trampas conocidas.

## Pruebas de tenancy

El aislamiento real de datos se prueba en modo `database` con bases SQLite
temporales en archivo (no `:memory:`), migrando el esquema operativo mediante
la conexión dinámica llamada `tenant` — los guards de las migraciones
(`000003`, `000004`, `000007`) solo omiten tablas de plataforma cuando la
conexión se llama exactamente `tenant`. Harness en
`tests/Feature/Traits/TenantDatabaseHarness.php`.

- Modo `single` (predeterminado en `phpunit.xml`): `php artisan test`.
- Modo `database`: `TENANT_DATABASE_MODE=database php artisan test --filter
  "FiscalTenantIsolationTest|EstablishmentBindingTest|ReprintTicketIsolationTest|GatesEstablecimientoTest"`.

En las peticiones de test, el host del tenant debe ir en la URL absoluta
(`http://acme.pos.localhost/...`): Symfony `Request::create` sobrescribe
`HTTP_HOST` con el host del URI.
