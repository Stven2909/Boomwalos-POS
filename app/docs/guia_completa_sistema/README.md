# Boomwalos POS: Documentación Técnica, Comercial y de Arquitectura

Bienvenido al compendio integral de documentación de **Boomwalos POS**, un sistema de Punto de Venta (POS) y plataforma SaaS multi-empresa especializado en pupuserías y restaurantes de alta rotación con integración fiscal para El Salvador (DTE / Facturación Electrónica).

Esta carpeta contiene toda la lógica, decisiones de arquitectura, modelo de negocio y guías operativas para comprender, mantener, desplegar y trasladar el proyecto a cualquier entorno o computadora.

---

## 📚 Índice de Documentos

| # | Documento | Descripción |
|---|---|---|
| **01** | [01_MODELO_NEGOCIO_Y_PLANES_SAAS.md](01_MODELO_NEGOCIO_Y_PLANES_SAAS.md) | Detalle de los 4 planes de suscripción SaaS ($29 a $99+), pricing, cuotas DTE y monetización. |
| **02** | [02_ARQUITECTURA_TECNICA_Y_FLUJOS.md](02_ARQUITECTURA_TECNICA_Y_FLUJOS.md) | Flujos del POS táctil, combos dinámicos por slots, KDS de cocina por tandas, caja y multi-tenancy. |
| **03** | [03_SEGURIDAD_HMAC_Y_CLIENTE_FISCAL.md](03_SEGURIDAD_HMAC_Y_CLIENTE_FISCAL.md) | Esquema de firma canónica HMAC SHA256 multielemento, cabeceras de seguridad y cliente HTTP fiscal. |
| **04** | [04_IMPRESORAS_Y_MODO_PDF_VIRTUAL.md](04_IMPRESORAS_Y_MODO_PDF_VIRTUAL.md) | Sistema de impresión ESC/POS y conector de Impresora Virtual / Modo PDF térmico de 80mm. |
| **05** | [05_IDENTIDAD_VISUAL_Y_BRANDING.md](05_IDENTIDAD_VISUAL_Y_BRANDING.md) | Paleta de colores institucional fija (#6B4E63 / #FF7338) y personalización comercial de logos y tickets. |
| **06** | [06_PORTAL_SMART_QR_Y_AUTOSERVICIO.md](06_PORTAL_SMART_QR_Y_AUTOSERVICIO.md) | Lógica de negocio del código QR en tickets según el plan contratado (Básico vs Smart QR vs Pro DTE). |
| **07** | [07_GUIA_DESPLIEGUE_Y_MIGRACION_OTRA_PC.md](07_GUIA_DESPLIEGUE_Y_MIGRACION_OTRA_PC.md) | Guía paso a paso para levantar el proyecto en otra computadora o servidor desde cero. |

---

## 🛠️ Stack Tecnológico

- **Lenguaje:** PHP 8.3+
- **Framework Web:** Laravel 13.8
- **Panel Administrativo & UI:** Filament 5.7 / Livewire / Tailwind CSS
- **Permisos y Roles:** Spatie Laravel Permission 8.3 / Filament Shield
- **Impresión Térmica:** Mike42 ESC/POS PHP + Barryvdh Laravel DomPDF
- **Bases de Datos:** SQLite (desarrollo local) / MySQL 8.0+ (producción)
- **Servidor Cloud:** Cloudways (Nginx + PHP-FPM 8.3 + OPcache)

---

## 🔑 Credenciales de Prueba en Entorno Local

- **Panel de Restaurante & POS:** `http://127.0.0.1:8000/admin`
  - **Administrador:** `admin@example.com` / `password`
  - **Cajero (Modo PIN):** Código `21` / PIN `1234`
- **Panel SaaS Central (Superadministrador):** `http://127.0.0.1:8000/platform`
  - **Superadmin:** `platform@example.com` / `password`
