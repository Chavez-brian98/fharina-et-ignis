# AGENTS.md

## Project Overview

PHP MVC bakery POS ("tiendita") running in Docker. Hand-rolled MVC — no framework. Code/URLs are in **English** (`products`, `categories`); DB table names are mixed Spanish per `doc/database.md`. The schema covers the full bakery design (roles, employees, clients, recipes, inventory, sales, orders) — the old `users` table was **merged into `empleados`** (auth: `username`/`email`/`password_hash`/`role_id`), no separate users table. Built so far: Products/Categories, Dashboard, Auth, Settings, **POS** (`/pos`, tickets PDF con mPDF), **Clientes** (`/clients`, tabla `clients`) y **Empleados** (`/employees`, tabla `empleados`, gestiona también las cuentas de acceso).

> **README.md is stale** — it describes an aspirational React/Laravel architecture. The real app is the plain-PHP MVC below; trust the code, not the README.

## Stack

- PHP 8.2 on **php-fpm** (`php:8.2-fpm`), MySQL 8.0, Composer deps `vlucas/phpdotenv` + **`mpdf/mpdf`** (tickets PDF del POS). Compose runs 3 services: `app` (php-fpm, builds image `fharina-et-ignis:1.0`), `web` (**nginx:alpine**, `8080:80`, proxies PHP to `app:9000`), `db` (mysql:8.0, `3306:3306`). The Dockerfile compiles `gd` + `mbstring` (needed by mPDF) — if you touch it, keep those extensions.
- Rewriting/Nginx lives in `docker/nginx/default.conf` (docroot `/var/www/html/public`). The legacy `src/public/.htaccess` still ships but is **inert under Nginx**.
- **Tailwind Play CDN** + Font Awesome CDN in `sidebar.php`; SweetAlert2 (jsDelivr) in `footer.php`. Toastify-js (CDN) shows flash messages as toasts. **ApexCharts (jsDelivr) in `sidebar.php` head** for the Dashboard charts. npm deps (`sweetalert2`, `flatpickr`, `sortablejs`, `toastify-js`, `animate.css`) are tracked in `node_modules/` but **not used at runtime** — no build step.

## Running

```bash
docker compose up --build
```

- Web: `http://localhost:8080` — containers are `fharina-et-ignis-app-1`, `fharina-et-ignis-web-1`, `fharina-et-ignis-db-1`.
- MySQL: `localhost:3306` — root/`1234`, database **`fharina_et_ignis`** (`MYSQL_DATABASE` in `docker-compose.yml`; compose also creates user `orellana`/`1234`). The `tiendita`/`glenda` names in older docs are stale — trust `src/.env` + `docker-compose.yml`.
- `.env` keys: `DB_SERVICE`, `DB_HOST` (=`db` inside Docker), `DB_NAME`, `DB_USER`, `DB_PASSWORD` (see `src/.env.example`; the real `src/.env` is gitignored). **`src/.env.example` is itself stale: `DB_NAME=tiendita`, real DB is `fharina_et_ignis`** — don't copy it verbatim.

> **`init.sql` only runs on first container boot.** Reapply schema: `docker compose down -v && docker compose up --build`. Live reload: `docker cp db/init.sql fharina-et-ignis-db-1:/tmp/init.sql` then `docker exec fharina-et-ignis-db-1 sh -c "mysql -uroot -p1234 fharina_et_ignis < /tmp/init.sql"`. On Windows, do NOT pipe via PowerShell (`Get-Content | docker exec` corrupts UTF-8) — always use `docker cp`. Files/images may not be UTF-8 stable when printed to the Windows console (`�`) — the stored data is fine.

## Routing & Layout

