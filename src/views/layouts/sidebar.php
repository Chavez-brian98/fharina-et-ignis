<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . ' | ' . setting('business_name', 'Panadería') : setting('business_name', 'Panadería') ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">

    <!-- ======================= SIDEBAR (colapsable) ======================= -->
    <aside id="sidebar" class="sidebar bg-white border-r border-gray-200 flex flex-col">
        <div class="relative flex items-center justify-center px-4 py-5 border-b border-gray-100">
            <div class="brand-row flex flex-col items-center gap-2.5 max-w-full">
                <div class="brand-box relative w-14 h-14 rounded-2xl flex items-center justify-center overflow-hidden shrink-0 <?= setting('system_logo') ? '' : 'bg-orange-500 text-white shadow-md shadow-orange-200' ?>">
                    <?php if (setting('system_logo')): ?>
                        <img src="<?= esc(setting('system_logo')) ?>" alt="Logo" class="brand-icon w-full h-full object-contain">
                    <?php else: ?>
                        <i class="fa-solid fa-bread-slice text-xl brand-icon"></i>
                    <?php endif; ?>
                    <i class="fa-solid fa-bars-staggered brand-expand text-orange-500 text-lg" aria-hidden="true"></i>
                </div>
            </div>
            <button type="button" id="sidebarToggle" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-orange-50 hover:text-orange-500 transition-colors" title="Colapsar menú">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">

            <!-- PUNTO DE VENTA -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Punto de Venta</p>
                <a href="#cash-register" title="Caja" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-cash-register w-4 text-center shrink-0"></i><span class="sidebar-text">Caja</span>
                </a>
                <a href="<?= url('pos') ?>" title="Ventas" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'pos' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-cart-shopping w-4 text-center shrink-0"></i><span class="sidebar-text">Ventas</span>
                </a>
                <a href="<?= url('clients') ?>" title="Clientes" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'clients' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-users-line w-4 text-center shrink-0"></i><span class="sidebar-text">Clientes</span>
                </a>
            </div>

            <!-- SISTEMAS -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Sistemas</p>
                <a href="<?= url('dashboard') ?>" title="Dashboard" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'dashboard' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-gauge-high w-4 text-center shrink-0"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="<?= url('employees') ?>" title="Empleados" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'employees' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-user-tie w-4 text-center shrink-0"></i><span class="sidebar-text">Empleados</span>
                </a>
            </div>

            <!-- CATÁLOGO -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Catálogo</p>
                <a href="<?= url('products') ?>" title="Productos" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'products' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-box w-4 text-center shrink-0"></i><span class="sidebar-text">Productos</span>
                </a>
                <a href="<?= url('categories') ?>" title="Categorías" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'categories' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-tags w-4 text-center shrink-0"></i><span class="sidebar-text">Categorías</span>
                </a>
            </div>

            <!-- OPERACIONES -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Operaciones</p>
                <a href="#inventory" title="Inventario" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-boxes-stacked w-4 text-center shrink-0"></i><span class="sidebar-text">Inventario</span>
                </a>
                <a href="#production" title="Producción" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-industry w-4 text-center shrink-0"></i><span class="sidebar-text">Producción</span>
                </a>
                <a href="#suppliers" title="Proveedores" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-truck w-4 text-center shrink-0"></i><span class="sidebar-text">Proveedores</span>
                </a>
                <a href="#orders" title="Pedidos" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-cake-candles w-4 text-center shrink-0"></i><span class="sidebar-text">Pedidos</span>
                </a>
                <a href="#promotions" title="Promociones" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-percent w-4 text-center shrink-0"></i><span class="sidebar-text">Promociones</span>
                </a>
            </div>

            <!-- REPORTES -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Reportes</p>
                <a href="#reports" title="Reportes" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-chart-line w-4 text-center shrink-0"></i><span class="sidebar-text">Reportes</span>
                </a>
                <a href="#statistics" title="Estadísticas" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-chart-pie w-4 text-center shrink-0"></i><span class="sidebar-text">Estadísticas</span>
                </a>
            </div>

            <!-- CONFIGURACION -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Configuracion</p>
                <a href="#notifications" title="Notificaciones" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-bell w-4 text-center shrink-0"></i><span class="sidebar-text">Notificaciones</span>
                </a>
                <a href="<?= url('settings') ?>" title="Configuración" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === 'settings' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-gear w-4 text-center shrink-0"></i><span class="sidebar-text">Configuración</span>
                </a>
            </div>

        </nav>

        <?php $currentUser = $_SESSION['user'] ?? null; ?>
        <div class="px-4 py-4 border-t border-gray-100 flex items-center gap-3 user-row">
            <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold shrink-0"><?= strtoupper(substr($currentUser['username'] ?? 'A', 0, 1)) ?></div>
            <div class="sidebar-text flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 truncate"><?= esc($currentUser['username'] ?? 'Administrador') ?></p>
                <p class="text-xs text-gray-400 truncate"><?= esc($currentUser['email'] ?? 'admin@bakery.com') ?></p>
            </div>
            <a href="<?= url('auth/logout') ?>" title="Cerrar sesión" class="btn-action sidebar-text">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- Fondo oscuro para el drawer en móvil/tablet -->
    <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

    <!-- ======================= CONTENIDO ======================= -->
    <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 min-w-0 w-full">
        <!-- Botón flotante (móvil/tablet): abre el sidebar -->
        <button type="button" id="sidebarOpenBtn"
                class="lg:hidden fixed top-3 right-3 z-50 w-10 h-10 rounded-xl bg-white border border-gray-200 shadow-lg shadow-gray-200/60 flex items-center justify-center text-orange-500 hover:bg-orange-50 transition-colors"
                title="Abrir menú" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>

        <?php if (file_exists(__DIR__ . '/breadcrumb.php')) require __DIR__ . '/breadcrumb.php'; ?>