# 01. Modelo de Negocio y Planes Comerciales SaaS

## 1. Visión General del Negocio

**Boomwalos POS** está concebido como una plataforma **SaaS (Software as a Service) Multi-Tenant** dirigida a restaurantes de alta rotación, taquerías y pupuserías en El Salvador y Centroamérica.

El modelo de ingresos se basa en **suscripciones mensuales o anuales por sucursal** con cobro escalonado según el volumen de facturación electrónica (DTE), funcionalidades táctiles de cocina y canales de auto-atención (Smart QR).

---

## 2. Matriz de Planes de Suscripción

```
┌─────────────────────────┐   ┌─────────────────────────┐   ┌─────────────────────────┐   ┌─────────────────────────┐
│     1. PLAN BÁSICO      │   │    2. PLAN SMART QR     │   │      3. PLAN PRO        │   │     4. PLAN CADENA      │
│      (OPERATIVO)        │   │   (A DEMANDA POR QR)    │   │ (EMISIÓN DIRECTA CAJA)  │   │     (ENTERPRISE)        │
│    ~$29 / mes           │   │   ~$49 / mes            │   │   ~$69 / mes            │   │   ~$99+ / mes           │
├─────────────────────────┤   ├─────────────────────────┤   ├─────────────────────────┤   ├─────────────────────────┤
│ • POS Táctil & Mesas    │   │ • Todo lo del Plan Base │   │ • Todo lo del Plan QR   │   │ • Multi-Sucursal        │
│ • Combos por Slots      │   │ • Ticket con QR Único   │   │ • Emisión Directa Caja  │   │ • Multi-Cajas y KDS     │
│ • Tandas de Envío       │   │ • Portal Web Cliente    │   │ • Cuota DTE Ampliada    │   │ • Control Insumos (*)   │
│ • KDS Cocina por Tandas │   │ • Emite CF y CCF según  │   │ • Transmisión Directa   │   │ • Comparativo Sucursales│
│ • Arqueo Ciego de Caja  │   │   solicitud del cliente │   │   en cada cobro         │   │ • Reportes Ejecutivos   │
│ • Tickets Internos      │   │ • Cuota DTE Base (500)  │   │ • Invalidaciones DTE    │   │ • Soporte Prioritario   │
└─────────────────────────┘   └─────────────────────────┘   └─────────────────────────┘   └─────────────────────────┘
```

---

## 3. Detalle de los Planes

### Plan 1: Básico (Operativo Local) — ~$29/mes
- **Público Objetivo:** Pupuserías tradicionales o pequeños locales que buscan digitalizar comandas, mesas y cuadre de caja sin emitir facturación electrónica masiva.
- **Características:**
  - Control de mesas (Salón, Terraza, Domicilio, Para Llevar).
  - Comandas de cocina en tiempo real (KDS).
  - Arqueo ciego de caja al cierre de turno.
  - Tickets internos de venta sin código QR interactivo.

### Plan 2: Smart QR (Auto-atención y Facturación a Demanda) — ~$49/mes
- **Público Objetivo:** Restaurantes medianos con alta afluencia de clientes donde hacer fila en caja para pedir factura retrasaría la operación.
- **Propuesta de Valor:**
  - En cada cobro, el ticket incluye un **código QR único**.
  - El cliente escanea el QR desde su celular, ve su cuenta y decide si solicita **Consumidor Final (CF)** o **Crédito Fiscal (CCF)** ingresando su NIT/NRC.
  - **Ahorro para el Restaurante:** Solo se consumen DTEs cuando el cliente realmente lo solicita.
  - Incluye cuota base de DTEs (ej. 500 DTEs/mes).

### Plan 3: Pro DTE (Emisión Directa en Caja) — ~$69/mes
- **Público Objetivo:** Negocios formalizados que emiten factura electrónica por cada venta obligatoriamente desde el punto de cobro.
- **Propuesta de Valor:**
  - Transmisión automática de cada cobro al Ministerio de Hacienda a través de la API Fiscal en Cloudways.
  - Generación de sellos de recepción, códigos de generación e invalidaciones fiscales.
  - Cuota ampliada de DTEs (ej. 1,500 - 3,000 DTEs/mes).

### Plan 4: Cadena / Enterprise — ~$99+/mes
- **Público Objetivo:** Cadenas con 2 o más sucursales y franquicias.
- **Propuesta de Valor:**
  - Panel centralizado para comparar rendimiento entre sucursales.
  - Múltiples pantallas de cocina KDS simultáneas.
  - Gestión de inventario avanzado y recetas de insumos.
  - Base de datos dedicada y soporte prioritario.

---

## 4. Gestión Técnica de Planes en Base de Datos

En la tabla `platform_tenants`, el campo `plan_code` gobierna qué módulos y qué tipo de ticket se activa para cada restaurante:
- `basic` $\to$ Sin QR interactivo.
- `smart_qr` $\to$ Generación de token QR para portal web.
- `pro` $\to$ Emisión directa a la API fiscal en Cloudways.
- `enterprise` $\to$ Multi-sucursal activo.
