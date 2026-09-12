<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

use Dotenv\Dotenv;

/**
 * Escapa un valor para uso seguro en HTML.
 */
function esc($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/ProductController.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$database = new Database();
$db = $database->getConnection();

/**
 * Genera una URL limpia a partir de una ruta interna: /products, /categories/edit/3
 */
function url($path = '')
{
    return '/' . ltrim($path, '/');
}

/**
 * Devuelve y limpia un mensaje flash (guardado en sesión).
 */
function flash($key, $message = null)
{
    if ($message !== null) {
        $_SESSION[$key] = $message;
        return null;
    }
    if (isset($_SESSION[$key])) {
        $value = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $value;
    }
    return null;
}

// --- Routing ----------------------------------------------------------------
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : '';
$segments = $url !== '' ? explode('/', $url) : [];

$controllerName = $segments[0] ?? 'products';
$action = $segments[1] ?? 'index';
$id = isset($segments[2]) ? (int) $segments[2] : null;

$controllerMap = [
    'product' => ProductController::class,
    'products' => ProductController::class,
    'category' => CategoryController::class,
    'categories' => CategoryController::class,
];

$key = strtolower($controllerName);

if (!isset($controllerMap[$key])) {
    http_response_code(404);
    echo '<h1>404 - Página no encontrada</h1>';
    exit;
}

$controller = new $controllerMap[$key]($db);

if (!method_exists($controller, $action)) {
    http_response_code(404);
    echo '<h1>404 - Acción no encontrada</h1>';
    exit;
}

$controller->{$action}($id);
