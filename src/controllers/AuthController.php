<?php

require_once __DIR__ . '/../models/User.php';

class AuthController
{
    private $userModel;

    public function __construct($db)
    {
        $this->userModel = new User($db);
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
                flash('error', 'Correo o contraseña incorrectos.');
                header('Location: ' . url('auth/login'));
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role_id' => (int) $user['role_id'],
                'role' => $user['role_name'],
            ];

            flash('success', 'Bienvenido de nuevo, ' . $user['username'] . '.');
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
        session_unset();
        session_destroy();
        header('Location: ' . url('auth/login'));
        exit;
    }
}