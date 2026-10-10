<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/ContactMessage.php';
require_once __DIR__ . '/../models/Sale.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Delivery.php';
require_once __DIR__ . '/../models/GoogleMaps.php';
require_once __DIR__ . '/../models/PayPal.php';

class SiteController
{
    private $db;
    private $saleModel;
    private $productModel;
    private $clientModel;
    private $deliveryModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->saleModel = new Sale($db);
        $this->productModel = new Product($db);
        $this->clientModel = new Client($db);
        $this->deliveryModel = new Delivery($db);
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

            // Envía notificaciones por correo (sin bloquear el flujo).
            $mailer = new Mailer();
            $htmlVisitante = '<h2 style="margin:0 0 12px 0;font-size:18px;color:#111827;">Gracias por escribirnos, ' . htmlspecialchars($name) . '</h2>'
                . '<p style="margin:0 0 8px 0;color:#374151;">Hemos recibido tu mensaje desde la página de contacto de ' . htmlspecialchars(setting('business_name', 'Panadería orellana')) . '.</p>'
                . '<p style="margin:0 0 8px 0;color:#374151;"><strong>Teléfono:</strong> ' . htmlspecialchars($phone ?: 'No especificado') . '<br>'
                . '<strong>Correo:</strong> ' . htmlspecialchars($email) . '</p>'
                . '<p style="margin:16px 0 0 0;color:#111827;font-weight:600;">Tu mensaje</p>'
                . '<div style="margin:8px 0 0 0;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa;white-space:pre-wrap;color:#374151;">' . htmlspecialchars($message) . '</div>'
                . '<p style="margin:16px 0 0 0;color:#6b7280;font-size:12px;">Este es un mensaje automático. No respondas a este correo.</p>';
            $mailer->send($email, 'Hemos recibido tu mensaje', $htmlVisitante, $mailer->businessMail() ?: $email);

            $toEmpresa = $mailer->businessMail() ?: $email;
            $htmlEmpresa = '<h2 style="margin:0 0 12px 0;font-size:18px;color:#111827;">Nuevo mensaje desde el sitio web</h2>'
                . '<p style="margin:0 0 8px 0;color:#374151;"><strong>Nombre:</strong> ' . htmlspecialchars($name) . '<br>'
                . '<strong>Correo:</strong> ' . htmlspecialchars($email) . '<br>'
                . '<strong>Teléfono:</strong> ' . htmlspecialchars($phone ?: 'No especificado') . '</p>'
                . '<p style="margin:16px 0 0 0;color:#111827;font-weight:600;">Mensaje</p>'
                . '<div style="margin:8px 0 0 0;padding:12px;border:1px solid #e5e7eb;border-radius:8px;background:#fafafa;white-space:pre-wrap;color:#374151;">' . htmlspecialchars($message) . '</div>'
                . '<p style="margin:16px 0 0 0;color:#6b7280;font-size:12px;">Formulario: /contacto</p>';
            $mailer->send($toEmpresa, 'Contacto web: ' . substr($name, 0, 60), $htmlEmpresa, $email);

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
            $email = trim($_POST['email'] ?? '');
            $password = (string) ($_POST['password'] ?? '');

            $cliente = $this->clientModel->findByEmail($email);
            if (!$cliente || $cliente['status'] !== 'active' || !password_verify($password, $cliente['password_hash'] ?? '')) {
                flash('error', 'Correo o contraseña incorrectos.');
                header('Location: ' . url('ingresar'));
                exit;
            }

            $_SESSION['cliente'] = [
                'id' => (int) $cliente['id'],
                'name' => trim($cliente['name'] . ' ' . $cliente['last_name']),
                'email' => $cliente['email'],
            ];

