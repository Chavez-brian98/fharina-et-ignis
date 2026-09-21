<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ContactMessage.php';
require_once __DIR__ . '/../models/Sale.php';

class SiteController
{
    private $db;
    private $saleModel;
    private $productModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->saleModel = new Sale($db);
        $this->productModel = new Product($db);
    }

    public function index()
    {
        $products = $this->saleModel->getCatalog();
        $featured = array_slice($products, 0, 4);

        $title = 'Inicio';
        $sitePage = 'home';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/home.php';
    }

    public function about()
    {
        $title = 'Sobre nosotros';
        $sitePage = 'about';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/about.php';
    }

    public function contact()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $message = trim($_POST['message'] ?? '');

            if ($name === '' || $email === '' || $message === '') {
                flash('error', 'Por favor completa tu nombre, tu correo y el mensaje.');
                header('Location: ' . url('contacto'));
                exit;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'El correo electrónico no es válido.');
                header('Location: ' . url('contacto'));
                exit;
            }

            $saved = (new ContactMessage($this->db))->create($name, $email, $phone, $message);

            flash($saved ? 'success' : 'error', $saved
                ? '¡Gracias ' . $name . '! Hemos recibido tu mensaje y te contactaremos muy pronto.'
                : 'No se pudo enviar el mensaje. Por favor inténtalo de nuevo.');

            header('Location: ' . url('contacto'));
            exit;
        }

        $title = 'Contáctanos';
        $sitePage = 'contact';

        require_once __DIR__ . '/../views/site/contact.php';
    }

    public function catalog()
    {
        $products = $this->saleModel->getCatalog();

        $categories = [];
        $seen = [];
        foreach ($products as $p) {
            if (!isset($seen[$p['category_id']])) {
                $seen[$p['category_id']] = true;
                $categories[] = ['id' => $p['category_id'], 'name' => $p['category_name']];
            }
        }

        $title = 'Catálogo de productos';
        $sitePage = 'catalog';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/catalog.php';
    }

    public function product($id = null)
    {
        if (!$id) {
            http_response_code(404);
            echo '<h1>404 - Producto no encontrado</h1>';
            exit;
        }

        $catalog = $this->saleModel->getCatalog();
        $product = null;
        foreach ($catalog as $p) {
            if ((int) $p['id'] === (int) $id) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            http_response_code(404);
            echo '<h1>404 - Producto no encontrado</h1>';
            exit;
        }

        $gallery = $this->productModel->getGallery($product['id']);
        $gallery = array_map(function ($g) {
            return $g['image_url'];
        }, $gallery);
        if (!empty($product['image_url'])) {
            array_unshift($gallery, $product['image_url']);
        }

        $related = array_values(array_filter($catalog, function ($p) use ($product) {
            return (int) $p['id'] !== (int) $product['id']
                && (int) $p['category_id'] === (int) $product['category_id'];
        }));
        if (empty($related)) {
            $related = array_values(array_filter($catalog, function ($p) use ($product) {
                return (int) $p['id'] !== (int) $product['id'];
            }));
        }
        $related = array_slice($related, 0, 4);

        $title = $product['name'];
        $sitePage = 'catalog';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/producto.php';
    }

    public function cart()
    {
        $title = 'Carrito de compras';
        $sitePage = 'cart';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/carrito.php';
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            flash('success', 'El inicio de sesión para clientes estará disponible próximamente.');
            header('Location: ' . url('ingresar'));
            exit;
        }

        $title = 'Iniciar sesión';
        $sitePage = 'login';

        require_once __DIR__ . '/../views/site/login.php';
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            flash('success', 'El registro de clientes estará disponible próximamente.');
            header('Location: ' . url('registro'));
            exit;
        }

        $title = 'Crear cuenta';
        $sitePage = 'register';

        require_once __DIR__ . '/../views/site/register.php';
    }
}