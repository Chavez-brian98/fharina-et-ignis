<?php

require_once __DIR__ . '/../models/Client.php';

class ClientController
{
    private $db;
    private $clientModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->clientModel = new Client($db);
    }

    public function index()
    {
        $clients = $this->clientModel->getAll();
        $title = 'Clientes';
        $currentModule = 'clients';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Clientes', 'url' => null],
        ];

        require_once __DIR__ . '/../views/clients/index.php';
    }

    public function create()
    {
        $title = 'Nuevo Cliente';
        $currentModule = 'clients';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Clientes', 'url' => url('clients')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/clients/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('clients'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;

        if ($name === '' || $last_name === '') {
            flash('error', 'Los campos nombre y apellido son obligatorios.');
            header('Location: ' . url('clients/create'));
            exit;
        }

        if ($email !== '' && $this->clientModel->emailExists($email)) {
            flash('error', 'Ya existe un cliente registrado con ese correo.');
            header('Location: ' . url('clients/create'));
            exit;
        }

        if ($this->clientModel->create($name, $last_name, $phone ?: null, $email ?: null, $address ?: null, $birth_date)) {
            flash('success', 'Cliente creado correctamente.');
        } else {
            flash('error', 'No se pudo crear el cliente.');
        }

        header('Location: ' . url('clients'));
        exit;
    }

    public function edit($id)
    {
        $client = $this->clientModel->getById($id);

        if (!$client) {
            flash('error', 'Cliente no encontrado.');
            header('Location: ' . url('clients'));
            exit;
        }

        $title = 'Editar Cliente';
        $currentModule = 'clients';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Clientes', 'url' => url('clients')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/clients/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('clients'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $last_name === '') {
            flash('error', 'Los campos nombre y apellido son obligatorios.');
            header('Location: ' . url('clients/edit/' . $id));
            exit;
        }

        if ($email !== '' && $this->clientModel->emailExists($email, $id)) {
            flash('error', 'Ya existe otro cliente registrado con ese correo.');
            header('Location: ' . url('clients/edit/' . $id));
            exit;
        }

        if ($this->clientModel->update($id, $name, $last_name, $phone ?: null, $email ?: null, $address ?: null, $birth_date, $status)) {
            flash('success', 'Cliente actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el cliente.');
        }

        header('Location: ' . url('clients'));
        exit;
    }

    public function toggle($id)
    {
        if ($this->clientModel->toggleStatus($id)) {
            flash('success', 'Estado del cliente actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del cliente.');
        }

        header('Location: ' . url('clients'));
        exit;
    }

    public function delete($id)
    {
        if ($this->clientModel->delete($id)) {
            flash('success', 'Cliente eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el cliente. Asegúrate de que no tenga pedidos u otros registros asociados.');
        }

        header('Location: ' . url('clients'));
        exit;
    }
}