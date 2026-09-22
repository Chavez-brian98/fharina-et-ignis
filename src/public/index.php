<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

// Carga automática simple de la app (sin PSR-4): config, models, controllers y core.
spl_autoload_register(function ($class) {
    foreach ([__DIR__ . '/../config', __DIR__ . '/../models', __DIR__ . '/../controllers', __DIR__ . '/../core'] as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

require_once __DIR__ . '/../config/helpers.php';

$db = (new Database())->getConnection();

// Configuración global de la app (tabla settings), disponible vía setting().
$GLOBALS['__settings'] = (new Setting($db))->getAll();

// --- Enrutado (definición en routes/web.php, resolución en core/Router.php) ---
$router = new Router(require __DIR__ . '/../routes/web.php');
$route = $router->resolve(isset($_GET['url']) ? trim($_GET['url'], '/') : '');

// Las rutas no públicas exigen sesión.
if (!$route['public'] && empty($_SESSION['user'])) {
    header('Location: ' . url('auth/login'));
    exit;
}

if ($route['controller'] === null) {
    http_response_code(404);
    echo '<h1>404 - Página no encontrada</h1>';
    exit;
}

$controller = new $route['controller']($db);

if (!method_exists($controller, $route['action'])) {
    http_response_code(404);
    echo '<h1>404 - Acción no encontrada</h1>';
    exit;
}

$controller->{$route['action']}($route['id']);