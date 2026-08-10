# Entrega del proyecto Los Boomwalos POS

## Objetivo de este documento

Este archivo es una guía para que otro integrante pueda recibir el proyecto, instalarlo en su computadora, entender lo que ya está construido y continuar el desarrollo sin subir credenciales ni archivos privados.

La aplicación es un sistema POS para el restaurante Los Boomwalos. Actualmente cubre autenticación, administración del catálogo, mesas, pedidos, combos, cobro y el tablero operativo de cocina.

## Importante antes de compartir

Este documento resume los cambios actuales del sistema. Para que el compañero pueda descargarlos, primero hay que crear un commit y subirlo al repositorio remoto.

El archivo '.env' nunca debe subirse. Cada integrante debe crear su propio '.env' local usando '.env.example'.

## Descargar los cambios

Si todavía no tiene el proyecto:

~~~powershell
git clone https://github.com/Stven2909/Boomwalos-POS.git
cd Boomwalos-POS
git checkout main
git pull origin main
~~~

Si ya tiene una copia:

~~~powershell
cd ruta\al\Boomwalos-POS
git checkout main
git pull origin main
~~~

Si tiene cambios locales, debe guardarlos o hacer commit antes de ejecutar 'git pull'.

## Orden recomendado para trabajar con Git

La persona que ya tiene los cambios debe publicarlos:

~~~powershell
git status
git add .
git commit -m "feat: flujo POS y cocina"
git push origin main
~~~

Después, el compañero puede ejecutar:

~~~powershell
git pull origin main
~~~

Si el equipo trabaja con ramas, se recomienda crear una rama propia antes de modificar:

~~~powershell
git checkout -b feature/nombre-del-cambio
~~~

No hacer 'git reset --hard' ni borrar cambios locales sin revisar primero 'git status'.

## Requisitos

- PHP compatible con Laravel.
- Composer.
- Node.js y npm.
- MySQL o la base de datos configurada en Laravel.

## Instalación en Windows

Desde la carpeta del proyecto:

~~~powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd install
~~~

Configurar en '.env' la conexión de base de datos y las credenciales locales de demostración:

~~~dotenv
BOOMWALOS_ADMIN_EMAIL=correo-del-admin
BOOMWALOS_ADMIN_PASSWORD=contrasena-local-del-admin
BOOMWALOS_CASHIER_EMAIL=correo-del-cajero
BOOMWALOS_CASHIER_CODE=codigo-del-cajero
BOOMWALOS_CASHIER_PIN=pin-del-cajero
~~~

Después ejecutar:

~~~powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
npm.cmd run build
~~~

## Qué hace cada comando

- 'composer install': instala las dependencias PHP del proyecto.
- 'Copy-Item .env.example .env': crea la configuración local, que no se sube al repositorio.
- 'php artisan key:generate': genera la clave local de Laravel.
- 'npm.cmd install': instala las dependencias de frontend.
- 'php artisan migrate': crea o actualiza las tablas de la base de datos.
- 'php artisan db:seed': carga permisos, usuarios demo y datos base del POS.
- 'php artisan storage:link': permite mostrar las imágenes cargadas desde el almacenamiento público.
- 'npm.cmd run build': compila los estilos y recursos frontend.

Para iniciar el entorno local:

~~~powershell
php artisan serve
npm.cmd run dev
~~~

## Funcionalidades implementadas

## Cómo entender el sistema

El sistema tiene cuatro áreas principales:

1. **Administración:** el administrador configura usuarios, mesas, categorías, productos, imágenes y combos.
2. **Caja/POS:** el cajero crea pedidos, selecciona mesas, agrega productos y envía tandas a cocina.
3. **Cobro:** el cajero cobra una cuenta abierta con efectivo o tarjeta.
4. **Cocina/KDS:** administrador y cajero avanzan las tandas hasta que estén listas y entregadas.

La regla principal es que las vistas no contienen nombres, precios, mesas ni pedidos inventados. Esos datos deben salir de la base de datos y ser configurados desde administración o seeders.

## Flujo completo de una venta

### 1. Inicio de sesión