- Rewriting is done by **Nginx** (`docker/nginx/default.conf`): `location /` → `try_files $uri $uri/ /index.php?$query_string`, `location ~ \.php$` proxies to `app:9000`. The front controller (`index.php`) maps `controller/action/id` via `$controllerMap` and checks `method_exists`; **default controller is `dashboard`** — `/` renders the Dashboard (KPI cards + ApexCharts + low-stock table). The Apache-style `src/public/.htaccess` (`index.php?url=$1`) ships but is inert under Nginx.
- **Auth gate in `index.php`**: every route requires `$_SESSION['user']` except `auth` (login/logout); anonymous hits to anything else 302 to `/auth/login`. `AuthController::login` (GET=form, POST=verify) validates via `password_verify` against `empleados.password_hash` (no existe tabla `users`) and stashes the employee in session; `logout` destroys it. `src/views/auth/login.php` is the **only view that does NOT use the sidebar layout** (standalone full-screen page).
- Add routes by extending `$controllerMap`; new controllers/models must also be `require_once`d in `index.php` (no PSR-4 autoloader). Controllers additionally `require_once` their own model at the top.
- `esc()`, `url()`, `flash()` are globals defined in `index.php`; views depend on them — don't move. Globals also include: **`setting($key, $default)`** (reads the `settings` table, loaded once as `$GLOBALS['__settings']` for every request) and **`upload_image($field)`** (saves `$_FILES[$field]` to `public/uploads/`, returns `/uploads/name.ext` or `null` if no file / `false` on failure, flashing the error). Controllers build `enctype="multipart/form-data"` forms and use `upload_image()` for any image field.
- Layout: every view starts with `require __DIR__.'/../layouts/sidebar.php'` and ends with `footer.php` (the **only exception is `auth/login.php`**, a standalone full-page). `sidebar.php` is the whole HTML skeleton (`<head>`, CDNs, collapsible sidebar, opens `<main>`). Controllers set `$title`, `$currentModule`, `$breadcrumbs` before requiring the view.
- Actions per module: `index`, `create`, `store` (POST), `edit/{id}`, `update/{id}` (POST), `toggle/{id}`, `delete/{id}`. `store`/`update`/`toggle`/`delete` validate + `flash()` + `header('Location: ...')` + `exit` (no redirect helper).

## UI Conventions — apply to EVERY future CRUD (non-negotiable)

- **Tailwind** (not Bootstrap), white + **orange-500** accents, rounded-2xl cards, `shadow-lg shadow-gray-200/50`. Reference `products/index.php` + `create.php`.
- **All user-facing text in Spanish** (labels, buttons, flashes, SweetAlert, breadcrumbs). English only in code/URLs.
- **Status column = toggle switch** (`.btn-toggle`, same class as the row-action toggle): green "Activo"/gray "Inactivo" pill + sliding knob, carries `data-url`, `data-name`, `data-state`; SweetAlert confirms "¿Desactivar?"/"¿Activar?" before navigating to `toggle/{id}` (see `products/index.php`).
- **Breadcrumb starts at `Sistema` → `/`**, defined per-controller as `$breadcrumbs` (label + optional url); last crumb is the title.
- **Real-time search**: `#searchInput` + `<tbody id="crudTableBody">`; each row carries `data-search` (lowercased searchable text) and `data-status`, `data-category`, `data-lowstock` (`"1"` when `stock <= min_stock`). Wired in `public/js/main.js` `applyFilters()`; `#emptyState` shows when 0 rows.
- **Row actions** (right-aligned, `.btn-action` base class + a behavior class):
  - Eye = `.btn-detail` — opens `#detailModal`; carries `data-detail` (JSON of Spanish label→value pairs, built with `json_encode(..., JSON_UNESCAPED_UNICODE)`), `data-title`, `data-image` (optional photo URL), `data-icon` (Font Awesome placeholder when no image). Body: image left, info cards `ring-1 ring-gray-100` right.
  - Edit = link to `{module}/edit/{id}` (`fa-pen-to-square`).
  - **$btn-toggle** / **$btn-delete** buttons carry `data-url` + `data-name`; both go through SweetAlert confirm in `main.js` before navigating. Active→toggle / inactive→delete semantics come from `data-state`.
