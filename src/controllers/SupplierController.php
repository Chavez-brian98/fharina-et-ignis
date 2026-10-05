<?php

require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/AuditLog.php';

class SupplierController
{
    private $db;
    private $supplierModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->supplierModel = new Supplier($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $suppliers = $this->supplierModel->getAll();

        $title = 'Proveedores';
        $currentModule = 'suppliers';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Proveedores', 'url' => null],
        ];

        require_once __DIR__ . '/../views/suppliers/index.php';
    }

    public function create()
    {
        $title = 'Nuevo Proveedor';
        $currentModule = 'suppliers';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Proveedores', 'url' => url('suppliers')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/suppliers/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('suppliers'));
            exit;
        }

        $data = $this->datosDelPost();

        if ($data === null) {
            header('Location: ' . url('suppliers/create'));
            exit;
        }

        if ($this->supplierModel->create($data)) {
            $recordId = (int) $this->db->lastInsertId();
            $new = $this->supplierModel->getById($recordId);
            $this->auditModel->write('create', 'proveedores', $recordId, null, $new ?: null, 'Proveedor creado.');
            flash('success', 'Proveedor creado correctamente.');
        } else {
            flash('error', 'No se pudo crear el proveedor.');
        }

        header('Location: ' . url('suppliers'));
        exit;
    }

    public function edit($id)
    {
        $supplier = $this->supplierModel->getById($id);

        if (!$supplier) {
            flash('error', 'Proveedor no encontrado.');
            header('Location: ' . url('suppliers'));
            exit;
        }

        $title = 'Editar Proveedor';
        $currentModule = 'suppliers';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Proveedores', 'url' => url('suppliers')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/suppliers/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('suppliers'));
            exit;
        }

        $before = $this->supplierModel->getById($id);

        if (!$before) {
            flash('error', 'Proveedor no encontrado.');
            header('Location: ' . url('suppliers'));
            exit;
        }

        $data = $this->datosDelPost($id);

        if ($data === null) {
            header('Location: ' . url('suppliers/edit/' . $id));
            exit;
        }

        if ($this->supplierModel->update($id, $data)) {
            $after = $this->supplierModel->getById($id);
            $this->auditModel->write('update', 'proveedores', $id, $before, $after ?: null, 'Proveedor actualizado.');
            flash('success', 'Proveedor actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el proveedor.');
        }

        header('Location: ' . url('suppliers'));
        exit;
    }

    public function toggle($id)
    {
        $before = $this->supplierModel->getById($id);

        if ($this->supplierModel->toggleStatus($id)) {
            $after = $this->supplierModel->getById($id);
            $this->auditModel->write('toggle', 'proveedores', $id, $before, $after ?: null, 'Estado del proveedor actualizado.');
            flash('success', 'Estado del proveedor actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del proveedor.');
        }

        header('Location: ' . url('suppliers'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->supplierModel->getById($id);

        if (!$before) {
            flash('error', 'Proveedor no encontrado.');
            header('Location: ' . url('suppliers'));
            exit;
        }

        $refs = $this->supplierModel->countReferences($id);
        $motivos = [];

        if ($refs['ingredientes'] > 0) {
            $motivos[] = $refs['ingredientes'] . ' ingrediente(s) como proveedor principal';
        }
        if ($refs['pedidos'] > 0) {
            $motivos[] = $refs['pedidos'] . ' pedido(s) de compra';
        }
        if ($refs['precios'] > 0) {
            $motivos[] = $refs['precios'] . ' registro(s) de historial de precios';
        }

        if ($motivos) {
            // "a", "a y b", "a, b y c"
            $lista = count($motivos) > 1
                ? implode(', ', array_slice($motivos, 0, -1)) . ' y ' . end($motivos)
                : $motivos[0];
            flash('error', 'No se puede eliminar: el proveedor tiene ' . $lista . ' asociados. Desactívalo en su lugar.');
            header('Location: ' . url('suppliers'));
            exit;
        }

        if ($this->supplierModel->delete($id)) {
            $this->auditModel->write('delete', 'proveedores', $id, $before, null, 'Proveedor eliminado.');
            flash('success', 'Proveedor eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el proveedor.');
        }

        header('Location: ' . url('suppliers'));
        exit;
    }

    /**
     * Normaliza y valida el POST de alta/edición. Devuelve null (y deja el
     * flash puesto) cuando algo no cuadra, para que la vista redibuje el
     * formulario con lo que el usuario escribió.
     */
    private function datosDelPost($excludeId = null)
    {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '') {
            flash('error', 'El nombre de la empresa es obligatorio.');
            return null;
        }

        if (mb_strlen($name) > 120) {
            flash('error', 'El nombre de la empresa no puede superar los 120 caracteres.');
            return null;
        }

        if ($this->supplierModel->nameExists($name, $excludeId)) {
            flash('error', 'Ya existe un proveedor con ese nombre.');
            return null;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El correo electrónico no tiene un formato válido.');
            return null;
        }

        if ($this->supplierModel->emailExists($email, $excludeId)) {
            flash('error', 'Ese correo ya está registrado en otro proveedor.');
            return null;
        }

        return [
            'name' => $name,
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'supplier_type' => trim($_POST['supplier_type'] ?? ''),
            'contact' => trim($_POST['contact'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => $email,
            'address' => trim($_POST['address'] ?? ''),
            'supplies' => trim($_POST['supplies'] ?? ''),
            'availability_days' => Supplier::normalizarDias($_POST['availability_days'] ?? []),
            'payment_terms' => trim($_POST['payment_terms'] ?? ''),
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }
}