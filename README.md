# 🥖 Sistema de Gestión de Ventas para Panadería

Sistema integral para la administración de una panadería: punto de venta, caja, inventario de materias primas, producción (recetas y hornadas), empleados, clientes, proveedores, pedidos personalizados con seguimiento de estado, promociones, dashboard de indicadores y notificaciones automáticas.

---

## 📋 Tabla de Contenidos

1. [¿Qué hace el sistema?](#-qué-hace-el-sistema)
2. [Módulos incluidos](#-módulos-incluidos)
3. [Stack tecnológico](#-stack-tecnológico)
4. [Requisitos previos](#-requisitos-previos)
5. [Instalación](#-instalación)
6. [Configuración del entorno (.env)](#-configuración-del-entorno-env)
7. [Base de datos](#-base-de-datos)
8. [Cómo iniciar el sistema](#-cómo-iniciar-el-sistema)
9. [Estructura del proyecto](#-estructura-del-proyecto)
10. [Roles de usuario](#-roles-de-usuario)
11. [Documentación adicional](#-documentación-adicional)

---

## 🎯 ¿Qué hace el sistema?

Este sistema centraliza en una sola plataforma todos los procesos operativos y administrativos de una panadería, reemplazando cuadernos, hojas de cálculo y sistemas de venta genéricos que no se adaptan al rubro. Permite:

- Cobrar ventas en mostrador de forma ágil (**POS**), aplicando descuentos y promociones vigentes automáticamente.
- Controlar el efectivo diario mediante **apertura, cierre y arqueo de caja**.
- Mantener trazabilidad completa del **inventario** de materias primas y productos terminados, con alertas automáticas de stock bajo.
- Planificar la **producción** (recetas, hornadas, tiempos de reposo/horneado) y registrar mermas.
- Administrar **empleados** (turnos, asistencia, sueldos, comisiones, evaluaciones de rendimiento).
- Mantener una base de **clientes** con historial de compras y segmentación para campañas.
- Gestionar **proveedores**, historial de precios, órdenes de compra y recepción de mercadería con actualización automática del inventario.
- Administrar **pedidos personalizados** (pasteles, roscas, catering) con **seguimiento de estado** (pendiente → aprobado → en producción → listo → entregado, o rechazado con motivo), generando **tickets automáticos** y enviando **correos electrónicos** al cliente en cada hito clave del pedido.
- Visualizar el estado del negocio en tiempo real mediante un **dashboard** con ventas, productos más vendidos, mermas, stock crítico y rendimiento del personal.
- Crear **promociones y descuentos** (2x1, happy hour, cupones, campañas temporales).
- Recibir **notificaciones automáticas** de eventos relevantes: stock bajo, vencimientos, pedidos listos, cumpleaños de clientes, pagos pendientes a proveedores y resumen diario de ventas.

---

## 🧩 Módulos incluidos

| # | Módulo | Función principal |
|---|---|---|
| 1 | POS | Punto de venta, cobros, descuentos, tickets |
| 2 | Caja | Apertura, cierre, arqueo, movimientos manuales |
| 3 | Inventario | Control de materias primas y productos terminados |
| 4 | Empleados | Personal, turnos, sueldos, comisiones, evaluaciones |
| 5 | Clientes | Historial de compras, segmentación, fechas especiales |
| 6 | Categorías | Clasificación del catálogo de productos |
| 7 | Productos | Fichas de producto, receta, costo y precio |
| 8 | Producción | Recetas, hornadas, tiempos, mermas |
| 9 | Proveedores | Catálogo, precios, órdenes de compra, recepción |
| 10 | Pedidos | Pedidos personalizados con seguimiento de estado, tickets y correos automáticos |
| 11 | Dashboard | Indicadores clave en tiempo real |
| 12 | Promociones / Descuentos | Ofertas, cupones, campañas |
| 13 | Notificaciones | Alertas automáticas del sistema |

> Documentación funcional completa en [`documentacion-sistema-panaderia.md`](./documentacion-sistema-panaderia.md).

---

## ⚙️ Stack Tecnológico

| Capa | Tecnología |
|---|---|
| Frontend | React.js + TailwindCSS |
| Backend / API | Laravel (PHP) o Node.js (Express/NestJS) |
| Base de datos | PostgreSQL / MySQL |
| Autenticación | JWT / Laravel Sanctum |
| Tiempo real | Laravel Echo / Socket.io (notificaciones) |
| Correo electrónico | SMTP / servicio transaccional (ej. Mailgun, SendGrid) |
| Librerías UI | SweetAlert2, Toastify.js, Flatpickr, Sortable.js, Animate.css, Chart.js/Recharts |

> Detalle completo del stack y librerías en la sección 7 de [`documentacion-sistema-panaderia.md`](./documentacion-sistema-panaderia.md).

---

## ✅ Requisitos Previos

Antes de instalar el sistema, asegúrate de tener instalado:

- **Node.js** `>= 18.x` y **npm** o **yarn** (frontend, y backend si es Node.js)
- **PHP** `>= 8.2` y **Composer** (solo si el backend es Laravel)
- **PostgreSQL** `>= 14` o **MySQL** `>= 8` (motor de base de datos)
- **Git**
- (Opcional) **Docker** y **Docker Compose**, si se desea levantar el entorno en contenedores

---

## 📦 Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/tu-organizacion/sistema-panaderia.git
cd sistema-panaderia
```

### 2. Instalar dependencias del Backend

**Si el backend es Laravel:**

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
```

**Si el backend es Node.js:**

```bash
cd backend
npm install
cp .env.example .env
```

### 3. Instalar dependencias del Frontend

```bash
cd frontend
npm install
```

---

## 🔐 Configuración del Entorno (`.env`)

Configura las variables de entorno en el archivo `.env` del backend:

```env
# Aplicación
APP_NAME="Sistema Panaderia"
APP_ENV=local
APP_URL=http://localhost:8000

# Base de datos
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=panaderia_db
DB_USERNAME=postgres
DB_PASSWORD=tu_password

# Autenticación
JWT_SECRET=coloca_una_clave_segura

# Correo electrónico (para notificaciones de pedidos)
MAIL_MAILER=smtp
MAIL_HOST=smtp.tuservicio.com
MAIL_PORT=587
MAIL_USERNAME=tu_usuario
MAIL_PASSWORD=tu_password
MAIL_FROM_ADDRESS=notificaciones@tupanaderia.com
MAIL_FROM_NAME="${APP_NAME}"

# Notificaciones en tiempo real (opcional)
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
```

En el frontend, configura la URL del API en su propio `.env`:

```env
VITE_API_URL=http://localhost:8000/api
```

---

## 🗄️ Base de Datos

El diseño completo de la base de datos (diccionario de datos, relaciones, cardinalidad e indexación) está documentado en [`database.md`](./database.md), y el diagrama fuente en [`panaderia-database.dbml`](./panaderia-database.dbml) (importable en [dbdiagram.io](https://dbdiagram.io)).

### Crear la base de datos

```sql
CREATE DATABASE panaderia_db;
```

### Ejecutar migraciones

**Laravel:**

```bash
php artisan migrate
```

**Node.js (ej. con Knex o Prisma):**

```bash
npx prisma migrate dev
# o
npx knex migrate:latest
```

### (Opcional) Cargar datos de prueba (seeders)

```bash
php artisan db:seed
# o
npx prisma db seed
```

---

## 🚀 Cómo Iniciar el Sistema

### 1. Iniciar el Backend

**Laravel:**

```bash
cd backend
php artisan serve
```

El API quedará disponible en `http://localhost:8000`.

**Node.js:**

```bash
cd backend
npm run dev
```

### 2. Iniciar el Frontend

```bash
cd frontend
npm run dev
```

La aplicación quedará disponible en `http://localhost:5173` (o el puerto que indique la consola).

### 3. (Opcional) Iniciar con Docker

Si el proyecto incluye `docker-compose.yml`:

```bash
docker-compose up -d --build
```

Esto levantará automáticamente el backend, frontend y la base de datos en contenedores.

### 4. Verificar que todo funcione

- Backend/API: `http://localhost:8000/api/health` (o el endpoint de verificación configurado)
- Frontend: `http://localhost:5173`
- Iniciar sesión con el usuario administrador creado por el seeder inicial

---

## 📁 Estructura del Proyecto

```
sistema-panaderia/
├── backend/                 # API (Laravel o Node.js)
│   ├── app/ | src/           # Lógica de negocio, controladores, modelos
│   ├── database/             # Migraciones y seeders
│   ├── routes/                # Definición de endpoints
│   └── .env
├── frontend/                # Aplicación React
│   ├── src/
│   │   ├── components/       # Componentes reutilizables (POS, Dashboard, etc.)
│   │   ├── pages/             # Vistas por módulo
│   │   └── services/          # Consumo del API
│   └── .env
├── documentacion-sistema-panaderia.md   # Documentación funcional completa
├── database.md                          # Diccionario de datos y diseño de BD
├── panaderia-database.dbml              # Diagrama de base de datos (dbdiagram.io)
└── README.md                            # Este archivo
```

---

## 👥 Roles de Usuario

| Rol | Acceso principal |
|---|---|
| Administrador / Propietario | Acceso total: reportes, configuración, gestión financiera |
| Cajero / Vendedor | Módulo POS y Caja |
| Encargado de producción / Panadero | Módulo Producción (recetas, hornadas, mermas) |
| Encargado de inventario / Compras | Inventario y Proveedores |
| Recursos Humanos | Módulo Empleados |

---

## 📚 Documentación Adicional

- **Documentación funcional completa** (introducción, objetivos, problemática, requerimientos, historias de usuario): [`documentacion-sistema-panaderia.md`](./documentacion-sistema-panaderia.md)
- **Diseño de base de datos** (diccionario de datos, relaciones, indexación, reglas de negocio): [`database.md`](./database.md)
- **Diagrama de base de datos** (código DBML para dbdiagram.io): [`panaderia-database.dbml`](./panaderia-database.dbml)

---

*Sistema desarrollado para digitalizar y optimizar la operación diaria de una panadería, desde la producción hasta la venta y atención al cliente.*