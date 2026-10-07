<?php

require_once __DIR__ . '/../models/Delivery.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/WsRelay.php';
require_once __DIR__ . '/../models/GoogleMaps.php';

/**
 * Domicilios en línea (módulo /deliveries).
 *
 * - index: cola de envíos activos para el equipo (filtro por estado).
 * - show: detalle con mapa en vivo (Google Maps + WebSocket), estado y
 *   controles de avance; el domiciliero asignado puede compartir su GPS.
 * - driver: pantalla del domiciliero (sus entregas + las disponibles para
 *   tomar), con el botón "Compartir ubicación" que manda la posición cada
 *   ~10s a /deliveries/location/{id}.
 * - asignar/avanzar: toman y avanzan la máquina de estados (tomado →
 *   preparando → en_camino → finalizado), con broadcast al relay WS y bitácora.
 * - rastrear/{token}: página pública de seguimiento para el cliente.
 *
 * Permisos: 'create' (tomar un envío), 'edit' (asignar domiciliero/avanzar,
 * reportar posición — ver Permiso::accionDeRuta), 'view' (consultar).
 */
class DeliveryController
{
    private $db;
    private $deliveryModel;
    private $orderModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->deliveryModel = new Delivery($db);
        $this->orderModel = new Order($db);
        $this->auditModel = new AuditLog($db);
    }

    // ---------------------------------------------------------------- lectura

    public function index()
    {
        $estado = $_GET['estado'] ?? 'todos';
        $deliveries = $this->deliveryModel->getAll($estado);

        $title = 'Domicilios';
        $currentModule = 'domicilios';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Domicilios', 'url' => null],
        ];

        require_once __DIR__ . '/../views/deliveries/index.php';
    }

    public function show($id)
    {
        $delivery = $this->deliveryModel->getById($id);
        if (!$delivery) {
            http_response_code(404);
            echo '<h1>404 - Domicilio no encontrado</h1>';
            exit;
        }

        $detalles = $this->orderModel->getDetalles($delivery['order_id']);
        $pagos = $this->deliveryModel->getPagos($delivery['order_id']);
        $ultimaPos = $this->deliveryModel->ultimaPosicion($id);
        $empleados = $this->empleadosActivos();

        $title = 'Domicilio #' . $id . ' · Seguimiento';
        $currentModule = 'domicilios';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Domicilios', 'url' => url('deliveries')],
            ['label' => 'Domicilio #' . $id, 'url' => null],
        ];

        require_once __DIR__ . '/../views/deliveries/show.php';
    }

    /** Pantalla del domiciliero: sus entregas en curso + las disponibles. */
    public function driver()
    {
        $empleadoId = (int) ($_SESSION['user']['id'] ?? 0);
        $misEntregas = $this->deliveryModel->getMisEntregas($empleadoId);
        $disponibles = $this->deliveryModel->getDisponibles();

        $title = 'Mis domicilios';
        $currentModule = 'domicilios';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Domicilios', 'url' => url('deliveries')],
            ['label' => 'Mis domicilios', 'url' => null],
        ];

        require_once __DIR__ . '/../views/deliveries/driver.php';
    }

    // ------------------------------------------------------------- mutación

    /** Toma un envío (o lo asigna a un domiciliero, si lo manda un supervisor). */
    public function asignar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('deliveries'));
            exit;
        }

        $delivery = $this->deliveryModel->getById($id);
        if (!$delivery || $delivery['state'] === 'finalizado') {
            flash('error', 'Ese envío ya no se puede tomar.');
            header('Location: ' . url('deliveries'));
            exit;
        }

        $destino = (int) ($_POST['driver_id'] ?? 0);
        $soyEditable = Permiso::puede('domicilios', 'edit');
        if ($destino > 0 && $destino !== (int) $_SESSION['user']['id'] && !$soyEditable) {
            flash('error', 'No tienes permiso para asignar a otro domiciliero.');
            header('Location: ' . url('deliveries/show/' . $id));
            exit;
        }
        if ($destino <= 0) {
            $destino = (int) $_SESSION['user']['id'];
        }

        $antes = $delivery;
        if (!$this->deliveryModel->asignar($id, $destino)) {
            flash('error', 'No se pudo asignar el domiciliero.');
            header('Location: ' . url('deliveries/show/' . $id));
            exit;
        }
        $despues = $this->deliveryModel->getById($id);
        $this->auditModel->write('update', 'deliveries', $id, $antes, $despues, 'Domicilio #' . $id . ' asignado al empl.' . $destino . '.');

        $nombre = trim(($delivery['driver_name'] ?? '') . ' ' . ($delivery['driver_last_name'] ?? ''));
        flash('success', 'Pedido tomado. ¡Buen viaje!' . ($nombre && $destino !== (int) $_SESSION['user']['id'] ? ' Asignado a ' . $nombre . '.' : ''));
        header('Location: ' . url('deliveries/show/' . $id));
        exit;
    }

    /** Avanza el domicilio al siguiente estado y lo difunde por WebSocket. */
    public function avanzar($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('deliveries'));
            exit;
        }

        $delivery = $this->deliveryModel->getById($id);
        if (!$delivery) {
            flash('error', 'El envío no existe.');
            header('Location: ' . url('deliveries'));
            exit;
        }
        $nuevo = Delivery::proximoEstado($delivery['state']);
        if ($nuevo === null) {
            flash('info', 'Este envío ya está ' . strtolower(Delivery::estadoTexto($delivery['state'])) . '.');
            header('Location: ' . url('deliveries/show/' . $id));
            exit;
        }

        $antes = $delivery;
        if (!$this->deliveryModel->avanzar($id)) {
            flash('error', 'No se pudo avanzar el estado.');
            header('Location: ' . url('deliveries/show/' . $id));
            exit;
        }
        $despues = $this->deliveryModel->getById($id);

        $autor = trim(($_SESSION['user']['name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''));
        $etiqueta = Delivery::estadoTexto($nuevo);
        $this->auditModel->write('update', 'deliveries', $id, $antes, $despues, 'Domicilio #' . $id . ' avanzó a «' . $etiqueta . '».');
        WsRelay::event($id, $nuevo, $etiqueta, $autor);

        flash('success', 'Estado actualizado: ' . $etiqueta . '.');
        header('Location: ' . url('deliveries/show/' . $id));
        exit;
    }

    /** Recibe la posición del navegador del domiciliero (JSON, cada ~10s). */
    public function location($id)
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'method']);
            exit;
        }

        $delivery = $this->deliveryModel->getById($id);
        if (!$delivery) {
            echo json_encode(['ok' => false, 'error' => 'not_found']);
            exit;
        }
        // Solo el domiciliero asignado manda la posición de este envío.
        $empleadoId = (int) ($_SESSION['user']['id'] ?? 0);
        if ((int) $delivery['driver_id'] !== $empleadoId) {
            echo json_encode(['ok' => false, 'error' => 'forbidden']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $lat = (float) ($input['lat'] ?? 0);
        $lng = (float) ($input['lng'] ?? 0);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            echo json_encode(['ok' => false, 'error' => 'coords']);
            exit;
        }

        $ok = $this->deliveryModel->registrarPosicion($id, $lat, $lng);
        if (!$ok) {
            echo json_encode(['ok' => false, 'error' => 'db']);
            exit;
        }
        WsRelay::location($id, $lat, $lng, $empleadoId);

        echo json_encode(['ok' => true, 'reported_at' => date('c')]);
        exit;
    }

    // ------------------------------------------------------------ seguimiento

    /** Página pública de seguimiento (por token opaco, sin sesión). */
    public function rastrear($token = null)
    {
        if ($token === null || $token === '') {
            http_response_code(404);
            echo '<h1>404 - Vínculo de seguimiento no válido</h1>';
            exit;
        }

        $delivery = $this->deliveryModel->getByToken($token);
        if (!$delivery) {
            http_response_code(404);
            echo '<h1>404 - Pedido no encontrado</h1>';
            exit;
        }

        $detalles = $this->orderModel->getDetalles($delivery['order_id']);
        $pagos = $this->deliveryModel->getPagos($delivery['order_id']);
        $ultimaPos = $this->deliveryModel->ultimaPosicion($delivery['id']);

        $title = 'Seguimiento de tu pedido';
        $sitePage = 'tracking';

        require_once __DIR__ . '/../views/site/rastrear.php';
    }

    // ---------------------------------------------------------------- helpers

    private function empleadosActivos()
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.name, e.last_name
               FROM empleados e
               JOIN roles r ON r.id = e.role_id
              WHERE e.status = 'active' AND r.status = 'active'
              ORDER BY e.name ASC;"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}