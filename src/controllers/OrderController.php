<?php

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Notificacion.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Pedidos con reserva.
 *
 * El listado (index) se centra en entregas futuras: por defecto filtra
 * delivery_date >= hoy. El seguimiento vive en la vista show con la línea de
 * tiempo de order_status_history; cambiar el estado es el endpoint estado/{id}
 * (POST), que exige 'edit' (Permiso::accionDeRuta). Cada cambio de estado y
 * cada pedido nuevo dispara una notificación global (campana del layout).
 */
class OrderController
{
    private $db;
    private $orders;
    private $clients;
    private $products;
    private $notificaciones;
    private $audit;

    public function __construct($db)
    {
        $this->db = $db;
        $this->orders = new Order($db);
        $this->clients = new Client($db);
        $this->products = new Product($db);
        $this->notificaciones = new Notificacion($db);
        $this->audit = new AuditLog($db);
    }

    public function index()
    {
        $desde = trim($_GET['desde'] ?? '');
        if ($desde === '' || !$this->fechaValida($desde)) {
            $desde = date('Y-m-d');
        }
        $hasta = trim($_GET['hasta'] ?? '');
        if ($hasta !== '' && !$this->fechaValida($hasta)) {
            $hasta = '';
        }
        $estado = trim($_GET['estado'] ?? '');

        $orders = $this->orders->getAll($desde, $hasta, $estado);

        $title = 'Pedidos';
        $currentModule = 'orders';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Pedidos', 'url' => null],
        ];

