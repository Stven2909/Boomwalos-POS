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
