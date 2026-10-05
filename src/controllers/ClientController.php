<?php

require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ClientController
{
    private $db;
    private $clientModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->clientModel = new Client($db);
        $this->auditModel = new AuditLog($db);
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
        $idDocument = trim($_POST['id_document'] ?? '');
        $clientType = $_POST['client_type'] ?? 'persona';
        $clientType = in_array($clientType, ['persona', 'empresa'], true) ? $clientType : 'persona';
        $companyName = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;

        if ($clientType === 'empresa') {
            $name = $companyName;
            $last_name = '';
            $birth_date = null;
        }

        if ($clientType === 'empresa') {
            if ($companyName === '') {
                flash('error', 'Ingresa el nombre de la empresa.');
                header('Location: ' . url('clients/create'));
                exit;
            }
        } else {
            if ($name === '' || $last_name === '') {
                flash('error', 'Los campos nombre y apellido son obligatorios.');
                header('Location: ' . url('clients/create'));
                exit;
            }
        }

        if ($email !== '' && $this->clientModel->emailExists($email)) {
            flash('error', 'Ya existe un cliente registrado con ese correo.');
            header('Location: ' . url('clients/create'));
            exit;
        }

        if ($idDocument !== '' && $this->clientModel->documentExists($idDocument)) {
            flash('error', 'Ya existe un cliente registrado con ese documento.');
            header('Location: ' . url('clients/create'));
            exit;
        }

        $profilePhoto = upload_image('profile_photo');

        if ($profilePhoto === false) {
            header('Location: ' . url('clients/create'));
            exit;
        }

        if ($this->clientModel->create($name, $last_name, $idDocument ?: null, $clientType, $clientType === 'empresa' ? $companyName : null, $phone ?: null, $email ?: null, $address ?: null, $birth_date, $profilePhoto)) {
            $recordId = (int) $this->db->lastInsertId();
            $new = $this->clientModel->getById($recordId);
            $this->auditModel->write('create', 'clients', $recordId, null, $new ?: null, 'Cliente creado.');
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
        $idDocument = trim($_POST['id_document'] ?? '');
        $clientType = $_POST['client_type'] ?? 'persona';
        $clientType = in_array($clientType, ['persona', 'empresa'], true) ? $clientType : 'persona';
        $companyName = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;
        $status = $_POST['status'] ?? 'active';

        if ($clientType === 'empresa') {
            $name = $companyName;
            $last_name = '';
            $birth_date = null;
        }

        if ($clientType === 'empresa') {
            if ($companyName === '') {
                flash('error', 'Ingresa el nombre de la empresa.');
                header('Location: ' . url('clients/edit/' . $id));
                exit;
            }
        } else {
            if ($name === '' || $last_name === '') {
                flash('error', 'Los campos nombre y apellido son obligatorios.');
                header('Location: ' . url('clients/edit/' . $id));
                exit;
            }
        }

        if ($email !== '' && $this->clientModel->emailExists($email, $id)) {
            flash('error', 'Ya existe otro cliente registrado con ese correo.');
            header('Location: ' . url('clients/edit/' . $id));
            exit;
        }

        if ($idDocument !== '' && $this->clientModel->documentExists($idDocument, $id)) {
            flash('error', 'Ya existe otro cliente registrado con ese documento.');
            header('Location: ' . url('clients/edit/' . $id));
            exit;
        }

        $before = $this->clientModel->getById($id);

        $profilePhoto = upload_image('profile_photo');

        if ($profilePhoto === false) {
            header('Location: ' . url('clients/edit/' . $id));
            exit;
        }

        if ($profilePhoto === null) {
            $profilePhoto = isset($_POST['remove_profile_photo']) ? null : (($before['profile_photo'] ?? null) ?: null);
        }

        if ($this->clientModel->update($id, $name, $last_name, $idDocument ?: null, $clientType, $clientType === 'empresa' ? $companyName : null, $phone ?: null, $email ?: null, $address ?: null, $birth_date, $status, $profilePhoto)) {
            $after = $this->clientModel->getById($id);
            $this->auditModel->write('update', 'clients', $id, $before, $after ?: null, 'Cliente actualizado.');
            flash('success', 'Cliente actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el cliente.');
        }

        header('Location: ' . url('clients'));
        exit;
    }

    public function toggle($id)
    {
        $before = $this->clientModel->getById($id);

        if ($this->clientModel->toggleStatus($id)) {
            $after = $this->clientModel->getById($id);
            $this->auditModel->write('toggle', 'clients', $id, $before, $after ?: null, 'Estado del cliente actualizado.');
            flash('success', 'Estado del cliente actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del cliente.');
        }

        header('Location: ' . url('clients'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->clientModel->getById($id);

        if ($this->clientModel->delete($id)) {
            $this->auditModel->write('delete', 'clients', $id, $before ?: null, null, 'Cliente eliminado.');
            flash('success', 'Cliente eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el cliente. Asegúrate de que no tenga pedidos u otros registros asociados.');
        }

        header('Location: ' . url('clients'));
        exit;
    }
}