- **Barcode**: products store a `barcode` (unique, nullable) + `productos.barcode`. create/edit forms have a `.btn-scan-barcode` button inside the field that opens `#barcodeModal` (camera) using **Html5-QrCode** (CDN `html5-qrcode@2.3.8` in `footer.php`); scan fills `#barcode` and closes. Shared partial `src/views/partials/barcode_scanner.php` (modal + JS) included once per form page. `Product::findByBarcode` blocks duplicates (controller checks before create/update).
- **Sidebar collapse**: `#sidebarToggle` toggles `body.sidebar-collapsed` (state in `localStorage('sidebar-collapsed')`); CSS lives in `public/css/style.css`.
- **Custom CSS classes** (not Tailwind): `.form-label`, `.form-input`, `.btn-action`, `.modal-overlay`, `.modal-panel`, `.sidebar*`. Reuse them in new views.
- **`old($key, $default)`** form-repopulation helper is defined inline at the top of `create.php`/`edit.php` (guarded by `function_exists`), not a global.
- Flashes (success/error) are consumed in `breadcrumb.php`, rendered as a hidden `#flashToast` marker (`data-type` + `data-message`) that `main.js` turns into a **Toastify toast** (green/red, top-right). Never render inline alert boxes for flashes.

## Structure

```
src/
  public/index.php       Front controller: routing + esc()/url()/flash() + require_once of models/controllers
  public/.htaccess       Rewrite to index.php (skips real files) — inert, real rewrite is in nginx conf
  public/css|js/         style.css (custom classes), main.js (search/filter + modal + SweetAlert)
  config/Database.php    PDO via $_ENV (DB_SERVICE/DB_HOST/DB_NAME/DB_USER/DB_PASSWORD)
  controllers/        ProductController, CategoryController, DashboardController, AuthController, SettingsController, PosController, ClientController, EmployeeController (+ require their model)
  models/             Category, Product, Dashboard, User, Setting, Sale, Client, Employee (plain PDO classes)
  views/              layouts/ (sidebar, breadcrumb, footer) + auth/ (login, standalone) + dashboard/ + products/, categories/ (index/create/edit) + settings/ (index) + pos/ (index: cards + carrito + pago mixto) + clients/, employees/ (index/create/edit) + partials/ (barcode_scanner.php: modal + JS Html5-QrCode)
  public/uploads/     User-uploaded images (logo, login photo, product photos) served at /uploads/...; tracked only via .gitkeep
  .env                loaded by Dotenv::createImmutable(__DIR__.'/../') i.e. src/.env
db/init.sql           Full schema + seed (incl. demo ventas/pedidos/caja for the Dashboard); the ONLY schema source. MySQL-adapted from doc/database.md. Seed: 3 empleados con cuenta de acceso (admin `admin@ignis.com`, `maria.gonzalez@bakery.com`, `carlos.ramirez@bakery.com`), todos con contraseña **`password`** (hash bcrypt de "password"); las credenciales viven en `empleados.username/email/password_hash/role_id`. Seeds `settings` rows (system/business name, contact, tax_rate, ticket_footer, logo/login photo) y promociones con `promotion_product` (descuentos que muestra el POS).
doc/                 database.md (canonical design, PostgreSQL-flavored), Documentacion.md
docker/Dockerfile    php:8.2-fpm + pdo_mysql + mbstring + gd (+ composer + node); gd/mbstring son requeridas por mPDF
docker/nginx/default.conf Nginx docroot /var/www/html/public; proxies PHP to app:9000
```

## DB Conventions

- Spanish legacy table names in the "PT" bakery domain: `categorias`, `productos`, `recetas`, `ingredientes`, `proveedores`, `pedidos`, `promociones`, `ventas`, `caja`. New/English tables: `roles`, `clients`, `shifts`, `attendances`, etc. **Check names before writing SQL — no single naming rule.** La tabla `users` NO existe: `empleados` lleva las credenciales de login (`username`/`email` UNIQUE NULL, `password_hash`, `role_id` FK→`roles`) con un CHECK que exige las 4 juntas o ninguna (`chk_empleados_login`).
- Columns are English: `name`, `sale_price`, `production_cost`, `stock`, `min_stock`, `display_order`, `status ENUM('active','inactive')`; FK is always `category_id` → `categorias.id`.
- `Category::countProducts` (blocks category deletes with products) reads `productos.category_id`.

