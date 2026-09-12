# AGENTS.md

## Project Overview

PHP MVC app ("tiendita" / bakery POS) running in Docker. Hand-rolled MVC — no framework. Code/URLs are in **English** (`products`, `categories`); DB table names are mixed Spanish (`categorias`, `productos`, `empleados`) per `database.md`. Schema is the full bakery design from `database.md` (roles, users, employees, clients, recipes, inventory, sales, orders, etc.), adapted to MySQL.

## Stack

- PHP 8.2 on Apache (`php:8.2-apache`), MySQL 8.0
- Composer dep: `vlucas/phpdotenv`. npm dep: `sweetalert2` (also loaded via CDN in footer). **Tailwind CSS via Play CDN** (`cdn.tailwindcss.com`) in `header.php` + Font Awesome CDN — no build step.
- Apache `mod_rewrite` enabled, document root `/var/www/html/public`. `AllowOverride All` comes from the image's baked-in `docker-php.conf`
- Docker image name: `fharina-et-ignis:1.0`

## Running

```bash
docker compose up --build
```

- Web: `http://localhost:8080`
- MySQL: `localhost:3306` — root/`1234`, database `tiendita`, user `glenda`/`1234`

> **`init.sql` only runs on first container boot.** To reapply schema changes: `docker compose down -v && docker compose up --build`. To apply to a running container: `docker cp db/init.sql mvc-db-1:/tmp/init.sql` then `docker exec mvc-db-1 sh -c "mysql -uroot -p1234 tiendita < /tmp/init.sql"`.

## Routing

`src/public/.htaccess` rewrites everything to `index.php?url=$1`. The front controller (`src/public/index.php`) parses `controller/action/id`. Add new routes by extending `$controllerMap` in `index.php`. URL helper: `url('products')` = `/products`.

- `products` / `categories`: `index`, `create`, `store` (POST), `edit/{id}`, `update/{id}` (POST), `toggle/{id}`, `delete/{id}`. `toggle` flips `status` active↔inactive.

## UI Conventions — apply to EVERY future CRUD (non-negotiable)

- **Tailwind UI** (not Bootstrap). Palette: white background + **orange** (`orange-500`/`#f97316`) accents. Rounded-2xl cards, shadow-sm, orange buttons. See `products/index.php` + `create.php` as reference.
- **All user-facing text in Spanish** (labels, buttons, flashes, SweetAlert, titles, breadcrumbs). English only in code identifiers/URLs.
- **Breadcrumb starts at `Sistema`** → `/` (dashboard), then module, then action. Defined per-controller as `$breadcrumbs`.
- **Real-time search bar**: `#searchInput` + table `<tbody id="crudTableBody">`, each row has `data-search` (lowercased concatenated searchable text). Bound in `public/js/main.js` `applyFilters()`.
- **Filters**: rows carry `data-status`, `data-category`, `data-lowstock` ("1" when `stock <= min_stock`). Filter controls use class `search-filter` + `#filterCategory` / `#filterStatus` / `#filterLowStock`. Add more by adding data attributes + JS conditions.
- **Row actions** (right-aligned, `.btn-action`):
  - Eye = detail **modal** (`#detailModal`, blur backdrop via `.modal-overlay`); button carries `data-detail` = JSON of label→value pairs, plus `data-title` (name), `data-image` (photo URL, optional) and `data-icon` (Font Awesome placeholder when no image). The modal body is a two-column layout: **image top-left, info to the right** (`#detailModalTitle` shows the name). Info fields render as small cards with `ring-1 ring-gray-100`.
  - Edit = link to `{module}/edit/{id}` (`fa-pen-to-square`).
  - **Active** row → toggle-off icon (`btn-toggle`, `data-url` + `data-name`) → `toggle/{id}` (SweetAlert confirm "¿Desactivar?").
  - **Inactive** row → trash icon (`btn-delete`) → `delete/{id}` (SweetAlert confirm "¿Eliminar?", then hard delete). Delete physically removes the record.
