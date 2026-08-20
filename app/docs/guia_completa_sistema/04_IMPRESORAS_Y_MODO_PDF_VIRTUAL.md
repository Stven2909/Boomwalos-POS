# 04. Impresoras y Modo PDF Virtual

## 1. El Problema que Resuelve el Modo PDF

Tradicionalmente, un sistema POS requiere que existan impresoras térmicas ESC/POS conectadas físicamente por red local (IP:9100) o USB. Cuando un desarrollador o usuario opera el sistema sin hardware físico:
- El worker de colas intenta abrir sockets TCP a IPs inexistentes.
- La conexión falla por timeout.
- Los trabajos de comanda y ticket quedan en estado de **`ERROR`**, bloqueando el flujo o generando alertas.

---

## 2. Arquitectura de la Impresora Virtual / Modo PDF

Para resolver esto, se implementó una **Impresora Virtual con Renderizador Térmico en PDF**:

```
┌────────────────────────────────────────────────────────────────────────┐
│                   CIRCUITO DE IMPRESIÓN CON MODO PDF                   │
├────────────────────────────────────────────────────────────────────────┤
│ 1. Ajustes ► Impresoras: Conexión elegida como "Simulador PDF"         │
│ 2. POS: Cajero cobra o envía a cocina                                  │
│ 3. EscPosPrintService: Detecta tipo PDF y usa MemoryPrintConnector    │
│ 4. PdfTicketService: Renderiza el documento a PDF térmico de 80mm      │
│ 5. Almacenamiento: Guarda en storage/app/public/impresiones/{id}.pdf   │
│ 6. Estado: Marcado como IMPRESO con 0 errores                          │
│ 7. Visualización: Botón directo "📄 PDF" para abrir/imprimir en web    │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Tipos de Conexión Disponibles en [`TipoConexionImpresora.php`](file:///f:/POSYSTEM/Boomwalos-POS/app/Enums/TipoConexionImpresora.php)

- **`RED`:** Para impresoras térmicas de red (Ethernet / WiFi) con IP y puerto (ej. 9100).
- **`USB`:** Para impresoras conectadas por cable USB directo (ej. `/dev/usb/lp0` o puerto COM en Windows).
- **`PDF`:** **`Simulador PDF / Virtual`** (Permite probar todo el sistema generando documentos PDF de 80mm sin hardware).

---

## 4. Especificaciones del PDF Térmico y QR Gráfico ([`PdfTicketService.php`](file:///f:/POSYSTEM/Boomwalos-POS/app/Services/Printing/PdfTicketService.php))

- **Ancho del Rollo:** `80 mm` (`226.77 pt`).
- **Altura:** Dinámica calculada según la cantidad de líneas del ticket o comanda.
- **Tipografía:** Monoespaciada (`Courier New`, `DejaVu Sans Mono`).
- **Estilo:** Márgenes térmicos compactos (4mm), divisores punteados (`---`), totales destacados en negrita y encabezados centrados.
- **Código QR Gráfico:** Generado localmente en Base64 con `chillerlan/php-qrcode` apuntando a `https://boomwalos.vercel.app/?tracking={id}`.
- **Visualizador Web:** Disponible en `/admin/impresion/trabajo/{id}/pdf` y `/admin/impresion/prueba/{id}/pdf`.
- **Blindaje y Fallback Automático:** Si el servidor en producción (ej. Cloudways) tiene problemas de permisos de escritura o librerías de fuentes de Dompdf, el sistema conmuta inmediatamente a [`ticket-fallback.blade.php`](file:///f:/POSYSTEM/Boomwalos-POS/resources/views/printing/ticket-fallback.blade.php), una vista HTML 80mm de alta fidelidad con disparo automático de `window.print()`.

---

## 5. Transición a Hardware Físico en el Futuro

Cuando el negocio instale impresoras térmicas físicas:
1. El usuario entra a **Ajustes $\to$ Impresoras** en el panel de administración.
2. Edita la impresora deseada (Cocina o Cajero).
3. Cambia el selector de `Simulador PDF / Virtual` a `Red (Ethernet)` o `USB`.
4. Ingresa la IP de la impresora.
5. **Listo.** No se requiere cambiar código ni reiniciar el servidor.
