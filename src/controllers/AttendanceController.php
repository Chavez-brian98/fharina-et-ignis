<?php

require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Mi Asistencia: consulta de la jornada propia del empleado.
 *
 * Es un modulo 'always' (ver src/config/permisos.php): todos los roles lo
 * tienen, igual que Mi Perfil. Solo opera sobre el empleado de la sesion,
 * nunca sobre otro, asi que no necesita 'edit' de supervisory.
 *
 * ESTE MODULO NO MARCA. Los cuatro endpoints de marcacion (checkin, checkout,
 * breakStart, breakEnd) rebotan con un flash: la entrada, la salida y los
 * descansos los registra unicamente el quiosco (KioskController), con QR o
 * rostro. Esto es solo lectura: turno del dia, salida estimada, QR e historial.
 */
class AttendanceController
{
    private $db;
    private $attendanceModel;
    private $shiftModel;
    private $employeeModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->attendanceModel = new Attendance($db);
        $this->shiftModel = new Shift($db);
        $this->employeeModel = new Employee($db);
        $this->auditModel = new AuditLog($db);
    }

    private function employeeId()
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    /**
     * Panel del dia: turno, salida estimada, marcaciones, QR e historial.
     */
    public function index()
    {
        $id = $this->employeeId();
        if ($id <= 0) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        $hoy = Attendance::fechaDeMySQL();

        $registro = Attendance::decorar($this->attendanceModel->deEmpleado($id, $hoy));
        $turno = $this->shiftModel->deEmpleado($id, $hoy);

        // Sin marcacion pero con turno asignado: se arma una fila virtual para
        // poder pintar la salida estimada sin escribir nada en la base.
        if ($registro === null && $turno !== null) {
            $registro = Attendance::decorar([
                'id' => null,
                'employee_id' => $id,
                'attendance_date' => $hoy,
                'check_in' => null,
                'check_out' => null,
                'break_start' => null,
                'break_end' => null,
                'state' => 'ausente',
                'check_in_method' => null,
                'check_out_method' => null,
                'start_time' => $turno['start_time'],
                'end_time' => $turno['end_time'],
                'shift_type' => $turno['shift_type'],
            ]);
        }

        $title = 'Mi Asistencia';
        $currentModule = 'attendance';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Mi Asistencia'],
        ];

        $historial = array_map(
            [Attendance::class, 'decorar'],
            $this->attendanceModel->historial($id, 14)
        );

        $empleado = $this->employeeModel->getById($id);
        $qrToken = $this->employeeModel->qrToken($id);

        require __DIR__ . '/../views/attendance/index.php';
    }

    public function checkin()
    {
        $this->marcar('entrada');
    }

    public function checkout()
    {
        $this->marcar('salida');
    }

    public function breakStart()
    {
        $this->marcar('descanso_inicio');
    }

    public function breakEnd()
    {
        $this->marcar('descanso_fin');
    }

    /**
     * Las marcaciones propias estan dadas de baja: se hacen solo en el quiosco.
     *
     * Los cuatro endpoints se conservan (para que un bookmar viejo no devuelva
     * 404) pero siempre rebotan. Sin esta guarda, un POST directo a
     * attendance/checkin seguia marcando igual que antes de sacar los botones.
     */
    private function marcar($tipo)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('attendance'));
            exit;
        }

        $id = $this->employeeId();
        if ($id <= 0) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        flash('warning', 'Las marcaciones se hacen en el quiosco (/kiosco), con tu QR o con tu rostro.');

        header('Location: ' . url('attendance'));
        exit;
    }

    /**
     * Emite un QR nuevo e invalida el impreso anterior.
     */
    public function regenerateQr()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('attendance'));
            exit;
        }

        $id = $this->employeeId();
        if ($id <= 0) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        $antes = ['qr_token' => '********'];
        $this->employeeModel->regenerateQr($id);
        $despues = ['qr_token' => '********'];

        $this->auditModel->write(
            'update',
            'empleados',
            $id,
            $antes,
            $despues,
            'Regeneracion manual del QR de asistencia',
            $id
        );

        flash('success', 'Se generó un código QR nuevo. El anterior ya no sirve.');

        header('Location: ' . url('attendance'));
        exit;
    }
}