# Playbook — Terminal POS (Dell Pro Micro QCM1250 + Laravel local)

> Arquitectura cerrada: **Windows 11 Pro + Laravel corriendo de forma local en el
> terminal + navegador Chrome en modo kiosk + sync fiscal asíncrono a ApiFact.**
> La nube vive en la ruta de sincronización fiscal (tolerante a caídas, vía cola
> `database`), nunca en el camino crítico de cobrar.

## Parámetros a completar el día de instalación

| Parámetro | Valor pendiente | Dónde se usa |
|---|---|---|
| Impresora térmica | Modelo / puerto USB Windows (`USB001…`) o IP LAN + 9100 | CRUD Impresoras + Fase 8 |
| `FISCAL_API_URL` | URL real de la API fiscal | `.env` |
| `WEBFACT_URL` | URL del portal/orquestador | `.env` |
| Credenciales seed | `POS_ADMIN_*`, `POS_CASHIER_CODE/PIN` | `.env` |

Scripts incluidos en el repo: `resources/scripts/windows/{kiosk.cmd, watchdog.ps1, backup.ps1}`.

---

## Fase 1 — Instalación de Windows 11 Pro

1. Arrancar desde USB: **Windows 11 Pro** (no Home; Pro habilita kiosk/auto-logon).
2. En OOBE: cuenta local `BOOMWALOS\pos-terminal` (sin Microsoft account), equipo `POS-01`.
3. Windows Update completo antes de continuar (drivers Dell + táctil 3nStar).
4. BIOS: `Auto Power On` si está disponible; deshabilitar suspensión ACPI.
5. SSD NVMe como único boot; Fast Startup opcional (evita arrastre de arranque).

## Fase 2 — Base de Windows

- Escala de pantalla **100%** (no 125% — recorta la resolución 1024x768).
- Nunca suspender ni apagar pantalla:
  ```
  powercfg /change monitor-timeout-ac 0
  powercfg /change standby-timeout-ac 0
  powercfg /change hibernate-timeout-ac 0
  powercfg /hibernate off
  ```
- UAC en "Never notify" **solo para esta terminal de kiosk** (tareas programadas sin prompt).
- No aplica Ctrl+Alt+Supr por usar AutoLogon.

## Fase 3 — Driver táctil 3nStar TCM008

- Instalar driver táctil 3nStar (USB). Verificar en Administrador de dispositivos → HID touch.
- Calibrar: `%windir%\system32\control.exe tabletpcsettings` → "Configurar".
- Conectar por **HDMI** (no VGA). Resolución nativa 1024x768, landscape fijo.
- Prueba de drift: deslizar el dedo durante 10 min y verificar puntero.

## Fase 4 — Runtime PHP + Composer (x64)

1. PHP 8.x x64 thread-safe (`windows.php.net`) → `C:\php`.
2. `php.ini`:
   ```
   extension_dir = "C:\php\ext"
   extension=sqlite3
   extension=pdo_sqlite
   extension=bcmath
   extension=mbstring
   extension=gd
   extension=fileinfo
   extension=openssl
   extension=zip
   extension=curl
   ```
3. Agregar `C:\php` al `PATH` del sistema.
4. Composer `composer-setup.exe` (para todos los usuarios).
5. Verificar: `php -v`, `composer --version`, `php -m | findstr sqlite`.

## Fase 5 — Aplicación (clonar + instalar + init)

