<?php

/**
 * Entradas y salidas (tabla attendances).
 *
 * La hora se resuelve con CURTIME()/CURDATE() en SQL y no con date() en PHP:
 * asi manda el reloj del servidor MySQL y no hay desfasaje por zona horaria
 * entre PHP y la base.
 *
 * Los metodos marcan devuelven ['ok' => bool, 'msg' => string] para que el
 * controller pueda_flashear el motivo exacto (duplicado, ya cerrado, etc.).
 */
class Attendance
{
    private $conn;

    /** Metodos de firma admitidos (mismo ENUM que la columna). */
    const METODOS = ['manual', 'qr', 'rostro'];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public static function metodoValido($metodo)
    {
        return in_array($metodo, self::METODOS, true);
    }

    public static function metodoTexto($metodo)
    {
        $m = [
            'manual' => 'Manual',
            'qr' => 'Código QR',
            'rostro' => 'Reconocimiento facial',
        ];

        return $m[$metodo] ?? '—';
    }

    public static function metodoIcon($metodo)
    {
        $m = [
            'manual' => 'fa-user-pen',
            'qr' => 'fa-qrcode',
            'rostro' => 'fa-face-smile',
        ];

        return $m[$metodo] ?? 'fa-circle-question';
    }

    /**
     * Estados posibles. 'ausente' y 'permiso' los fija un supervisor;
     * 'presente'/'descanso' los manage el propio empleado al marcar.
     */
    public static function estados()
    {
        return [
            'presente' => 'Presente',
            'descanso' => 'En descanso',
            'ausente' => 'Ausente',
            'permiso' => 'Permiso',
        ];
    }

    public static function estadoTexto($state)
    {
        $e = self::estados();

        return $e[$state] ?? ucfirst((string) $state);
    }

