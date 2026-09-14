# AGENTS.md

## Project Overview

PHP MVC bakery POS ("tiendita") running in Docker. Hand-rolled MVC — no framework. Code/URLs are in **English** (`products`, `categories`); DB table names are mixed Spanish per `doc/database.md`. The schema covers the full bakery design (roles, users, employees, clients, recipes, inventory, sales, orders), only Products/Categories are built so far.

> **README.md is stale** — it describes an aspirational React/Laravel architecture. The real app is the plain-PHP MVC below; trust the code, not the README.

## Stack

- PHP 8.2 on Apache (`php:8.2-apache`), MySQL 8.0, Composer dep `vlucas/phpdotenv`
- **Tailwind Play CDN** + Font Awesome CDN in `sidebar.php`; SweetAlert2 (jsDelivr) in `footer.php`. Toastify-js (CDN) shows flash messages as toasts. **ApexCharts (jsDelivr) in `sidebar.php` head** for the Dashboard charts. npm deps (`sweetalert2`, `flatpickr`, `sortablejs`, `toastify-js`, `animate.css`) are tracked in `node_modules/` but **not used at runtime** — no build step.
- Document root `/var/www/html/public` (`docker/Dockerfile`), `mod_rewrite` on, `./src` host-mounted.
- Image name `fharina-et-ignis:1.0`.

## Running

```bash
docker compose up --build
```

- Web: `http://localhost:8080`
- MySQL: `localhost:3306` — root/`1234`, database **`fharina_et_ignis`** (`MYSQL_DATABASE` in `docker-compose.yml`; compose also creates user `orellana`/`1234`). The `tiendita`/`glenda` names in older docs are stale — trust `src/.env` + `docker-compose.yml`.
- `.env` keys: `DB_SERVICE`, `DB_HOST` (=`db` inside Docker), `DB_NAME`, `DB_USER`, `DB_PASSWORD` (see `src/.env.example`; the real `src/.env` is gitignored).

> **`init.sql` only runs on first container boot.** Reapply schema: `docker compose down -v && docker compose up --build`. Live reload: `docker cp db/init.sql fharina-et-ignis-db-1:/tmp/init.sql` then `docker exec fharina-et-ignis-db-1 sh -c "mysql -uroot -p1234 fharina_et_ignis < /tmp/init.sql"`. On Windows, do NOT pipe via PowerShell (`Get-Content | docker exec` corrupts UTF-8) — always use `docker cp`. Files/images may not be UTF-8 stable when printed to the Windows console (`�`) — the stored data is fine.

## Routing & Layout

- `src/public/.htaccess` rewrites all to `index.php?url=$1`. Front controller (`index.php`) maps `controller/action/id` via `$controllerMap` and checks `method_exists`; **default controller is `dashboard`** — `/` renders the Dashboard (KPI cards + ApexCharts + low-stock table).
- **Auth gate in `index.php`**: every route requires `$_SESSION['user']` except `auth` (login/logout); anonymous hits to anything else 302 to `/auth/login`. `AuthController::login` (GET=form, POST=verify) validates via `password_verify` against `users.password_hash` and stashes the user in session; `logout` destroys it. `src/views/auth/login.php` is the **only view that does NOT use the sidebar layout** (standalone full-screen page).
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
- **Barcode barcode**: products store a `barcode` (unique, nullable) + `productos.barcode`. create/edit forms have a `.btn-scan-barcode` button inside the field that opens `#barcodeModal` (camera) using **Html5-QrCode** (CDN `html5-qrcode@2.3.8` in `footer.php`); scan fills `#barcode` and closes. Shared partial `src/views/partials/barcode_scanner.php` (modal + JS) included once per form page. `Product::findByBarcode` blocks duplicates (controller checks before create/update).
- **Sidebar collapse**: `#sidebarToggle` toggles `body.sidebar-collapsed` (state in `localStorage('sidebar-collapsed')`); CSS lives in `public/css/style.css`.
- **Custom CSS classes** (not Tailwind): `.form-label`, `.form-input`, `.btn-action`, `.modal-overlay`, `.modal-panel`, `.sidebar*`. Reuse them in new views.
- **`old($key, $default)`** form-repopulation helper is defined inline at the top of `create.php`/`edit.php` (guarded by `function_exists`), not a global.
- Flashes (success/error) are consumed in `breadcrumb.php`, rendered as a hidden `#flashToast` marker (`data-type` + `data-message`) that `main.js` turns into a **Toastify toast** (green/red, top-right). Never render inline alert boxes for flashes.

