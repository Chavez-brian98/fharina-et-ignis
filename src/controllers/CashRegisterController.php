<?php

require_once __DIR__ . '/../models/CashRegister.php';
require_once __DIR__ . '/../models/CashMovement.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Módulo Caja.
 *
 * Reglas de negocio:
 *  - Un empleado no puede tener dos cajas abiertas (garantizado por el UNIQUE
 *    uq_cash_open_per_employee de la tabla caja).
 *  - El fondo inicial por defecto sale de la configuración `cash_register_base`
 *    (125.00). Quien tiene permiso Crear puede ajustarlo; el cajero que abre su
 *    propia caja usa siempre el fondo configurado.
 *  - Reabrir una caja cerrada exige el permiso Editar del módulo (o ser admin),
 *    un motivo y queda en la bitácora.
 *  - El cajero sin caja asignada abre la suya reingresando su contraseña.
 */
class CashRegisterController
{
    private $db;
    private $registerModel;
    private $movementModel;
    private $employeeModel;
    private $userModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->registerModel = new CashRegister($db);
        $this->movementModel = new CashMovement($db);
        $this->employeeModel = new Employee($db);
        $this->userModel = new User($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $employeeId = (int) ($_SESSION['user']['id'] ?? 0);

        $miCaja = $this->registerModel->getOpenByEmployee($employeeId);
        $miResumen = $miCaja ? $this->registerModel->getSummary($miCaja['id']) : null;
        $misCajas = $this->registerModel->getByEmployee($employeeId, 30);

        // El panel de supervisión (abrir/asignar y reabrir) pide Editar.
        $puedeGestionar = puede('cash_register', 'edit');

        $cajasAbiertas = $puedeGestionar ? $this->registerModel->getOpen() : [];
        $cajas = $puedeGestionar ? $this->registerModel->getAll(150) : $misCajas;

        // Sólo pueden recibir una caja los empleados con rol cajero.
        $cajeros = $puedeGestionar ? $this->empleadosCajeros() : [];

        $fondoBase = (float) setting('cash_register_base', 125);

        $title = 'Caja';
        $currentModule = 'cash_register';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Caja', 'url' => null],
        ];

