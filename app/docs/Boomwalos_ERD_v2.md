# POS documentation — ERD v2

## Changelog respecto a v1

- **Eliminadas:** `roles`, `permisos`, `rol_permisos`, y la columna `usuarios.rol_id`.
  Reemplazadas por las tablas que crea `spatie/laravel-permission` vía sus propias
  migraciones (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
  `role_has_permissions`). No se declaran aquí porque el paquete las gestiona.
- **Añadida nota de integridad** en `detalles_pedido`: constraint `CHECK` para
  forzar que `producto_id` XOR `combo_id` (uno de los dos, nunca ambos, nunca
  ninguno).
- **Añadida nota de invariante de aplicación** en `mesas`/`pedidos`: "una mesa =
  un pedido activo" no es forzable como índice único parcial en MySQL: se
  aplica en la capa de servicio (`PedidoService`), respaldado por tests.
- **Añadida nota de bloqueo post-cobro** en `pedidos`: una vez
  `estado_comercial = COBRADO`, el servicio de aplicación debe rechazar nuevas
  filas en `detalles_pedido`/`tandas_pedido` para ese pedido. `uk_pago_pedido`
  en `pagos` ya impide un segundo pago a nivel de base de datos.

---

## Summary

- [Introduction](#introduction)
- [Database Type](#database-type)
- [Table Structure](#table-structure)
	- [usuarios](#usuarios)
	- [establecimientos](#establecimientos)
	- [categorias](#categorias)
	- [productos](#productos)
	- [combos](#combos)
	- [opciones_combo](#opciones_combo)
	- [opciones_combo_productos](#opciones_combo_productos)
	- [mesas](#mesas)
	- [pedidos](#pedidos)
	- [tandas_pedido](#tandas_pedido)
	- [detalles_pedido](#detalles_pedido)
	- [notas_cocina](#notas_cocina)
	- [detalle_pedido_notas](#detalle_pedido_notas)
	- [pagos](#pagos)
	- [sesiones_caja](#sesiones_caja)
	- [eventos_auditoria](#eventos_auditoria)
	- [impresoras](#impresoras)
	- [trabajos_impresion](#trabajos_impresion)
	- [documentos_fiscales](#documentos_fiscales)
- [Roles y permisos (Spatie)](#roles-y-permisos-spatie)
- [Relationships](#relationships)
- [Database Diagram](#database-diagram)

## Introduction

## Database type

- **Database system:** MySQL

## Table structure

### usuarios

| Name           | Type         | Settings                   | Note |
| -------------- | ------------ | --------------------------- | ---- |
| **id**         | BIGINT       | 🔑 PK, autoincrement         |      |
| **nombre**     | VARCHAR(100) | not null                    |      |
| **usuario**    | VARCHAR(50)  | not null, unique            |      |
| **email**      | VARCHAR(100) | null, unique                |      |
| **password**   | VARCHAR(255) | not null                    |      |
| **created_at** | TIMESTAMP    | null                        |      |
| **updated_at** | TIMESTAMP    | null                        |      |

> El rol ya no se guarda como `rol_id` en esta tabla. La relación usuario↔rol
> la gestiona Spatie a través de `model_has_roles` (pivote polimórfico), usando
> el trait `HasRoles` en el modelo `Usuario`.

### establecimientos

| Name                       | Type         | Settings                   | Note |
| -------------------------- | ------------ | --------------------------- | ---- |
| **id**                     | BIGINT       | 🔑 PK, autoincrement         |      |
| **nombre**                 | VARCHAR(100) | not null                    |      |
| **direccion**              | TEXT         | not null                    |      |
| **codigo_establecimiento** | VARCHAR(10)  | null                        |      |
| **codigo_punto_venta**     | VARCHAR(10)  | null                        |      |
| **created_at**             | TIMESTAMP    | null                        |      |
| **updated_at**             | TIMESTAMP    | null                        |      |

### categorias

| Name            | Type        | Settings           | Note |
| --------------- | ----------- | -------------------- | ---- |
| **id**          | BIGINT      | 🔑 PK, autoincrement  |      |
| **nombre**      | VARCHAR(50) | not null             |      |
| **descripcion** | TEXT        | null                 |      |
| **created_at**  | TIMESTAMP   | null                 |      |
| **updated_at**  | TIMESTAMP   | null                 |      |

### productos

| Name               | Type                 | Settings                       | Note |
| ------------------ | -------------------- | -------------------------------- | ---- |
| **id**             | BIGINT                | 🔑 PK, autoincrement              |      |
| **categoria_id**   | BIGINT                | not null, FK → categorias.id      |      |
| **nombre**         | VARCHAR(100)          | not null                          |      |
| **precio**         | DECIMAL(10,2)         | not null                          |      |
| **imagen_url**     | VARCHAR(255)          | null                              |      |
| **disponibilidad** | DISPONIBILIDAD_ENUM   | not null, default: DISPONIBLE     | `DISPONIBLE / AGOTADO / TEMPORALMENTE_NO_DISPONIBLE` |
| **created_at**     | TIMESTAMP             | null                              |      |
| **updated_at**     | TIMESTAMP             | null                              |      |

### combos

| Name            | Type          | Settings           | Note |
| --------------- | ------------- | -------------------- | ---- |
| **id**          | BIGINT        | 🔑 PK, autoincrement  |      |
| **nombre**      | VARCHAR(100)  | not null              |      |
| **precio_fijo** | DECIMAL(10,2) | not null              |      |
| **created_at**  | TIMESTAMP     | null                  |      |
| **updated_at**  | TIMESTAMP     | null                  |      |

### opciones_combo

| Name                   | Type        | Settings                    | Note |
| ---------------------- | ----------- | ------------------------------ | ---- |
| **id**                 | BIGINT      | 🔑 PK, autoincrement            |      |
| **combo_id**           | BIGINT      | not null, FK → combos.id        |      |
| **nombre**             | VARCHAR(50) | not null                        | ej. "Pupusas", "Bebida" |
| **cantidad_requerida** | INT         | not null, default: 1            |      |
| **es_obligatorio**     | BOOLEAN     | not null, default: true         |      |
| **created_at**         | TIMESTAMP   | null                            |      |
| **updated_at**         | TIMESTAMP   | null                            |      |

### opciones_combo_productos

| Name                | Type   | Settings                              | Note |
| ------------------- | ------ | ---------------------------------------- | ---- |
| **id**              | BIGINT | 🔑 PK, autoincrement                      |      |
| **opcion_combo_id** | BIGINT | not null, FK → opciones_combo.id          |      |
| **producto_id**     | BIGINT | not null, FK → productos.id               |      |

#### Indexes
| Name               | Unique | Fields                       |
| ------------------ | ------ | ----------------------------- |
| uk_opcion_producto | ✅      | opcion_combo_id, producto_id  |

### mesas

| Name                   | Type             | Settings                        | Note |
| ---------------------- | ---------------- | ---------------------------------- | ---- |
| **id**                 | BIGINT           | 🔑 PK, autoincrement                |      |
| **establecimiento_id** | BIGINT           | not null, FK → establecimientos.id  |      |
| **numero**             | VARCHAR(10)      | not null, unique                   |      |
| **estado**             | ESTADO_MESA_ENUM | not null, default: LIBRE            | `LIBRE / OCUPADA` |
| **created_at**         | TIMESTAMP        | null                                |      |
| **updated_at**         | TIMESTAMP        | null                                |      |

> **Invariante de aplicación (no forzable por schema en MySQL):** una mesa no
> puede pasar a un nuevo pedido mientras `estado = OCUPADA`. Se valida en
> `PedidoService::crear()` antes del insert, no por constraint de DB.

### pedidos

| Name                   | Type                   | Settings                          | Note |
| ---------------------- | ---------------------- | ------------------------------------ | ---- |
| **id**                 | BIGINT                 | 🔑 PK, autoincrement                  |      |
| **numero_seguimiento** | VARCHAR(20)             | not null, unique                     | Independiente del `numeroControl` fiscal |
| **tipo_pedido**        | TIPO_PEDIDO_ENUM        | not null                             | `MESA / PARA_LLEVAR` |
| **mesa_id**            | BIGINT                  | null, FK → mesas.id                   | null si es para llevar |
| **establecimiento_id** | BIGINT                  | not null, FK → establecimientos.id    |      |
| **usuario_id**         | BIGINT                  | not null, FK → usuarios.id            |      |
| **estado_comercial**   | ESTADO_COMERCIAL_ENUM   | not null, default: ABIERTO           | `ABIERTO / COBRADO / CERRADO` |
| **created_at**         | TIMESTAMP               | null                                  |      |
| **updated_at**         | TIMESTAMP               | null                                  |      |

> **Regla de bloqueo post-cobro (aplicación, no schema):** cuando
> `estado_comercial` pasa a `COBRADO`, el servicio debe rechazar cualquier
> intento de insertar nuevas filas en `detalles_pedido` o `tandas_pedido`
> para este `pedido_id`. Ver `pagos.uk_pago_pedido` para el respaldo a nivel
> de base de datos (impide un segundo pago).

### tandas_pedido

| Name              | Type               | Settings                     | Note |
| ----------------- | ------------------- | ------------------------------- | ---- |
| **id**            | BIGINT               | 🔑 PK, autoincrement              |      |
| **pedido_id**     | BIGINT               | not null, FK → pedidos.id         |      |
| **numero_tanda**  | INT                  | not null                          |      |
| **estado_cocina** | ESTADO_COCINA_ENUM   | not null, default: PENDIENTE      | `PENDIENTE / EN_PREPARACION / LISTA / ENTREGADA / CANCELADA` |
| **created_at**    | TIMESTAMP            | null                              |      |
| **updated_at**    | TIMESTAMP            | null                              |      |

#### Indexes
| Name            | Unique | Fields                  |
| --------------- | ------ | ------------------------ |
| uk_tanda_pedido | ✅      | pedido_id, numero_tanda  |

### detalles_pedido

| Name                | Type          | Settings                        | Note |
| ------------------- | ------------- | ----------------------------------- | ---- |
| **id**              | BIGINT         | 🔑 PK, autoincrement                  |      |
| **pedido_id**       | BIGINT         | not null, FK → pedidos.id             |      |
| **tanda_id**        | BIGINT         | not null, FK → tandas_pedido.id       |      |
| **producto_id**     | BIGINT         | null, FK → productos.id               | XOR con combo_id |
| **combo_id**        | BIGINT         | null, FK → combos.id                  | XOR con producto_id |
| **cantidad**        | INT            | not null                              |      |
| **precio_unitario** | DECIMAL(10,2)  | not null                              | capturado al momento de venta |
| **seleccion_combo** | JSON           | null                                  | sabores/bebida elegidos, si combo_id no es null |
| **created_at**      | TIMESTAMP      | null                                  |      |
| **updated_at**      | TIMESTAMP      | null                                  |      |

> **Constraint recomendado (MySQL ≥ 8.0.16):**
> ```sql
> ALTER TABLE detalles_pedido
>   ADD CONSTRAINT chk_producto_o_combo
>   CHECK (
>     (producto_id IS NOT NULL AND combo_id IS NULL) OR
>     (producto_id IS NULL AND combo_id IS NOT NULL)
>   );
> ```

### notas_cocina

| Name           | Type        | Settings                | Note |
| -------------- | ----------- | -------------------------- | ---- |
| **id**         | BIGINT      | 🔑 PK, autoincrement         |      |
| **nombre**     | VARCHAR(50) | not null, unique            | ej. "Sin curtido" |
| **activo**     | BOOLEAN     | not null, default: true     |      |
| **created_at** | TIMESTAMP   | null                        |      |
| **updated_at** | TIMESTAMP   | null                        |      |

### detalle_pedido_notas

| Name                  | Type   | Settings                                | Note |
| --------------------- | ------ | ------------------------------------------- | ---- |
| **id**                | BIGINT | 🔑 PK, autoincrement                          |      |
| **detalle_pedido_id** | BIGINT | not null, FK → detalles_pedido.id             |      |
| **nota_cocina_id**    | BIGINT | not null, FK → notas_cocina.id                |      |

#### Indexes
| Name            | Unique | Fields                             |
| --------------- | ------ | ------------------------------------- |
| uk_detalle_nota | ✅      | detalle_pedido_id, nota_cocina_id     |

### pagos

| Name                | Type              | Settings                   | Note |
| ------------------- | ----------------- | ----------------------------- | ---- |
| **id**              | BIGINT             | 🔑 PK, autoincrement            |      |
| **pedido_id**       | BIGINT             | not null, FK → pedidos.id       |      |
| **metodo_pago**     | METODO_PAGO_ENUM   | not null                       | `EFECTIVO / TARJETA` |
| **monto_recibido**  | DECIMAL(10,2)      | null                            |      |
| **cambio_devuelto** | DECIMAL(10,2)      | null                            |      |
| **created_at**      | TIMESTAMP          | null                            |      |
| **updated_at**      | TIMESTAMP          | null                            |      |

#### Indexes
| Name           | Unique | Fields    |
| -------------- | ------ | --------- |
| uk_pago_pedido | ✅      | pedido_id | ← garantiza un solo pago por pedido a nivel de DB

### sesiones_caja

| Name                    | Type          | Settings                          | Note |
| ----------------------- | ------------- | ------------------------------------- | ---- |
| **id**                  | BIGINT         | 🔑 PK, autoincrement                    |      |
| **establecimiento_id**  | BIGINT         | not null, FK → establecimientos.id      |      |
| **usuario_apertura_id** | BIGINT         | not null, FK → usuarios.id              |      |
| **usuario_cierre_id**   | BIGINT         | null, FK → usuarios.id                  |      |
| **monto_inicial**       | DECIMAL(10,2)  | not null                                |      |
| **efectivo_esperado**   | DECIMAL(10,2)  | null                                    |      |
| **efectivo_contado**    | DECIMAL(10,2)  | null                                    |      |
| **diferencia**          | DECIMAL(10,2)  | null                                    |      |
| **fecha_apertura**      | TIMESTAMP      | not null                                |      |
| **fecha_cierre**        | TIMESTAMP      | null                                    |      |
| **created_at**          | TIMESTAMP      | null                                    |      |
| **updated_at**          | TIMESTAMP      | null                                    |      |

### eventos_auditoria

| Name             | Type         | Settings                          | Note |
| ---------------- | ------------ | ------------------------------------ | ---- |
| **id**           | BIGINT        | 🔑 PK, autoincrement                   |      |
| **entidad_tipo** | VARCHAR(50)   | not null                              | polimórfico — `App\Models\Pedido`, etc. |
| **entidad_id**   | BIGINT        | not null                              |      |
| **usuario_id**   | BIGINT        | not null, FK → usuarios.id             |      |
| **tipo_evento**  | VARCHAR(100)  | not null                              | `pedido_creado`, `pedido_cobrado`, etc. |
| **payload**      | JSON          | null                                  |      |
| **created_at**   | TIMESTAMP     | null, default: CURRENT_TIMESTAMP      |      |

### impresoras

| Name              | Type                  | Settings           | Note |
| ----------------- | --------------------- | --------------------- | ---- |
| **id**            | BIGINT                 | 🔑 PK, autoincrement    |      |
| **nombre**        | VARCHAR(50)            | not null               |      |
| **tipo**          | TIPO_IMPRESORA_ENUM    | not null               | `TICKET / COMANDA` |
| **configuracion** | JSON                   | null                   |      |
| **created_at**    | TIMESTAMP              | null                   |      |
| **updated_at**    | TIMESTAMP              | null                   |      |

### trabajos_impresion

| Name             | Type                    | Settings                        | Note |
| ---------------- | ----------------------- | ------------------------------------ | ---- |
| **id**           | BIGINT                   | 🔑 PK, autoincrement                   |      |
| **impresora_id** | BIGINT                   | not null, FK → impresoras.id           |      |
| **tanda_id**     | BIGINT                   | null, FK → tandas_pedido.id            | set si es comanda |
| **pedido_id**    | BIGINT                   | null, FK → pedidos.id                  | set si es ticket |
| **estado**       | ESTADO_IMPRESION_ENUM    | not null, default: PENDIENTE           | `PENDIENTE / IMPRESO / ERROR` |
| **contenido**    | TEXT                     | not null                               |      |
| **created_at**   | TIMESTAMP                | null                                   |      |
| **updated_at**   | TIMESTAMP                | null                                   |      |

### documentos_fiscales

| Name                  | Type                | Settings                       | Note |
| --------------------- | ------------------- | ---------------------------------- | ---- |
| **id**                | BIGINT               | 🔑 PK, autoincrement                 |      |
| **pedido_id**         | BIGINT               | not null, FK → pedidos.id            |      |
| **tipo_documento**    | TIPO_DOCUMENTO_ENUM  | not null                            | `FACTURA / CCF` |
| **numero_control**    | VARCHAR(50)          | null                                | formato DTE: 31 chars |
| **codigo_generacion** | VARCHAR(36)          | null                                | UUID v4 |
| **sello_recepcion**   | VARCHAR(100)         | null                                |      |
| **estado**            | ESTADO_FISCAL_ENUM   | not null, default: PENDIENTE        | `PENDIENTE / EMITIDO / RECHAZADO` |
| **datos_solicitante** | JSON                 | null                                | nombre, NIT, correo |
| **created_at**        | TIMESTAMP            | null                                |      |
| **updated_at**        | TIMESTAMP            | null                                |      |

#### Indexes
| Name                | Unique | Fields                    |
| ------------------- | ------ | -------------------------- |
| uk_documento_fiscal | ✅      | pedido_id, tipo_documento  |

---

## Roles y permisos (Spatie)

No se declaran tablas custom. `spatie/laravel-permission` crea vía sus propias
migraciones:

- `roles` (name, guard_name)
- `permissions` (name, guard_name)
- `model_has_roles` (pivote polimórfico usuario↔rol)
- `model_has_permissions` (pivote polimórfico usuario↔permiso directo, opcional)
- `role_has_permissions`

**Roles iniciales a sembrar:** `administrador`, `cajero`.

**Permisos iniciales sugeridos** (nombrado `accion_recurso`, guard `web`):

```
crear_pedido, cobrar_pedido, cancelar_pedido, aplicar_descuento,
gestionar_productos, gestionar_combos, gestionar_notas_cocina,
gestionar_usuarios, abrir_caja, cerrar_caja,
ver_reportes, gestionar_solicitudes_fiscales
```

> `cancelar_pedido`, `aplicar_descuento` y `gestionar_solicitudes_fiscales`
> quedan sembrados como permisos existentes, pero **sin asignar a ningún rol
> todavía** — su asignación depende de decisiones del negocio que siguen
> pendientes (ver `docs/decisiones-pendientes.md`).

---

## Relationships

- **establecimientos to mesas**: one_to_many
- **mesas to pedidos**: one_to_many
- **establecimientos to pedidos**: one_to_many
- **usuarios to pedidos**: one_to_many
- **pedidos to tandas_pedido**: one_to_many
- **pedidos to detalles_pedido**: one_to_many
- **tandas_pedido to detalles_pedido**: one_to_many
- **categorias to productos**: one_to_many
- **productos to detalles_pedido**: one_to_many
- **combos to detalles_pedido**: one_to_many
- **combos to opciones_combo**: one_to_many
- **opciones_combo to opciones_combo_productos**: one_to_many
- **productos to opciones_combo_productos**: one_to_many
- **detalles_pedido to detalle_pedido_notas**: one_to_many
- **notas_cocina to detalle_pedido_notas**: one_to_many
- **pedidos to pagos**: one_to_one (forzado por `uk_pago_pedido`)
- **establecimientos to sesiones_caja**: one_to_many
- **usuarios to sesiones_caja**: one_to_many (apertura y cierre)
- **usuarios to eventos_auditoria**: one_to_many
- **impresoras to trabajos_impresion**: one_to_many
- **tandas_pedido to trabajos_impresion**: one_to_many
- **pedidos to trabajos_impresion**: one_to_many
- **pedidos to documentos_fiscales**: one_to_many
- **usuarios to roles (Spatie)**: many_to_many vía `model_has_roles`

## Database Diagram

```mermaid
erDiagram
	establecimientos ||--o{ mesas : references
	mesas ||--o{ pedidos : references
	establecimientos ||--o{ pedidos : references
	usuarios ||--o{ pedidos : references
	pedidos ||--o{ tandas_pedido : references
	pedidos ||--o{ detalles_pedido : references
	tandas_pedido ||--o{ detalles_pedido : references
	categorias ||--o{ productos : references
	productos ||--o{ detalles_pedido : references
	combos ||--o{ detalles_pedido : references
	combos ||--o{ opciones_combo : references
	opciones_combo ||--o{ opciones_combo_productos : references
	productos ||--o{ opciones_combo_productos : references
	detalles_pedido ||--o{ detalle_pedido_notas : references
	notas_cocina ||--o{ detalle_pedido_notas : references
	pedidos ||--o| pagos : references
	establecimientos ||--o{ sesiones_caja : references
	usuarios ||--o{ sesiones_caja : references
	usuarios ||--o{ eventos_auditoria : references
	impresoras ||--o{ trabajos_impresion : references
	tandas_pedido ||--o{ trabajos_impresion : references
	pedidos ||--o{ trabajos_impresion : references
	pedidos ||--o{ documentos_fiscales : references

	usuarios {
		BIGINT id
		VARCHAR(100) nombre
		VARCHAR(50) usuario
		VARCHAR(100) email
		VARCHAR(255) password
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	establecimientos {
		BIGINT id
		VARCHAR(100) nombre
		TEXT direccion
		VARCHAR(10) codigo_establecimiento
		VARCHAR(10) codigo_punto_venta
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	categorias {
		BIGINT id
		VARCHAR(50) nombre
		TEXT descripcion
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	productos {
		BIGINT id
		BIGINT categoria_id
		VARCHAR(100) nombre
		DECIMAL(10,2) precio
		VARCHAR(255) imagen_url
		DISPONIBILIDAD_ENUM disponibilidad
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	combos {
		BIGINT id
		VARCHAR(100) nombre
		DECIMAL(10,2) precio_fijo
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	opciones_combo {
		BIGINT id
		BIGINT combo_id
		VARCHAR(50) nombre
		INT cantidad_requerida
		BOOLEAN es_obligatorio
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	opciones_combo_productos {
		BIGINT id
		BIGINT opcion_combo_id
		BIGINT producto_id
	}

	mesas {
		BIGINT id
		BIGINT establecimiento_id
		VARCHAR(10) numero
		ESTADO_MESA_ENUM estado
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	pedidos {
		BIGINT id
		VARCHAR(20) numero_seguimiento
		TIPO_PEDIDO_ENUM tipo_pedido
		BIGINT mesa_id
		BIGINT establecimiento_id
		BIGINT usuario_id
		ESTADO_COMERCIAL_ENUM estado_comercial
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	tandas_pedido {
		BIGINT id
		BIGINT pedido_id
		INT numero_tanda
		ESTADO_COCINA_ENUM estado_cocina
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	detalles_pedido {
		BIGINT id
		BIGINT pedido_id
		BIGINT tanda_id
		BIGINT producto_id
		BIGINT combo_id
		INT cantidad
		DECIMAL(10,2) precio_unitario
		JSON seleccion_combo
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	notas_cocina {
		BIGINT id
		VARCHAR(50) nombre
		BOOLEAN activo
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	detalle_pedido_notas {
		BIGINT id
		BIGINT detalle_pedido_id
		BIGINT nota_cocina_id
	}

	pagos {
		BIGINT id
		BIGINT pedido_id
		METODO_PAGO_ENUM metodo_pago
		DECIMAL(10,2) monto_recibido
		DECIMAL(10,2) cambio_devuelto
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	sesiones_caja {
		BIGINT id
		BIGINT establecimiento_id
		BIGINT usuario_apertura_id
		BIGINT usuario_cierre_id
		DECIMAL(10,2) monto_inicial
		DECIMAL(10,2) efectivo_esperado
		DECIMAL(10,2) efectivo_contado
		DECIMAL(10,2) diferencia
		TIMESTAMP fecha_apertura
		TIMESTAMP fecha_cierre
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	eventos_auditoria {
		BIGINT id
		VARCHAR(50) entidad_tipo
		BIGINT entidad_id
		BIGINT usuario_id
		VARCHAR(100) tipo_evento
		JSON payload
		TIMESTAMP created_at
	}

	impresoras {
		BIGINT id
		VARCHAR(50) nombre
		TIPO_IMPRESORA_ENUM tipo
		JSON configuracion
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	trabajos_impresion {
		BIGINT id
		BIGINT impresora_id
		BIGINT tanda_id
		BIGINT pedido_id
		ESTADO_IMPRESION_ENUM estado
		TEXT contenido
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}

	documentos_fiscales {
		BIGINT id
		BIGINT pedido_id
		TIPO_DOCUMENTO_ENUM tipo_documento
		VARCHAR(50) numero_control
		VARCHAR(36) codigo_generacion
		VARCHAR(100) sello_recepcion
		ESTADO_FISCAL_ENUM estado
		JSON datos_solicitante
		TIMESTAMP created_at
		TIMESTAMP updated_at
	}
```