## Gotchas

- **Nginx routing gotcha**: `index.php` reads `$_GET['url']` (Apache set it via `index.php?url=$1`). The nginx `try_files $uri $uri/ /index.php?$query_string` does **not** populate `url` (everything falls to the `dashboard` default → auth redirect loop). The fix is implemented in `docker/nginx/default.conf`: `location @rewrite` does `rewrite ^/(.*)$ /index.php?url=$1 last;` with `try_files $uri $uri/ @rewrite` — keep that `?url=` mapping when touching the nginx config.
- No autoloader: every new controller/model must be `require_once`d in `src/public/index.php` (controllers also require their model). `vendor/`/`node_modules/` are host-mounted and committed; install locally in `src/` if missing.
- `db/init.sql` starts with `SET NAMES utf8mb4` (keep it) or Spanish accents double-encode.
- `src/.env.example` is stale (`DB_NAME=tiendita`); the real DB is `fharina_et_ignis` — copy from `src/.env`, not the example.
- Sidebar has placeholder links (classes `sidebar-anchor`): `#users`, `#notifications`, `#inventory`, `#production`, `#suppliers`, `#orders`, `#promotions`, `#cash-register`, `#reports`, `#statistics` (all 13 modules from `doc/Documentacion.md` §5). Only Dashboard (`/dashboard`), Products, Categories, Settings (`/settings`, edita la tabla `settings` via `SettingsController`), **POS (`/pos`)**, **Clientes** (`/clients`, tabla `clients`) y **Empleados** (`/employees`, tabla `empleados`) are real. ApexCharts CDN is loaded globally in `sidebar.php` `<head>` — don't duplicate it.
- Image uploads go through the `upload_image()` global to `public/uploads/` (gitignored). Server-side: real-image check via `getimagesize`, whitelist JPG/PNG/WEBP/GIF, 2 MB cap. Set `name` to a key that's meaningful (`image_file`, `system_logo`, `login_photo`). On `false` the controller must redirect back (error flash already set). Keep the previous value with a hidden `name="image_url"` on edit forms (products) or file leave-empty (settings).
- **POS**: `Sale::getCatalog()` returns active products + `category_name` + `discount_percent` (descuento vigente vía `promociones`/`promotion_product`) + `final_price`. `Sale::createSale()` valida stock/pago en transacción e inserta `ventas` + `sale_details` + `sale_payments` (pago mixto). `ventas.cash_register_id`/`employee_id` son NULLables (sin módulo de caja/empleados formal aún). El ticket se genera en `PosController::ticket` con mPDF (72 mm, `dejavusans`); el número es el id padded a 6 dígitos. El JS del POS es inline en `views/pos/index.php` (cards `.pos-card` con `data-search`/`data-category`/`data-stock`/`data-discount` + `data-barcode`; escaneo por cámara reutilizando el hook `window.onBarcodeDetected` del partial `barcode_scanner.php`).
- **Clientes/Empleados**: CRUD estándar sobre `clients` y `empleados` (tabla mixta ES). Validan unicidad (correo / DUI). El `delete` de clientes es transaccional (limpia `client_segment`, pone `cupones.client_id=NULL`); el de empleados es un `DELETE` directo — si hay FKs duras (turnos/asistencias/ventas/pedidos/caja) falla → flash de error. El módulo Empleados gestiona las cuentas de acceso: campos `username`/`email`/`password`/`role_id` (en create la contraseña es obligatoria solo si se crea cuenta; en edit en blanco = mantener la actual); resuelve vía `resolveLogin()` en el controller, hash bcrypt con `password_hash()`.
- Views reference assets by absolute `/css/...`, `/js/...` (webroot = `public/`).