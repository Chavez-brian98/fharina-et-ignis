<?php

/**
 * Rutas de la aplicación.
 *
 * 'controllers' mapea el primer segmento de la URL (formato controller/action/id,
 * ej. /products, /categories/edit/3) al controlador que la atiende.
 * 'site_actions' traduce las URLs públicas en español del sitio público a los
 * métodos del SiteController. Las rutas públicas del sitio y 'auth' no exigen
 * sesión; el resto de rutas redirigen a /auth/login si no hay sesión.
 */
return [
    // Mapa URL → controlador
    'controllers' => [
        // Sitio público
        'home'       => SiteController::class,
        'nosotros'   => SiteController::class,
        'contacto'   => SiteController::class,
        'catalogo'   => SiteController::class,
        'carrito'    => SiteController::class,
        'producto'   => SiteController::class,
        'ingresar'   => SiteController::class,
        'registro'   => SiteController::class,

        // Administración (requieren sesión, excepto auth)
        'auth'       => AuthController::class,
        'dashboard'  => DashboardController::class,
        'products'   => ProductController::class,
        'categories' => CategoryController::class,
        'settings'   => SettingsController::class,
        'pos'        => PosController::class,
        'clients'    => ClientController::class,
        'employees'  => EmployeeController::class,
    ],

    // Ruta pública en español → método del SiteController
    'site_actions' => [
        'home'     => 'index',
        'nosotros' => 'about',
        'contacto' => 'contact',
        'catalogo' => 'catalog',
        'carrito'  => 'cart',
        'producto' => 'product',
        'ingresar' => 'login',
        'registro' => 'register',
    ],
];