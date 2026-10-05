<?php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

class AuthController
{
    private $userModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->userModel = new User($db);
        $this->auditModel = new AuditLog($db);
    }

    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_SESSION['user'])) {
                header('Location: ' . url('dashboard'));
                exit;
            }

            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                flash('error', 'Ingresa tu correo y tu contraseña.');
                header('Location: ' . url('auth/login'));
                exit;
            }

            $user = $this->userModel->findByEmail($email);

            if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
                $this->auditModel->write('login_failed', null, null, null, null, 'Intento de inicio de sesión fallido: ' . $email);
                flash('error', 'Correo o contraseña incorrectos.');
                header('Location: ' . url('auth/login'));
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'profile_photo' => $user['profile_photo'],
                'role' => $user['role'],
                'role_id' => (int) $user['role_id'],
            ];

            $this->auditModel->write('login', null, (int) $user['id'], null, null, 'Inicio de sesión.', (int) $user['id']);

            flash('success', 'Bienvenido de nuevo, ' . $user['name'] . '.');
            header('Location: ' . url('dashboard'));
            exit;
        }

        if (isset($_SESSION['user'])) {
            header('Location: ' . url('dashboard'));
            exit;
        }

        $title = 'Iniciar sesión';
        require_once __DIR__ . '/../views/auth/login.php';
    }

    public function logout()
    {
        $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
        session_unset();
        session_destroy();
        $this->auditModel->write('logout', null, $userId, null, null, 'Cierre de sesión.', $userId);
        header('Location: ' . url('auth/login'));
        exit;
    }
}