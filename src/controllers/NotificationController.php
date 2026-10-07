<?php

require_once __DIR__ . '/../models/Notificacion.php';

/**
 * Notificaciones internas del sistema (la campana del layout).
 *
 * - index: página completa con las notificaciones visibles del empleado.
 * - unread: JSON con el contador de no leídas (lo usa el badge de la campana
 *   para refrescarse sin recargar la página).
 * - leer/{id}: POST, marca una notificación como leída.
 * - leer:      POST, marca todo lo visible como leído.
 *
 * Es un módulo 'always' (permisos.php): no requiere fila en role_permissions y
 * cualquier empleado logueado puede ver su campana. El 403 de index.php no lo
 * frena porque `puede('notifications')` devuelve true para todos.
 */
class NotificationController
{
    private $db;
    private $notificaciones;

    public function __construct($db)
    {
        $this->db = $db;
        $this->notificaciones = new Notificacion($db);
    }

    public function index()
    {
        $employeeId = $this->empleadoActual();

        if (!$employeeId) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        $notificaciones = $this->notificaciones->para($employeeId, 150);
        $noLeidas = $this->notificaciones->noLeidas($employeeId);

        $title = 'Notificaciones';
        $currentModule = 'notifications';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Notificaciones', 'url' => null],
        ];

        require_once __DIR__ . '/../views/notifications/index.php';
    }

    /** GET: contador de no leídas para el badge de la campana. */
    public function unread()
    {
        $employeeId = $this->empleadoActual();

        $this->json([
            'no_leidas' => $employeeId ? $this->notificaciones->noLeidas($employeeId) : 0,
        ]);
    }

    /** POST: marca una (con id) o todas (sin id) como leídas. */
    public function leer($id = null)
    {
        $employeeId = $this->empleadoActual();

        if (!$employeeId) {
            $this->json(['ok' => false, 'mensaje' => 'Sesión no válida.'], 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
        }

        if ($id !== null) {
            $this->notificaciones->marcarLeida((int) $id, $employeeId);
        } else {
            $this->notificaciones->marcarTodasLeidas($employeeId);
        }

        $this->json([
            'ok' => true,
            'no_leidas' => $this->notificaciones->noLeidas($employeeId),
        ]);
    }

    private function empleadoActual()
    {
        $id = (int) ($_SESSION['user']['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function json($payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}