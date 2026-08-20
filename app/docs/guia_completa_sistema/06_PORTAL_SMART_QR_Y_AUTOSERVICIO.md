# 06. Portal Smart QR y Autoservicio

## 1. Concepto del Flujo Smart QR

El módulo **Smart QR** es la característica comercial diferenciadora del **Plan Smart QR ($49/mes)** y **Plan Pro ($69/mes)**.

Permite al restaurante emitir un ticket de venta tradicional con un **código QR dinámico** que traslada la carga operativa de la facturación electrónica hacia el propio comensal, reduciendo tiempos de espera en caja y optimizando el consumo de DTEs ante el Ministerio de Hacienda.

---

## 2. Flujo de Experiencia del Cliente

```
┌────────────────────────────────────────────────────────────────────────┐
│                        FLUJO COMERCIAL SMART QR                        │
├────────────────────────────────────────────────────────────────────────┤
│ 1. El cliente consume en el local o pide para llevar.                  │
│ 2. El cajero realiza el cobro en el POS y entrega el ticket.           │
│ 3. El ticket contiene un código QR único con token criptográfico.      │
│ 4. El cliente escanea el QR desde su smartphone.                       │
│ 5. Se abre el Portal Web del restaurante (https://tenant.pos.com/qr/..)│
│ 6. El cliente puede:                                                   │
│    • Consultar el desglose de su cuenta.                               │
│    • Solicitar Factura de Consumidor Final (CF) ingresando su DUI/NIT. │
│    • Solicitar Comprobante de Crédito Fiscal (CCF) con datos empresa.  │
│    • Ver el menú digital para ordenar una nueva tanda de comida.       │
│ 7. La API Fiscal genera el DTE y le entrega el PDF/JSON en su móvil.   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 3. ¿Por qué el QR no aparece en el Plan Básico?

- **Plan Básico ($29/mes):** Diseñado para clientes que solo buscan operación local (mesas, comandas y caja). El método `qrLine()` retorna `null` y el ticket muestra el texto de comprobante interno de caja.
- **Plan Smart QR ($49/mes):** El sistema detecta `plan_code === 'smart_qr'` en la base de datos del tenant, genera el token opaco único y renderiza el código QR en la parte inferior del ticket térmico o PDF.

---

## 4. Estructura de Datos del Token QR

Para evitar que un comensal altere la URL o acceda a pedidos de otras mesas, el token QR se construye mediante un **hash opaco firmado**:

$$\text{Token QR} = \text{hash('sha256', tenant\_id + '|' + pedido\_id + '|' + total + '|' + secret)}$$

Cuando el cliente escanea el QR, el sistema valida la firma del token, asegurando que el pedido exista, pertenezca al restaurante correcto y corresponda al monto cobrado.
