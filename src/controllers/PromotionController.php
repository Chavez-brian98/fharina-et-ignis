<?php

require_once __DIR__ . '/../models/Promocion.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Promociones y cupones.
 *
 * CRUD estándar sobre promociones (store/update/toggle/delete) más la gestión
 * de cupones de una promoción: cupones/{id} (vista), generarCupon/{id} y
 * eliminarCupon/{id} (POST). Los dos últimos se mapean en
 * Permiso::accionDeRuta a create/delete (no caen en el fallback por prefijo).
 */
class PromotionController
{
    private $db;
    private $promociones;
    private $products;
    private $clients;
    private $audit;

    public function __construct($db)
    {
        $this->db = $db;
        $this->promociones = new Promocion($db);
        $this->products = new Product($db);
        $this->clients = new Client($db);
        $this->audit = new AuditLog($db);
    }

    public function index()
    {
        $promotions = $this->promociones->getAll();

        $title = 'Promociones';
        $currentModule = 'promotions';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Promociones', 'url' => null],
        ];

        require_once __DIR__ . '/../views/promotions/index.php';
    }

    public function create()
    {
        $products = $this->products->getAll();

        $title = 'Nueva Promoción';
        $currentModule = 'promotions';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Promociones', 'url' => url('promotions')],
            ['label' => 'Nueva', 'url' => null],
        ];

        require_once __DIR__ . '/../views/promotions/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('promotions'));
            exit;
        }

        $data = $this->datosDelPost();

        if ($data === null) {
            header('Location: ' . url('promotions/create'));
            exit;
        }

        $promotionId = $this->promociones->create(
            $data['name'],
            $data['tipo'],
            $data['porcentaje'],
            $data['start_date'],
            $data['end_date'],
            $data['start_time'],
            $data['end_time'],
            $data['status'],
            $data['product_ids']
        );

        if ($promotionId) {
            $after = $this->promociones->getById($promotionId);
            $this->audit->write('create', 'promociones', $promotionId, null, $after ?: null, 'Promoción creada: ' . $data['name'] . '.');
            flash('success', 'Promoción creada correctamente.');
            header('Location: ' . url('promotions'));
            exit;
        }

        flash('error', 'No se pudo crear la promoción.');
        header('Location: ' . url('promotions/create'));
        exit;
    }

    public function edit($id)
    {
        $promotion = $this->promociones->getById($id);

        if (!$promotion) {
            flash('error', 'Promoción no encontrada.');
            header('Location: ' . url('promotions'));
            exit;
        }

        $products = $this->products->getAll();
        $ligados = array_map('intval', array_column($this->promociones->getProductos($id), 'product_id'));

        $title = 'Editar Promoción';
        $currentModule = 'promotions';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Promociones', 'url' => url('promotions')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/promotions/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('promotions'));
            exit;
        }

        $before = $this->promociones->getById($id);

        if (!$before) {
            flash('error', 'Promoción no encontrada.');
            header('Location: ' . url('promotions'));
            exit;
        }

        $data = $this->datosDelPost();

        if ($data === null) {
            header('Location: ' . url('promotions/edit/' . $id));
            exit;
        }

        if ($this->promociones->update(
            $id,
            $data['name'],
            $data['tipo'],
            $data['porcentaje'],
            $data['start_date'],
            $data['end_date'],
            $data['start_time'],
            $data['end_time'],
            $data['status'],
            $data['product_ids']
        )) {
            $after = $this->promociones->getById($id);
            $this->audit->write('update', 'promociones', $id, $before, $after ?: null, 'Promoción actualizada: ' . $data['name'] . '.');
            flash('success', 'Promoción actualizada correctamente.');
        } else {
            flash('error', 'No se pudo actualizar la promoción.');
        }

        header('Location: ' . url('promotions'));
        exit;
    }

    public function toggle($id)
    {
        $before = $this->promociones->getById($id);

        if ($this->promociones->toggleStatus($id)) {
            $after = $this->promociones->getById($id);
            $this->audit->write('toggle', 'promociones', $id, $before, $after ?: null, 'Estado de la promoción actualizado.');
            flash('success', 'Estado de la promoción actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado de la promoción.');
        }

        header('Location: ' . url('promotions'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->promociones->getById($id);

        if ($before && $this->promociones->delete($id)) {
            $this->audit->write('delete', 'promociones', $id, $before, null, 'Promoción eliminada: ' . $before['name'] . '.');
            flash('success', 'Promoción eliminada correctamente.');
        } else {
            flash('error', 'No se pudo eliminar la promoción. Solo se borran las promociones sin cupones ni ventas asociadas.');
        }

        header('Location: ' . url('promotions'));
        exit;
    }

    /**
     * Vista de cupones de una promoción: los lista y permite generarlos,
     * asignarlos a un cliente y borrarlos.
     */
    public function cupones($id)
    {
        $promotion = $this->promociones->getById($id);

        if (!$promotion) {
            flash('error', 'Promoción no encontrada.');
            header('Location: ' . url('promotions'));
            exit;
        }

        $coupons = $this->promociones->cuponesDePromocion($id);
        $clients = $this->clients->getAll();

        $title = 'Cupones de ' . $promotion['name'];
        $currentModule = 'promotions';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Promociones', 'url' => url('promotions')],
            ['label' => 'Promoción', 'url' => url('promotions/edit/' . $id)],
            ['label' => 'Cupones', 'url' => null],
        ];

        require_once __DIR__ . '/../views/promotions/cupones.php';
    }

    /** POST: genera un cupón nuevo para la promoción. */
    public function generarCupon($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('promotions'));
            exit;
        }

        $promotion = $this->promociones->getById($id);

        if (!$promotion) {
            flash('error', 'Promoción no encontrada.');
            header('Location: ' . url('promotions'));
            exit;
        }

        $code = mb_strtoupper(trim($_POST['code'] ?? ''));
        if ($code === '') {
            flash('error', 'Indica o genera un código para el cupón.');
            header('Location: ' . url('promotions/cupones/' . $id));
            exit;
        }

        if (!preg_match('/^[A-Za-z0-9\-_]{3,30}$/', $code)) {
            flash('error', 'El código solo puede tener letras, números, guiones y subrayados (3 a 30 caracteres).');
            header('Location: ' . url('promotions/cupones/' . $id));
            exit;
        }

        $clientId = (int) ($_POST['client_id'] ?? 0);
        $maxUses = (int) ($_POST['max_uses'] ?? 1);

        if ($clientId > 0 && !$this->clients->getById($clientId)) {
            flash('error', 'El cliente seleccionado no existe.');
            header('Location: ' . url('promotions/cupones/' . $id));
            exit;
        }

        $cuponId = $this->promociones->crearCupon($id, $code, $clientId, $maxUses);

        if ($cuponId) {
            $this->audit->write('create', 'cupones', $cuponId, null, null, 'Cupón ' . $code . ' generado para la promoción ' . $promotion['name'] . '.');
            flash('success', 'Cupón ' . $code . ' generado correctamente.');
        } else {
            flash('error', 'No se pudo crear el cupón. Revisa que el código no esté repetido.');
        }

        header('Location: ' . url('promotions/cupones/' . $id));
        exit;
    }

    /** Elimina un cupón de la promoción (GET, con confirmación vía SweetAlert). */
    public function eliminarCupon($id)
    {
        $promotion = $this->promociones->getById($id);

        if (!$promotion) {
            flash('error', 'Promoción no encontrada.');
            header('Location: ' . url('promotions'));
            exit;
        }

        $cuponId = (int) ($_GET['cupon_id'] ?? $_POST['cupon_id'] ?? 0);

        if ($cuponId <= 0 || !$this->promociones->eliminarCupon($id, $cuponId)) {
            flash('error', 'El cupón no se encontró o ya fue eliminado.');
            header('Location: ' . url('promotions/cupones/' . $id));
            exit;
        }

        $this->audit->write('delete', 'cupones', $cuponId, null, null, 'Cupón eliminado de la promoción ' . $promotion['name'] . '.');
        flash('success', 'Cupón eliminado.');

        header('Location: ' . url('promotions/cupones/' . $id));
        exit;
    }

    /**
     * Valida y normaliza el POST del formulario de promoción. null = con flash
     * puesto, para que la vista redibuje el formulario.
     */
    private function datosDelPost()
    {
        $name = trim($_POST['name'] ?? '');
        $tipo = trim($_POST['tipo'] ?? '');
        $porcentaje = (float) str_replace(',', '.', (string) ($_POST['discount_percentage'] ?? 0));
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
        $productIds = $_POST['product_ids'] ?? [];

        if ($name === '') {
            flash('error', 'El nombre de la promoción es obligatorio.');
            return null;
        }

        if (!in_array($tipo, Promocion::TIPOS, true)) {
            flash('error', 'Tipo de promoción no válido.');
            return null;
        }

        if ($startDate === '' || $endDate === '' || !$this->fechaValida($startDate) || !$this->fechaValida($endDate)) {
            flash('error', 'Las fechas de vigencia son obligatorias y deben ser válidas.');
            return null;
        }

        if ($endDate < $startDate) {
            flash('error', 'La fecha de fin no puede ser anterior a la de inicio.');
            return null;
        }

        if ($startTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            flash('error', 'La hora de inicio no tiene un formato válido.');
            return null;
        }

        if ($endTime !== '' && !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            flash('error', 'La hora de fin no tiene un formato válido.');
            return null;
        }

        if (Promocion::usaPorcentaje($tipo)) {
            if ($porcentaje <= 0 || $porcentaje > 100) {
                flash('error', 'Para este tipo de promoción el descuento debe estar entre 1 y 100 por ciento.');
                return null;
            }
            $porcentaje = number_format($porcentaje, 2, '.', '');
        } else {
            // 2x1 y cupones no llevan porcentaje: se deja NULL en la BD para
            // que el POS no las aplique por accidente.
            $porcentaje = null;
        }

        if (!is_array($productIds)) {
            $productIds = [];
        }
        $productIds = array_map('intval', $productIds);
        $productIds = array_values(array_filter($productIds, function ($pid) {
            return $pid > 0;
        }));

        if (!$productIds) {
            flash('error', 'Selecciona al menos un producto para la promoción.');
            return null;
        }

        return [
            'name' => $name,
            'tipo' => $tipo,
            'porcentaje' => $porcentaje,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'status' => $status,
            'product_ids' => $productIds,
        ];
    }

    private function fechaValida($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }
}