    public static function estadoColor($state)
    {
        $c = [
            'presente' => 'bg-green-100 text-green-700',
            'descanso' => 'bg-amber-100 text-amber-700',
            'ausente' => 'bg-red-100 text-red-700',
            'permiso' => 'bg-blue-100 text-blue-700',
        ];

        return $c[$state] ?? 'bg-gray-100 text-gray-600';
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /**
     * Registro de un empleado para una fecha, con su turno (puede ser null).
     */
    public function deEmpleado($employeeId, $fecha)
    {
        $sql = "SELECT a.*, s.start_time, s.end_time, s.shift_type
                FROM attendances a
                LEFT JOIN shifts s
                       ON s.employee_id = a.employee_id
                      AND s.work_date = a.attendance_date
                WHERE a.employee_id = ? AND a.attendance_date = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, $fecha);
        $stmt->execute();

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM attendances WHERE id = ?");
        $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        $fila = $stmt->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Historial de un empleado. $limite acota las filas.
     */
    public function historial($employeeId, $limite = 30)
    {
        $sql = "SELECT a.*, s.start_time, s.end_time, s.shift_type
                FROM attendances a
                LEFT JOIN shifts s
                       ON s.employee_id = a.employee_id
                      AND s.work_date = a.attendance_date
                WHERE a.employee_id = ?
                ORDER BY a.attendance_date DESC
                LIMIT ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(2, (int) $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Todos los registros de una fecha (para la supervision de Horarios).
     */
    public function delDia($fecha)
    {
        $stmt = $this->conn->prepare("SELECT * FROM attendances WHERE attendance_date = ?");
        $stmt->bindValue(1, $fecha);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Marcaciones en un rango de fechas, para el modulo Registros del Quiosco.
     *
     * $origen acota por el metodo con que se marco:
     *   'kiosco' (defecto) -> qr o rostro, lo que escribio /kiosco
     *   'manual'            -> ambos manual (ajuste desde Horarios)
     *   'todos'             -> cualquier fila que tenga al menos un metodo
     * Sin metodo no hay marcacion: las filas 'ausente' puras quedan fuera de
     * los tres casos a proposito.
     */
    public function registros($desde, $hasta, $origen = 'kiosco', $limite = 1000)
    {
        $sql = "SELECT a.*, e.name, e.last_name, e.profile_photo,
                       e.status AS empleado_status,
                       s.start_time, s.end_time, s.shift_type
                FROM attendances a
                JOIN empleados e ON e.id = a.employee_id
                LEFT JOIN shifts s
                       ON s.employee_id = a.employee_id
                      AND s.work_date = a.attendance_date
                WHERE a.attendance_date BETWEEN :desde AND :hasta";

        if ($origen === 'kiosco') {
            $sql .= " AND (a.check_in_method IN ('qr','rostro')
                        OR a.check_out_method IN ('qr','rostro'))";
        } elseif ($origen === 'manual') {
            $sql .= " AND a.check_in_method = 'manual'
                     AND a.check_out_method = 'manual'";
        } else {
            $sql .= " AND (a.check_in_method IS NOT NULL
                        OR a.check_out_method IS NOT NULL)";
        }

        $sql .= " ORDER BY a.attendance_date DESC, e.last_name, e.name
                  LIMIT " . (int) $limite;

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Totales del mismo rango para las tarjetas del resumen.
     * Repite el filtro de registros() para que los numeros no se contradigan
     * cuando la lista se corta por el LIMIT.
     *
     * @return array total, empleados, qr, rostro, manual, descansos, cerrados
     */
    public function resumenRegistros($desde, $hasta, $origen = 'kiosco')
    {
        $sql = "SELECT COUNT(*) AS total,
                       COUNT(DISTINCT a.employee_id) AS empleados,
                       SUM(CASE WHEN a.check_in_method = 'qr' THEN 1 ELSE 0 END) AS qr,
                       SUM(CASE WHEN a.check_in_method = 'rostro' THEN 1 ELSE 0 END) AS rostro,
                       SUM(CASE WHEN a.check_in_method = 'manual' THEN 1 ELSE 0 END) AS manual,
                       SUM(CASE WHEN a.break_start IS NOT NULL THEN 1 ELSE 0 END) AS descansos,
                       SUM(CASE WHEN a.check_out IS NOT NULL THEN 1 ELSE 0 END) AS cerrados
                FROM attendances a
                WHERE a.attendance_date BETWEEN :desde AND :hasta";

        if ($origen === 'kiosco') {
            $sql .= " AND (a.check_in_method IN ('qr','rostro')
                        OR a.check_out_method IN ('qr','rostro'))";
        } elseif ($origen === 'manual') {
            $sql .= " AND a.check_in_method = 'manual'
                     AND a.check_out_method = 'manual'";
        } else {
            $sql .= " AND (a.check_in_method IS NOT NULL
                        OR a.check_out_method IS NOT NULL)";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':desde', $desde);
        $stmt->bindValue(':hasta', $hasta);
        $stmt->execute();

        $fila = $stmt->fetch();
        if ($fila === false) {
            $fila = [];
        }

        foreach (['total', 'empleados', 'qr', 'rostro', 'manual', 'descansos', 'cerrados'] as $clave) {
            $fila[$clave] = (int) ($fila[$clave] ?? 0);
        }

        return $fila;
    }

    // ------------------------------------------------------------------
    // Marcaciones del propio empleado
    // ------------------------------------------------------------------

    public function marcarEntrada($employeeId, $metodo = 'qr', $registradoPor = null, $fecha = null)
    {
        return $this->marcar('entrada', $employeeId, $metodo, $registradoPor, $fecha);
    }

    public function marcarSalida($employeeId, $metodo = 'qr', $registradoPor = null, $fecha = null)
    {
        return $this->marcar('salida', $employeeId, $metodo, $registradoPor, $fecha);
    }

// Las cuatro marcaciones comparten firma: (employeeId, metodo, registradoPor, fecha).
// Antes marcarEntrada/marcarSalida recibian $metodo en 2do lugar y los descansos
// $fecha, lo que hacia que el controller pasara el metodo donde iba la fecha.
public function iniciarDescanso($employeeId, $metodo = 'manual', $registradoPor = null, $fecha = null)
    {
        return $this->marcar('descanso_inicio', $employeeId, $metodo, $registradoPor, $fecha);
    }

public function terminarDescanso($employeeId, $metodo = 'manual', $registradoPor = null, $fecha = null)
    {
        return $this->marcar('descanso_fin', $employeeId, $metodo, $registradoPor, $fecha);
    }

    /**
     * Nucleo de las marcaciones.
     *
     * @param string $tipo entrada|salida|descanso_inicio|descanso_fin
     */
    private function marcar($tipo, $employeeId, $metodo, $registradoPor = null, $fecha = null)
    {
        if (!self::metodoValido($metodo)) {
            $metodo = 'manual';
        }

        $hoy = self::fechaDeMySQL();
        if ($fecha !== null && $fecha !== $hoy) {
            return ['ok' => false, 'msg' => 'Solo se puede marcar sobre el día de hoy.'];
        }

        $actual = $this->deEmpleado($employeeId, $hoy);

        // No se puede marcar dos veces lo mismo, ni volver atras una jornada cerrada.
        if ($actual && $actual['check_out'] !== null) {
            return ['ok' => false, 'msg' => 'Tu jornada ya fue cerrada hoy.'];
        }
        if ($tipo === 'entrada' && $actual && $actual['check_in'] !== null) {
            return ['ok' => false, 'msg' => 'Ya registraste tu entrada hoy.'];
        }
        if ($tipo === 'salida' && (!$actual || $actual['check_in'] === null)) {
            return ['ok' => false, 'msg' => 'Primero tenés que registrar la entrada.'];
        }
        if ($tipo === 'descanso_inicio') {
            if (!$actual || $actual['check_in'] === null) {
                return ['ok' => false, 'msg' => 'Primero tenés que registrar la entrada.'];
            }
            if ($actual['break_start'] !== null && $actual['break_end'] === null) {
                return ['ok' => false, 'msg' => 'Ya tenés un descanso en curso.'];
            }
        }
        if ($tipo === 'descanso_fin' && (!$actual || $actual['break_start'] === null || $actual['break_end'] !== null)) {
            return ['ok' => false, 'msg' => 'No tenés un descanso en curso.'];
        }

        if ($actual === null) {
            // Sin registro previo solo puede pasar la entrada: las demas
            // marcaciones ya quedaron filtradas por los guards de arriba.
            $stmt = $this->conn->prepare(
                "INSERT INTO attendances
                     (employee_id, attendance_date, check_in, state, check_in_method, registered_by)
                 VALUES (?, CURDATE(), CURTIME(), 'presente', ?, ?)"
            );
            $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
            $stmt->bindValue(2, $metodo);
            $stmt->bindValue(3, $registradoPor !== null ? (int) $registradoPor : null, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $columna = [
                'entrada' => 'check_in',
                'salida' => 'check_out',
                'descanso_inicio' => 'break_start',
                'descanso_fin' => 'break_end',
            ][$tipo];
            $metodoCol = $tipo === 'entrada' ? 'check_in_method' : ($tipo === 'salida' ? 'check_out_method' : null);

            $sql = "UPDATE attendances SET $columna = CURTIME()";
            if ($tipo === 'descanso_inicio') {
                // Reinicia el descanso: no se puede haber uno abierto a la vez.
                $sql .= ", break_end = NULL, state = 'descanso'";
            } elseif ($tipo === 'descanso_fin') {
                $sql .= ", state = 'presente'";
            }
            if ($registradoPor !== null) {
                $sql .= ", registered_by = ?";
            }
            $sql .= " WHERE id = ?";

            $stmt = $this->conn->prepare($sql);
            if ($registradoPor !== null) {
                $stmt->bindValue(1, (int) $registradoPor, PDO::PARAM_INT);
                $stmt->bindValue(2, (int) $actual['id'], PDO::PARAM_INT);
            } else {
                $stmt->bindValue(1, (int) $actual['id'], PDO::PARAM_INT);
            }
            $stmt->execute();

            if ($metodoCol !== null) {
                $this->setMetodo((int) $actual['id'], $metodoCol, $metodo);
            }
        }

        return ['ok' => true, 'msg' => $this->mensajeDe($tipo)];
    }

    /**
     * CURDATE() del servidor. Un unico origen para el "hoy" del modulo.
     */
    public static function fechaDeMySQL()
    {
        if (isset($GLOBALS['__db'])) {
            $stmt = $GLOBALS['__db']->prepare("SELECT CURDATE()");
            $stmt->execute();

            return $stmt->fetchColumn();
        }

        return date('Y-m-d');
    }

    /**
     * Minutos de CURTIME(), cacheados por request para no repetir la consulta
     * en cada fila decorada de una tabla.
     */
    private static $minutosAhora = null;

    public static function minutosAhora()
    {
        if (self::$minutosAhora !== null) {
            return self::$minutosAhora;
        }

        $min = null;
        if (isset($GLOBALS['__db'])) {
            try {
                $stmt = $GLOBALS['__db']->prepare("SELECT CURTIME()");
                $stmt->execute();
                $min = self::minutos($stmt->fetchColumn());
            } catch (PDOException $e) {
                $min = null;
            }
        }

        if ($min === null) {
            $ahora = new DateTime();
            $min = (int) $ahora->format('G') * 60 + (int) $ahora->format('i');
        }

        self::$minutosAhora = $min;

        return $min;
    }

    private function setMetodo($attendanceId, $columna, $metodo)
    {
        $stmt = $this->conn->prepare("UPDATE attendances SET $columna = ? WHERE id = ?");
        $stmt->bindValue(1, $metodo);
        $stmt->bindValue(2, (int) $attendanceId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function mensajeDe($tipo)
    {
        $m = [
            'entrada' => 'Entrada registrada correctamente.',
            'salida' => 'Salida registrada. Buen turno.',
            'descanso_inicio' => 'Descanso iniciado.',
            'descanso_fin' => 'Fin del descanso.',
        ];

        return $m[$tipo] ?? 'Registro guardado.';
    }

    // ------------------------------------------------------------------
    // Ajuste manual por parte de un supervisor
    // ------------------------------------------------------------------

    /**
     * Crea o reemplaza el registro de un empleado para una fecha cualquiera.
     * Es la via de correccion: el supervisor puede fechar un turno pasado.
     */
    public function registrarManual($employeeId, $fecha, $checkIn = null, $checkOut = null, $state = 'presente', $registradoPor = null, $notas = null)
    {
        if ($checkIn !== null && $checkOut !== null) {
            if (self::minutos($checkOut) < self::minutos($checkIn)) {
                return ['ok' => false, 'msg' => 'La salida no puede ser anterior a la entrada.'];
            }
        }

        $existente = $this->deEmpleado($employeeId, $fecha);

        if ($existente === null) {
            $sql = "INSERT INTO attendances
                        (employee_id, attendance_date, check_in, check_out, state,
                         check_in_method, check_out_method, notes, registered_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, (int) $employeeId, PDO::PARAM_INT);
            $stmt->bindValue(2, $fecha);
            $stmt->bindValue(3, $checkIn);
            $stmt->bindValue(4, $checkOut);
            $stmt->bindValue(5, $state);
            $stmt->bindValue(6, $checkIn !== null ? 'manual' : null);
            $stmt->bindValue(7, $checkOut !== null ? 'manual' : null);
            $stmt->bindValue(8, $notas);
            $stmt->bindValue(9, $registradoPor !== null ? (int) $registradoPor : null, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $sql = "UPDATE attendances
                    SET check_in = ?, check_out = ?, state = ?, notes = ?, registered_by = ?
                    WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, $checkIn);
            $stmt->bindValue(2, $checkOut);
            $stmt->bindValue(3, $state);
            $stmt->bindValue(4, $notas);
            $stmt->bindValue(5, $registradoPor !== null ? (int) $registradoPor : null, PDO::PARAM_INT);
            $stmt->bindValue(6, (int) $existente['id'], PDO::PARAM_INT);
            $stmt->execute();
        }

        return ['ok' => true, 'msg' => 'Registro de asistencia guardado.'];
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM attendances WHERE id = ?");
        $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    // ------------------------------------------------------------------
    // Calculos
    // ------------------------------------------------------------------

    /**
     * Minutos desde medianoche de una hora TIME de MySQL ("H:MM:SS").
     */
    public static function minutos($time)
    {
        if ($time === null || $time === '') {
            return null;
        }
        $p = explode(':', $time);

        return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
    }

    /**
     * "7h 30m" a partir de minutos.
     */
    public static function horasMinutos($minutos)
    {
        if ($minutos === null) {
            return '—';
        }
        $minutos = (int) $minutos;
        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return $h > 0 ? "{$h}h " . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . "m" : "{$m}m";
    }

    /**
     * Calcula derived de una fila de asistencia + su turno.
     * Devuelve el mismo array con las claves calculadas agregadas.
     */
    public static function decorar($fila)
    {
        if ($fila === null) {
            return null;
        }

        $entrada = self::minutos($fila['check_in'] ?? null);
        $salida = self::minutos($fila['check_out'] ?? null);
        $turnoFin = self::minutos($fila['end_time'] ?? null);
        $turnoIni = self::minutos($fila['start_time'] ?? null);

        // Si la jornada sigue abierta, el tiempo_async se cuenta hasta ahora.
        if ($entrada !== null && $salida === null) {
            $salida = self::minutosAhora();
        }

        $brkIni = self::minutos($fila['break_start'] ?? null);
        $brkFin = self::minutos($fila['break_end'] ?? null);
        if ($brkIni !== null && $brkFin === null) {
            $brkFin = self::minutosAhora();
        }

        $bruto = null;
        $cerradoReal = ($fila['check_out'] ?? null) !== null;

        if ($entrada !== null && $salida !== null) {
            $bruto = $salida - $entrada;
            // Wrap a medianoche solo tiene sentido en una jornada ya cerrada
            // (turno nocturno: entrada 22:00, salida 06:00). Con la jornada
            // abierta el "ahora" puede ser anterior a la entrada por desfase
            // de reloj o por haber marcado antes de empezar: en ese caso se
            // cuenta 0 en vez de inventar 24 horas.
            if ($bruto < 0) {
                $bruto = $cerradoReal ? $bruto + 1440 : 0;
            }
        }

        $descanso = null;
        if ($brkIni !== null && $brkFin !== null) {
            $descanso = $brkFin - $brkIni;
            if ($descanso < 0) {
                $descanso += 1440;
            }
        }

        $fila['min_bruto'] = $bruto;
        $fila['min_descanso'] = $descanso;
        $fila['min_efectivos'] = ($bruto !== null && $descanso !== null)
            ? max(0, $bruto - $descanso)
            : $bruto;
        $fila['horas_texto'] = self::horasMinutos($fila['min_efectivos']);
        $fila['descanso_texto'] = $descanso !== null ? self::horasMinutos($descanso) : '—';

        // Entrada posterior al inicio del turno.
        $fila['tarde'] = ($entrada !== null && $turnoIni !== null && $entrada > $turnoIni);
        $fila['min_tarde'] = $fila['tarde'] ? $entrada - $turnoIni : 0;

        // Salida estimada: el fin del turno del dia.
        $fila['salida_estimada'] = $fila['end_time'] ?? null;
        $fila['salida_estimada_texto'] = $fila['end_time'] !== null
            ? substr($fila['end_time'], 0, 5)
            : '—';

        $fila['en_descanso'] = ($brkIni !== null && $fila['break_end'] === null);
        $fila['abierta'] = ($entrada !== null && $salida === null && $fila['check_out'] === null);

        // Cuanto se ha cubierto del turno.
        if ($turnoIni !== null && $turnoFin !== null && $entrada !== null) {
            $largo = $turnoFin - $turnoIni;
            if ($largo < 0) {
                $largo += 1440;
            }
            $fila['avance'] = $largo > 0
                ? max(0, min(100, (int) round(($fila['min_efectivos'] / $largo) * 100)))
                : 0;
        } else {
            $fila['avance'] = null;
        }

        return $fila;
    }
}