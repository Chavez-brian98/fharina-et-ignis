<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ProfileController
{
    private $db;
    private $userModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $user = $this->userModel->findById((int) ($_SESSION['user']['id'] ?? 0));

        if (!$user || $user['status'] !== 'active') {
            flash('error', 'Tu cuenta ya no está activa.');
            header('Location: ' . url('auth/logout'));
            exit;
        }

        $title = 'Mi Perfil';
        $currentModule = 'profile';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Mi Perfil', 'url' => null],
        ];

        require_once __DIR__ . '/../views/profile/edit.php';
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('profile'));
            exit;
        }

        $id = (int) ($_SESSION['user']['id'] ?? 0);
        $user = $this->userModel->findById($id);

        if (!$user) {
            flash('error', 'No se encontró tu cuenta.');
            header('Location: ' . url('auth/logout'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($name === '' || $last_name === '' || $email === '') {
            flash('error', 'Los campos nombre, apellido y correo electrónico son obligatorios.');
            header('Location: ' . url('profile'));
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'El correo electrónico no es válido.');
            header('Location: ' . url('profile'));
            exit;
        }

        if ($password !== '' && strlen($password) < 6) {
            flash('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
            header('Location: ' . url('profile'));
            exit;
        }

        if ($this->userModel->emailExists($email, $id)) {
            flash('error', 'Ya existe otra cuenta con ese correo electrónico.');
            header('Location: ' . url('profile'));
            exit;
        }

        if ($this->userModel->emailExists($email, $id)) {
            flash('error', 'Ya existe otra cuenta con ese correo electrónico.');
            header('Location: ' . url('profile'));
            exit;
        }

        $photo = upload_image('profile_photo');
        if ($photo === false) {
            header('Location: ' . url('profile'));
            exit;
        }

        $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

        if ($this->userModel->updateProfile($id, $name, $last_name, $email, $phone ?: null, $address ?: null, $photo, $passwordHash)) {
            $after = $this->userModel->findById($id);
            $this->auditModel->write('update', 'empleados', $id, $user, $after ?: null, 'Perfil actualizado.');

            $_SESSION['user'] = [
                'id' => $id,
                'email' => $email,
                'name' => $name,
                'last_name' => $last_name,
                'profile_photo' => $photo !== null ? $photo : $user['profile_photo'],
                'role' => $user['role'],
            ];

            flash('success', 'Perfil actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el perfil.');
        }

        header('Location: ' . url('profile'));
        exit;
    }
}