- **Sidebar is collapsible**: `#sidebarToggle` toggles `body.sidebar-collapsed` (icons-only via CSS in `style.css`, hides `.sidebar-text`/section titles); state persists in `localStorage('sidebar-collapsed')`. Link tooltips come from `title` attributes.
- **Layout depth**: `main` is full-width (`min-w-0 w-full`, no max-width), body is `bg-gray-50`, cards/tables use `shadow-lg`+`shadow-gray-200/50`, primary buttons `shadow-lg shadow-orange-500/25`, inputs are white with `shadow-sm`.
- **Status column** always present: green "Activo" / gray "Inactivo" badge; low-stock shows a red badge with the count.
- **Flashes** (success/error) rendered in `breadcrumb.php` via `flash('success'|'error')` — always Spanish.

## Structure

```
src/
  public/index.php       Front controller + routing + esc()/url()/flash() helpers
  public/.htaccess       Rewrite to index.php (skips real files)
  public/css|js/         Tailwind-helper CSS (sidebar, modal blur), real-time search + SweetAlert JS
  config/Database.php    PDO connection via $_ENV
  controllers/           ProductController, CategoryController (no construct typo)
  models/                Product, Category — plain PDO classes
  views/
    layouts/             header.php (sidebar), footer.php, breadcrumb.php (flashes)
    products/            index.php, create.php, edit.php
    categories/          index.php, create.php, edit.php
  vendor/ node_modules/  Committed deps (host-mounted, no Docker install)
  .env                   DB config, loaded by Dotenv::createImmutable(__DIR__.'/../')
db/
  init.sql               Full bakery schema (database.md) + seed; SET NAMES utf8mb4 required
docker/
  Dockerfile             php:8.2-apache, installs Composer, Node 20, pdo_mysql
database.md              Canonical DB design doc (PostgreSQL-flavored) — source of truth for schema
```

## DB Conventions (from database.md, MySQL-adapted)

- "PT" tables keep Spanish names: `categorias`, `productos`, `empleados`, `recetas`, `ingredientes`, `proveedores`, `pedidos`, `promociones`, `ventas`, `clientes`→`clients` (English), `caja`. New/English-named tables: `roles`, `users`, `clients`, `shifts`, `attendances`, etc. **Check names before writing SQL — no single naming rule.**
- Columns are **English** when the feature is English: `name`, `sale_price`, `production_cost`, `stock`, `min_stock`, `display_order`, `status` (enum `active`/`inactive`). Spanish leftovers remain in legacy tables (`categoria_id` is used everywhere → FK from `categorias.id`).
- `products.status`, `categories.status` are `ENUM('active','inactive')`. `productos` also has `stock` + `min_stock` (low-stock flag = `stock <= min_stock`).
- Deleting a category with products is blocked in `CategoryController::delete` (checks `countProducts`).

## Gotchas

- **No Composer PSR-4 autoloader** — explicit `require_once` everywhere. New controller/model files must be `require_once`d in `src/public/index.php`.
- **`esc()`, `url()`, `flash()` are defined in `index.php`** as global functions — views depend on them. Don't move them without updating all views.
- **Form helpers** `.form-label`/`.form-input` and `.btn-action`/`.modal-overlay` are custom CSS in `public/css/style.css` — not Tailwind utilities. Reuse them in new CRUD views.
- **Tailwind is the Play CDN** (runtime, no build). Fine for dev; lock utilities to standard `orange-*`/`gray-*` scale so a compiled build works later.
- **Flash messages** live in `$_SESSION`; `breadcrumb.php` renders `success`/`error` alerts. Flashes are consumed on the redirect target.
- **`vendor/`/`node_modules/` are host-mounted** (`./src:/var/www/html`). No install runs inside Docker — run `composer install`/`npm install` locally in `src/` if missing.
- **`sky: .htaccess` must be served** — load assets via `/css/...`, `/js/...` absolute paths (webroot is `public/`).
- **When importing init.sql manually, use `SET NAMES utf8mb4`** (already in file) or Spanish accents get double-encoded. On Windows, pipe via `docker cp` + `docker exec ... sh -c "mysql ... < /tmp/init.sql"` — PowerShell `Get-Content | docker exec` corrupts UTF-8.
- **Sidebar has anchor placeholders** (`#dashboard`, `#sales`, etc.) for modules not yet built — only Products and Categories are real.