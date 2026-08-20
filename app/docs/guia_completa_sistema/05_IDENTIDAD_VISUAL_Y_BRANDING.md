# 05. Identidad Visual y Branding

## 1. Decisión de Diseño: Paleta Fija e Institucional

Para asegurar que la interfaz del Punto de Venta y de Administración mantenga una **experiencia visual profesional, rápida y con 100% de contraste de accesibilidad**, el sistema adopta una **paleta de colores institucional fija y no modificable por los clientes**:

```
┌────────────────────────────────────────────────────────────────────────┐
│                          PALETA INSTITUCIONAL DEL SISTEMA              │
├───────────────────┬─────────────┬──────────────────────────────────────┤
│ ROL               │ COLOR / HEX │ USO EN EL SISTEMA                    │
├───────────────────┼─────────────┼──────────────────────────────────────┤
│ 1. Primario       │ #6B4E63     │ Barra lateral, títulos, selecciones  │
│ 2. Acción / Fuego │ #FF7338     │ Botones de acción: "Cobrar", "Cocina"│
│ 3. Éxito          │ #287E67     │ Mesas libres, cobros completados     │
│ 4. Ocupado/Alerta │ #B85C2E     │ Mesas con cuenta abierta             │
│ 5. Peligro        │ #E11D48     │ Anulaciones, cancelaciones, errores  │
│ 6. Fondo Suave    │ #F8F7FA     │ Superficie táctil descansada         │
└───────────────────┴─────────────┴──────────────────────────────────────┘
```

---

## 2. ¿Por qué no se permite a las empresas cambiar los colores?

1. **Evita Interfaces Ilegibles:** Si un cliente selecciona un color amarillo claro o verde pastel como primario, los botones con texto blanco se vuelven invisibles y difíciles de pulsar en pantallas táctiles con grasa o reflejos.
2. **Cero Conflictos de Caché CSS:** La compilación de estilos con Vite es estática y súper rápida, eliminando renderizados lentos en caliente.
3. **Identidad de Software Robusta:** El software mantiene una imagen de alta calidad, coherente con las marcas líderes de la industria (Toast, Square, Clover).

---

## 3. Lo que SÍ Personaliza Cada Empresa ([`BrandSettings.php`](file:///f:/POSYSTEM/Boomwalos-POS/app/Filament/Pages/BrandSettings.php))

En la pantalla de **Administración $\to$ Marca de la empresa** (`/admin/marca`), el restaurante puede configurar:

- **Nombre Comercial:** El nombre de la pupusería o restaurante.
- **Logotipo de la Empresa:** Inyectado automáticamente en la barra lateral del panel administrativo (`brandLogo`), en la pantalla de Login y en los encabezados.
- **Favicon:** Icono de pestaña del navegador.
- **Encabezado del Ticket:** Razón social, dirección de la sucursal, NIT/NRC o teléfono.
- **Pie del Ticket:** Mensaje de agradecimiento, redes sociales o avisos al cliente.
- **Datos de Contacto:** Teléfono y correo comercial.
