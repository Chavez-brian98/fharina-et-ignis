<?php

require_once __DIR__ . '/../models/Purchase.php';
require_once __DIR__ . '/../models/Ingredient.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Compras (órdenes de compra a proveedores).
 *
 * Estados: pendiente → parcial → recibida, más cancelada.
 * - store/update/delete exigen los permisos create/edit/delete del catálogo.
 * - receive exige 'edit' (ver Permiso::accionDeRuta) porque mueve stock.
 * - cancel exige 'delete' por la misma razón que en el resto de módulos:
 *   descarta la orden.
 */
class PurchaseController
{
    private $db;
    private $purchaseModel;
    private $ingredientModel;
    private $supplierModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->purchaseModel = new Purchase($db);
        $this->ingredientModel = new Ingredient($db);
        $this->supplierModel = new Supplier($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $purchases = $this->purchaseModel->getAll();
        $suppliers = $this->supplierModel->getAllForSelect();

        $title = 'Compras';
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/index.php';
    }

    public function create()
    {
        $suppliers = $this->supplierModel->getAllForSelect();
        $ingredients = $this->ingredientModel->getAll();

        $title = 'Nueva Compra';
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => url('purchases')],
            ['label' => 'Nueva', 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('purchases/create'));
            exit;
        }

        $orderId = $this->purchaseModel->create($data, $items);

        if ($orderId) {
            $new = $this->purchaseModel->getById($orderId);
            $this->auditModel->write('create', 'purchase_orders', $orderId, null, $new ?: null, 'Orden de compra #' . $orderId . ' creada.');
            flash('success', 'Orden de compra #' . $orderId . ' creada correctamente.');
            header('Location: ' . url('purchases/edit/' . $orderId));
            exit;
        }

        flash('error', 'No se pudo crear la orden de compra.');
        header('Location: ' . url('purchases/create'));
        exit;
    }

    public function edit($id)
    {
        $purchase = $this->purchaseModel->getById($id);

        if (!$purchase) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        $details = $this->purchaseModel->getDetails($id);
        $receipts = $this->purchaseModel->getReceipts($id);
        $receivedTotals = $this->purchaseModel->getReceivedTotals($id);

        $suppliers = $this->supplierModel->getAllForSelect();
        $ingredients = $this->ingredientModel->getAll();

        $editable = $purchase['state'] === 'pendiente' && !$receipts;

        $title = 'Editar Compra #' . $id;
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => url('purchases')],
            ['label' => 'Compra #' . $id, 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($before['state'] !== 'pendiente' || $this->purchaseModel->countReceipts($id) > 0) {
            flash('error', 'Solo se puede editar una orden pendiente y sin recepciones registradas.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        if ($this->purchaseModel->update($id, $data, $items)) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('update', 'purchase_orders', $id, $before, $after ?: null, 'Orden de compra #' . $id . ' actualizada.');
            flash('success', 'Orden de compra #' . $id . ' actualizada correctamente.');
        } else {
            flash('error', 'No se pudo actualizar la orden de compra.');
        }

        header('Location: ' . url('purchases/edit/' . $id));
        exit;
    }

    public function receive($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if (in_array($before['state'], ['recibida', 'cancelada'], true)) {
            flash('error', 'No se puede recibir mercancía en una orden ' . Purchase::estadoTexto($before['state']) . '.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        // El POST trae received[<detail_id>] con la cantidad real de cada línea.
        $posted = $_POST['received'] ?? [];
        $recibido = [];

        if (is_array($posted)) {
            foreach ($posted as $detailId => $cantidad) {
                $cantidad = (float) str_replace(',', '.', (string) $cantidad);
                if ($cantidad > 0) {
                    $recibido[(int) $detailId] = $cantidad;
                }
            }
        }

        if (!$recibido) {
            flash('error', 'Indica al menos una cantidad recibida mayor que cero.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $observations = trim($_POST['observations'] ?? '');

        if (mb_strlen($observations) > 500) {
            flash('error', 'Las observaciones no pueden superar los 500 caracteres.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $employeeId = $this->employeeIdActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión para registrar la recepción.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $resultado = $this->purchaseModel->receive($id, $recibido, $employeeId, $observations);

        if ($resultado) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('receive', 'purchase_orders', $id, $before, $after ?: null,
                'Mercancía recibida en la orden #' . $id . ' (' . $resultado['lineas'] . ' línea(s), estado ' . Purchase::estadoTexto($resultado['estado']) . ').');
            flash('success', 'Recepción registrada: la orden #' . $id . ' quedó ' . Purchase::estadoTexto($resultado['estado']) . '.');
        } else {
            flash('error', 'No se pudo registrar la recepción de mercancía.');
        }

        header('Location: ' . url('purchases/edit/' . $id));
        exit;
    }

    public function cancel($id)
    {
        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if (in_array($before['state'], ['recibida', 'cancelada'], true)) {
            flash('error', 'La orden #' . $id . ' ya está ' . Purchase::estadoTexto($before['state']) . '.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($this->purchaseModel->cancel($id)) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('cancel', 'purchase_orders', $id, $before, $after ?: null, 'Orden de compra #' . $id . ' cancelada.');
            flash('success', 'Orden de compra #' . $id . ' cancelada.');
        } else {
            flash('error', 'No se pudo cancelar la orden de compra.');
        }

        header('Location: ' . url('purchases'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        $receipts = $this->purchaseModel->countReceipts($id);

        if ($receipts > 0) {
            flash('error', 'No se puede eliminar: la orden #' . $id . ' tiene ' . $receipts . ' recepción(es) de mercancía registrada(s). Cancélala en su lugar.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($this->purchaseModel->delete($id)) {
            $this->auditModel->write('delete', 'purchase_orders', $id, $before, null, 'Orden de compra #' . $id . ' eliminada.');
            flash('success', 'Orden de compra #' . $id . ' eliminada.');
        } else {
            flash('error', 'No se pudo eliminar la orden de compra.');
        }

        header('Location: ' . url('purchases'));
        exit;
    }

    /**
     * Valida la cabecera del POST. Devuelve null (con el flash puesto) si algo
     * no cuadra, para que la vista redibuje el formulario.
     */
    private function datosDeCabecera()
    {
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $orderDate = trim($_POST['order_date'] ?? '');
        $estimated = trim($_POST['estimated_delivery_date'] ?? '');

        if ($supplierId <= 0 || !$this->purchaseModel->supplierExists($supplierId)) {
            flash('error', 'Selecciona un proveedor válido.');
            return null;
        }

        if ($orderDate === '') {
            flash('error', 'La fecha de la orden es obligatoria.');
            return null;
        }

        if (!$this->fechaValida($orderDate)) {
            flash('error', 'La fecha de la orden no tiene un formato válido.');
            return null;
        }

        if ($estimated !== '' && !$this->fechaValida($estimated)) {
            flash('error', 'La fecha estimada de entrega no tiene un formato válido.');
            return null;
        }

        if ($estimated !== '' && $estimated < $orderDate) {
            flash('error', 'La entrega estimada no puede ser anterior a la fecha de la orden.');
            return null;
        }

        $employeeId = $this->employeeIdActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión.');
            return null;
        }

        return [
            'supplier_id' => $supplierId,
            'employee_id' => $employeeId,
            'order_date' => $orderDate,
            'estimated_delivery_date' => $estimated,
        ];
    }

    /**
     * Normaliza las líneas del POST: Descarta las filas vacías y descarta
     * ingredientes repetidos (se suman las cantidades). Devuelve null con flash
     * si no queda ninguna línea válida.
     */
    private function itemsDelPost()
    {
        $ingredientIds = $_POST['ingredient_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['unit_price'] ?? [];

        if (!is_array($ingredientIds) || !is_array($quantities) || !is_array($prices)) {
            flash('error', 'Las líneas de la orden no se recibieron correctamente.');
            return null;
        }

        $items = [];
        $vistos = [];

        foreach ($ingredientIds as $i => $rawId) {
            $ingredientId = (int) $rawId;

            // Fila vacía: el usuario añadió una línea y la borró.
            if ($ingredientId <= 0) {
                continue;
            }

            if (!$this->ingredientModel->isActive($ingredientId)) {
                flash('error', 'Uno de los ingredientes seleccionados no está activo.');
                return null;
            }

            $quantity = (float) str_replace(',', '.', (string) ($quantities[$i] ?? 0));
            $price = (float) str_replace(',', '.', (string) ($prices[$i] ?? 0));

            if ($quantity <= 0) {
                flash('error', 'Indica una cantidad mayor que cero para cada ingrediente.');
                return null;
            }

            if ($price < 0) {
                flash('error', 'El precio unitario no puede ser negativo.');
                return null;
            }

            if (isset($vistos[$ingredientId])) {
                // Mismo ingrediente en dos filas: se acumula para no duplicar
                // purchase_order_details (el detalle es único por orden).
                $items[$vistos[$ingredientId]]['quantity'] += $quantity;
                continue;
            }

            $vistos[$ingredientId] = count($items);
            $items[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        if (!$items) {
            flash('error', 'Agrega al menos un ingrediente a la orden.');
            return null;
        }

        return $items;
    }

    private function fechaValida($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }

    private function employeeIdActual()
    {
        $id = (int) ($_SESSION['user']['id'] ?? 0);

        return $id > 0 ? $id : null;
    }
}