- El administrador entra con correo y contraseña.
- El cajero entra con código numérico y PIN.
- El usuario solo ve los módulos permitidos por su rol.

### 2. Crear el pedido

Desde 'Punto de Venta' se elige:

- **En el local:** se muestra el mapa de mesas. Una mesa libre crea una cuenta; una mesa ocupada abre la cuenta existente.
- **Para llevar:** se crea un pedido sin mesa y se abre directamente el catálogo.

Una mesa con pedido abierto no debe crear otro pedido. Esto evita duplicar cuentas cuando el cajero vuelve a entrar a la mesa.

### 3. Agregar productos

El cajero puede:

- Buscar productos.
- Filtrar por categoría.
- Ver solo productos disponibles.
- Agregar varias unidades.
- Disminuir, aumentar o eliminar líneas pendientes.
- Deshacer una eliminación antes de continuar.

Una línea enviada a cocina ya no se edita desde la orden. Si necesita anularse después, debe existir el permiso correspondiente y debe quedar registrada en auditoría.

### 4. Agregar combos

Los combos aparecen en su propia sección del catálogo. Al seleccionar uno, el sistema abre un configurador.

Ejemplo de un combo de 10 pupusas:

~~~text
4 pupusas de queso
3 pupusas revueltas
3 pupusas de chicharrón
Total seleccionado: 10 de 10
~~~

Se pueden repetir tipos de producto y mezclar diferentes tipos. El combo solo se agrega cuando la suma coincide exactamente con la cantidad requerida. Una combinación diferente se conserva como otra línea para que cocina reciba el desglose correcto.

### 5. Enviar a cocina

Al pulsar 'Enviar a cocina':

- Se crea una tanda nueva.
- Las líneas pendientes se asignan a esa tanda.
- Se registra auditoría.
- El pedido continúa abierto.
- La mesa continúa ocupada.
- El cajero vuelve al menú '¿Cómo será este pedido?' para iniciar otra venta o volver a entrar a una mesa.

El segundo clic no debe crear una segunda tanda.

### 6. Preparar en cocina

En '/admin/cocina' cada tanda avanza así:

~~~text
PENDIENTE → EN_PREPARACION → LISTA → ENTREGADA
~~~

La cocina puede filtrar por zona o por pedidos para llevar. Cada tarjeta muestra los productos, cantidades, combos, selección interna y notas de cocina. Las tandas se actualizan automáticamente mediante polling.

### 7. Cobrar

El botón 'Cobrar cuenta' aparece cuando:

- El pedido está abierto.
- No hay líneas pendientes por enviar.
- Existe una sesión de caja activa.
- El usuario tiene permiso para cobrar.

Con efectivo se registra el monto recibido y se calcula el cambio. Con tarjeta se registra el total exacto. El pedido pasa a 'COBRADO', pero la mesa no se libera hasta que todas sus tandas sean entregadas.

### 8. Cerrar y liberar la mesa

Cuando todas las tandas están entregadas o canceladas:

- Si la cuenta ya fue cobrada, el pedido pasa a 'CERRADO'.
- La mesa vuelve a mostrarse libre.
- Si todavía no se ha cobrado, el pedido permanece abierto.

Esto separa correctamente el trabajo de caja del trabajo de cocina.

### Autenticación y usuarios

- Login para administrador con correo y contraseña.
- Login para cajero con código numérico y PIN.
- Roles y permisos separados.
- Botón para cerrar sesión.
- Nombre del usuario y caja activa visibles en las pantallas operativas.

Las credenciales de cada computadora deben definirse en su propio '.env'. No se deben copiar contraseñas reales dentro de este documento ni dentro del código.

### Punto de venta

Flujo principal:

~~~text
Servicio → En el local o Para llevar → Mesa → Tomar orden → Enviar a cocina
~~~

Rutas principales:

- '/admin/pos/servicio'
- '/admin/pos/mesas'
- '/admin/pos/orden/{pedido}'
- '/admin/pos/cobro/{pedido}'

Comportamiento:

