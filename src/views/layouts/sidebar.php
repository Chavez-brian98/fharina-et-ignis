<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . ' | Bakery POS' : 'Bakery POS' ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">

    <!-- ======================= SIDEBAR (colapsable) ======================= -->
    <aside id="sidebar" class="sidebar w-64 bg-white border-r border-gray-200 flex flex-col shrink-0 sticky top-0 h-screen">
        <div class="flex items-center justify-between gap-2 px-4 py-5 border-b border-gray-100">
            <div class="flex items-center gap-2.5 brand-row">
                <div class="w-9 h-9 rounded-xl bg-orange-500 flex items-center justify-center text-white shadow-md shadow-orange-200 shrink-0">
                    <i class="fa-solid fa-bread-slice"></i>
                </div>
                <div class="sidebar-text">
                    <p class="font-bold text-gray-900 leading-tight">Bakery POS</p>
                    <p class="text-xs text-gray-400">Panel de control</p>
                </div>
            </div>
            <button type="button" id="sidebarToggle" class="w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-orange-50 hover:text-orange-500 transition-colors shrink-0" title="Colapsar menú">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">

            <!-- SISTEMAS -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Sistemas</p>
                <a href="/" title="Dashboard" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link <?= ($currentModule ?? '') === '' ? 'nav-active' : '' ?>">
                    <i class="fa-solid fa-gauge-high w-4 text-center shrink-0"></i><span class="sidebar-text">Dashboard</span>
                </a>
                <a href="#employees" title="Empleados" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-user-tie w-4 text-center shrink-0"></i><span class="sidebar-text">Empleados</span>
                </a>
                <a href="#users" title="Usuarios" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-users w-4 text-center shrink-0"></i><span class="sidebar-text">Usuarios</span>
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

            <!-- PUNTO DE VENTA -->
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title">Punto de Venta</p>
                <a href="#cash-register" title="Caja" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-cash-register w-4 text-center shrink-0"></i><span class="sidebar-text">Caja</span>
                </a>
                <a href="#sales" title="Ventas" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-cart-shopping w-4 text-center shrink-0"></i><span class="sidebar-text">Ventas</span>
                </a>
                <a href="#clients" title="Clientes" class="flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link sidebar-anchor">
                    <i class="fa-solid fa-users-line w-4 text-center shrink-0"></i><span class="sidebar-text">Clientes</span>
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
        </nav>

        <div class="px-4 py-4 border-t border-gray-100 flex items-center gap-3 user-row">
            <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold shrink-0">A</div>
            <div class="sidebar-text">
                <p class="text-sm font-semibold text-gray-900">Administrador</p>
                <p class="text-xs text-gray-400">admin@bakery.com</p>
            </div>
        </div>
    </aside>

    <!-- ======================= CONTENIDO ======================= -->
    <main class="flex-1 px-8 py-6 min-w-0 w-full">
        <?php if (file_exists(__DIR__ . '/breadcrumb.php')) require __DIR__ . '/breadcrumb.php'; ?>