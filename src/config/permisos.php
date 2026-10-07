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
    'purchases' => [
        'label' => 'Compras', 'group' => 'Operaciones', 'icon' => 'fa-clipboard-list',
        'controller' => \PurchaseController::class, 'url' => 'purchases',
    ],
    'orders' => [
        'label' => 'Pedidos', 'group' => 'Operaciones', 'icon' => 'fa-cake-candles',
        'controller' => \OrderController::class, 'url' => 'orders',
    ],
    'domicilios' => [
        // Entregas a domicilio en tiempo real: tracking con mapa + estados
        // tomado/preparando/en_camino/finalizado. Lo opera el rol domiciliero.
        'label' => 'Domicilios', 'group' => 'Operaciones', 'icon' => 'fa-motorcycle',
        'controller' => \DeliveryController::class, 'url' => 'deliveries',
    ],
    'promotions' => [
        'label' => 'Promociones', 'group' => 'Operaciones', 'icon' => 'fa-percent',
        'controller' => \PromotionController::class, 'url' => 'promotions',
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
        'label' => 'Notificaciones',
        // 'nav' => false: la campana vive en el layout (arriba a la derecha), no
        // en el menú. 'always' => true: cualquier empleado logueado la ve.
        'nav' => false,
        'group' => 'Configuración',
        'icon' => 'fa-bell',
        'controller' => \NotificationController::class,
        'url' => 'notifications',
        'always' => true,
    ],
    'settings' => [
        'label' => 'Configuración', 'group' => 'Configuración', 'icon' => 'fa-gear',
        'controller' => 'SettingsController', 'url' => 'settings',
    ],
    'attendance' => [
        'label' => 'Mi Asistencia', 'nav' => 'Mi Asistencia', 'group' => 'Personal', 'icon' => 'fa-fingerprint',
        'controller' => \AttendanceController::class, 'url' => 'attendance',
        'always' => true,
    ],
    'schedules' => [
        'label' => 'Horarios', 'nav' => 'Horarios', 'group' => 'Personal', 'icon' => 'fa-calendar-week',
        'controller' => \ScheduleController::class, 'url' => 'schedules',
    ],
    'kiosco' => [
        'label' => 'Quiosco de asistencia',
        'nav' => false,
        'group' => 'Personal',
        'icon' => 'fa-desktop',
        'controller' => KioskController::class,
        'url' => 'kiosco',
    ],
    // Listado de las marcaciones que hizo el quiosco (QR o rostro). A diferencia
    // de 'kiosco' (la pantalla de pared, sin gate) este es un modulo normal:
    // si no hay fila en role_permissions, nadie lo ve salvo el administrador.
    'kiosk_log' => [
        'label' => 'Registros del Quiosco',
        'group' => 'Personal',
        'icon' => 'fa-clipboard-user',
        'controller' => \KioskLogController::class,
        'url' => 'kiosk_log',
    ],
    'profile' => [
        'label' => 'Mi Perfil', 'nav' => false, 'group' => 'Personal', 'icon' => 'fa-user',
        'controller' => 'ProfileController', 'url' => 'profile',
        'always' => true,
    ],
];