        require_once __DIR__ . '/../views/cash_register/index.php';
    }

    /**
     * Abre una caja. Sin `employee_id` la abre el usuario en sesión (tras
     * revalidar su contraseña); con `employee_id` se la asigna a un cajero
     * concreto y eso exige el permiso Editar.
     */
    public function open()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('cash_register'));
            exit;
        }

        $actorId = (int) ($_SESSION['user']['id'] ?? 0);
        $actor = $this->userModel->findById($actorId);

        if (!$actor || $actor['status'] !== 'active') {
            flash('error', 'Tu cuenta ya no está activa.');
            header('Location: ' . url('auth/logout'));
            exit;
        }

        $paraOtro = isset($_POST['employee_id']) && (int) $_POST['employee_id'] > 0
            && (int) $_POST['employee_id'] !== $actorId;

        if ($paraOtro && !puede('cash_register', 'edit')) {
            flash('error', 'No tienes permiso para abrir una caja a nombre de otra persona.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $targetId = $paraOtro ? (int) $_POST['employee_id'] : $actorId;
        $target = $this->userModel->findById($targetId);

        if (!$target || $target['status'] !== 'active' || $target['role_status'] !== 'active') {
            flash('error', 'El empleado seleccionado no existe o está inactivo.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        // Asignar: sólo un cajero puede recibir la caja. Operar en el propio
        // turno: el cajero, y quien administra el módulo (admin / sub jefe).
        $puedeOperar = $paraOtro
            ? $this->esCajero($target)
            : ($this->esCajero($target) || puede('cash_register', 'edit'));

        if (!$puedeOperar) {
            flash('error', $paraOtro
                ? 'Sólo se puede asignar una caja a un empleado con rol cajero.'
                : 'Sólo los cajeros pueden abrir su propia caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($this->registerModel->getOpenByEmployee($targetId)) {
            flash('error', trim($target['name'] . ' ' . $target['last_name']) . ' ya tiene una caja abierta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        // Quien abre su propia caja debe reingresar su contraseña.
        if (!$paraOtro && !$this->verificarPassword($actor, $_POST['password'] ?? '')) {
            $this->auditModel->write(
                'cash_open_denied',
                'caja',
                null,
                null,
                ['empleado' => trim($target['name'] . ' ' . $target['last_name'])],
                'Apertura de caja cancelada: contraseña incorrecta.'
            );
            flash('error', 'La contraseña no coincide. Vuelve a intentarlo.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        // Quien abre la suya usa siempre el fondo configurado; al asignarla se
        // puede ajustar el monto.
        $fondoBase = (float) setting('cash_register_base', 125);
        $initial = $paraOtro ? ($_POST['initial_amount'] ?? $fondoBase) : $fondoBase;
        $initial = round((float) $initial, 2);

        if ($initial < 0) {
            flash('error', 'El fondo inicial no puede ser negativo.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        try {
            $cajaId = $this->registerModel->open($targetId, $initial);
        } catch (PDOException $e) {
            // 1062 = el UNIQUE de caja abierta por empleado saltó (carrera).
            flash('error', 'Ese empleado ya tiene una caja abierta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if (!$cajaId) {
            flash('error', 'No se pudo abrir la caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $caja = $this->registerModel->getById($cajaId);
        $this->auditModel->write(
            $paraOtro ? 'cash_open_assign' : 'cash_open',
            'caja',
            $cajaId,
            null,
            [
                'caja' => $cajaId,
                'empleado' => trim($target['name'] . ' ' . $target['last_name']),
                'fondo' => $initial,
                'abierta_por' => trim($actor['name'] . ' ' . $actor['last_name']),
            ],
            $paraOtro ? 'Caja abierta y asignada a un cajero.' : 'Caja propia abierta.'
        );

        flash(
            'success',
            $paraOtro
                ? 'Caja abierta para ' . trim($target['name'] . ' ' . $target['last_name']) . '.'
                : 'Tu caja quedó abierta con fondo de $' . number_format($initial, 2) . '.'
        );

        header('Location: ' . url('cash_register'));
        exit;
    }

    /**
     * Cierra la caja con el conteo físico del efectivo.
     */
    public function close()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('cash_register'));
            exit;
        }

        $actorId = (int) ($_SESSION['user']['id'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);
        $caja = $this->registerModel->getById($id);

        if (!$caja) {
            flash('error', 'La caja no existe.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        // Cada quien cierra la suya; otro rol necesita Editar.
        if ((int) $caja['opening_employee_id'] !== $actorId && !puede('cash_register', 'edit')) {
            flash('error', 'Sólo puedes cerrar tu propia caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($caja['state'] !== 'abierta') {
            flash('error', 'Esa caja ya está cerrada.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $physical = $_POST['physical_final_amount'] ?? '';

        if ($physical === '' || !is_numeric($physical)) {
            flash('error', 'Ingresa el monto físico contado en la caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $resumen = $this->registerModel->getSummary($id);
        $esperado = (float) $resumen['expected_cash'];

        if (!$this->registerModel->close($id, (float) $physical, $actorId)) {
            flash('error', 'No se pudo cerrar la caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $cerrada = $this->registerModel->getById($id);
        $this->auditModel->write(
            'cash_close',
            'caja',
            $id,
            ['estado' => 'abierta', 'esperado' => $esperado],
            [
                'estado' => 'cerrada',
                'esperado' => (float) $cerrada['system_final_amount'],
                'fisico' => (float) $cerrada['physical_final_amount'],
                'diferencia' => (float) $cerrada['difference'],
            ],
            'Caja cerrada.'
        );

        $diff = (float) $cerrada['difference'];
        $cierre = 'Caja cerrada. Esperado $' . number_format((float) $cerrada['system_final_amount'], 2)
            . ', contado $' . number_format((float) $cerrada['physical_final_amount'], 2) . '.';

        if (abs($diff) < 0.005) {
            flash('success', $cierre . ' Cuadra perfecto.');
        } elseif ($diff > 0) {
            flash('info', $cierre . ' Sobrante de $' . number_format($diff, 2) . '.');
        } else {
            flash('warning', $cierre . ' Faltante de $' . number_format(abs($diff), 2) . '.');
        }

        header('Location: ' . url('cash_register'));
        exit;
    }

    /**
     * Reabre una caja cerrada. Exige Editar (o admin) + motivo.
     */
    public function reopen()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('cash_register'));
            exit;
        }

        if (!puede('cash_register', 'edit')) {
            flash('error', 'No tienes permiso para reabrir cajas.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $actorId = (int) ($_SESSION['user']['id'] ?? 0);
        $id = (int) ($_POST['id'] ?? 0);
        $motivo = trim($_POST['reason'] ?? '');
        $caja = $this->registerModel->getById($id);

        if (!$caja) {
            flash('error', 'La caja no existe.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($caja['state'] !== 'cerrada') {
            flash('error', 'Esa caja ya está abierta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($motivo === '') {
            flash('error', 'Escribe el motivo de la reapertura.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        // La caja vuelve al empleado que la abrió; si ya tiene otra abierta no se toca.
        if ($this->registerModel->getOpenByEmployee($caja['opening_employee_id'])) {
            flash('error', 'Ese empleado ya tiene otra caja abierta. Ciérrala antes de reabrir esta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $antes = [
            'estado' => $caja['state'],
            'cerrada' => $caja['closing_time'],
            'reaperturas' => (int) $caja['reopen_count'],
        ];

        if (!$this->registerModel->reopen($id, $motivo, $actorId)) {
            flash('error', 'No se pudo reabrir la caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $despues = $this->registerModel->getById($id);
        $this->auditModel->write(
            'cash_reopen',
            'caja',
            $id,
            $antes,
            [
                'estado' => $despues['state'],
                'motivo' => $motivo,
                'reaperturas' => (int) $despues['reopen_count'],
            ],
            'Caja cerrada reabierta: ' . $motivo
        );

        flash('success', 'Caja #' . $id . ' reabierta.');
        header('Location: ' . url('cash_register'));
        exit;
    }

    /**
     * Ingreso extra o retiro de una caja abierta.
     */
    public function movement()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('cash_register'));
            exit;
        }

        $actorId = (int) ($_SESSION['user']['id'] ?? 0);
        $id = (int) ($_POST['cash_register_id'] ?? 0);
        $tipo = $_POST['movement_type'] ?? '';
        $motivo = trim($_POST['reason'] ?? '');
        $monto = $_POST['amount'] ?? '';

        if (!in_array($tipo, ['ingreso_extra', 'retiro'], true)) {
            flash('error', 'Tipo de movimiento no válido.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($monto === '' || !is_numeric($monto) || (float) $monto <= 0) {
            flash('error', 'Ingresa un monto mayor a cero.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ($motivo === '') {
            flash('error', 'Describe el motivo del movimiento.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $caja = $this->registerModel->getById($id);

        if (!$caja || $caja['state'] !== 'abierta') {
            flash('error', 'La caja no está abierta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if ((int) $caja['opening_employee_id'] !== $actorId && !puede('cash_register', 'edit')) {
            flash('error', 'Sólo puedes mover efectivo de tu propia caja.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $movementId = $this->movementModel->create($id, $actorId, $tipo, (float) $monto, $motivo);

        if (!$movementId) {
            flash('error', 'No se pudo registrar el movimiento.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $this->auditModel->write(
            'cash_movement',
            'cash_register_movements',
            $movementId,
            null,
            [
                'caja' => $id,
                'tipo' => $tipo,
                'monto' => round((float) $monto, 2),
                'motivo' => $motivo,
            ],
            $tipo === 'retiro' ? 'Retiro de caja.' : 'Ingreso extra a la caja.'
        );

        flash('success', $tipo === 'retiro' ? 'Retiro registrado.' : 'Ingreso extra registrado.');
        header('Location: ' . url('cash_register'));
        exit;
    }

    /**
     * Elimina una caja cerrada sin ventas asociadas.
     */
    public function delete($id)
    {
        $id = (int) $id;

        if (!puede('cash_register', 'delete')) {
            flash('error', 'No tienes permiso para eliminar cajas.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $caja = $this->registerModel->getById($id);

        if (!$caja) {
            flash('error', 'La caja no existe.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        if (!$this->registerModel->delete($id)) {
            flash('error', 'No se puede eliminar: la caja está abierta o tiene ventas asociadas.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        $this->auditModel->write(
            'delete',
            'caja',
            $id,
            ['caja' => $id, 'fondo' => (float) $caja['initial_amount']],
            null,
            'Caja eliminada.'
        );

        flash('success', 'Caja #' . $id . ' eliminada.');
        header('Location: ' . url('cash_register'));
        exit;
    }

    /**
     * Empleados que pueden recibir una caja: rol cajero, empleado y rol activos.
     */
    private function empleadosCajeros()
    {
        $stmt = $this->db->prepare(
            "SELECT e.id, e.name, e.last_name, e.email, e.role_id, r.name AS role,
                    (c.id IS NOT NULL) AS has_open_register
               FROM empleados e
               JOIN roles r ON r.id = e.role_id
               LEFT JOIN caja c ON c.opening_employee_id = e.id AND c.state = 'abierta'
              WHERE e.status = 'active'
                AND r.status = 'active'
                AND r.name = 'cajero'
              ORDER BY e.name ASC;"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Un cajero es el único perfil que puede recibir una caja.
     */
    private function esCajero($employee)
    {
        return isset($employee['role']) && $employee['role'] === 'cajero';
    }

    /**
     * Reautenticación: el cajero confirma su contraseña antes de abrir la caja.
     */
    private function verificarPassword($user, $password)
    {
        if (!is_string($password) || $password === '') {
            return false;
        }

        if (empty($user['password_hash'])) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }
}
