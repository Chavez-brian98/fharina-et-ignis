# AGENTS.md

## Project Overview

Hand-rolled PHP MVC bakery POS ("tiendita") in Docker — **no framework**. Code/URLs in **English** (`products`, `categories`); DB table names are mixed Spanish per `doc/database.md`. Built: public website (`/`), Auth, Dashboard, Products, Categories, Settings, POS (`/pos` + mPDF tickets), Clientes, Empleados. `doc/database.md` describes the full design (recipes, inventory, orders) — most of those tables exist but have no module yet.

> **README.md is stale** — it describes an aspirational React/Laravel architecture. Trust the code.

## Stack

- PHP 8.2 on `php-fpm` (`php:8.2-fpm`), MySQL 8.0, Composer deps `vlucas/phpdotenv` + `mpdf/mpdf`. Three compose services: `app` (php-fpm, image `fharina-et-ignis:1.0`), `web` (**nginx:alpine**, `80:80` + `443:443`, proxies PHP to `app:9000`), `db` (mysql:8.0, `3306:3306`). Both `app` and `web` bind-mount `./src:/var/www/html`, so PHP code changes are live — **no rebuild needed for code edits**, only for Dockerfile changes.
- **The Dockerfile never runs `composer install`** — `src/vendor/` and `src/node_modules/` are committed to git. Adding a Composer dependency means running composer in `src/` and committing `vendor/`.
- Dockerfile compiles `gd` + `mbstring` (required by mPDF) — keep them if you touch it.
- Rewriting lives in `docker/nginx/default.conf` (docroot `/var/www/html/public`). `src/public/.htaccess` still ships but is **inert under Nginx**.
- **Tailwind Play CDN** + Font Awesome + **ApexCharts** in `sidebar.php`; SweetAlert2 + Html5-QrCode in `footer.php`; Toastify shows flashes. npm deps in `package.json` are **not used at runtime** (CDNs are) — no build step.

## Setup / Running

```bash
# 1. REQUIRED FIRST: SSL certs. certs/ is gitignored and absent from the repo.
#    Without them the `web` container hard-fails:
#    [emerg] cannot load certificate "/etc/nginx/ssl/server.crt"
mkdir -p certs
openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
  -keyout certs/server.key -out certs/server.crt \
  -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost"

# 2. REQUIRED: src/.env does NOT exist in the tree. Create it —
#    .env.example is stale (DB_NAME=tiendita, password placeholder).
#    Correct values: DB_NAME=fharina_et_ignis, DB_PASSWORD=1234, DB_USER=root, DB_HOST=db
cp src/.env.example src/.env   # then edit the two values above

# 3. Run
docker compose up --build
```

- Web: `http://localhost` and `https://localhost`. MySQL: `localhost:3306`, root/`1234`, DB `fharina_et_ignis` (compose also creates `orellana`/`1234`). Containers: `fharina-et-ignis-app-1`, `-web-1`, `-db-1`.
- Seeded logins (all password **`password`**): `admin@ignis.com`, `maria.gonzalez@bakery.com`, `carlos.ramirez@bakery.com`. They live in `empleados.username/email/password_hash/role_id`, **not** a users table.
- `setting('login_photo')` is seeded with a remote Unsplash URL — the app needs internet for the admin login art.

> **`db/init.sql` only runs on first container boot.** Reapply: `docker compose down -v && docker compose up --build`. Live reload: `docker cp db/init.sql fharina-et-ignis-db-1:/tmp/init.sql` then `docker exec fharina-et-ignis-db-1 sh -c "mysql -uroot -p1234 fharina_et_ignis < /tmp/init.sql"`. On Windows never pipe via PowerShell (`Get-Content | docker exec` corrupts UTF-8) — use `docker cp`. Console output may show `�` for accents; stored data is fine.
>
> `db/pos_patch.sql` is a **historical one-off migration already folded into `init.sql`** (`sale_payments`, nullable `ventas` FKs, promo seeds). Do not re-apply it. `init.sql` is the only schema source — edit it there, not in a new patch file.

## Routing & Layout

