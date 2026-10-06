<?php

require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Horarios: asignacion de turnos y supervision de la asistencia.
 *
 * A diferencia de Mi Asistencia (modulo 'always'), este si esta gateado por
 * roles: `schedules`. Quien solo tiene 'view' ve el roster pero no asigna
 * turnos ni corrige marcaciones.
 *
 * Las acciones usan el mapeo de Permiso::accionDeRuta():
 *   assign -> create   update -> edit   delete -> delete
 */
class ScheduleController
{
    private $db;
    private $shiftModel;
    private $attendanceModel;
    private $employeeModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->shiftModel = new Shift($db);
        $this->attendanceModel = new Attendance($db);
        $this->employeeModel = new Employee($db);
        $this->auditModel = new AuditLog($db);
    }

    private function employeeId()
    {
        return (int) ($_SESSION['user']['id'] ?? 0);
    }

    /**
     * Roster del dia + resumen + selector de fecha.
     */
    public function index()
    {
        $fecha = $_GET['fecha'] ?? Attendance::fechaDeMySQL();
        if (!$this->fechaValida($fecha)) {
            $fecha = Attendance::fechaDeMySQL();
        }

        $turnos = array_map(
            [Attendance::class, 'decorar'],
            $this->shiftModel->delDia($fecha)
        );

        // Empleados activos sin turno ese dia: aparecen como "sin turno" para
        // que el supervisor vea la cobertura real, no solo lo asignado.
        $conTurno = array_column($turnos, 'employee_id');
        $sinTurno = [];
        foreach ($this->empleadosActivos() as $e) {
            if (!in_array((int) $e['id'], array_map('intval', $conTurno), true)) {
                $sinTurno[] = $e;
            }
        }

        $resumen = [
            'asignados' => count($turnos),
            'presentes' => 0,
            'ausentes' => 0,
            'en_turno' => 0,
            'tarde' => 0,
            'cerrados' => 0,
        ];
        foreach ($turnos as $t) {
            if ($t['check_in'] === null) {
                $resumen['ausentes']++;
                continue;
            }
            $resumen['presentes']++;
            if ($t['check_out'] !== null) {
                $resumen['cerrados']++;
            } else {
                $resumen['en_turno']++;
            }
            if ($t['tarde']) {
                $resumen['tarde']++;
            }
        }

        $title = 'Horarios';
        $currentModule = 'schedules';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Horarios'],
        ];

        $semana = $this->semanaDe($fecha);
        $grilla = $this->shiftModel->grillaSemana($semana['desde'], $semana['hasta']);

        $puedeAsignar = puede('schedules', 'create');
        $puedeEditar = puede('schedules', 'edit');

        require __DIR__ . '/../views/schedules/index.php';
    }

    /**
     * Formulario de alta de turno.
     */
    public function create()
    {
        $title = 'Asignar Turno';
        $currentModule = 'schedules';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Horarios', 'url' => url('schedules')],
            ['label' => 'Asignar Turno'],
        ];

        $empleados = $this->empleadosActivos();
        $tipos = Shift::tipos();
        $fecha = $_GET['fecha'] ?? Attendance::fechaDeMySQL();

        $shift = [
            'id' => null,
            'employee_id' => '',
            'work_date' => $this->fechaValida($fecha) ? $fecha : Attendance::fechaDeMySQL(),
            'start_time' => '08:00',
            'end_time' => '16:00',
            'shift_type' => 'manana',
            'notes' => '',
        ];

        require __DIR__ . '/../views/schedules/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('schedules'));
            exit;
        }

        $id = $this->employeeId();
        if ($id <= 0) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        $data = $this->datosDelPost();

        if ($data === false) {
            header('Location: ' . url('schedules/create'));
            exit;
        }

        if ($this->shiftModel->yaTiene($data['employee_id'], $data['work_date'])) {
            flash('warning', 'Ese empleado ya tiene un turno asignado para esa fecha.');
            header('Location: ' . url('schedules/create?fecha=' . urlencode($data['work_date'])));
            exit;
        }

        $nuevoId = $this->shiftModel->create(
            $data['employee_id'],
            $data['work_date'],
            $data['start_time'],
            $data['end_time'],
            $data['shift_type'],
            $data['notes']
        );

        $this->auditModel->write('create', 'shifts', $nuevoId, false, $this->shiftModel->getById($nuevoId));

        flash('success', 'Turno asignado correctamente.');

        header('Location: ' . url('schedules?fecha=' . urlencode($data['work_date'])));
        exit;
    }

    /**
     * Asignacion masiva: el mismo turno para varios empleados durante un rango
     * de fechas. Evita tener que cargar el formulario turno por turno.
     */
    public function bulk()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('schedules/rango'));
            exit;
        }

        $registrador = $this->employeeId();
        if ($registrador <= 0) {
            header('Location: ' . url('auth/login'));
            exit;
        }

        $datos = $this->datosDelPostMasivo();
        if ($datos === false) {
            header('Location: ' . url('schedules/rango'));
            exit;
        }

        try {
            $res = $this->shiftModel->asignarRango(
                $datos['empleado_ids'],
                $datos['desde'],
                $datos['hasta'],
                $datos['start_time'],
                $datos['end_time'],
                $datos['shift_type'],
                $datos['notes'],
                $datos['dias'],
                $datos['sobrescribir']
            );
        } catch (Throwable $e) {
            flash('error', 'No se pudieron asignar los turnos: ' . $e->getMessage());
            header('Location: ' . url('schedules/rango'));
            exit;
        }

        // Una sola entrada de bitacora para toda la operacion: un turno por fila
        // llenaria la bitacora de N lineas identicas. El orden de argumentos de
        // AuditLog::write() es (accion, tabla, record_id, antes, despues, nota).
        $this->auditModel->write(
            'bulk',
            'shifts',
            null,
            false,
            [
                'desde' => $datos['desde'],
                'hasta' => $datos['hasta'],
                'empleados' => count($datos['empleado_ids']),
                'dias_semana' => $datos['dias'],
                'start_time' => $datos['start_time'],
                'end_time' => $datos['end_time'],
                'shift_type' => $datos['shift_type'],
                'creados' => $res['creados'],
                'omitidos' => $res['omitidos'],
                'sobrescritos' => $res['sobrescritos'],
            ],
            ($datos['sobrescribir'] ? 'Asignacion masiva (sobrescribe)' : 'Asignacion masiva')
            . ': ' . count($datos['empleado_ids']) . ' empleado(s), '
            . $datos['desde'] . ' a ' . $datos['hasta']
        );

        $flash = 'success';
        if ($res['creados'] === 0 && $res['sobrescritos'] === 0) {
            $flash = 'warning';
        } elseif ($res['omitidos'] > 0) {
            $flash = 'info';
        }

        $partes = [];
        if ($res['creados'] > 0) {
            $partes[] = $res['creados'] . ' turno' . ($res['creados'] === 1 ? '' : 's') . ' asignados';
        }
        if ($res['sobrescritos'] > 0) {
            $partes[] = $res['sobrescritos'] . ' actualizado' . ($res['sobrescritos'] === 1 ? '' : 's');
        }
        if ($res['omitidos'] > 0) {
            $partes[] = $res['omitidos'] . ' omitido' . ($res['omitidos'] === 1 ? '' : 's')
                . ' por ya tener turno';
        }

        flash($flash, 'Asignación masiva: ' . implode(', ', $partes) . '.');

        header('Location: ' . url('schedules?fecha=' . urlencode($datos['desde'])));
        exit;
    }

    /**
     * Formulario de asignacion masiva (POST -> bulk).
     */
    public function rango()
    {
        $title = 'Asignar Turnos por Rango';
        $currentModule = 'schedules';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Horarios', 'url' => url('schedules')],
            ['label' => 'Asignar por Rango'],
        ];

        $empleados = $this->empleadosActivos();
        $tipos = Shift::tipos();

        $hoy = Attendance::fechaDeMySQL();
        $desde = $_GET['desde'] ?? $hoy;
        $hasta = $_GET['hasta'] ?? date('Y-m-d', strtotime($hoy . ' +6 days'));

        $seleccionados = [];
        if (isset($_GET['empleado']) && (int) $_GET['empleado'] > 0) {
            $seleccionados[] = (int) $_GET['empleado'];
        }

        require __DIR__ . '/../views/schedules/bulk.php';
    }

    /**
     * PDF del periodo para repartir entre los empleados.
     * GET schedules/pdf?desde&hasta&empleado=<id|0>&formato=<matriz|detalle>
     */
    public function pdf()
    {
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('monday this week'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d', strtotime('sunday this week'));
        if (!$this->fechaValida($desde)) {
            $desde = date('Y-m-d', strtotime('monday this week'));
        }
        if (!$this->fechaValida($hasta)) {
            $hasta = date('Y-m-d', strtotime('sunday this week'));
        }
        if ($desde > $hasta) {
            $tmp = $desde;
            $desde = $hasta;
            $hasta = $tmp;
        }

        // Tope defensivo: un rango de años no es un horario.
        if ((strtotime($hasta) - strtotime($desde)) / 86400 > 366) {
            flash('warning', 'El período es demasiado largo (máximo 1 año).');
            header('Location: ' . url('schedules'));
            exit;
        }

        $empleadoId = (int) ($_GET['empleado'] ?? 0);
        $empleado = null;
        if ($empleadoId > 0) {
            $empleado = $this->employeeModel->getById($empleadoId);
            if ($empleado === null) {
                flash('error', 'El empleado no existe.');
                header('Location: ' . url('schedules'));
                exit;
            }
        }

        $formato = ($_GET['formato'] ?? 'matriz') === 'detalle' ? 'detalle' : 'matriz';
        $turnos = $this->shiftModel->rango($desde, $hasta);

        $filtroDias = $this->diasDelPost($_GET['dias'] ?? '1,2,3,4,5,6');
        if (!$filtroDias) {
            $filtroDias = [1, 2, 3, 4, 5, 6];
        }

        if ($formato === 'matriz') {
            $grilla = $this->shiftModel->grillaSemana($desde, $hasta);
            $dias = $grilla['dias'];
        } else {
            $dias = $this->diasInclusivos($desde, $hasta);
        }

        $porEmpleado = [];
        $indice = [];
        foreach ($turnos as $t) {
            $emp = (int) $t['employee_id'];
            if ($empleadoId > 0 && $emp !== $empleadoId) {
                continue;
            }
            $porEmpleado[$emp] = $porEmpleado[$emp] ?? [
                'employee_id' => $emp,
                'nombre' => trim($t['name'] . ' ' . $t['last_name']),
                'turnos' => [],
            ];
            $porEmpleado[$emp]['turnos'][] = $t;
            $indice[$emp][$t['work_date']] = $t;
        }
        ksort($porEmpleado);

        if (!$porEmpleado) {
            flash('warning', 'No hay turnos asignados en ese período.');
            header('Location: ' . url('schedules?fecha=' . urlencode($desde)));
            exit;
        }

        require_once __DIR__ . '/../vendor/autoload.php';

        $html = $this->renderPdfHtml($porEmpleado, $indice, $dias, $desde, $hasta, $formato, $filtroDias);

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 12,
            'default_font' => 'dejavusans',
            'default_font_size' => 8,
            'tempDir' => sys_get_temp_dir() . '/mpdf-horarios',
        ]);
        $negocio = setting('business_name', 'Fharina et Ignis');
        $mpdf->SetTitle('Horarios ' . $desde . ' a ' . $hasta, true);
        $mpdf->SetAuthor($negocio, true);
        $mpdf->SetSubject('Horario del personal — ' . $desde . ' a ' . $hasta, true);

        $ePdf = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        // mPDF: {PAGENO} es la pagina ACTUAL y {nbpg} el total del grupo
        // (que con un solo documento equivale al total de paginas). {nb} no
        // sirve para esto: sale vacio cuando el pie se define antes del HTML.
        $mpdf->SetFooter('<table style="width:100%;font-size:6.5pt;color:#9ca3af;border-collapse:collapse">'
            . '<tr><td style="text-align:left">' . $ePdf($negocio) . ' &mdash; Horarios del personal</td>'
            . '<td style="text-align:center">Impreso el ' . $ePdf(date('d/m/Y')) . ' a las ' . $ePdf(date('H:i')) . '</td>'
            . '<td style="text-align:right">P&aacute;gina {PAGENO} de {nbpg}</td>'
            . '</tr></table>');

        $mpdf->WriteHTML($html);

        $nombre = $empleado !== null
            ? 'Horario-' . preg_replace('/[^A-Za-z0-9]+/', '-', $this->nombreDe($empleado))
            : 'Horarios-' . $desde . '-a-' . $hasta;
        $mpdf->Output($nombre . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }

    /**
     * HTML del PDF de horarios. Tabla sin bordes doubles: mPDF deja huecos
     * entre celdas, asi que el separador se hace con celdas de 1px y colores
     * planos (mismo criterio que el ticket del POS).
     */
    private function renderPdfHtml($porEmpleado, $indice, $dias, $desde, $hasta, $formato, $filtroDias)
    {
        $negocio = setting('business_name', 'Fharina et Ignis');
        $diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

        // El PDF usa el color configurado en Configuración, igual que la web.
        $primario = $this->colorDeTema();
        $primarioClaro = $this->aclara($primario, 0.86);
        $primarioSuave = $this->aclara($primario, 0.93);
        $logo = $this->logoParaPdf();

        $e = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };

        $h = '';
        $h .= '<style>'
            . 'body{font-family:dejavusans;font-size:8pt;color:#1f2937}'
            . 'table{border-collapse:collapse;width:100%}'
            . 'td,th{padding:3px 4px;vertical-align:middle}'
            . '.sep td{height:2px;padding:0}'
            . '.bar td{font-size:1px;line-height:1px;padding:0}'
            . '.col{font-weight:bold;font-size:7.5pt;text-align:center}'
            . '.cell{text-align:center;font-size:7.5pt}'
            . '.nowrap{white-space:nowrap}'
            . '.libre{color:#9ca3af;font-size:7pt}'
            . '.tipo{font-size:6.5pt;color:#6b7280}'
            . '.marca{font-size:6.5pt}'
            . '.fin{border-bottom:1px solid #e5e7eb}'
            . '.tot{font-weight:bold;text-align:center}'
            . '</style>';

        $h .= '<table class="sep"><tr><td class="bar" style="background:' . $primario . '">&nbsp;</td></tr></table>';
        $h .= '<table><tr>';
        if ($logo !== null) {
            $h .= '<td width="130" style="text-align:left;padding-right:8px">'
                . '<img src="' . $e($logo) . '" style="width:115px"></td>';
        }
        $h .= '<td style="text-align:' . ($logo !== null ? 'left' : 'center') . '">'
            . '<span style="font-size:13pt;font-weight:bold;color:' . $primario . '">' . $e($negocio) . '</span>'
            . '<br><span style="font-size:10pt;font-weight:bold;color:#111827">Horarios del personal</span>'
            . '<br><span style="font-size:7.5pt;color:#6b7280">'
            . 'Per&iacute;odo: del ' . $e(date('d/m/Y', strtotime($desde)))
            . ' al ' . $e(date('d/m/Y', strtotime($hasta)))
            . '</span></td>';
        $h .= '<td width="150" style="text-align:right;font-size:7.5pt;color:#6b7280">'
            . 'Fecha de impresi&oacute;n<br>'
            . '<span style="font-size:8pt;color:#111827;font-weight:bold">'
            . $e(date('d/m/Y')) . ' &middot; ' . $e(date('H:i')) . '</span></td>';
        $h .= '</tr></table>';
        $h .= '<table class="sep"><tr><td class="bar" style="background:' . $primario . '">&nbsp;</td></tr></table>';

        foreach ($porEmpleado as $emp) {
            $h .= '<table class="sep"><tr><td></td></tr></table>';
            $h .= '<table>'
                . '<tr><td style="font-size:11pt;font-weight:bold;color:#111827">'
                . $e($emp['nombre']) . '</td>'
                . '<td align="right" style="font-size:7.5pt;color:#6b7280">'
                . count($emp['turnos']) . ' turno' . (count($emp['turnos']) === 1 ? '' : 's')
                . ' &middot; ' . $this->horasTotales($emp['turnos']) . '</td></tr>'
                . '</table>';

            if ($formato === 'matriz') {
                // Una SOLA tabla con colgroup: antes el encabezado, el
                // separador y el cuerpo eran tres tablas distintas y mPDF
                // repartia el ancho por separado, asi que las horas quedaban
                // aplastadas a la izquierda y sin columna de total.
                $anchoNom = 38;
                $anchoTot = 22;
                $anchoDia = (int) floor((277 - $anchoNom - $anchoTot) / max(count($dias), 1));

                $h .= '<table>'
                    . '<colgroup><col width="' . $anchoNom . 'mm">';
                foreach ($dias as $d) {
                    $h .= '<col width="' . $anchoDia . 'mm">';
                }
                $h .= '<col width="' . $anchoTot . 'mm"></colgroup>';

                $h .= '<tr><td class="col nowrap" style="background:' . $primarioClaro . ';text-align:left;padding-left:6px">Empleado</td>';
                foreach ($dias as $d) {
                    $w = (int) date('w', strtotime($d));
                    $h .= '<td class="col nowrap" style="background:' . $primarioClaro . '">'
                        . $e($diasSemana[$w])
                        . '<br><span style="font-weight:normal;color:#6b7280">'
                        . $e(date('d/m', strtotime($d))) . '</span>'
                        . (in_array($w, $filtroDias, true) ? '' : '<br><span class="marca" style="color:' . $primario . '">&bull;</span>')
                        . '</td>';
                }
                $h .= '<td class="col nowrap" style="background:' . $primarioClaro . '">Total</td></tr>';

                $h .= '<tr><td class="libre nowrap" style="text-align:left;padding-left:6px">&nbsp;</td>';
                foreach ($dias as $d) {
                    $celda = $indice[$emp['employee_id']][$d] ?? null;
                    $w = (int) date('w', strtotime($d));
                    if ($celda) {
                        $h .= '<td class="cell nowrap" style="background:' . $primarioSuave . '">'
                            . '<span style="font-weight:bold;color:' . $primario . '">' . $e(substr($celda['start_time'], 0, 5)) . '</span>'
                            . '<br><span style="color:#374151">' . $e(substr($celda['end_time'], 0, 5)) . '</span>'
                            . '<br><span class="tipo">' . $e(Shift::tipoTexto($celda['shift_type'])) . '</span>'
                            . '</td>';
                    } elseif (!in_array($w, $filtroDias, true)) {
                        $h .= '<td class="cell libre">&middot;</td>';
                    } else {
                        $h .= '<td class="cell libre">libre</td>';
                    }
                }
                $h .= '<td class="tot nowrap" style="background:' . $primarioClaro . '">'
                    . $e($this->horasTotales($emp['turnos'])) . '</td></tr>'
                    . '</table>';
            } else {
                $h .= '<table>'
                    . '<colgroup><col width="26mm"><col width="16mm"><col width="30mm">'
                    . '<col width="20mm"><col width="20mm"><col width="20mm"><col></colgroup>'
                    . '<tr>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">Fecha</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">D&iacute;a</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">Turno</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">Inicio</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">Fin</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . '">Horas</td>'
                    . '<td class="col nowrap" style="background:' . $primarioClaro . ';text-align:left;padding-left:6px">Observaciones</td>'
                    . '</tr>';

                foreach ($emp['turnos'] as $t) {
                    $w = (int) date('w', strtotime($t['work_date']));
                    $marca = in_array($w, $filtroDias, true) ? '' : ' <span class="marca" style="color:' . $primario . '">(fuera del d&iacute;a)</span>';
                    $h .= '<tr class="fin">'
                        . '<td class="nowrap">' . $e(date('d/m/Y', strtotime($t['work_date']))) . '</td>'
                        . '<td class="cell">' . $e($diasSemana[$w]) . '</td>'
                        . '<td class="nowrap">' . $e(Shift::tipoTexto($t['shift_type'])) . $marca . '</td>'
                        . '<td class="cell nowrap">' . $e(substr($t['start_time'], 0, 5)) . '</td>'
                        . '<td class="cell nowrap">' . $e(substr($t['end_time'], 0, 5)) . '</td>'
                        . '<td class="cell nowrap">' . $e($this->horasDeTurno($t)) . '</td>'
                        . '<td class="nowrap" style="text-align:left;padding-left:6px;color:#6b7280">' . $e((string) ($t['notes'] ?? '')) . '</td>'
                        . '</tr>';
                }
                $h .= '<tr><td class="tot" style="text-align:left;padding-left:6px">Total</td>'
                    . '<td></td><td></td><td></td><td></td>'
                    . '<td class="tot nowrap">' . $e($this->horasTotales($emp['turnos'])) . '</td><td></td></tr>'
                    . '</table>';
            }
        }

        $h .= '<table class="sep"><tr><td class="bar" style="background:' . $primarioClaro . '">&nbsp;</td></tr></table>'
            . '<table><tr><td style="font-size:6.5pt;color:#9ca3af">'
            . 'Documento generado por el sistema de horarios &middot; '
            . $e(date('d/m/Y')) . ' ' . $e(date('H:i'))
            . ' &middot; Cualquier cambio debe avisarse a la supervisi&oacute;n.'
            . '</td></tr></table>';

        return $h;
    }

    private function horasDeTurno($t)
    {
        $min = $this->minutosDe($t['start_time'], $t['end_time']);

        return $this->minutosATexto($min);
    }

    private function horasTotales($turnos)
    {
        $total = 0;
        foreach ($turnos as $t) {
            $total += $this->minutosDe($t['start_time'], $t['end_time']);
        }

        return $this->minutosATexto($total);
    }

    /**
     * Color de acento del sistema (el mismo primary_color que maneja el theme
     * de la web). Si alguien guardo un valor raro, se cae al naranja de la marca.
     */
    private function colorDeTema()
    {
        $color = strtolower(trim((string) setting('primary_color', '')));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#f97316';
    }

    /** Mezcla un color con blanco: $blanco 0.86 deja un tono muy claro. */
    private function aclara($hex, $blanco)
    {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string) $hex, $m)) {
            return '#f3f4f6';
        }

        $out = '#';
        for ($i = 1; $i <= 3; $i++) {
            $v = (int) hexdec($m[$i]);
            $out .= str_pad(dechex((int) round($v + ((255 - $v) * $blanco))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /**
     * Ruta del logo para mPDF. En la web el setting guarda "/uploads/x.png",
     * que el navegador resuelve solo; mPDF necesita la ruta real en disco (o una
     * URL absoluta si el negocio puso una de internet).
     */
    private function logoParaPdf()
    {
        $logo = trim((string) setting('system_logo', ''));
        if ($logo === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $logo)) {
            return $logo;
        }

        $rel = ltrim((string) parse_url($logo, PHP_URL_PATH), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }

        $abs = dirname(__DIR__) . '/public/' . $rel;

        return is_file($abs) ? $abs : null;
    }

    /**
     * Minutos entre H:i y H:j. Un turno que cruza medianoche (22:00 a 06:00)
     * cuenta hacia el dia siguiente.
     */
    private function minutosDe($start, $end)
    {
        $sh = (int) substr($start, 0, 2);
        $sm = (int) substr($start, 3, 2);
        $eh = (int) substr($end, 0, 2);
        $em = (int) substr($end, 3, 2);

        $min = ($eh * 60 + $em) - ($sh * 60 + $sm);
        if ($min <= 0) {
            $min += 1440;
        }

        return $min;
    }

    private function minutosATexto($minutos)
    {
        $h = (int) floor($minutos / 60);
        $m = $minutos % 60;
        if ($m === 0) {
            return $h . 'h';
        }

        return $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
    }

    private function nombreDe($empleado)
    {
        return trim($empleado['name'] . ' ' . $empleado['last_name']);
    }

    private function diasInclusivos($desde, $hasta)
    {
        $dias = [];
        $cursor = new DateTime($desde);
        $fin = new DateTime($hasta);
        while ($cursor <= $fin) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
        }

        return $dias;
    }

    /**
     * Valida el POST de la asignacion masiva. Devuelve el array o false.
     */
    private function datosDelPostMasivo()
    {
        $ids = $_POST['empleado_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = $ids !== '' ? [$ids] : [];
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, function ($id) {
            return $id > 0 && $this->employeeModel->getById($id) !== null;
        }));
        if (!$ids) {
            flash('error', 'Selecciona al menos un empleado.');

            return false;
        }

        $desde = $_POST['desde'] ?? '';
        $hasta = $_POST['hasta'] ?? '';
        if (!$this->fechaValida($desde) || !$this->fechaValida($hasta)) {
            flash('error', 'El rango de fechas no es válido.');

            return false;
        }
        if ($desde > $hasta) {
            flash('error', 'La fecha inicial no puede ser posterior a la final.');

            return false;
        }

        $diasTotales = (strtotime($hasta) - strtotime($desde)) / 86400;
        if ($diasTotales > 366) {
            flash('error', 'El rango no puede ser mayor a un año.');

            return false;
        }

        $dias = $this->diasDelPost($_POST['dias'] ?? '1,2,3,4,5');
        if (!$dias) {
            flash('error', 'Selecciona al menos un día de la semana.');

            return false;
        }

        $start = $this->horaDelPost('start_time');
        $end = $this->horaDelPost('end_time');
        if ($start === null || $end === null) {
            flash('error', 'Las horas de inicio y fin son obligatorias.');

            return false;
        }
        if ($start === $end) {
            flash('warning', 'El inicio y el fin del turno no pueden ser iguales.');

            return false;
        }

        $type = $_POST['shift_type'] ?? '';
        $type = Shift::existeTipo($type) ? $type : null;

        $notes = trim($_POST['notes'] ?? '');
        if (mb_strlen($notes) > 255) {
            $notes = mb_substr($notes, 0, 255);
        }

        return [
            'empleado_ids' => $ids,
            'desde' => $desde,
            'hasta' => $hasta,
            'dias' => $dias,
            'start_time' => $start,
            'end_time' => $end,
            'shift_type' => $type,
            'notes' => $notes !== '' ? $notes : null,
            'sobrescribir' => !empty($_POST['sobrescribir']),
        ];
    }

    /**
     * "1,2,3" o ["1","2"] -> [1,2,3] (0 = domingo ... 6 = sabado).
     */
    private function diasDelPost($valor)
    {
        if (is_string($valor)) {
            $valor = $valor !== '' ? explode(',', $valor) : [];
        }
        if (!is_array($valor)) {
            return [];
        }
        $dias = [];
        foreach ($valor as $d) {
            $n = (int) $d;
            if ($n >= 0 && $n <= 6 && !in_array($n, $dias, true)) {
                $dias[] = $n;
            }
        }
        sort($dias);

        return $dias;
    }

    public function edit($shiftId)
    {
        $shift = $this->shiftModel->getById($shiftId);
        if ($shift === null) {
            flash('error', 'El turno no existe.');
            header('Location: ' . url('schedules'));
            exit;
        }

        $title = 'Editar Turno';
        $currentModule = 'schedules';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Horarios', 'url' => url('schedules')],
            ['label' => 'Editar Turno'],
        ];

        $empleados = $this->empleadosActivos();
        $tipos = Shift::tipos();

        // Asistencia del empleado en la fecha del turno, para corregirla de paso.
        $asistencia = $this->attendanceModel->deEmpleado($shift['employee_id'], $shift['work_date']);
        $asistencia = $asistencia === null ? null : Attendance::decorar($asistencia);

        require __DIR__ . '/../views/schedules/edit.php';
    }

    public function update($shiftId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('schedules/edit/' . (int) $shiftId));
            exit;
        }

        $id = $this->employeeId();
        $antes = $this->shiftModel->getById($shiftId);

        if ($antes === null) {
            flash('error', 'El turno no existe.');
            header('Location: ' . url('schedules'));
            exit;
        }

        $data = $this->datosDelPost();

        if ($data === false) {
            header('Location: ' . url('schedules/edit/' . (int) $shiftId));
            exit;
        }

        if ($this->shiftModel->yaTiene($data['employee_id'], $data['work_date'], $shiftId)) {
            flash('warning', 'Ese empleado ya tiene un turno asignado para esa fecha.');
            header('Location: ' . url('schedules/edit/' . (int) $shiftId));
            exit;
        }

        $this->shiftModel->update(
            $shiftId,
            $data['employee_id'],
            $data['work_date'],
            $data['start_time'],
            $data['end_time'],
            $data['shift_type'],
            $data['notes']
        );

        $this->auditModel->write('update', 'shifts', $shiftId, $antes, $this->shiftModel->getById($shiftId));

        flash('success', 'Turno actualizado.');

        header('Location: ' . url('schedules?fecha=' . urlencode($data['work_date'])));
        exit;
    }

    public function delete($shiftId)
    {
        $id = $this->employeeId();
        $antes = $this->shiftModel->getById($shiftId);

        if ($antes === null) {
            flash('error', 'El turno no existe.');
            header('Location: ' . url('schedules'));
            exit;
        }

        // La asistencia no se borra con el turno: es historico del empleado.
        $this->shiftModel->delete($shiftId);

        $this->auditModel->write('delete', 'shifts', $shiftId, $antes, false);

        flash('success', 'Turno eliminado.');

        header('Location: ' . url('schedules?fecha=' . urlencode($antes['work_date'])));
        exit;
    }

    /**
     * Ajuste manual de la marcacion de un empleado (supervisor).
     * Es la unica via para fechar dias pasados o corregir una entrada.
     */
    public function attendance($employeeId)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('schedules'));
            exit;
        }

        $registrador = $this->employeeId();
        $empleado = $this->employeeModel->getById($employeeId);

        if ($empleado === null) {
            flash('error', 'El empleado no existe.');
            header('Location: ' . url('schedules'));
            exit;
        }

        $fecha = $_POST['attendance_date'] ?? Attendance::fechaDeMySQL();
        if (!$this->fechaValida($fecha)) {
            flash('warning', 'La fecha no es válida.');
            header('Location: ' . url('schedules'));
            exit;
        }

        $checkIn = $this->horaDelPost('check_in');
        $checkOut = $this->horaDelPost('check_out');
        $state = $_POST['state'] ?? 'presente';
        if (!array_key_exists($state, Attendance::estados())) {
            $state = 'presente';
        }

        $notas = trim($_POST['notes'] ?? '');
        if (mb_strlen($notas) > 255) {
            $notas = mb_substr($notas, 0, 255);
        }

        $antes = $this->attendanceModel->deEmpleado($employeeId, $fecha);

        $res = $this->attendanceModel->registrarManual(
            $employeeId,
            $fecha,
            $checkIn,
            $checkOut,
            $state,
            $registrador,
            $notas !== '' ? $notas : null
        );

        flash($res['ok'] ? 'success' : 'warning', $res['msg']);

        if ($res['ok']) {
            $this->auditModel->write(
                'update',
                'attendances',
                $this->attendanceModel->deEmpleado($employeeId, $fecha)['id'] ?? null,
                $antes,
                $this->attendanceModel->deEmpleado($employeeId, $fecha),
                'Ajuste manual de ' . $this->nombreDe($empleado) . ' el ' . date('d/m/Y', strtotime($fecha)),
                $registrador
            );
        }

        header('Location: ' . url('schedules?fecha=' . urlencode($fecha)));
        exit;
    }

    /**
     * Borra el registro de asistencia (correccion del supervisor).
     */
    public function attendanceDelete($attendanceId)
    {
        $registrador = $this->employeeId();
        $antes = $this->attendanceModel->getById($attendanceId);

        if ($antes === null) {
            flash('error', 'El registro no existe.');
            header('Location: ' . url('schedules'));
            exit;
        }

        $this->attendanceModel->delete($attendanceId);

        $this->auditModel->write(
            'delete',
            'attendances',
            $attendanceId,
            $antes,
            false,
            'Correccion manual del supervisor: ' . date('d/m/Y', strtotime($antes['attendance_date'])),
            $registrador
        );

        flash('success', 'Registro de asistencia eliminado.');

        header('Location: ' . url('schedules?fecha=' . urlencode($antes['attendance_date'])));
        exit;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function empleadosActivos()
    {
        return array_values(array_filter(
            $this->employeeModel->getAll(),
            function ($e) {
                return $e['status'] === 'active';
            }
        ));
    }

    private function fechaValida($fecha)
    {
        if (!is_string($fecha) || $fecha === '') {
            return false;
        }
        $d = DateTime::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }

    /**
     * Lunes y domingo de la semana a la que pertenece $fecha.
     */
    private function semanaDe($fecha)
    {
        $d = new DateTime($fecha);
        $lunes = clone $d;
        // getDay(): 0 domingo ... 6 sabado. Se retrocede al lunes.
        $lunes->modify('-' . (($d->format('w') == 0 ? 6 : $d->format('w')) - 1) . ' days');

        $domingo = clone $lunes;
        $domingo->modify('+6 days');

        return [
            'desde' => $lunes->format('Y-m-d'),
            'hasta' => $domingo->format('Y-m-d'),
        ];
    }

    /**
     * Valida el POST de un turno. Devuelve el array de datos o false.
     */
    private function datosDelPost()
    {
        $employeeId = (int) ($_POST['employee_id'] ?? 0);
        if ($employeeId <= 0 || $this->employeeModel->getById($employeeId) === null) {
            flash('error', 'Selecciona un empleado válido.');
            return false;
        }

        $workDate = $_POST['work_date'] ?? '';
        if (!$this->fechaValida($workDate)) {
            flash('error', 'La fecha del turno no es válida.');
            return false;
        }

        $start = $this->horaDelPost('start_time');
        $end = $this->horaDelPost('end_time');
        if ($start === null || $end === null) {
            flash('error', 'Las horas de inicio y fin son obligatorias.');
            return false;
        }
        if ($start === $end) {
            flash('warning', 'El inicio y el fin del turno no pueden ser iguales.');
            return false;
        }

        $type = $_POST['shift_type'] ?? '';
        $type = Shift::existeTipo($type) ? $type : null;

        $notes = trim($_POST['notes'] ?? '');
        if (mb_strlen($notes) > 255) {
            $notes = mb_substr($notes, 0, 255);
        }

        return [
            'employee_id' => $employeeId,
            'work_date' => $workDate,
            'start_time' => $start,
            'end_time' => $end,
            'shift_type' => $type,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    /**
     * Normaliza "H:MM" o "HH:MM:SS" a "HH:MM:SS". Null si no viene nada.
     */
    private function horaDelPost($campo)
    {
        $v = trim($_POST[$campo] ?? '');
        if ($v === '') {
            return null;
        }
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $v, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }

        return sprintf('%02d:%02d:00', $h, $min);
    }
}