1. Clonar el repo POS en `C:\pos\boomwalos-pos`; excepción en Windows Defender para `C:\pos`.
2. `composer install --no-dev --optimize-autoloader`.
3. `.env` (base: `.env.example`, que ya usa SQLite y cola `database`):
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=http://localhost:8000
   APP_TIMEZONE=America/El_Salvador

   DB_CONNECTION=sqlite
   QUEUE_CONNECTION=database
   CACHE_STORE=database
   SESSION_DRIVER=database
   SESSION_LIFETIME=43200

   POS_REQUIRE_EXPLICIT_ESTABLISHMENT=false
   ```
   Borrar/comentar claves `DB_HOST/DB_PORT/DB_DATABASE/...` de MySQL/MariaDB (no se usan).
4. `php artisan key:generate` y `php artisan migrate --force`.
5. `php artisan db:seed --force` (roles/permisos + admin/cajero desde `POS_ADMIN_*`, `POS_CASHIER_CODE/PIN`).
6. Crear la sucursal única en la app (auto-seleccionada por `true` implícito del contexto).
7. Assets si aplica: `npm ci && npm run build`.

## Fase 6 — Servicios (Task Scheduler)

Tres tareas disparadas al inicio de sesión de `pos-terminal` (con `delayed start` 10 s):

| Tarea | Acción |
|---|---|
| `pos-web` | `php artisan serve --host=127.0.0.1 --port=8000` |
| `pos-queue` | `php artisan queue:work database --tries=3 --sleep=3 --timeout=90` |
| `pos-kiosk` | `kiosk.cmd` (Fase 9) — **hace polling a localhost:8000 antes de abrir Chrome**, evita la carrera de arranque |

Nota: el servidor embebido es de 1 proceso — suficiente para 1 terminal/1 cajero. Si crece, migrar a Nginx en otra fase.

## Fase 7 — Sync fiscal a ApiFact (asíncrono, tolerante a caídas)

`.env` (claves reales de `config/fiscal.php`):
```
FISCAL_API_URL=https://<api-fiscal-real>/api
FISCAL_API_PREFIX=/api/fiscal/v1
FISCAL_API_TIMEOUT=15
FISCAL_GATEWAY=http
FISCAL_MOCK_ENABLED=false
WEBFACT_URL=https://boomwalos.vercel.app
PROVISIONING_TOKEN=<token_onboarding>
# HMAC si el servicio lo exige:
FISCAL_HMAC_HEADER=X-Signature
FISCAL_KEY_HEADER=X-Client-Id
FISCAL_HMAC_TIMESTAMP_HEADER=X-Timestamp
```
- Validar con un DTE de prueba que `cola_ventas_fiscales` se vacía y aparece en ApiFact.
- Con `QUEUE_CONNECTION=database` nada se pierde por caída de Internet; se reprocesa sola al reconectar.

## Fase 8 — Impresora térmica + gaveta

> Sección **genérica/configurable**: el modelo llega después. Único punto de setup es el CRUD
> `Impresoras` (Tipo: Ticket, activa, sucursal). Se usa `EscPosPrintService` (USB) o
> `NetworkPrintConnector` (LAN) — no requiere desarrollo adicional.

- **Ruta USB:** conectar la impresora, anotar el puerto de Windows (Panel → Impresoras → Puertos, ej. `USB001`). En el CRUD: conexión **USB** + ese puerto.
- **Ruta LAN/Ethernet:** IP fija (reserva DHCP) + puerto raw **9100** abierto en el firewall. En el CRUD: conexión **Red** con `[IP]:9100`.

**Gaveta (sin configuración adicional):** el cable RJ11/RJ12 de la gaveta va al puerto **CashDrawer**
de la impresora; el pulso (`PulseGavetaDriver` → `Printer::pulse()`) viaja por el mismo cable de datos.
Mientras no haya impresora física, el modo **manual** (llave + confirmación en pantalla) sigue activo.

**Verificación:** "Probar impresoras" (test ESC/POS) + abrir/cerrar un turno y comprobar corte impreso y pulso.

## Fase 9 — Kiosk Chrome + AutoLogon

1. **AutoLogon:**
   ```
   reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\WinLogon" /v AutoAdminLogon /t REG_SZ /d 1 /f
   reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\WinLogon" /v DefaultUserName /t REG_SZ /d pos-terminal /f
   reg add "HKLM\SOFTWARE\Microsoft\Windows NT\CurrentVersion\WinLogon" /v DefaultDomainName /t REG_SZ /d BOOMWALOS /f
   ```
2. **`kiosk.cmd`** (versionado en `resources/scripts/windows/kiosk.cmd`): hace polling a
   `http://localhost:8000` (2 s entre intentos, tope ~120 s) y recién entonces abre Chrome:
   ```
   chrome.exe --kiosk --app=http://localhost:8000 ^
     --disable-pinch --touch-events=enabled --overscroll-history-navigation=0 ^
     --noerrdialogs --no-first-run --disable-infobars --force-device-scale-factor=1 ^
     --user-data-dir=C:\pos-kiosk-profile
   ```
   No relanza `pos-web` (evita doble instancia del `artisan serve`): solo espera; el watchdog cubre fallos reales.
3. Login una vez con **"Recordarme"** para fijar sesión larga y apuntar la URL directo a la sucursal.
4. En `Operación del POS`: gaveta en **manual** hoy; pasar a **pulso automático** cuando llegue la impresora.

## Fase 10 — Watchdog

`resources/scripts/windows/watchdog.ps1`, tarea programada cada **60 s**:
- Si el POS web no responde: si el puerto 8000 no escucha → `schtasks /run pos-web + pos-queue`;
  si escucha pero la app no responde → mata el PHP del puerto y relanza ambos.
- Si hay trabajos "procesando" atascados > 5 min → reinicia el stack PHP y relanza web + queue.
- Si no hay `chrome.exe` con el perfil kiosk → relanza `kiosk.cmd`.
- Log con rotación (`C:\pos\logs\watchdog*.log`, 7 días).

## Fase 11 — Validación E2E (checklist de aceptación)

- [ ] Encendido → arranca directo al POS sin escritorio visible (sin pantalla de error en los primeros segundos).
- [ ] Touch: apertura de turno, búsqueda de producto, cobro con teclado táctil.
- [ ] Cobro + corte/factura impresos (ruta USB o LAN según llegue la impresora).
- [ ] Apertura/cierre de turno → gaveta abre y audita (`gaveta_abierta`, `caja_abierta`, `caja_cerrada` en `evento_auditorias`).
- [ ] DTE enviado a ApiFact y visible en el portal (`webfact`).
- [ ] Caída de Internet simulada (desconectar cable) → venta OK, cola retiene; al reconectar se vacía.
- [ ] Reinicio del equipo → todo vuelve solo (web, cola, kiosk).
- [ ] **NTP/hora (crítico para el HMAC fiscal):** `w32tm /query /status` con `Stratum ≥ 2` y sync reciente; hora local igual a `America/El_Salvador`. Verificar con un DTE real que el sello horario sea aceptado.
- [ ] Opcional: tarea diaria silenciosa `w32tm /resync` contra el drift.

## Fase 12 — Seguridad y mantenimiento

- **Backup SQLite consistente** (`resources/scripts/windows/backup.ps1`): usa `SQLite3::backup()` vía PHP
  (seguro con la app corriendo) + `storage/logs` + `.env`, retención 7 días. Programar diario 04:30.
- Credenciales de `pos-terminal` no compartidas; PIN de cajero rotado trimestral.
- Windows Update en ventana manual; probar el arranque del kiosk tras cada actualización.
- Monitorear `failed_jobs` y `cola_ventas_fiscales`.