            $next = $_GET['next'] ?? 'cuenta';
            $next = in_array($next, ['finalizar', 'cuenta'], true) ? $next : 'cuenta';
            flash('success', '¡Hola ' . $_SESSION['cliente']['name'] . '! Sesión iniciada.');
            header('Location: ' . url($next));
            exit;
        }

        if (!empty($_SESSION['cliente'])) {
            header('Location: ' . url('cuenta'));
            exit;
        }

        $title = 'Iniciar sesión';
        $sitePage = 'login';

        require_once __DIR__ . '/../views/site/login.php';
    }

    public function register()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nameFull = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');

            if ($nameFull === '' || $email === '') {
                flash('error', 'Completa tu nombre y tu correo electrónico.');
                header('Location: ' . url('registro'));
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'El correo electrónico no es válido.');
                header('Location: ' . url('registro'));
                exit;
            }
            if ($this->clientModel->emailExists($email)) {
                flash('error', 'Ya existe una cuenta con ese correo.');
                header('Location: ' . url('registro'));
                exit;
            }
            if (mb_strlen($password) < 6) {
                flash('error', 'La contraseña debe tener al menos 6 caracteres.');
                header('Location: ' . url('registro'));
                exit;
            }
            if ($password !== $confirm) {
                flash('error', 'Las contraseñas no coinciden.');
                header('Location: ' . url('registro'));
                exit;
            }

            // El formulario pide un solo "nombre completo"; se reparte en
            // name (primera palabra) y last_name (el resto).
            $parts = preg_split('/\s+/', $nameFull, 2);
            $name = $parts[0];
            $lastName = isset($parts[1]) ? $parts[1] : '';

            $created = $this->clientModel->createWeb($name, $lastName, $phone, $email, password_hash($password, PASSWORD_DEFAULT), $address);
            if (!$created) {
                flash('error', 'No se pudo crear la cuenta. Inténtalo de nuevo.');
                header('Location: ' . url('registro'));
                exit;
            }

            $cliente = $this->clientModel->findByEmail($email);
            $_SESSION['cliente'] = [
                'id' => (int) $cliente['id'],
                'name' => $nameFull,
                'email' => $cliente['email'],
            ];

            flash('success', '¡Cuenta creada! Bienvenido a la familia.');
            header('Location: ' . url('finalizar'));
            exit;
        }

        if (!empty($_SESSION['cliente'])) {
            header('Location: ' . url('cuenta'));
            exit;
        }

        $title = 'Crear cuenta';
        $sitePage = 'register';

        require_once __DIR__ . '/../views/site/register.php';
    }

    /**
     * Checkout de pedidos a domicilio.
     *
     * GET  /finalizar                 → formulario (requiere sesión de cliente).
     * GET  /finalizar?paypal=success&pedido={id} → vuelta de PayPal: captura y
     *                                               muestra el seguimiento.
     * GET  /finalizar?paypal=cancel&pedido={id}  → el comprador canceló.
     * POST /finalizar                 → crea pedido + domicilio + orden PayPal,
     *                                    responde JSON con la URL de aprobación.
     */
    public function checkout()
    {
        if (isset($_GET['paypal'])) {
            $this->paypalReturn((string) $_GET['paypal']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = $_POST;
            }
            if (($input['action'] ?? '') === 'capture') {
                $this->capturarPedidoDomicilio($input);
                return;
            }
            $this->crearPedidoDomicilio($input);
            return;
        }

        if (empty($_SESSION['cliente'])) {
            header('Location: ' . url('ingresar') . '?next=finalizar');
            exit;
        }

        $cliente = $this->clientModel->findByEmail($_SESSION['cliente']['email']);
        $paypal = new PayPal();
        $paypalConfigurado = $paypal->configured();
        $paypalClientId = $paypal->clientId();

        $title = 'Finalizar pedido';
        $sitePage = 'checkout';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/finalizar.php';
    }

    /** Vuelta de la pasarela: captura el pago aprobado o avisa de la cancelación. */
    private function paypalReturn($status)
    {
        if ($status === 'cancel') {
            flash('info', 'Pago cancelado. Tu pedido quedó sin cobrar; puedes intentarlo de nuevo.');
            header('Location: ' . url('finalizar'));
            exit;
        }

        // La return_url viaja con el pedido interno (pedido=N) porque el id de
        // PayPal no se conoce hasta crear la orden; se resuelve desde la fila
        // de order_payments que dejó registrarPagoIniciado().
        $orderId = (int) ($_GET['pedido'] ?? 0);
        $pagos = [];
        $paypalOrderId = '';

        if ($orderId > 0) {
            $pagos = $this->deliveryModel->getPagos($orderId);
            foreach ($pagos as $p) {
                if (!empty($p['paypal_order_id'])) {
                    $paypalOrderId = $p['paypal_order_id'];
                    break;
                }
            }
        }

        // Compatibilidad: antes la vuelta traía ?order= con el id de PayPal.
        if ($paypalOrderId === '') {
            $paypalOrderId = trim($_GET['order'] ?? '');
        }

        if ($paypalOrderId === '') {
            flash('error', 'La pasarela devolvió una respuesta inesperada.');
            header('Location: ' . url('finalizar'));
            exit;
        }

        // Ya capturado (p. ej. al refrescar la vuelta): no se vuelve a cobrar.
        foreach ($pagos as $p) {
            if (!empty($p['paypal_capture_id'])) {
                $embarque = $this->deliveryModel->getByOrderId((int) $p['order_id']);
                flash('success', '¡Pago confirmado! Tu pedido ya está en camino de preparación.');
                header('Location: ' . url('rastrear/' . $embarque['tracking_token']));
                exit;
            }
        }

        try {
            $capture = (new PayPal())->captureOrder($paypalOrderId);
            if (($capture['capture_status'] ?? '') !== 'COMPLETED') {
                throw new Exception('El pago no fue completado por PayPal.');
            }
            $orderId = $this->deliveryModel->confirmarPago(
                $capture['paypal_order_id'],
                $capture['capture_id'],
                (float) ($capture['amount'] ?? 0)
            );
            if (!$orderId) {
                throw new Exception('No se encontró el pedido asociado al pago.');
            }
        } catch (Exception $e) {
            flash('error', 'No se pudo confirmar el pago: ' . $e->getMessage());
            header('Location: ' . url('finalizar'));
            exit;
        }

        $delivery = $this->deliveryModel->getByOrderId($orderId);
        flash('success', '¡Pago confirmado! Tu pedido ya está en camino de preparación.');
        header('Location: ' . url('rastrear/' . $delivery['tracking_token']));
        exit;
    }

    /**
     * Captura JSON del SDK JS de PayPal (in-context). El navegador ya aprobó
     * la orden, así que aquí se cobra y se devuelve el seguimiento. Idempotente
     * frente a reintentos: si la fila ya trae capture_id, no se vuelve a cobrar.
     */
    private function capturarPedidoDomicilio(array $input)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['cliente'])) {
            echo json_encode(['ok' => false, 'error' => 'login']);
            exit;
        }

        $orderId = (int) ($input['order_id'] ?? 0);
        $paypalOrderId = trim((string) ($input['paypal_order_id'] ?? ''));

        $pagos = [];
        if ($orderId > 0) {
            $pagos = $this->deliveryModel->getPagos($orderId);
            if ($paypalOrderId === '') {
                foreach ($pagos as $p) {
                    if (!empty($p['paypal_order_id'])) {
                        $paypalOrderId = $p['paypal_order_id'];
                        break;
                    }
                }
            }
        }

        if ($paypalOrderId === '') {
            echo json_encode(['ok' => false, 'error' => 'paypal', 'message' => 'Orden de PayPal no informada.']);
            exit;
        }

        // Ya capturado (reintento/refresco): no se vuelve a cobrar.
        foreach ($pagos as $p) {
            if (!empty($p['paypal_capture_id'])) {
                $embarque = $this->deliveryModel->getByOrderId((int) $p['order_id']);
                echo json_encode(['ok' => true, 'redirect' => url('rastrear/' . $embarque['tracking_token'])]);
                exit;
            }
        }

        try {
            $capture = (new PayPal())->captureOrder($paypalOrderId);
            if (($capture['capture_status'] ?? '') !== 'COMPLETED') {
                throw new Exception('El pago no fue completado por PayPal.');
            }
            $orderId = $this->deliveryModel->confirmarPago(
                $capture['paypal_order_id'],
                $capture['capture_id'],
                (float) ($capture['amount'] ?? 0)
            );
            if (!$orderId) {
                throw new Exception('No se encontró el pedido asociado al pago.');
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'paypal', 'message' => $e->getMessage()]);
            exit;
        }

        $delivery = $this->deliveryModel->getByOrderId($orderId);
        echo json_encode(['ok' => true, 'redirect' => url('rastrear/' . $delivery['tracking_token'])]);
        exit;
    }

    /** Crea el pedido a domicilio y la orden de pago PayPal (JSON). */
    private function crearPedidoDomicilio(?array $input = null)
    {
        header('Content-Type: application/json; charset=utf-8');

        if (empty($_SESSION['cliente'])) {
            echo json_encode(['ok' => false, 'error' => 'login']);
            exit;
        }

        // El cliente viene con Content-Type: application/json, así que NO viene
        // en $_POST: se lee del body.
        if ($input === null) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!is_array($input)) {
                $input = $_POST;
            }
        }

        $items = $this->itemsDelPost($input);
        if (empty($items)) {
            echo json_encode(['ok' => false, 'error' => 'items']);
            exit;
        }

        $direccion = trim((string) ($input['address'] ?? ''));
        if ($direccion === '') {
            echo json_encode(['ok' => false, 'error' => 'address']);
            exit;
        }
        $notas = trim((string) ($input['notes'] ?? ''));

        // Recomponer las líneas con el precio de la BD (nunca se confía en el
        // precio que llega del navegador). Se usa el catálogo con descuentos
        // aplicados (final_price), que es lo que el cliente vio al agregar.
        $catalogo = $this->saleModel->getCatalog();
        $porId = [];
        foreach ($catalogo as $p) {
            $porId[(int) $p['id']] = $p;
        }
        $lines = [];
        foreach ($items as $item) {
            $producto = $porId[(int) ($item['id'] ?? 0)] ?? null;
            if (!$producto || ($producto['status'] ?? 'active') !== 'active') {
                echo json_encode(['ok' => false, 'error' => 'product']);
                exit;
            }
            $lines[] = [
                'product_id' => (int) $producto['id'],
                'quantity' => max(1, min(99, (int) ($item['qty'] ?? 1))),
                'unit_price' => (float) $producto['final_price'],
            ];
        }
        $total = 0;
        foreach ($lines as $line) {
            $total += $line['quantity'] * $line['unit_price'];
        }
        $total = round($total, 2);

        $geo = GoogleMaps::geocode($direccion);
        $lat = $geo['lat'] ?? null;
        $lng = $geo['lng'] ?? null;

        $cliente = $this->clientModel->findByEmail($_SESSION['cliente']['email']);
        $creado = $this->deliveryModel->createDomicilio((int) $cliente['id'], $direccion, $lat, $lng, $total, $lines, $notas);
        if (!$creado) {
            echo json_encode(['ok' => false, 'error' => 'db']);
            exit;
        }

        // Limpia el carrito client-side (lo hace el JS con el ok).
        $orderId = (int) $creado['order_id'];

        $paypal = new PayPal();
        if (!$paypal->configured()) {
            // Sin pasarela el pedido queda pendiente de pago; el personal lo
            // cobra/confirma a la entrega.
            echo json_encode([
                'ok' => true,
                'order_id' => $orderId,
                'redirect' => url('rastrear/' . $creado['token']),
            ]);
            exit;
        }

        try {
            // 'js' (SDK in-context, popup) no necesita URLs de vuelta: el popup
            // de PayPal resuelve la aprobación y onApprove captura. 'redirect' es
            // el modo clásico de página completa (fallback si el SDK no carga).
            $flow = ($input['flow'] ?? 'redirect') === 'js' ? 'js' : 'redirect';
            $orden = $paypal->createOrder(
                $total,
                'Pedido #' . $orderId,
                $flow === 'redirect' ? urlAbsoluta('finalizar?paypal=success&pedido=' . $orderId) : null,
                $flow === 'redirect' ? urlAbsoluta('finalizar?paypal=cancel&pedido=' . $orderId) : null
            );
            if (empty($orden['id'])) {
                throw new Exception('PayPal no devolvió un id de orden.');
            }
            $pagoId = $this->deliveryModel->registrarPagoIniciado($orderId, $orden['id'], $total);
            if (!$pagoId) {
                throw new Exception('No se pudo registrar el pago iniciado.');
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'paypal', 'message' => $e->getMessage(), 'order_id' => $orderId]);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'order_id' => $orderId,
            'paypal_order_id' => $orden['id'],
            'approval_url' => $orden['approve_link'],
        ]);
        exit;
    }

    /** Líneas del POST (items como JSON o array paralelo). */
    private function itemsDelPost(array $input)
    {
        $raw = $input['items'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        if (is_array($raw)) {
            return $raw;
        }
        return [];
    }

    /** Página de cuenta del cliente: sus datos + historial de pedidos. */
    public function account()
    {
        if (empty($_SESSION['cliente'])) {
            header('Location: ' . url('ingresar') . '?next=cuenta');
            exit;
        }

        $cliente = $this->clientModel->findByEmail($_SESSION['cliente']['email']);
        $historial = $this->deliveryModel->getPorCliente((int) $cliente['id']);

        $title = 'Mi cuenta';
        $sitePage = 'account';
        $currency = setting('currency', '$');

        require_once __DIR__ . '/../views/site/cuenta.php';
    }

    /** Cierra la sesión del cliente (salir). */
    public function logout()
    {
        unset($_SESSION['cliente']);
        flash('info', 'Cerraste sesión. ¡Vuelve pronto!');
        header('Location: ' . url('/'));
        exit;
    }
}