## Structure

```
src/
  public/index.php       Front controller: routing + esc()/url()/flash() + require_once of models/controllers
  public/.htaccess       Rewrite to index.php (skips real files)
  public/css|js/         style.css (custom classes), main.js (search/filter + modal + SweetAlert)
  config/Database.php    PDO via $_ENV (DB_SERVICE/DB_HOST/DB_NAME/DB_USER/DB_PASSWORD)
  controllers/           ProductController, CategoryController, DashboardController, AuthController, SettingsController (+ require their model)
  models/                Category, Product, Dashboard, User, Setting (plain PDO classes)
  views/                 layouts/ (sidebar, breadcrumb, footer) + auth/ (login, standalone) + dashboard/ + products/, categories/ (index/create/edit) + settings/ (index) + partials/ (barcode_scanner.php: modal + JS Html5-QrCode)
  public/uploads/        User-uploaded images (logo, login photo, product photos) served at /uploads/...; tracked only via .gitkeep
  .env                   loaded by Dotenv::createImmutable(__DIR__.'/../') i.e. src/.env
db/init.sql              Full schema + seed (incl. demo ventas/pedidos/caja for the Dashboard); the ONLY schema source. MySQL-adapted from doc/database.md. Seed users `admin@bakery.com`, `maria.gonzalez@bakery.com`, `carlos.ramirez@bakery.com` all log in with password **`password`**. Seeds `settings` rows (system/business name, contact, tax_rate, ticket_footer, logo/login photo).
doc/                     database.md (canonical design, PostgreSQL-flavored), Documentacion.md
docker/Dockerfile        php:8.2-apache + apache mod_rewrite + docroot /var/www/html/public
```

## DB Conventions

- Spanish legacy table names in the "PT" bakery domain: `categorias`, `productos`, `recetas`, `ingredientes`, `proveedores`, `pedidos`, `promociones`, `ventas`, `caja`. New/English tables: `roles`, `users`, `clients`, `shifts`, `attendances`, etc. **Check names before writing SQL — no single naming rule.**
- Columns are English: `name`, `sale_price`, `production_cost`, `stock`, `min_stock`, `display_order`, `status ENUM('active','inactive')`; FK is always `category_id` → `categorias.id`.
- `Category::countProducts` (blocks category deletes with products) reads `productos.category_id`.

## Gotchas

- No autoloader: every new controller/model must be `require_once`d in `src/public/index.php` (controllers also require their model). `vendor/`/`node_modules/` are host-mounted and committed; install locally in `src/` if missing.
- `db/init.sql` starts with `SET NAMES utf8mb4` (keep it) or Spanish accents double-encode.
- Sidebar has placeholder links (classes `sidebar-anchor`): `#employees`, `#users`, `#notifications`, `#inventory`, `#production`, `#suppliers`, `#orders`, `#promotions`, `#cash-register`, `#sales`, `#clients`, `#reports`, `#statistics` (all 13 modules from `doc/Documentacion.md` §5). Only Dashboard (`/dashboard`), Products, Categories and Settings (`/settings`, edita la tabla `settings` via `SettingsController`) are real. ApexCharts CDN is loaded globally in `sidebar.php` `<head>` — don't duplicate it.
- Image uploads go through the `upload_image()` global to `public/uploads/` (gitignored). Server-side: real-image check via `getimagesize`, whitelist JPG/PNG/WEBP/GIF, 2 MB cap. Set `name` to a key that's meaningful (`image_file`, `system_logo`, `login_photo`). On `false` the controller must redirect back (error flash already set). Keep the previous value with a hidden `name="image_url"` on edit forms (products) or file leave-empty (settings).
- Views reference assets by absolute `/css/...`, `/js/...` (webroot = `public/`).