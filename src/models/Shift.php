<?php

/**
 * Turnos asignados (tabla shifts).
 *
 * Un turno por empleado por dia (uq_shift_emp_date): es lo que hace util la
 * "salida estimada" de un empleado, que es el end_time de su turno del dia.
 */
class Shift
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Tipos de turno sugeridos. shift_type es VARCHAR libre: el controller
     * valida contra esta lista, pero el modelo no la impone.
     */
    public static function tipos()
    {
        return [
            'manana' => 'Mañana',
            'tarde' => 'Tarde',
            'noche' => 'Noche',
            'cerrada' => 'Cierre',
        ];
    }

    public static function tipoTexto($tipo)
    {
        $tipos = self::tipos();

        return $tipos[$tipo] ?? ($tipo !== null && $tipo !== '' ? ucfirst($tipo) : '—');
    }

    public static function existeTipo($tipo)
    {
        return array_key_exists($tipo, self::tipos());
    }

    /**
     * Turnos de una fecha con datos del empleado y su asistencia del mismo dia.
     * LEFT JOINs: un turno sin asistencia es legitimo (aun no entro).
     */
    public function delDia($fecha)
    {
        $sql = "SELECT s.id, s.employee_id, s.work_date, s.start_time, s.end_time,
                       s.shift_type, s.notes,
                       e.name, e.last_name, e.profile_photo,
                       e.status AS employee_status,
                       a.id AS attendance_id, a.check_in, a.check_out,
                       a.break_start, a.break_end, a.state,
                       a.check_in_method, a.check_out_method
                FROM shifts s
                JOIN empleados e ON e.id = s.employee_id
                LEFT JOIN attendances a
                       ON a.employee_id = s.employee_id
                      AND a.attendance_date = s.work_date
                WHERE s.work_date = ?
                ORDER BY s.start_time ASC, e.last_name ASC, e.name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $fecha);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Agenda de una semana: una fila por turno con el nombre del empleado.
     * $desde y $hasta son fechas YYYY-MM-DD (inclusive).
     */
    public function rango($desde, $hasta)
    {
        $sql = "SELECT s.id, s.employee_id, s.work_date, s.start_time, s.end_time,
                       s.shift_type, s.notes,
                       e.name, e.last_name
                FROM shifts s
                JOIN empleados e ON e.id = s.employee_id
                WHERE s.work_date BETWEEN ? AND ?
                ORDER BY s.work_date ASC, s.start_time ASC, e.last_name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $desde);
        $stmt->bindValue(2, $hasta);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Grilla semanal: dias de la semana x empleado, con el turno asignado.
     * Devuelve ['dias' => ['YYYY-MM-DD', ...], 'filas' => [emp => ['nombre' =>, 'celdas' => [fecha => turno]]]].
     */
    public function grillaSemana($desde, $hasta)
    {
        $dias = [];
        $cursor = new DateTime($desde);
        $fin = new DateTime($hasta);
        while ($cursor <= $fin) {
            $dias[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
        }

        $filas = [];
        foreach ($this->rango($desde, $hasta) as $t) {
            $emp = (int) $t['employee_id'];
            if (!isset($filas[$emp])) {
                $filas[$emp] = [
                    'employee_id' => $emp,
                    'nombre' => trim($t['name'] . ' ' . $t['last_name']),
                    'celdas' => [],
                ];
            }
            $filas[$emp]['celdas'][$t['work_date']] = $t;
        }

        return ['dias' => $dias, 'filas' => $filas];
    }

    /**
     * Asignacion masiva: el mismo turno para varios empleados y varios dias.
     *
     * Recorre el rango [desde, hasta] y solo los dias de la semana indicados en
     * $diasSemana (0 = domingo ... 6 = sabado). Corre en una transaccion: si un
     * dia ya tiene turno, se salta (uq_shift_emp_date) y no se aborta el resto.
     * $sobrescribir borra el turno existente antes de insertar el nuevo.
     *
     * @param  array $diasSemana claves de date('w') admitidas
     * @return array ['creados' => int, 'omitidos' => int, 'sobrescritos' => int, 'ids' => int[]]
     */
    public function asignarRango(
        array $empleadoIds,
        $desde,
        $hasta,
        $startTime,
        $endTime,
        $shiftType = null,
        $notes = null,
        array $diasSemana = [1, 2, 3, 4, 5, 6],
        $sobrescribir = false
    ) {
        $empleadoIds = array_values(array_unique(array_map('intval', $empleadoIds)));
        $empleadoIds = array_values(array_filter($empleadoIds, function ($id) {
            return $id > 0;
        }));

        if (!$empleadoIds || !$desde || !$hasta || $desde > $hasta) {
            return ['creados' => 0, 'omitidos' => 0, 'sobrescritos' => 0, 'ids' => []];
        }

        $diasSemana = array_values(array_unique(array_map('intval', $diasSemana)));
        if (!$diasSemana) {
            return ['creados' => 0, 'omitidos' => 0, 'sobrescritos' => 0, 'ids' => []];
        }

        $rangoDias = $this->diasEntre($desde, $hasta);
        $selUpd = $this->conn->prepare(
            "UPDATE shifts
             SET start_time = ?, end_time = ?, shift_type = ?, notes = ?
             WHERE employee_id = ? AND work_date = ?"
        );

        $res = ['creados' => 0, 'omitidos' => 0, 'sobrescritos' => 0, 'ids' => []];

        $this->conn->beginTransaction();

        try {
            foreach ($empleadoIds as $empId) {
                foreach ($rangoDias as $dia) {
                    if (!in_array((int) $dia['w'], $diasSemana, true)) {
                        continue;
                    }

                    $existente = $this->deEmpleado($empId, $dia['fecha']);

                    if ($existente === null) {
                        $res['ids'][] = (int) $this->create(
                            $empId,
                            $dia['fecha'],
                            $startTime,
                            $endTime,
                            $shiftType,
                            $notes
                        );
                        $res['creados']++;
                        continue;
                    }

                    if (!$sobrescribir) {
                        $res['omitidos']++;
                        continue;
                    }

                    $selUpd->bindValue(1, $startTime);
                    $selUpd->bindValue(2, $endTime);
                    $selUpd->bindValue(3, $shiftType);
                    $selUpd->bindValue(4, $notes);
                    $selUpd->bindValue(5, $empId, PDO::PARAM_INT);
                    $selUpd->bindValue(6, $dia['fecha']);
                    $selUpd->execute();
                    $res['ids'][] = (int) $existente['id'];
                    $res['sobrescritos']++;
                }
            }

            $this->conn->commit();
        } catch (Throwable $e) {
            $this->conn->rollBack();
            throw $e;
        }

        unset($selUpd);

        return $res;
    }

    /**
     * Dias del rango inclusivo como ['fecha' => 'YYYY-MM-DD', 'w' => 0..6].
     */
    private function diasEntre($desde, $hasta)
    {
        $dias = [];
        $cursor = new DateTime($desde);
        $fin = new DateTime($hasta);
        while ($cursor <= $fin) {
            $dias[] = ['fecha' => $cursor->format('Y-m-d'), 'w' => (int) $cursor->format('w')];
            $cursor->modify('+1 day');
        }

        return $dias;
    }

    /**
     * Turno de un empleado para una fecha, o null si no tiene asignado.
     */
    public function deEmpleado($employeeId, $fecha)
    {
        $sql = "SELECT * FROM shifts WHERE employee_id = ? AND work_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $fecha);
        $stmt->execute();

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM shifts WHERE id = ?");
        $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    public function create($employeeId, $workDate, $startTime, $endTime, $shiftType = null, $notes = null)
    {
        $sql = "INSERT INTO shifts (employee_id, work_date, start_time, end_time, shift_type, notes)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $workDate);
        $stmt->bindValue(3, $startTime);
        $stmt->bindValue(4, $endTime);
        $stmt->bindValue(5, $shiftType);
        $stmt->bindValue(6, $notes);

        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function update($id, $employeeId, $workDate, $startTime, $endTime, $shiftType = null, $notes = null)
    {
        $sql = "UPDATE shifts
                SET employee_id = ?, work_date = ?, start_time = ?, end_time = ?,
                    shift_type = ?, notes = ?
                WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $workDate);
        $stmt->bindValue(3, $startTime);
        $stmt->bindValue(4, $endTime);
        $stmt->bindValue(5, $shiftType);
        $stmt->bindValue(6, $notes);
        $stmt->bindValue(7, (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM shifts WHERE id = ?");
        $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * El empleado ya tiene un turno ese dia (para validar antes de insertar).
     */
    public function yaTiene($employeeId, $workDate, $excludeId = null)
    {
        $sql = "SELECT COUNT(*) FROM shifts WHERE employee_id = ? AND work_date = ?";
        $params = [(int) $employeeId, $workDate];

        if ($excludeId !== null) {
            $sql .= " AND id <> ?";
            $params[] = (int) $excludeId;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $params[0], PDO::PARAM_INT);
        $stmt->bindValue(2, $params[1]);
        if ($excludeId !== null) {
            $stmt->bindValue(3, $params[2], PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}