- `src/public/index.php` is the front controller: `error_reporting(E_ALL)` + `display_errors=1` (errors are visible in-page — easy to mistake for app output), dotenv, autoload, helpers, DB connect, load `settings` into `$GLOBALS['__settings']`, then `Router::resolve()`.
- `Router` (`src/core/Router.php`) splits the URL into `controller/action/id` and checks `method_exists`. **Default controller is `home`**, so `/` renders the public website. The admin Dashboard is `/dashboard`. Anything unrouted → 404 page.
- **Auth gate lives in `index.php`**: `Router` marks the `site_actions` keys + `'auth'` public; everything else 302s to `/auth/login` when `$_SESSION['user']` is empty.
- Add routes in `src/routes/web.php` (`controllers` map + `site_actions` for the Spanish public URLs: `nosotros→about`, `contacto→contact`, `catalogo→catalog`, `carrito→cart`, `producto→product`, `ingresar→login`, `registro→register`). `producto/{id}` is special-cased inside `Router::resolve()`.
- **There IS an autoloader** (`spl_autoload_register` in `index.php`) covering `config/`, `models/`, `controllers/`, `core/` — new classes need no manual `require`. `views/` is **not** autoloaded; views are `require_once`d by controllers.
- Globals from `src/config/helpers.php` (required by `index.php`; views depend on them — don't move or remove): `esc()`, `url($path)` (returns `/path`), `flash($key, $msg=null)` (set when `$msg` given, get-and-clear when omitted), `setting($key, $default)`, `upload_image($field)`.
- `upload_image()` returns `/uploads/<field>_<random-hex>.<ext>`, `null` if no file, or `false` on failure (it already flashed the error). Forms need `enctype="multipart/form-data"`. Pick a meaningful field name — it becomes the filename prefix (`image_file`, `system_logo`, `login_photo`). On `false` the controller must redirect back. Keep prior value via hidden `name="image_url"` (products) or leave the file empty (settings).
- Controllers set `$title`, `$currentModule`, `$breadcrumbs` before requiring the view. Every view opens with `require __DIR__.'/../layouts/sidebar.php'` and ends with `footer.php` — **the only exception is `auth/login.php`** (standalone full-page). `views/site/*` instead uses `partials/header.php` + `partials/footer.php`.
- Action set per module: `index`, `create`, `store` (POST), `edit/{id}`, `update/{id}` (POST), `toggle/{id}`, `delete/{id}`. The mutating actions validate → `flash()` → `header('Location: ...')` → `exit`. **There is no redirect helper.**
- Views reference assets as absolute `/css/...`, `/js/...` (webroot is `public/`).

## UI Conventions — non-negotiable for every CRUD

Reference `products/index.php` + `create.php` before writing a new module.

- **Tailwind** (never Bootstrap), white + **orange-500** accents, `rounded-2xl` cards, `shadow-lg shadow-gray-200/50`.
- **All user-facing text in Spanish** (labels, buttons, flashes, SweetAlert copy, breadcrumbs). English only in code and URLs.
- **Status column is a toggle switch** using class `.btn-toggle` (same class as the row-action toggle): green "Activo" / gray "Inactivo" pill + sliding knob, carrying `data-url`, `data-name`, `data-state`.
- **Breadcrumbs start at `Sistema` → `url('dashboard')`**, defined per-controller as `$breadcrumbs` (label + optional url), last crumb is the title. `/` is the public site and never an admin breadcrumb.
- **Real-time search**: `#searchInput` + `<tbody id="crudTableBody">`; each row carries `data-search` (lowercased searchable text) plus `data-status` / `data-category` / `data-lowstock` (`"1"` when `stock <= min_stock`). Driven by `applyFilters()` in `public/js/main.js`; `#emptyState` shows when zero rows match. The optional selects are `#filterCategory`, `#filterStatus`, `#filterLowStock` inside a `.search-filter` wrapper.
- **Row actions** (right-aligned, `.btn-action` base + behavior class):
  - Eye = `.btn-detail` → opens `#detailModal`; carries `data-detail` (JSON of Spanish label→value pairs via `json_encode(..., JSON_UNESCAPED_UNICODE)`), `data-title`, `data-image`, `data-icon` (Font Awesome fallback). Body is image left, info cards right.
  - Edit = link to `{module}/edit/{id}` (`fa-pen-to-square`).
  - Toggle / delete buttons carry `data-url` + `data-name`; `main.js` intercepts clicks on `.btn-toggle` / `.btn-delete` and routes through SweetAlert confirm before navigating.
- **Flashes are never inline alert boxes.** `layouts/breadcrumb.php` renders a hidden `#flashToast` marker (`data-type` + `data-message`) that `main.js` turns into a top-right Toastify toast.
- **Custom CSS classes** (not Tailwind) in `public/css/style.css` — reuse them: `.form-label`, `.form-input`, `.btn-action`, `.modal-overlay`, `.modal-panel`, `.sidebar*`.
- **`old($key, $default)`** is not a global — each `create.php`/`edit.php` defines it inline at the top, guarded by `function_exists`.
- **Sidebar** is responsive: desktop (≥1024px) collapses inline to icons, persisted as `localStorage['sidebar-collapsed']` (`'1'`/`'0'`) on `body.sidebar-collapsed`; below 1024px it's a drawer on `body.sidebar-open` with `#sidebarOpenBtn` / `#sidebarBackdrop` and Esc-to-close. Clicking `.sidebar .brand-row` expands it when desktop-collapsed. All in `main.js`.
- **Barcode**: `productos.barcode` (unique, nullable). create/edit have a `.btn-scan-barcode` button that opens `#barcodeModal` (camera, Html5-QrCode). Shared partial `src/views/partials/barcode_scanner.php` — include it once per form page. `Product::findByBarcode($code, $excludeId)` blocks duplicates; the controller checks before create/update.

## Structure

```
src/public/index.php     Front controller: bootstrap + routing. Autoloader lives here.
src/public/{css,js}/     style.css (custom classes), main.js (sidebar, filters, modal, SweetAlert)
src/public/uploads/      User images served at /uploads/...; only .gitkeep is tracked
src/config/              Database.php (PDO via $_ENV), helpers.php (globals)
src/core/Router.php      URL → controller/action/id + public-route flags
src/routes/web.php       controllers map + site_actions (Spanish public URLs)
src/controllers/         Site, Product, Category, Dashboard, Auth, Settings, Pos, Client, Employee
src/models/              plain PDO classes, one per table (see DB Conventions for the trap on User)
src/views/               layouts/ + auth/ + site/ + dashboard/ + products/ categories/ settings/
                         pos/ clients/ employees/ + partials/barcode_scanner.php
db/init.sql              THE ONLY schema source: full schema + seed
docker/                  Dockerfile, nginx/default.conf
```

## DB Conventions

- **There is no `users` table.** Auth credentials live in `empleados` (`username`/`email` UNIQUE NULL, `password_hash`, `role_id` FK→`roles`) with CHECK `chk_empleados_login` requiring all four or none. `src/models/User.php` survives as a misleadingly-named shim — the class is `User` but its query joins `empleados e` + `roles r`. Don't "fix" it by assuming a `users` table exists.
- **Table naming has no single rule.** Spanish legacy: `categorias`, `productos`, `recetas`, `ingredientes`, `proveedores`, `pedidos`, `promociones`, `ventas`, `caja`, `empleados`. English/new: `roles`, `clients`, `shifts`, `attendances`, `contact_messages`, `product_images`, `sale_details`, `sale_payments`, `purchase_orders`. **Check `db/init.sql` before writing SQL.**
- Columns are English: `name`, `sale_price`, `production_cost`, `stock`, `min_stock`, `display_order`, `status ENUM('active','inactive')`. Product FK is always `category_id` → `categorias.id`.
- `Category::countProducts` (blocks deletes while products reference a category) reads `productos.category_id`.

## Gotchas

- **Nginx rewrite**: `index.php` reads `$_GET['url']`. The plain `try_files … /index.php?$query_string` does *not* populate it, and every URL then falls to the `home` default. The working config is `try_files $uri $uri/ @rewrite` + `location @rewrite { rewrite ^/(.*)$ /index.php?url=$1 last; }`. Preserve the `?url=` mapping.
- `db/init.sql` starts with `SET NAMES utf8mb4` — keep it, or Spanish accents double-encode.
- Sidebar placeholder links (class `sidebar-anchor`, non-functional) are: `#cash-register`, `#inventory`, `#production`, `#suppliers`, `#orders`, `#promotions`, `#reports`, `#statistics`, `#notifications`. Real modules: Dashboard, POS, Clientes, Empleados, Productos, Categorías, Configuración. ApexCharts is loaded globally in `sidebar.php` `<head>` — don't add a second copy.
- **POS**: `Sale::getCatalog()` returns active products + `category_name` + `discount_percent` (via `promociones`/`promotion_product`) + `final_price`. `Sale::createSale()` validates stock and payment inside a transaction, then inserts `ventas` + `sale_details` + `sale_payments` (split payments). `ventas.cash_register_id`/`employee_id` are nullable — there is no cash-register or employee-picker module yet. `PosController::ticket` renders the receipt with mPDF (72 mm, `dejavusans`), numbering the ticket as the sale id zero-padded to 6 digits. All POS JS is inline in `views/pos/index.php`; the cards are `.pos-card` with `data-search`/`data-category`/`data-stock`/`data-discount`/`data-barcode`, and camera scanning hooks into the shared `window.onBarcodeDetected` from the barcode partial.
- **Clientes/Empleados**: standard CRUD over `clients` and `empleados`. Uniqueness is validated (email / DUI `id_document`). Client delete is transactional (clears `client_segment`, sets `cupones.client_id = NULL`); employee delete is a plain `DELETE` that will fail on hard FKs (shifts, attendances, ventas, pedidos, caja) and surface as a flash error. The Empleados module *is* the access-account manager: fields `username`/`email`/`password`/`role_id`, resolved by `resolveLogin()` in the controller. On create the password is required only when creating a login; on edit, blank means keep the current one. Hashed with `password_hash()`. The `clients` table is English-named while `empleados` is Spanish — easy to mix up in joins.