        require_once __DIR__ . '/../views/orders/index.php';
    }

    public function create()
    {
        $clients = $this->clients->getAll();
        $products = array_values(array_filter($this->products->getAll(), function ($p) {
            return ($p['status'] ?? '') === 'active';
        }));

        $title = 'Nuevo Pedido';
        $currentModule = 'orders';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Pedidos', 'url' => url('orders')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/orders/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('orders'));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('orders/create'));
            exit;
        }

        $orderId = $this->orders->create(
            $data['client_id'],
            $data['employee_id'],
            $data['delivery_date'],
            $data['delivery_address'],
            $data['notes'],
            $data['total'],
            $items
        );

        if ($orderId) {
            $after = $this->orders->getById($orderId);
            $this->audit->write('create', 'pedidos', $orderId, null, $after ?: null, 'Pedido #' . $orderId . ' creado (entrega ' . $data['delivery_date'] . ').');

            $this->notificaciones->crear(
                'pedido_nuevo',
                'Nuevo pedido',
                Order::nombreCliente($after) . ' reservó el pedido #' . $orderId . ' para el ' . date('d/m/Y', strtotime($data['delivery_date'])) . '.',
                'pedido',
                $orderId
            );

            flash('success', 'Pedido #' . $orderId . ' creado correctamente.');
            header('Location: ' . url('orders/show/' . $orderId));
            exit;
        }

        flash('error', 'No se pudo crear el pedido.');
        header('Location: ' . url('orders/create'));
        exit;
    }

    public function edit($id)
    {
        $order = $this->orders->getById($id);

        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . url('orders'));
            exit;
        }

        if (!in_array($order['state'], ['pendiente', 'aprobado'], true)) {
            flash('warning', 'Este pedido está ' . Order::estadoTexto($order['state']) . ' y ya no se puede editar.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        $details = $this->orders->getDetalles($id);
        $clients = $this->clients->getAll();
        $products = array_values(array_filter($this->products->getAll(), function ($p) {
            return ($p['status'] ?? '') === 'active';
        }));

        $title = 'Editar Pedido #' . $id;
        $currentModule = 'orders';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Pedidos', 'url' => url('orders')],
            ['label' => 'Pedido #' . $id, 'url' => null],
        ];

        require_once __DIR__ . '/../views/orders/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('orders'));
            exit;
        }

        $before = $this->orders->getById($id);

        if (!$before) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . url('orders'));
            exit;
        }

        if (!in_array($before['state'], ['pendiente', 'aprobado'], true)) {
            flash('error', 'Solo se puede editar un pedido pendiente o aprobado.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('orders/edit/' . $id));
            exit;
        }

        if ($this->orders->update($id, $data['client_id'], $data['delivery_date'], $data['delivery_address'], $data['notes'], $data['total'], $items)) {
            $after = $this->orders->getById($id);
            $this->audit->write('update', 'pedidos', $id, $before, $after ?: null, 'Pedido #' . $id . ' actualizado.');
            flash('success', 'Pedido #' . $id . ' actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el pedido.');
        }

        header('Location: ' . url('orders/show/' . $id));
        exit;
    }

    /**
     * Vista de seguimiento: cabecera, artículos y línea de tiempo.
     */
    public function show($id)
    {
        $order = $this->orders->getById($id);

        if (!$order) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . url('orders'));
            exit;
        }

        $details = $this->orders->getDetalles($id);
        $historial = $this->orders->getHistorial($id);
        $transiciones = $this->orders->transicionesDesde($order['state']);

        $title = 'Pedido #' . $id;
        $currentModule = 'orders';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Pedidos', 'url' => url('orders')],
            ['label' => 'Pedido #' . $id, 'url' => null],
        ];

        require_once __DIR__ . '/../views/orders/show.php';
    }

    /**
     * POST: avanza el pedido a un estado permitido.
     */
    public function estado($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('orders'));
            exit;
        }

        $before = $this->orders->getById($id);

        if (!$before) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . url('orders'));
            exit;
        }

        $nuevoEstado = trim($_POST['estado'] ?? '');
        $comment = trim($_POST['comment'] ?? '');

        if ($nuevoEstado === '') {
            flash('error', 'Selecciona el nuevo estado del pedido.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        if (!in_array($nuevoEstado, $this->orders->transicionesDesde($before['state']), true)) {
            flash('error', 'No se puede pasar el pedido de ' . Order::estadoTexto($before['state']) . ' a ' . Order::estadoTexto($nuevoEstado) . '.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        if ($nuevoEstado === 'rechazado' && $comment === '') {
            flash('error', 'Indica el motivo del rechazo.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        if (mb_strlen($comment) > 255) {
            flash('error', 'El comentario no puede superar los 255 caracteres.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        $employeeId = $this->empleadoActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión.');
            header('Location: ' . url('orders/show/' . $id));
            exit;
        }

        $resultado = $this->orders->cambiarEstado($id, $nuevoEstado, $employeeId, $comment);

        if ($resultado['ok']) {
            $this->audit->write('update', 'pedidos', $id, $before, $resultado['fila'] ?: null,
                'Pedido #' . $id . ': ' . Order::estadoTexto($before['state']) . ' → ' . Order::estadoTexto($nuevoEstado) . '.');

            $this->notificarCambioDeEstado($id, $before, $nuevoEstado, $comment);

            flash('success', 'Pedido #' . $id . ' marcado como ' . Order::estadoTexto($nuevoEstado) . '.');
        } else {
            flash('error', 'No se pudo cambiar el estado del pedido.');
        }

        header('Location: ' . url('orders/show/' . $id));
        exit;
    }

    public function delete($id)
    {
        $before = $this->orders->getById($id);

        if (!$before) {
            flash('error', 'Pedido no encontrado.');
            header('Location: ' . url('orders'));
            exit;
        }

        if ($this->orders->delete($id)) {
            $this->audit->write('delete', 'pedidos', $id, $before, null, 'Pedido #' . $id . ' eliminado.');
            $this->notificaciones->resolver('pedido_nuevo', 'pedido', $id);
            flash('success', 'Pedido #' . $id . ' eliminado.');
        } else {
            flash('error', 'No se pudo eliminar el pedido. Solo se borran los pendientes y sin pagos.');
        }

        header('Location: ' . url('orders'));
        exit;
    }

    /**
     * Valida la cabecera del POST. Devuelve null (con flash puesto) si algo no
     * cuadra, para que la vista redibuje el formulario.
     */
    private function datosDeCabecera()
    {
        $clientId = (int) ($_POST['client_id'] ?? 0);
        $deliveryDate = trim($_POST['delivery_date'] ?? '');
        $deliveryAddress = trim($_POST['delivery_address'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($clientId <= 0 || !$this->clients->getById($clientId)) {
            flash('error', 'Selecciona un cliente válido.');
            return null;
        }

        if ($deliveryDate === '' || !$this->fechaValida($deliveryDate)) {
            flash('error', 'La fecha de entrega es obligatoria y debe tener un formato válido.');
            return null;
        }

        if ($deliveryDate < date('Y-m-d')) {
            flash('error', 'La fecha de entrega no puede ser anterior a hoy.');
            return null;
        }

        if (mb_strlen($deliveryAddress) > 255) {
            flash('error', 'La dirección no puede superar los 255 caracteres.');
            return null;
        }

        $employeeId = $this->empleadoActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión.');
            return null;
        }

        $items = $this->itemsDelPost();
        $total = 0;
        if (is_array($items)) {
            foreach ($items as $item) {
                $total += (int) $item['quantity'] * (float) $item['unit_price'];
            }
        }

        return [
            'client_id' => $clientId,
            'employee_id' => $employeeId,
            'delivery_date' => $deliveryDate,
            'delivery_address' => $deliveryAddress,
            'notes' => $notes,
            'total' => round($total, 2),
        ];
    }

    /**
     * Normaliza las líneas del POST (arrays paralelos product_id[] / quantity[] /
     * unit_price[] / description[]): descarta filas vacías y fusiona productos
     * repetidos sumando cantidades. Devuelve null con flash si no quedó nada.
     */
    private function itemsDelPost()
    {
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        $descriptions = $_POST['description'] ?? [];

        if (!is_array($productIds) || !is_array($quantities) || !is_array($prices)) {
            flash('error', 'Las líneas del pedido no se recibieron correctamente.');
            return null;
        }

        if (!is_array($descriptions)) {
            $descriptions = [];
        }

        $items = [];
        $indices = [];

        foreach ($productIds as $i => $rawId) {
            $productId = (int) $rawId;

            if ($productId <= 0) {
                continue;
            }

            $product = $this->products->getById($productId);
            if (!$product || ($product['status'] ?? '') !== 'active') {
                flash('error', 'Uno de los productos seleccionados no está activo.');
                return null;
            }

            $quantity = (int) ($quantities[$i] ?? 0);
            $price = (float) str_replace(',', '.', (string) ($prices[$i] ?? 0));
            $description = trim((string) ($descriptions[$i] ?? ''));

            if ($quantity <= 0) {
                flash('error', 'Indica una cantidad mayor que cero para cada producto.');
                return null;
            }

            if ($price < 0) {
                flash('error', 'El precio unitario no puede ser negativo.');
                return null;
            }

            // Precio en blanco = el precio de venta actual del producto.
            if ($price <= 0) {
                $price = (float) $product['sale_price'];
            }

            if (isset($indices[$productId])) {
                $items[$indices[$productId]]['quantity'] += $quantity;
                continue;
            }

            $indices[$productId] = count($items);
            $items[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $price,
                'description' => $description,
            ];
        }

        if (!$items) {
            flash('error', 'Agrega al menos un producto al pedido.');
            return null;
        }

        return $items;
    }

    /** Crea la notificación global que corresponde a cada transición. */
    private function notificarCambioDeEstado($orderId, $before, $nuevoEstado, $comment)
    {
        $nombreCliente = Order::nombreCliente($before);

        $mensajes = [
            'aprobado' => 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' fue aprobado y pasa a producción.',
            'en_produccion' => 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' está en producción.',
            'listo' => 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' está listo para entregar.',
            'entregado' => 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' fue entregado.',
        ];

        if ($nuevoEstado === 'rechazado') {
            $motivo = $comment !== '' ? ': ' . $comment : '.';
            $mensaje = 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' fue rechazado' . $motivo;
            $tipo = 'pedido_rechazado';
            $titulo = 'Pedido rechazado';
        } elseif ($nuevoEstado === 'cancelado') {
            $mensaje = 'El pedido #' . $orderId . ' de ' . $nombreCliente . ' fue cancelado.';
            $tipo = 'pedido_cancelado';
            $titulo = 'Pedido cancelado';
        } elseif ($nuevoEstado === 'listo') {
            $mensaje = $mensajes['listo'];
            $tipo = 'pedido_listo';
            $titulo = 'Pedido listo';
        } elseif (isset($mensajes[$nuevoEstado])) {
            $mensaje = $mensajes[$nuevoEstado];
            $tipo = 'pedido_estado';
            $titulo = 'Pedido #' . $orderId . ' ' . Order::estadoTexto($nuevoEstado);
        } else {
            return;
        }

        $this->notificaciones->crear($tipo, $titulo, $mensaje, 'pedido', $orderId);
    }

    private function fechaValida($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }

    private function empleadoActual()
    {
        $id = (int) ($_SESSION['user']['id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}