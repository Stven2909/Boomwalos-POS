# 07. Guía de Despliegue y Migración a Otra Computadora

Esta guía contiene los pasos exactos para clonar, instalar y levantar **Boomwalos POS** en otra computadora (Windows / Linux / Mac) o servidor en la nube desde cero.

---

## 1. Requisitos Previos

- **PHP:** Versión 8.3 o superior con extensiones habilitadas (`sqlite3`, `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`).
- **Composer:** Versión 2.7+ instalada globalmente.
- **Node.js & NPM:** Node 18+ (opcional para compilar assets en desarrollo).
- **Git:** Instalado.

---

## 2. Paso a Paso para Levantar el Proyecto en una Nueva PC

### Paso 1: Clonar o Copiar la Carpeta del Proyecto
Si trasladas el proyecto por memoria USB o Git:
```bash
cd Boomwalos-POS
```

### Paso 2: Instalar Dependencias de Composer
```bash
composer install
```

### Paso 3: Configurar el Archivo `.env`
Copia el archivo de ejemplo si no existe `.env`:
```bash
cp .env.example .env
```
Genera la clave de encriptación de la aplicación:
```bash
php artisan key:generate
```

Asegúrate de que tu `.env` tenga configuradas las variables fiscales y de base de datos:
```dotenv
APP_NAME=POS
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=America/El_Salvador

DB_CONNECTION=sqlite

# Credenciales de seeders por defecto
POS_ADMIN_EMAIL=admin@example.com
POS_ADMIN_PASSWORD=password
POS_CASHIER_EMAIL=cajero@example.com
POS_CASHIER_CODE=21
POS_CASHIER_PIN=1234
POS_PLATFORM_ADMIN_EMAIL=platform@example.com
POS_PLATFORM_ADMIN_PASSWORD=password

# Conexión Fiscal con Cloudways
FISCAL_API_URL=https://phplaravel-1581457-6620216.cloudwaysapps.com
FISCAL_GATEWAY=http
FISCAL_API_TIMEOUT=15
FISCAL_MOCK_ENABLED=false
```

### Paso 4: Crear la Base de Datos SQLite y Ejecutar Migraciones
Crea el archivo vacío de base de datos (si no existe):
- En Windows (PowerShell): `New-Item database/database.sqlite -ItemType File -Force`
- En Linux/Mac: `touch database/database.sqlite`

Corre las migraciones y los seeders con los datos de prueba iniciales:
```bash
php artisan migrate --seed
```

### Paso 5: Enlazar el Almacenamiento Público
Crea el enlace simbólico para que los PDFs de tickets y logotipos se puedan descargar y visualizar:
```bash
php artisan storage:link
```

### Paso 6: Limpiar y Optimizar Cachés
```bash
php artisan optimize:clear
```

### Paso 7: Iniciar el Servidor Local
```bash
php artisan serve
```
El sistema quedará disponible de inmediato en: **`http://127.0.0.1:8000`**.

---

## 3. Guía de Optimización para Servidores en la Nube (Cloudways)

Cuando despliegues la aplicación en producción (Cloudways o VPS Linux):

1. Configura el `.env` de producción:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   ```
2. Ejecuta los comandos de aceleración en la consola SSH:
   ```bash
   php artisan optimize
   php artisan view:cache
   php artisan event:cache
   composer dump-autoload -o --no-dev
   ```
3. En el panel de Cloudways $\to$ **Settings & Packages $\to$ PHP**:
   - Activa **OPcache** con al menos `128M` o `256M`.
   - Asigna **Memory Limit** a `512M`.

---

## 4. Ejecución de la Suite de Pruebas Automatizadas

Para validar que todos los módulos y la integridad del sistema están funcionando al 100%:

```bash
php artisan test
```

Resultado esperado: **183 tests pasados / 0 fallos (100% OK)**.
