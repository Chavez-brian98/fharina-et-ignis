# Base de Datos
## Sistema de Gestión de Ventas para Panadería

> Motor sugerido: **PostgreSQL** (compatible también con MySQL con ajustes menores de tipos ENUM/timestamp).
> Diseño normalizado a **3FN**, con indexación en claves foráneas y campos de búsqueda frecuente, y cardinalidad explícita en cada relación.
> Diagrama fuente: `panaderia-database.dbml` (dbdiagram.io).

---

## Índice

1. [Convenciones generales](#1-convenciones-generales)
2. [Modelo Entidad-Relación (resumen visual)](#2-modelo-entidad-relación-resumen-visual)
3. [Catálogo de Enums](#3-catálogo-de-enums)
4. [Diccionario de Datos por Módulo](#4-diccionario-de-datos-por-módulo)
   - 4.1 Seguridad / Usuarios
   - 4.2 Empleados
   - 4.3 Clientes
   - 4.4 Categorías y Productos
   - 4.5 Producción / Recetas
   - 4.6 Inventario
   - 4.7 Proveedores / Compras
   - 4.8 Pedidos Personalizados (módulo crítico)
   - 4.9 Promociones / Descuentos
   - 4.10 Caja
   - 4.11 Ventas (POS)
   - 4.12 Notificaciones Internas
5. [Relaciones y Cardinalidad](#5-relaciones-y-cardinalidad)
6. [Estrategia de Indexación](#6-estrategia-de-indexación)
7. [Reglas de Negocio a Nivel de Base de Datos](#7-reglas-de-negocio-a-nivel-de-base-de-datos)

---

## 1. Convenciones Generales

- Toda tabla tiene una clave primaria `id` de tipo `int`, autoincremental.
- Las claves foráneas siguen el patrón `<entidad>_id`.
- Los campos de auditoría temporal usan `timestamp` (fecha y hora) o `date` (solo fecha) según corresponda.
- Los estados de catálogo (activo/inactivo) usan el enum `estado_general`.
- Todas las tablas transaccionales (ventas, pedidos, movimientos de caja e inventario) registran `usuario_id` para trazabilidad de auditoría.
- Los montos monetarios usan `decimal(10,2)`; las cantidades de insumos usan `decimal(10,3)` para admitir fracciones de unidad (kg, litros, etc.).

---

## 2. Modelo Entidad-Relación (resumen visual)

```
roles ──< usuarios >── empleados ──< turnos
                │           │
                │           ├──< asistencias
                │           ├──< comisiones >── ventas
                │           └──< evaluaciones_desempeno
                │
clientes ──< cliente_segmento >── segmentos_clientes
   │
   ├──< pedidos ──< detalle_pedido >── productos
   │      ├──< abonos_pedido
   │      ├──< historial_estados_pedido
   │      ├──< tickets_pedido
   │      └──< notificaciones_email
   │
   └──< ventas ──< detalle_venta >── productos

categorias ──< productos ──> recetas ──< receta_ingredientes >── ingredientes
                  │                              │
                  │                              ├──< movimientos_inventario_insumos
                  │                              └──< historial_precios_proveedor >── proveedores
                  │
                  └──< movimientos_inventario_productos

recetas ──< hornadas ──< mermas_produccion
                  │
                  └── empleados (empleado_asignado_id)

proveedores ──< ordenes_compra ──< detalle_orden_compra >── ingredientes
                      │
                      └──< recepciones_mercaderia ──< detalle_recepcion >── ingredientes

promociones ──< promocion_producto >── productos
     │
     └──< cupones >── clientes

caja ──< movimientos_caja
  │
  └──< ventas
```

---

## 3. Catálogo de Enums

| Enum | Valores | Uso |
|---|---|---|
| `estado_general` | `activo`, `inactivo` | Estado de catálogo (empleados, clientes, productos, categorías, ingredientes, proveedores, promociones, cupones). |
| `tipo_movimiento_inventario` | `entrada`, `salida`, `ajuste`, `merma` | Tipo de movimiento en insumos o productos terminados. |
| `estado_pedido` | `pendiente`, `aprobado`, `en_produccion`, `listo`, `entregado`, `rechazado`, `cancelado` | Estado del ciclo de vida de un pedido personalizado. |
| `tipo_ticket` | `confirmacion`, `entrega`, `rechazo` | Tipo de ticket generado para un pedido. |
| `estado_envio_email` | `pendiente`, `enviado`, `fallido` | Estado del correo de notificación al cliente. |
| `tipo_notificacion_email` | `pedido_aprobado`, `pedido_entregado`, `pedido_rechazado` | Motivo del correo enviado al cliente. |
| `tipo_promocion` | `descuento_volumen`, `dos_por_uno`, `happy_hour`, `cupon`, `campana_temporal` | Tipo de promoción comercial. |
| `tipo_movimiento_caja` | `ingreso_extra`, `retiro` | Movimiento manual de caja distinto a una venta. |
| `estado_caja` | `abierta`, `cerrada` | Estado del turno de caja. |
| `estado_venta` | `completada`, `anulada` | Estado de una venta del POS. |
| `estado_orden_compra` | `pendiente`, `parcial`, `recibida`, `cancelada` | Estado de una orden de compra a proveedor. |
| `tipo_evento_produccion` | `planificada`, `en_reposo`, `horneando`, `finalizada`, `cancelada` | Estado de una hornada. |

---

## 4. Diccionario de Datos por Módulo

### 4.1 Seguridad / Usuarios

**`roles`** — catálogo de roles del sistema.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | Identificador |
| nombre | varchar(50) | NOT NULL, UNIQUE | administrador, cajero, panadero, encargado_inventario, rrhh, etc. |
| descripcion | varchar(255) | | Detalle del rol |

**`usuarios`** — cuentas de acceso al sistema.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | Identificador |
| empleado_id | int | FK → empleados.id, NULL | Vínculo opcional con la ficha de empleado |
| rol_id | int | NOT NULL, FK → roles.id | Rol asignado |
| nombre_usuario | varchar(50) | NOT NULL, UNIQUE | Usuario de acceso |
| email | varchar(150) | NOT NULL, UNIQUE | Correo institucional |
| password_hash | varchar(255) | NOT NULL | Contraseña cifrada |
| estado | estado_general | NOT NULL, default activo | Habilitado/deshabilitado |
| ultimo_acceso | timestamp | | Último login |
| created_at | timestamp | NOT NULL, default now() | Fecha de creación |

---

### 4.2 Empleados

**`empleados`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(100) | NOT NULL | |
| apellido | varchar(100) | NOT NULL | |
| documento_identidad | varchar(30) | NOT NULL, UNIQUE | DPI/documento oficial |
| telefono | varchar(20) | | |
| direccion | varchar(255) | | |
| fecha_nacimiento | date | | |
| fecha_contratacion | date | NOT NULL | |
| puesto | varchar(80) | NOT NULL | |
| salario_base | decimal(10,2) | NOT NULL | |
| estado | estado_general | NOT NULL, default activo | |
| created_at | timestamp | NOT NULL, default now() | |

**`turnos`** — horarios asignados por día.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| empleado_id | int | NOT NULL, FK → empleados.id | |
| fecha | date | NOT NULL | |
| hora_inicio | time | NOT NULL | |
| hora_fin | time | NOT NULL | |
| tipo_turno | varchar(50) | | matutino, vespertino, etc. |

**`asistencias`** — registro diario de entrada/salida.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| empleado_id | int | NOT NULL, FK → empleados.id | |
| fecha | date | NOT NULL | |
| hora_entrada | time | | |
| hora_salida | time | | |
| estado | varchar(30) | NOT NULL, default 'presente' | presente, tarde, ausente |

**`comisiones`** — comisión de un empleado sobre una venta.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| empleado_id | int | NOT NULL, FK → empleados.id | |
| venta_id | int | NOT NULL, FK → ventas.id | |
| monto | decimal(10,2) | NOT NULL | |
| fecha | date | NOT NULL | |

**`evaluaciones_desempeno`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| empleado_id | int | NOT NULL, FK → empleados.id | Evaluado |
| evaluador_id | int | NOT NULL, FK → empleados.id | Quien evalúa |
| fecha | date | NOT NULL | |
| puntuacion | decimal(3,1) | NOT NULL | Escala definida por negocio (ej. 0–10) |
| comentarios | text | | |

---

### 4.3 Clientes

**`clientes`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(100) | NOT NULL | |
| apellido | varchar(100) | NOT NULL | |
| telefono | varchar(20) | | |
| email | varchar(150) | UNIQUE | |
| direccion | varchar(255) | | |
| fecha_nacimiento | date | | Usado para notificaciones de cumpleaños |
| fecha_registro | timestamp | NOT NULL, default now() | |
| estado | estado_general | NOT NULL, default activo | |

**`segmentos_clientes`** — catálogo de segmentos para campañas.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(80) | NOT NULL, UNIQUE | Ej. "Frecuentes", "VIP" |
| descripcion | varchar(255) | | |

**`cliente_segmento`** — tabla puente (N:M).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| cliente_id | int | NOT NULL, FK → clientes.id | |
| segmento_id | int | NOT NULL, FK → segmentos_clientes.id | |
| — | | UNIQUE(cliente_id, segmento_id) | Evita duplicados |

---

### 4.4 Categorías y Productos

**`categorias`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(80) | NOT NULL, UNIQUE | Panes dulces, Panes salados, Repostería, etc. |
| descripcion | varchar(255) | | |
| orden_visualizacion | int | NOT NULL, default 0 | Orden en el catálogo/POS |
| estado | estado_general | NOT NULL, default activo | |

**`productos`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| categoria_id | int | NOT NULL, FK → categorias.id | |
| receta_id | int | FK → recetas.id, NULL | Receta asociada (si aplica) |
| nombre | varchar(120) | NOT NULL | |
| descripcion | text | | |
| precio_venta | decimal(10,2) | NOT NULL | |
| costo_produccion | decimal(10,2) | NOT NULL | |
| imagen_url | varchar(255) | | |
| estado | estado_general | NOT NULL, default activo | Activo/inactivo |
| created_at | timestamp | NOT NULL, default now() | |

---

### 4.5 Producción / Recetas

**`ingredientes`** — materias primas.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(100) | NOT NULL, UNIQUE | Harina, levadura, sal, mantequilla, etc. |
| unidad_medida | varchar(20) | NOT NULL | kg, litro, unidad |
| stock_actual | decimal(10,3) | NOT NULL, default 0 | |
| stock_minimo | decimal(10,3) | NOT NULL, default 0 | Umbral de alerta |
| costo_unitario | decimal(10,4) | NOT NULL | |
| proveedor_principal_id | int | FK → proveedores.id, NULL | |
| estado | estado_general | NOT NULL, default activo | |

**`recetas`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(120) | NOT NULL | |
| rendimiento_cantidad | decimal(10,2) | NOT NULL | Cantidad producida por lote |
| tiempo_preparacion_min | int | | |
| tiempo_horneado_min | int | | |
| instrucciones | text | | |

**`receta_ingredientes`** — tabla puente (N:M) con cantidad.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| receta_id | int | NOT NULL, FK → recetas.id | |
| ingrediente_id | int | NOT NULL, FK → ingredientes.id | |
| cantidad | decimal(10,3) | NOT NULL | |
| unidad_medida | varchar(20) | NOT NULL | |
| — | | UNIQUE(receta_id, ingrediente_id) | |

**`hornadas`** — planificación de producción diaria.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| receta_id | int | NOT NULL, FK → recetas.id | |
| empleado_asignado_id | int | NOT NULL, FK → empleados.id | |
| fecha | date | NOT NULL | |
| cantidad_lotes | int | NOT NULL | |
| hora_inicio_preparacion | time | | |
| hora_inicio_reposo | time | | |
| hora_inicio_horneado | time | | |
| hora_fin_horneado | time | | |
| estado | tipo_evento_produccion | NOT NULL, default 'planificada' | |
| observaciones | text | | |

**`mermas_produccion`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| hornada_id | int | NOT NULL, FK → hornadas.id | |
| cantidad | decimal(10,3) | NOT NULL | |
| motivo | varchar(255) | NOT NULL | |
| fecha | date | NOT NULL | |

---

### 4.6 Inventario

**`movimientos_inventario_insumos`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| ingrediente_id | int | NOT NULL, FK → ingredientes.id | |
| tipo_movimiento | tipo_movimiento_inventario | NOT NULL | entrada, salida, ajuste, merma |
| cantidad | decimal(10,3) | NOT NULL | |
| motivo | varchar(255) | | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| referencia | varchar(100) | | Ej. número de orden de compra o hornada |
| fecha | timestamp | NOT NULL, default now() | |

**`movimientos_inventario_productos`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| producto_id | int | NOT NULL, FK → productos.id | |
| tipo_movimiento | tipo_movimiento_inventario | NOT NULL | |
| cantidad | decimal(10,3) | NOT NULL | |
| motivo | varchar(255) | | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| referencia | varchar(100) | | |
| fecha | timestamp | NOT NULL, default now() | |

> **Nota de diseño:** se mantienen dos tablas de movimientos (insumos y productos terminados) en lugar de una sola tabla con referencia polimórfica, para preservar la integridad referencial estricta de 3FN sin columnas FK nulas ambiguas.

---

### 4.7 Proveedores / Compras

**`proveedores`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(120) | NOT NULL | |
| tipo_proveedor | varchar(60) | | Harinera, lácteos, semillas, etc. |
| contacto | varchar(100) | | |
| telefono | varchar(20) | | |
| email | varchar(150) | | |
| direccion | varchar(255) | | |
| condiciones_pago | varchar(150) | | |
| estado | estado_general | NOT NULL, default activo | |

**`historial_precios_proveedor`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| proveedor_id | int | NOT NULL, FK → proveedores.id | |
| ingrediente_id | int | NOT NULL, FK → ingredientes.id | |
| precio | decimal(10,4) | NOT NULL | |
| fecha | date | NOT NULL | |

**`ordenes_compra`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| proveedor_id | int | NOT NULL, FK → proveedores.id | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| fecha_pedido | date | NOT NULL | |
| fecha_entrega_estimada | date | | |
| estado | estado_orden_compra | NOT NULL, default 'pendiente' | |
| total | decimal(10,2) | NOT NULL, default 0 | |

**`detalle_orden_compra`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| orden_compra_id | int | NOT NULL, FK → ordenes_compra.id | |
| ingrediente_id | int | NOT NULL, FK → ingredientes.id | |
| cantidad | decimal(10,3) | NOT NULL | |
| precio_unitario | decimal(10,4) | NOT NULL | |
| subtotal | decimal(10,2) | NOT NULL | |

**`recepciones_mercaderia`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| orden_compra_id | int | NOT NULL, FK → ordenes_compra.id | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| fecha_recepcion | timestamp | NOT NULL, default now() | |
| observaciones | text | | |

**`detalle_recepcion`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| recepcion_id | int | NOT NULL, FK → recepciones_mercaderia.id | |
| ingrediente_id | int | NOT NULL, FK → ingredientes.id | |
| cantidad_esperada | decimal(10,3) | NOT NULL | |
| cantidad_recibida | decimal(10,3) | NOT NULL | |

> Al insertar un registro en `detalle_recepcion`, la aplicación debe generar automáticamente el movimiento `entrada` correspondiente en `movimientos_inventario_insumos` y actualizar `ingredientes.stock_actual`.

---

### 4.8 Pedidos Personalizados (módulo crítico)

**`pedidos`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| cliente_id | int | NOT NULL, FK → clientes.id | |
| usuario_registro_id | int | NOT NULL, FK → usuarios.id | Empleado que registró el pedido |
| fecha_pedido | timestamp | NOT NULL, default now() | |
| fecha_entrega | date | NOT NULL | |
| estado | estado_pedido | NOT NULL, default 'pendiente' | Ver flujo de estados en sección 7 |
| motivo_rechazo | varchar(255) | | Obligatorio si estado = rechazado |
| total | decimal(10,2) | NOT NULL, default 0 | |
| monto_abonado | decimal(10,2) | NOT NULL, default 0 | |
| saldo_pendiente | decimal(10,2) | NOT NULL, default 0 | |
| notas | text | | |

**`detalle_pedido`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| pedido_id | int | NOT NULL, FK → pedidos.id | |
| producto_id | int | NOT NULL, FK → productos.id | |
| descripcion_personalizada | varchar(255) | | Ej. "Pastel de chocolate, dedicatoria: Feliz Cumpleaños Ana" |
| cantidad | int | NOT NULL | |
| precio_unitario | decimal(10,2) | NOT NULL | |
| subtotal | decimal(10,2) | NOT NULL | |

**`abonos_pedido`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| pedido_id | int | NOT NULL, FK → pedidos.id | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| monto | decimal(10,2) | NOT NULL | |
| metodo_pago | varchar(40) | NOT NULL | |
| fecha | timestamp | NOT NULL, default now() | |

**`historial_estados_pedido`** — auditoría del seguimiento de estado.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| pedido_id | int | NOT NULL, FK → pedidos.id | |
| usuario_id | int | NOT NULL, FK → usuarios.id | Quien realizó el cambio |
| estado_anterior | estado_pedido | | NULL si es el primer registro |
| estado_nuevo | estado_pedido | NOT NULL | |
| comentario | varchar(255) | | |
| fecha | timestamp | NOT NULL, default now() | |

**`tickets_pedido`** — ticket generado automáticamente.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| pedido_id | int | NOT NULL, FK → pedidos.id | |
| codigo_ticket | varchar(30) | NOT NULL, UNIQUE | Código único legible (ej. TK-2026-000123) |
| tipo | tipo_ticket | NOT NULL | confirmacion, entrega, rechazo |
| url_documento | varchar(255) | | Enlace al PDF generado |
| fecha_generacion | timestamp | NOT NULL, default now() | |

**`notificaciones_email`** — cola de correos al cliente.

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| pedido_id | int | FK → pedidos.id, NULL | |
| cliente_id | int | NOT NULL, FK → clientes.id | |
| tipo | tipo_notificacion_email | NOT NULL | pedido_aprobado, pedido_entregado, pedido_rechazado |
| destinatario_email | varchar(150) | NOT NULL | |
| asunto | varchar(150) | NOT NULL | |
| cuerpo | text | NOT NULL | |
| estado_envio | estado_envio_email | NOT NULL, default 'pendiente' | |
| intentos | int | NOT NULL, default 0 | Para reintentos ante fallo |
| fecha_envio | timestamp | | |

---

### 4.9 Promociones / Descuentos

**`promociones`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| nombre | varchar(120) | NOT NULL | |
| tipo | tipo_promocion | NOT NULL | descuento_volumen, dos_por_uno, happy_hour, cupon, campana_temporal |
| porcentaje_descuento | decimal(5,2) | | |
| monto_descuento | decimal(10,2) | | |
| fecha_inicio | date | NOT NULL | |
| fecha_fin | date | NOT NULL | |
| hora_inicio | time | | Para happy hour |
| hora_fin | time | | |
| estado | estado_general | NOT NULL, default activo | |

**`promocion_producto`** — tabla puente (N:M).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| promocion_id | int | NOT NULL, FK → promociones.id | |
| producto_id | int | NOT NULL, FK → productos.id | |
| — | | UNIQUE(promocion_id, producto_id) | |

**`cupones`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| promocion_id | int | NOT NULL, FK → promociones.id | |
| cliente_id | int | FK → clientes.id, NULL | Cupón nominal (opcional) |
| codigo | varchar(30) | NOT NULL, UNIQUE | |
| usos_maximos | int | NOT NULL, default 1 | |
| usos_actuales | int | NOT NULL, default 0 | |
| estado | estado_general | NOT NULL, default activo | |

---

### 4.10 Caja

**`caja`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| usuario_apertura_id | int | NOT NULL, FK → usuarios.id | |
| usuario_cierre_id | int | FK → usuarios.id, NULL | |
| fecha | date | NOT NULL | |
| hora_apertura | time | NOT NULL | |
| monto_inicial | decimal(10,2) | NOT NULL | |
| hora_cierre | time | | |
| monto_final_sistema | decimal(10,2) | | Calculado por el sistema |
| monto_final_fisico | decimal(10,2) | | Contado manualmente |
| diferencia | decimal(10,2) | | monto_final_fisico − monto_final_sistema |
| estado | estado_caja | NOT NULL, default 'abierta' | |

**`movimientos_caja`** — ingresos/retiros manuales (no ventas).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| caja_id | int | NOT NULL, FK → caja.id | |
| usuario_id | int | NOT NULL, FK → usuarios.id | |
| tipo | tipo_movimiento_caja | NOT NULL | ingreso_extra, retiro |
| monto | decimal(10,2) | NOT NULL | |
| motivo | varchar(255) | NOT NULL | |
| fecha | timestamp | NOT NULL, default now() | |

---

### 4.11 Ventas (POS)

**`ventas`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| caja_id | int | NOT NULL, FK → caja.id | Turno de caja en el que se registró |
| cliente_id | int | FK → clientes.id, NULL | Venta puede ser sin cliente identificado |
| empleado_id | int | NOT NULL, FK → empleados.id | Vendedor/cajero |
| promocion_id | int | FK → promociones.id, NULL | |
| fecha | timestamp | NOT NULL, default now() | |
| subtotal | decimal(10,2) | NOT NULL | |
| descuento_total | decimal(10,2) | NOT NULL, default 0 | |
| impuesto | decimal(10,2) | NOT NULL, default 0 | |
| total | decimal(10,2) | NOT NULL | |
| metodo_pago | varchar(40) | NOT NULL | Efectivo, tarjeta, transferencia |
| estado | estado_venta | NOT NULL, default 'completada' | |

**`detalle_venta`**

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| venta_id | int | NOT NULL, FK → ventas.id | |
| producto_id | int | NOT NULL, FK → productos.id | |
| cantidad | int | NOT NULL | |
| precio_unitario | decimal(10,2) | NOT NULL | |
| descuento | decimal(10,2) | NOT NULL, default 0 | |
| subtotal | decimal(10,2) | NOT NULL | |

---

### 4.12 Notificaciones Internas

**`notificaciones`** — alertas dentro del sistema (distintas de los correos a clientes).

| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| id | int | PK, auto | |
| usuario_destino_id | int | FK → usuarios.id, NULL | NULL = notificación general/broadcast |
| tipo | varchar(60) | NOT NULL | stock_bajo, pedido_listo, cumpleanos_cliente, pago_proveedor, resumen_ventas |
| titulo | varchar(120) | NOT NULL | |
| mensaje | varchar(255) | NOT NULL | |
| referencia_tipo | varchar(60) | | Ej. "pedido", "ingrediente" |
| referencia_id | int | | ID de la entidad relacionada |
| leido | boolean | NOT NULL, default false | |
| fecha_creacion | timestamp | NOT NULL, default now() | |

---

## 5. Relaciones y Cardinalidad

| Relación | Cardinalidad | Descripción |
|---|---|---|
| roles → usuarios | 1:N | Un rol puede tener muchos usuarios |
| empleados → usuarios | 1:N (opcional) | Un empleado puede o no tener cuenta de usuario |
| empleados → turnos / asistencias / comisiones / evaluaciones | 1:N | |
| clientes ↔ segmentos_clientes | N:M | vía `cliente_segmento` |
| categorias → productos | 1:N | |
| recetas → productos | 1:N (opcional) | Un producto puede no tener receta asociada (ej. reventa) |
| recetas ↔ ingredientes | N:M | vía `receta_ingredientes`, con cantidad |
| recetas → hornadas | 1:N | |
| hornadas → mermas_produccion | 1:N | |
| ingredientes → movimientos_inventario_insumos | 1:N | |
| productos → movimientos_inventario_productos | 1:N | |
| proveedores → ingredientes | 1:N (opcional, proveedor principal) | |
| proveedores → historial_precios_proveedor / ordenes_compra | 1:N | |
| ordenes_compra → detalle_orden_compra | 1:N | |
| ordenes_compra → recepciones_mercaderia | 1:N | |
| recepciones_mercaderia → detalle_recepcion | 1:N | |
| clientes → pedidos | 1:N | |
| pedidos → detalle_pedido / abonos_pedido / historial_estados_pedido / tickets_pedido / notificaciones_email | 1:N | |
| productos → detalle_pedido | 1:N | |
| promociones ↔ productos | N:M | vía `promocion_producto` |
| promociones → cupones | 1:N | |
| clientes → cupones | 1:N (opcional) | Cupón nominal |
| caja → movimientos_caja / ventas | 1:N | |
| clientes → ventas | 1:N (opcional) | Venta puede no tener cliente asociado |
| empleados → ventas | 1:N | |
| ventas → detalle_venta | 1:N | |
| productos → detalle_venta | 1:N | |
| usuarios → notificaciones | 1:N (opcional) | |

---

## 6. Estrategia de Indexación

| Tipo de índice | Aplicado en | Justificación |
|---|---|---|
| **Índice único** | `usuarios.email`, `usuarios.nombre_usuario`, `empleados.documento_identidad`, `clientes.email`, `categorias.nombre`, `ingredientes.nombre`, `tickets_pedido.codigo_ticket`, `cupones.codigo`, `roles.nombre` | Garantiza unicidad de identificadores de negocio y acelera búsquedas de login/validación. |
| **Índice simple en FK** | Todas las columnas `*_id` (ej. `cliente_id`, `producto_id`, `pedido_id`, `empleado_id`) | Optimiza los `JOIN` entre tablas relacionadas, operación más frecuente del sistema. |
| **Índice compuesto** | `(empleado_id, fecha)` en `turnos` y `asistencias`; `(apellido, nombre)` en `clientes` y `empleados`; `(proveedor_id, ingrediente_id, fecha)` en `historial_precios_proveedor`; `(fecha_inicio, fecha_fin)` en `promociones` | Acelera consultas de reportes y filtros combinados frecuentes en el Dashboard. |
| **Índice en campos de estado** | `estado` en `pedidos`, `ventas`, `caja`, `ordenes_compra`, `hornadas` | Los listados operativos filtran constantemente por estado (ej. "pedidos pendientes", "caja abierta"). |
| **Índice en campos de fecha** | `fecha` en `ventas`, `movimientos_inventario_*`, `hornadas`, `abonos_pedido`, `historial_estados_pedido` | Soporta reportes por rango de fechas y el Dashboard en tiempo real. |
| **Índice en `stock_actual`** | `ingredientes.stock_actual` | Acelera la consulta recurrente de "insumos por debajo del stock mínimo" para notificaciones. |

---

## 7. Reglas de Negocio a Nivel de Base de Datos

Estas reglas deben aplicarse mediante restricciones (`CHECK`, `NOT NULL`, triggers) o lógica de aplicación, según la capacidad del motor elegido:

1. **Motivo de rechazo obligatorio:** si `pedidos.estado = 'rechazado'`, el campo `motivo_rechazo` no puede ser nulo ni vacío.
2. **Generación automática de ticket:** al actualizar `pedidos.estado` a `aprobado`, `entregado` o `rechazado`, se debe insertar automáticamente un registro en `tickets_pedido` con el `tipo` correspondiente.
3. **Envío automático de correo:** los mismos cambios de estado del punto anterior deben generar un registro en `notificaciones_email` con `estado_envio = 'pendiente'`, para que el proceso de envío lo procese.
4. **Auditoría de estado:** cada `UPDATE` sobre `pedidos.estado` debe generar un registro correspondiente en `historial_estados_pedido` (vía trigger o lógica de aplicación transaccional).
5. **Actualización de inventario:** al insertar en `detalle_recepcion`, debe generarse un movimiento `entrada` en `movimientos_inventario_insumos` y sumarse a `ingredientes.stock_actual`.
6. **Descuento de inventario en ventas:** al insertar en `detalle_venta`, debe generarse un movimiento `salida` en `movimientos_inventario_productos` y restarse del stock del producto.
7. **Alerta de stock mínimo:** cuando `ingredientes.stock_actual < ingredientes.stock_minimo`, debe generarse un registro en `notificaciones` de tipo `stock_bajo`.
8. **Consistencia de saldo en pedidos:** `pedidos.saldo_pendiente` debe recalcularse automáticamente como `total − monto_abonado` cada vez que se inserta un registro en `abonos_pedido`.
9. **Cierre de caja:** `caja.diferencia` se calcula como `monto_final_fisico − monto_final_sistema` al momento del cierre; no debe permitirse una nueva apertura de caja si existe una caja en estado `abierta`.
10. **Vigencia de promociones:** una promoción solo debe aplicarse en el POS si la fecha/hora actual está dentro del rango `[fecha_inicio, fecha_fin]` y, si aplica, `[hora_inicio, hora_fin]`.

---

*Este documento describe la estructura de base de datos correspondiente a `panaderia-database.dbml`. Cualquier cambio en el diagrama debe reflejarse también aquí para mantener ambos artefactos sincronizados.*