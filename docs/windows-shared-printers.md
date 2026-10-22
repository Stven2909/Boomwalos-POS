# Conexiones a impresoras compartidas de Windows

La opción **Windows compartida** envía ESC/POS mediante el conector existente
`WindowsPrintConnector`. No usa una IP y un puerto TCP de impresora.

## Configuración

- Tipo: Ticket o Comanda, según el uso existente.
- Conexión: Windows compartida.
- Ruta compartida: `\\CAJA-PC\RECIBOS`.
- La ruta debe contener exactamente servidor y recurso compartido, sin credenciales.
- IP, puerto y dispositivo USB no participan en esta modalidad.

La fábrica valida la ruta y la transforma en `smb://CAJA-PC/RECIBOS`.
PHP debe ejecutarse en Windows, con una sesión autorizada para ese recurso.
El servidor web y el trabajador de colas pueden usar identidades diferentes:
comprobar el acceso desde ambos antes de activar la impresora.
No almacenar contraseñas en la aplicación.

## Despliegue

1. Respaldar los registros que se van a modificar.
2. Aplicar `2026_10_22_000002_add_windows_printer_connection.php`.
3. Recargar los trabajadores existentes, sin crear instancias duplicadas.
4. Configurar el registro elegido y dejarlo desactivado durante las pruebas.
5. Usar la acción Probar y verificar también un diagnóstico mediante la cola real.
6. Confirmar físicamente la impresión antes de activar el registro.

La aceptación de datos por Windows no demuestra por sí sola que salió papel.
Los servicios de tickets, formato, gaveta, reintentos y selección por sucursal
conservan su comportamiento previo.

## Pruebas

Ejecutar PHPUnit con `--filter WindowsPrinter`. Las pruebas de persistencia
exigen SQLite `:memory:`; no utilizar la base de producción.

La prueba MySQL se omite por defecto. Para ejecutarla, usar un servidor MySQL
de pruebas y definir `PRINTER_MYSQL_MIGRATION_TEST=1`, con las variables
`DB_HOST`, `DB_PORT`, `DB_USERNAME` y `DB_PASSWORD` apuntando a ese servidor.
Requiere PDO MySQL y permiso para crear/eliminar una base temporal.
La prueba crea exclusivamente una base `codex_printer_test_<aleatorio>` y
la elimina al terminar; no migra ni borra la base configurada de la aplicación.

## Reversión

Restaurar los datos respaldados y desactivar el registro si la prueba falla.
La migración impide el rollback mientras exista una conexión `WINDOWS`:
restaurar primero esas conexiones y después revertir código y esquema.
No cambiar configuraciones de Windows ni controladores como parte de este despliegue.