- Las mesas provienen de la base de datos.
- El administrador configura número, zona y activación de las mesas.
- El cajero puede operar mesas, pero no modificar su configuración.
- Una mesa ocupada reanuda su pedido activo y no crea duplicados.
- Los pedidos para llevar no requieren mesa.
- Las zonas disponibles son Salón, Terraza y Bar.
- El diseño tiene botones grandes para teclado, tablet y pantallas táctiles.

La pantalla está pensada para operar con el dedo. Los controles principales tienen áreas amplias y no dependen de pasar el mouse. También debe poder utilizarse con teclado en una computadora de escritorio.

### Productos y categorías

- El administrador puede crear y editar categorías.
- El administrador puede crear productos, precio, disponibilidad e imagen.
- Las imágenes se cargan en el almacenamiento público.
- El POS solo muestra productos disponibles.
- No hay nombres, precios ni imágenes de negocio escritos directamente en las vistas.

### Combos configurables

- El administrador puede configurar combos y grupos de selección.
- Un combo puede requerir, por ejemplo, 10 pupusas.
- El cliente puede combinar diferentes tipos de pupusa.
- La cantidad seleccionada debe coincidir exactamente con la cantidad requerida.
- Las combinaciones diferentes se guardan como líneas independientes.
- La selección completa se muestra en la orden y en la cocina.
- Las líneas pendientes se pueden editar, disminuir, eliminar y deshacer.
- Las líneas enviadas a cocina quedan bloqueadas.

### Cocina KDS

Ruta:

- '/admin/cocina'

Flujo de tandas:

~~~text
Nueva → En preparación → Lista → Entregada
~~~

Características:

- Las tarjetas representan tandas reales, no datos de ejemplo escritos en la vista.
- Se muestran mesa o Para llevar, seguimiento, productos, combos y notas.
- Los filtros son Todos, Salón, Terraza, Bar y Para llevar.
- No se utiliza Delivery en esta versión.
- El tablero se actualiza con polling cada 5 segundos.
- El sonido se activa después de una interacción del usuario.
- La pantalla es usable en escritorio, tablet y pantalla táctil.
- Las tandas entregadas salen de la cola activa.
- La mesa solo se libera cuando el pedido queda cerrado.

La pantalla KDS no depende de que exista una sesión de caja activa, porque cocina debe continuar trabajando aunque la caja se cierre.

### Cobro

- El cajero puede cobrar una cuenta abierta.
- Métodos disponibles: efectivo y tarjeta.
- El efectivo calcula el cambio.
- La tarjeta registra el total exacto.
- No se puede cobrar si existen líneas pendientes por enviar a cocina.
- Se evita crear pagos duplicados por doble clic o concurrencia.
- Al cobrar, el pedido pasa a 'COBRADO'.
- La mesa permanece ocupada hasta que cocina entregue todas las tandas.
- El ticket del cliente, QR fiscal y facturación electrónica quedan para una etapa posterior.

## Permisos principales

Administrador:

- 'gestionar_productos'
- 'gestionar_combos'
- 'gestionar_mesas'
- 'gestionar_usuarios'
- 'crear_pedido'
- 'cobrar_pedido'
- 'operar_cocina'
- 'abrir_caja'
- 'cerrar_caja'

Cajero:

- 'crear_pedido'
- 'cobrar_pedido'
- 'operar_cocina'
- 'abrir_caja'
- 'cerrar_caja'

## Archivos importantes

- 'app/Services/PedidoService.php'
- 'app/Services/CobroService.php'
- 'app/Services/KitchenService.php'
- 'app/Filament/Pages/Kitchen/KitchenDisplay.php'
- 'resources/views/filament/admin/pages/kitchen/kitchen-display.blade.php'
- 'app/Application/Kitchen/QueueKitchenBatch.php'
- 'database/seeders/RolesPermissionsSeeder.php'
- 'database/seeders/DemoUsersSeeder.php'
- 'database/seeders/DemoPosSeeder.php'
- 'resources/css/filament/admin/theme.css'

Las migraciones nuevas están en 'database/migrations/' y deben ejecutarse con 'php artisan migrate'.

## Datos iniciales y seeders

Los seeders sirven únicamente para preparar una instalación de prueba:

