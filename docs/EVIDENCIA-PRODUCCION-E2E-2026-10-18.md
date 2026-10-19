# Evidencia de Pase a Producción y Contingencia E2E (Ministerio de Hacienda)

- **Fecha:** 2026-10-18
- **Ambiente:** `01` — Producción Real (Ministerio de Hacienda de El Salvador)
- **Emisor:** INVERSIONES TICAS S. A. DE C. V. (NIT `06141601141064`, NRC `2310805`)
- **Establecimiento / Punto de Venta:** `M001` / `P001`
- **Host Terminal:** `DESKTOP-668SAI0` (`C:\pos\boomwalos-pos`)
- **Backend API:** ApiFact en Cloudways (`qvfckebhyf`)
- **Resultado Global:** **APROBADO (PASS 100%)**

---

## 1. Venta Normal en Línea (DTE-01 #1)

### 1.1 Ejecución
1. Cobro de producto de prueba ($1.00 USD) en la UI del kiosko POS en modo online.
2. Transmisión HTTP inmediata hacia ApiFact con autenticación HMAC-SHA256.
3. Firma electrónica JWS y envío a los servidores de Hacienda en Producción (`https://api.dtes.mh.gob.sv/fesv/recepciondte`).

### 1.2 Resultados Oficiales de Hacienda
- **Tipo de Documento:** DTE-01 (Factura de Consumidor Final)
- **Número de Control:** `DTE-01-M001P001-000000000000001`
- **Código de Generación:** `BF78BD58-1ABD-494B-986A-4BA4EC4ED296`
- **Sello de Recepción MH:** `202622548D808AAF4EB5955EECF8C960DAD9Q2KA`
- **Respuesta:**
  ```json
  {
    "version": 2,
    "ambiente": "01",
    "versionApp": 2,
    "estado": "PROCESADO",
    "codigoGeneracion": "BF78BD58-1ABD-494B-986A-4BA4EC4ED296",
    "selloRecibido": "202622548D808AAF4EB5955EECF8C960DAD9Q2KA",
    "fhProcesamiento": "18/10/2026 16:52:09",
    "clasificaMsg": "10",
    "codigoMsg": "001",
    "descripcionMsg": "RECIBIDO",
    "observaciones": []
  }
  ```
- **Portal MH:** Verificado externamente con estado **`TRANSMITIDO`**.

---

## 2. Venta en Contingencia Offline y Reconciliación (DTE-01 #2)

### 2.1 Fase Offline (Corte de Conectividad)
1. Simulación de caída de enlace fiscal (`FISCAL_API_URL` a puerto cerrado).
2. Venta cobrada en efectivo ($1.00 USD, 1 x Agua).
3. El POS detecta la falta de conectividad vía sonda socket en milisegundos.
4. Genera e imprime el **Comprobante Transitorio Diferido**:
   - Modelo: `DIFERIDO` (`tipoModelo = 2`)
   - Operación: `CONTINGENCIA` (`tipoOperacion = 2`)
   - Tipo de Contingencia: `3` (Falla de internet del emisor, CAT-005)
   - UUID de Generación: `13DFBDFD-6C8E-4B70-9F11-B90E5DC436C3`

### 2.2 Fase Online (Reconexión y Evento de Contingencia v4)
1. Restablecimiento de conectividad.
2. Transmisión a ApiFact Cloudways: el DTE ingresa retenido reglamentariamente como **`HELD_CONTINGENCIA`**.
3. Cierre de ventana (300 s / 5 min sin nuevos DTEs).
4. `ContingenciaCoordinator` genera el **Evento Oficial de Contingencia v4**:
   - **Código de Generación del Evento:** `3102030D-CF98-4838-A755-636D5CE46B80`
   - **Responsable del Establecimiento:** Jorge Daniel López Medrano (DUI `002583240`)
   - **Estado:** `PROCESSED / SEALED`
   - **Sello Oficial del Evento MH:** `2026D70AA4F13272466A9B2A73C8CAB54D8349QH`
5. **Liberación Automática:** El evento sellado libera el DTE diferido (`QUEUED_CONTINGENCIA`), transmitiéndose a Hacienda:
   - **Número de Control:** `DTE-01-M001P001-000000000000002` (Correlativo consecutivo #2)
   - **Código de Generación:** `13DFBDFD-6C8E-4B70-9F11-B90E5DC436C3`
   - **Estado:** `PROCESSED / SEALED`
   - **Sello Oficial de Recepción MH:** `20265470981A418946E18EADE5E264B8A26AKL6N`

### 2.3 Reconciliación en la Terminal Local
Ejecución de `pos:reconciliar-fiscales`:
```
estado:           SINCRONIZADO
codigo_generacion: 13DFBDFD-6C8E-4B70-9F11-B90E5DC436C3
numero_control:   DTE-01-M001P001-000000000000002
sello_recepcion:  20265470981A418946E18EADE5E264B8A26AKL6N
```

---

## 3. Sistema de Impresión Térmica 80mm

1. **Ticket Comercial de Cliente:** Renderizado con desglose de productos, subtotales, método de pago, cambio, cajero y Smart QR con enlace de tracking a `https://boomwalos.vercel.app`.
2. **Comprobante Fiscal Definitivo:** Generado automáticamente en 80mm al sellar la venta, incluyendo número de control oficial, código de generación y sello de recepción de Hacienda.
3. **Comanda de Cocina:** Generada y encolada con éxito para la estación de preparación.

---

## 4. Acceso Remoto y Despliegue Físico

- **TeamViewer ID:** `855 131 732`
- **Modo:** Acceso no presencial (Unattended Access) con contraseña fija permanente e inicio automático con Windows.
- **Energía Windows:** Monitor, suspensión y disco configurados en timeout `0` (nunca suspender) vía `powercfg`.
