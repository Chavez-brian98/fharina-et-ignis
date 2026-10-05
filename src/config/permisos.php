<?php

/**
 * Catálogo único de módulos con permisos.
 *
 * - clave: identificador del módulo (se usa en role_permissions.module,
 *          employee_permissions.module, en el gate de index.php y en el sidebar)
 * - label: texto en español que ve el usuario
 * - group: sección del grid de permisos y del sidebar
 * - icon: clase de Font Awesome
 * - controller: clase que atiende el módulo (null si aún no existe)
 * - url: ruta del módulo (null si es un placeholder sin ruta todavía)
 *
 * Módulos sin 'url' son los placeholders actuales del sidebar (#inventory, etc.):
 * sus permisos se pueden configurar ya y se activan al construir el módulo.
 */
return [
    'dashboard' => [
        'label' => 'Dashboard', 'group' => 'Sistema', 'icon' => 'fa-gauge-high',
        'controller' => 'DashboardController', 'url' => 'dashboard',
    ],
    'pos' => [
        'label' => 'Punto de Venta', 'nav' => 'Ventas', 'group' => 'Punto de Venta', 'icon' => 'fa-cart-shopping',
        'controller' => 'PosController', 'url' => 'pos',
    ],
    'clients' => [
        'label' => 'Clientes', 'group' => 'Punto de Venta', 'icon' => 'fa-users-line',
        'controller' => 'ClientController', 'url' => 'clients',
    ],
    'cash_register' => [
        'label' => 'Caja', 'group' => 'Punto de Venta', 'icon' => 'fa-cash-register',
        'controller' => 'CashRegisterController', 'url' => 'cash_register',
    ],
    'products' => [
        'label' => 'Productos', 'group' => 'Catálogo', 'icon' => 'fa-box',
        'controller' => 'ProductController', 'url' => 'products',
    ],
    'categories' => [
        'label' => 'Categorías', 'group' => 'Catálogo', 'icon' => 'fa-tags',
        'controller' => 'CategoryController', 'url' => 'categories',
    ],
    'inventory' => [
        'label' => 'Inventario', 'group' => 'Operaciones', 'icon' => 'fa-boxes-stacked',
        'controller' => null, 'url' => null, 'placeholder' => '#inventory',
    ],
    'production' => [
        'label' => 'Producción', 'group' => 'Operaciones', 'icon' => 'fa-industry',
        'controller' => null, 'url' => null, 'placeholder' => '#production',
    ],
    'suppliers' => [
        'label' => 'Proveedores', 'group' => 'Operaciones', 'icon' => 'fa-truck',
        'controller' => \SupplierController::class, 'url' => 'suppliers',
    ],
    'orders' => [
        'label' => 'Pedidos', 'group' => 'Operaciones', 'icon' => 'fa-cake-candles',
        'controller' => null, 'url' => null, 'placeholder' => '#orders',
    ],
    'promotions' => [
        'label' => 'Promociones', 'group' => 'Operaciones', 'icon' => 'fa-percent',
        'controller' => null, 'url' => null, 'placeholder' => '#promotions',
    ],
    'employees' => [
        'label' => 'Empleados', 'group' => 'Sistema', 'icon' => 'fa-user-tie',
        'controller' => 'EmployeeController', 'url' => 'employees',
    ],
    'roles' => [
        'label' => 'Roles y Permisos', 'nav' => 'Roles', 'group' => 'Sistema', 'icon' => 'fa-shield-halved',
        'controller' => 'RoleController', 'url' => 'roles',
    ],
    'audit' => [
        'label' => 'Bitácora', 'nav' => 'Bitácora', 'group' => 'Sistema', 'icon' => 'fa-clipboard-list',
        'controller' => 'AuditController', 'url' => 'audit',
    ],
    'reports' => [
        'label' => 'Reportes', 'group' => 'Reportes', 'icon' => 'fa-chart-line',
        'controller' => null, 'url' => null, 'placeholder' => '#reports',
    ],
    'statistics' => [
        'label' => 'Estadísticas', 'group' => 'Reportes', 'icon' => 'fa-chart-pie',
        'controller' => null, 'url' => null, 'placeholder' => '#statistics',
    ],
    'notifications' => [
        'label' => 'Notificaciones', 'group' => 'Configuración', 'icon' => 'fa-bell',
        'controller' => null, 'url' => null, 'placeholder' => '#notifications',
    ],
    'settings' => [
        'label' => 'Configuración', 'group' => 'Configuración', 'icon' => 'fa-gear',
        'controller' => 'SettingsController', 'url' => 'settings',
    ],
    'profile' => [
        'label' => 'Mi Perfil', 'group' => 'Personal', 'icon' => 'fa-user',
        'controller' => 'ProfileController', 'url' => 'profile',
        'always' => true,
    ],
];