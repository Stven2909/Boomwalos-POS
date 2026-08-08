---
paths:
  - 'app/Models/**'
---

# Models

## Tablas en español exigen $table y columnas morph custom
Las tablas usan plural español (usuarios, detalles_pedido, trabajo_impresion, opciones_combo...), distinto del plural automático de Laravel: cada modelo debe declarar protected $table. User vive en `usuarios` (columnas nombre/usuario, no name). EventoAuditoria es morph con entidad_tipo/entidad_id (no entidad_type) y no tiene updated_at (const UPDATED_AT = null).