- 'RolesPermissionsSeeder': crea permisos y asigna permisos a los roles.
- 'DemoUsersSeeder': crea el administrador y el cajero usando variables del '.env'.
- 'DemoPosSeeder': crea el establecimiento, zonas, mesas, categorías, productos, combos y datos base.

El archivo '.env.example' no contiene contraseñas reales. Si 'DemoUsersSeeder' no encuentra alguna variable obligatoria, mostrará un error para evitar crear usuarios con credenciales débiles o predecibles.

Si se necesita reiniciar una base de datos local de prueba, revisar primero que no contenga datos importantes. Solo después de confirmarlo se puede usar:

~~~powershell
php artisan migrate:fresh --seed
~~~

Este comando borra todas las tablas de la base de datos configurada, por lo que no debe utilizarse en una base de datos compartida o productiva.

## Validación después de bajar los cambios

~~~powershell
php artisan test
php artisan view:cache
npm.cmd run build
~~~

Lista manual recomendada:

1. Entrar como administrador.
2. Crear o verificar categorías, productos, combos y mesas.
3. Entrar como cajero.
4. Abrir una mesa libre y agregar productos.
5. Configurar y agregar un combo con diferentes tipos de pupusa.
6. Enviar la orden a cocina.
7. Abrir '/admin/cocina' y avanzar la tanda.
8. Cobrar la cuenta con efectivo y verificar el cambio.
9. Entregar todas las tandas.
10. Verificar que la mesa vuelva a quedar libre.

## Problemas comunes

### No carga el logo o las imágenes

Ejecutar:

~~~powershell
php artisan storage:link
~~~

Después confirmar que las imágenes estén en el almacenamiento público y que la aplicación esté usando la URL local correcta.

### No aparecen productos o mesas

Revisar:

1. Que se hayan ejecutado las migraciones.
2. Que se haya ejecutado el seeder.
3. Que el producto esté disponible.
4. Que la mesa esté activa.
5. Que el usuario tenga el permiso requerido.

### El login no acepta las credenciales

Revisar las variables de usuario en el '.env' local y volver a ejecutar el seeder si la base de datos es de prueba. No copiar el '.env' de otra computadora.

### El frontend no refleja cambios

Ejecutar:

~~~powershell
npm.cmd run build
php artisan optimize:clear
~~~

### Un compañero tiene conflictos al hacer pull

No sobrescribir los archivos automáticamente. Guardar primero el trabajo local con un commit o un stash, revisar los archivos en conflicto y luego continuar la integración.

## Qué debe revisar el compañero antes de programar

1. Leer este documento completo.
2. Revisar el estado del repositorio con 'git status'.
3. Confirmar que trabaja sobre la última versión de 'main'.
4. Crear su propio '.env'.
5. Ejecutar migraciones y seeders en una base de datos local.
6. Ejecutar las pruebas y el build.
7. Probar primero el flujo POS completo.
8. Revisar permisos antes de agregar una nueva pantalla.
9. Mantener las reglas de negocio en servicios y no dentro de las vistas.
10. Agregar pruebas cuando cambie pedidos, cobros, tandas o permisos.

## Archivos que no deben subirse

Estos archivos están excluidos del repositorio:

- '.env'
- '.ai/'
- 'opencode.json'
- 'boost.json'
- 'design-qa.md'
- '.psysh-config/'
- 'vendor/'
- 'node_modules/'
- 'public/build/'
- archivos de caché y temporales

Antes de publicar, revisar:

~~~powershell
git status --short --untracked-files=all
git diff --check
git diff --cached --check
git check-ignore .env .ai opencode.json boost.json design-qa.md
~~~

## Pendientes para siguientes etapas

- Ticket del cliente.
- QR y facturación electrónica real.
- Integración DTE.
- Impresión física ESC/POS.
- Impresión automática desde cocina.
- Pagos divididos.
- Inventario.
- Posiciones de mesas mediante drag-and-drop.
- Validación final de impresión y cierre operativo.

## Regla de trabajo

No agregar credenciales reales, datos privados ni archivos generados por herramientas externas al repositorio. Los datos de demostración deben configurarse mediante variables de entorno locales o seeders de prueba.
