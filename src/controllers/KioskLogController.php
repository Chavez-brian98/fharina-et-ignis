<?php

require_once __DIR__ . '/../models/Attendance.php';

/**
 * Registros del Quiosco (/kiosk_log).
 *
 * Listado de las marcaciones que hizo el quiosco: empleado, entrada, inicio y
 * fin de lonche, salida y el metodo con que se identifico (QR o rostro).
 *
 * Es un modulo de solo lectura con gate normal (a diferencia de /kiosco, que
 * index.php exenta porque es la pantalla de pared): sin fila en
 * role_permissions se ve un 403 salvo para el administrador.
 *
 * El filtro por fecha y origen va por GET porque hay que volver a consultar la
 * base; la busqueda de texto es client-side, igual que en los demas modulos.
 */
class KioskLogController
{
    /** Dias que abre el rango por defecto (lunes a domingo si hoy es domingo). */
    const DIAS_POR_DEFECTO = 6;

    /** Dias maximos que permite consultar de una vez. */
    const DIAS_MAXIMOS = 92;

    private $db;
    private $attendanceModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->attendanceModel = new Attendance($db);
    }

    public function index()
    {
        $hoy = Attendance::fechaDeMySQL();

        $desde = $this->fecha($_GET['desde'] ?? '', date('Y-m-d', strtotime($hoy . ' -' . self::DIAS_POR_DEFECTO . ' days')));
        $hasta = $this->fecha($_GET['hasta'] ?? '', $hoy);

        // Rango invertido (hasta < desde) o demasiado largo: se recortan en vez
        // de dejar el formulario mandar una consulta de meses.
        if ($desde > $hasta) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }
        $maxDesde = date('Y-m-d', strtotime($hasta . ' -' . self::DIAS_MAXIMOS . ' days'));
        if ($desde < $maxDesde) {
            $desde = $maxDesde;
        }

        $origen = (string) ($_GET['origen'] ?? '');
        if (!in_array($origen, ['kiosco', 'manual', 'todos'], true)) {
            $origen = 'kiosco';
        }

        $registros = array_map(
            [Attendance::class, 'decorar'],
            $this->attendanceModel->registros($desde, $hasta, $origen)
        );
        $resumen = $this->attendanceModel->resumenRegistros($desde, $hasta, $origen);

        $title = 'Registros del Quiosco';
        $currentModule = 'kiosk_log';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Registros del Quiosco'],
        ];

        require __DIR__ . '/../views/kiosk_log/index.php';
    }

    /**
     * Fecha YYYY-MM-DD o $porDefecto si viene vacia o mal formada.
     */
    private function fecha($valor, $porDefecto)
    {
        $valor = trim((string) $valor);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) || !strtotime($valor)) {
            return $porDefecto;
        }

        return $valor;
    }
}
