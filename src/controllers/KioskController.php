<?php

/**
 * Quiosco de asistencia por reconocimiento facial / QR (/kiosco).
 *
 * A diferencia del resto de modulos NO exige sesion de empleado: se abre con
 * una clave del local y se bloquea con la misma. Por eso vive fuera del
 * catalogo de permisos (Permiso::moduloDeControlor devuelve null para el y el
 * gate de index.php lo deja pasar) y se protege solo:
 *
 *   1. index/unlock exigen la clave correcta (hash_equals + enfriamiento por
 *      intentos fallidos).
 *   2. verificar() exige POST + quiosco desbloqueado + la clave de nuevo en el
 *      cuerpo, para que otra pagina de la red local no pueda POSTear en
 *      nombre del quiosco.
 *
 * La identificacion es "QR o cara": el token del QR busca al empleado, y si
 * no hay token se compara el descriptor de la persona frente a la camara
 * contra los rostros enrolados. El matching ocurre en PHP para que los
 * descriptores guardados nunca salgan del servidor.
 *
 * El metodo con el que se registro queda en check_in_method / check_out_method
 * ('qr' o 'rostro'), que es justo para lo que existe ese ENUM.
 */
class KioskController
{
    /** Intentos de clave fallidos antes de enfriar el quiosco. */
    const INTENTOS_MAXIMOS = 5;

    /** Minutos de enfriamiento tras agotar los intentos. */
    const ENFRIAMIENTO_MINUTOS = 5;

    private $conn;
    private $attendance;
    private $employees;
    private $faces;
    private $audit;
    private $shifts;

    public function __construct($db)
    {
        $this->conn = $db;
        $this->attendance = new Attendance($db);
        $this->employees = new Employee($db);
        $this->faces = new EmpleadoFace($db);
        $this->audit = new AuditLog($db);
        $this->shifts = new Shift($db);
    }

    /**
     * Pantalla unica: la clave si el quiosco esta bloqueado, la camara si ya
     * se desbloqueo. Sin sidebar ni breadcrumbs: es una pantalla de pared.
     */
    public function index()
    {
        $titulo = 'Quiosco de asistencia';

        if ($this->desbloqueado()) {
            $enfriamiento = $this->minutosDeEnfriamiento();
            $rostrosEnrolados = $this->faces->enroladosActivos();

            require __DIR__ . '/../views/kiosk/index.php';

            return;
        }

        $error = null;
        $enfriamiento = $this->minutosDeEnfriamiento();

        require __DIR__ . '/../views/kiosk/locked.php';
    }

    /** POST: valida la clave del local y abre el quiosco. */
    public function unlock()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('kiosco'));
            exit;
        }

        if ($this->desbloqueado()) {
            header('Location: ' . url('kiosco'));
            exit;
        }

        if ($this->minutosDeEnfriamiento() > 0) {
            $this->redirectConError('Demasiados intentos. Esperá unos minutos.');
        }

        $clave = (string) ($_POST['clave'] ?? '');
        $correcta = (string) setting('kiosk_key', '');

        // hash_equals evita que el tiempo de respuesta delata el prefijo correcto.
        if ($correcta === '' || !hash_equals($correcta, $clave)) {
            $this->registrarIntentoFallido();

            $msg = 'Clave incorrecta.';
            if ($this->minutosDeEnfriamiento() > 0) {
                $msg = 'Demasiados intentos. Esperá unos minutos.';
            }
            $this->redirectConError($msg);
        }

        unset($_SESSION['kiosco_intentos'], $_SESSION['kiosco_bloqueado_hasta']);
        session_regenerate_id(true);
        $_SESSION['kiosco'] = ['desde' => time(), 'nivel' => 1];

        $this->audit->write(
            'kiosk_open',
            null,
            null,
            null,
            null,
            'Quiosco de asistencia desbloqueado'
        );

        header('Location: ' . url('kiosco'));
        exit;
    }

    /** POST: vuelve a pedir la clave. */
    public function lock()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('kiosco'));
            exit;
        }

        unset($_SESSION['kiosco']);
        $this->audit->write('kiosk_close', null, null, null, null, 'Quiosco bloqueado');

        header('Location: ' . url('kiosco'));
        exit;
    }

    /**
     * POST: corazon del quiosco. Acepta qr_token y/o descriptor y devuelve
     * JSON con el empleado reconocido y la marcacion aplicada.
     */
    public function verificar()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
        }

        if (!$this->desbloqueado()) {
            $this->json(['ok' => false, 'mensaje' => 'El quiosco está bloqueado.'], 403);
        }

        // La clave va en el cuerpo: sin esto, cualquier pagina de la red local
        // podria POSTear contra un quiosco abierto.
        if (!hash_equals((string) setting('kiosk_key', ''), (string) ($_POST['clave'] ?? ''))) {
            $this->json(['ok' => false, 'mensaje' => 'Quiosco no válido.'], 403);
        }

        $accion = (string) ($_POST['accion'] ?? 'auto');
        $qrToken = trim((string) ($_POST['qr_token'] ?? ''));
        $descriptorCrudo = $_POST['descriptor'] ?? null;

        $descriptor = $descriptorCrudo !== null ? EmpleadoFace::normalizar($descriptorCrudo) : null;

        if ($qrToken === '' && $descriptor === null) {
            $this->json(['ok' => false, 'mensaje' => 'No se recibió ni el QR ni el rostro.'], 422);
        }

        // --- Identificación: el QR manda si viene, si no se busca el rostro ---
        $empleado = null;
        $metodo = 'rostro';
        $confianza = null;

        if ($qrToken !== '') {
            $metodo = 'qr';
            $fila = $this->employees->findByQrToken($qrToken);
            if ($fila === null) {
                $this->json(['ok' => false, 'mensaje' => 'Ese QR no está registrado.'], 404);
            }
            $empleado = $fila;
        } else {
            $coincidencia = $this->faces->buscar($descriptor, EmpleadoFace::UMBRAL_EUCLIDEANO, EmpleadoFace::MODELO);
            if ($coincidencia === null) {
                $this->json([
                    'ok' => false,
                    'mensaje' => 'No reconocí el rostro. Probá con luz de frente o usá tu QR.',
                ], 404);
            }
            $empleado = $this->employees->getById($coincidencia['employee_id']);
            if (!$empleado) {
                $this->json(['ok' => false, 'mensaje' => 'El empleado ya no existe.'], 404);
            }
            $confianza = [
                'distancia' => $coincidencia['distancia'],
                'similitud' => $coincidencia['similitud'],
                'texto' => EmpleadoFace::confianzaTexto($coincidencia['similitud']),
            ];
        }

        $employeeId = (int) $empleado['id'];

        // Turno asignado hoy: lo pinta la pantalla como "salida estimada" para
        // que el empleado sepa hasta cuando está su jornada. null si no hay.
        $turno = null;
        $filaTurno = $this->shifts->deEmpleado($employeeId, Attendance::fechaDeMySQL());
        if ($filaTurno) {
            $turno = [
                'inicio' => substr((string) $filaTurno['start_time'], 0, 5),
                'fin' => substr((string) $filaTurno['end_time'], 0, 5),
                'tipo' => Shift::tipoTexto($filaTurno['shift_type']),
            ];
        }

        // Una cuenta desactivada (empleado o rol) no marca, igual que no
        // puede iniciar sesion. Sin esto el quiosco seria un puerta trasera.
        if (($empleado['status'] ?? 'active') !== 'active') {
            $this->json([
                'ok' => false,
                'mensaje' => 'Tu cuenta está desactivada. Hablá con el encargado.',
            ], 403);
        }

        // --- Marcación ---
        $resultado = $this->aplicar($employeeId, $accion, $metodo);

        $this->audit->write(
            $metodo === 'qr' ? 'kiosk_qr' : 'kiosk_rostro',
            'attendances',
            // record_id es el id de la marcacion, no el del empleado: la bitacora
            // lista 'attendances', asi que un id de empleado no encuentra
            // ninguna fila.
            $resultado['registro'] ? $resultado['registro']['id'] : null,
            null,
            $resultado['registro'],
            $resultado['msg']
        );

        $this->json([
            'ok' => $resultado['ok'],
            'mensaje' => $resultado['msg'],
            'empleado' => [
                'id' => $employeeId,
                'nombre' => trim($empleado['name'] . ' ' . $empleado['last_name']),
                'foto' => $empleado['profile_photo'] ?? null,
            ],
            'accion' => $resultado['accion'],
            'metodo' => $metodo,
            'confianza' => $confianza,
            'registro' => $resultado['registro'],
            'turno' => $turno,
        ], $resultado['ok'] ? 200 : 422);
    }

    /**
     * Traduce la accion del kiosco a una llamada de Attendance y devuelve el
     * registro ya decorado.
     *
     * 'descanso' decide del lado del servidor si arrancar o terminar, para que el
     * cliente no tenga que adivinar. 'auto' es el valor por defecto de la pantalla
     * y tambien se resuelve aca: si cayera en la rama de descanso, la entrada del
     * turno quedaria registrada como "inicio de descanso".
     */
    private function aplicar($employeeId, $accion, $metodo)
    {
        $hoy = Attendance::fechaDeMySQL();
        $antes = $this->attendance->deEmpleado($employeeId, $hoy);

        if ($accion === 'auto' || $accion === '') {
            $accion = $this->accionSugerida($employeeId);
        }

        if ($accion === 'entrada') {
            $r = $this->attendance->marcarEntrada($employeeId, $metodo);
            $tipo = 'entrada';
        } elseif ($accion === 'salida') {
            $r = $this->attendance->marcarSalida($employeeId, $metodo);
            $tipo = 'salida';
        } else {
            $descansoAbierto = $antes && $antes['break_start'] !== null && $antes['break_end'] === null;
            if ($descansoAbierto) {
                $r = $this->attendance->terminarDescanso($employeeId, $metodo);
                $tipo = 'descanso_fin';
            } else {
                $r = $this->attendance->iniciarDescanso($employeeId, $metodo);
                $tipo = 'descanso_inicio';
            }
        }

        $despues = $this->attendance->deEmpleado($employeeId, $hoy);

        return [
            'ok' => $r['ok'],
            'msg' => $r['msg'],
            'accion' => $tipo,
            'registro' => $despues ? [
                'id' => (int) $despues['id'],
                'estado' => $despues['state'],
                'entrada' => $despues['check_in'],
                'salida' => $despues['check_out'],
                'descanso_desde' => $despues['break_start'],
                'descanso_hasta' => $despues['break_end'],
                'metodo' => $metodo,
            ] : null,
        ];
    }

    /** Sugerencia de la proxima accion, para el cliente. */
    private function accionSugerida($employeeId)
    {
        $hoy = Attendance::fechaDeMySQL();
        $r = $this->attendance->deEmpleado($employeeId, $hoy);

        if (!$r || $r['check_in'] === null) {
            return 'entrada';
        }
        if ($r['check_out'] !== null) {
            return 'entrada';
        }
        if ($r['break_start'] !== null && $r['break_end'] === null) {
            return 'descanso';
        }

        return 'salida';
    }

    // ---------------------------------------------------------------- helpers

    private function desbloqueado()
    {
        return !empty($_SESSION['kiosco']);
    }

    private function minutosDeEnfriamiento()
    {
        $hasta = $_SESSION['kiosco_bloqueado_hasta'] ?? null;
        if ($hasta === null) {
            return 0;
        }
        $restantes = $hasta - time();

        return $restantes > 0 ? (int) ceil($restantes / 60) : 0;
    }

    private function registrarIntentoFallido()
    {
        $intentos = (int) ($_SESSION['kiosco_intentos'] ?? 0) + 1;
        $_SESSION['kiosco_intentos'] = $intentos;

        if ($intentos >= self::INTENTOS_MAXIMOS) {
            $_SESSION['kiosco_bloqueado_hasta'] = time() + (self::ENFRIAMIENTO_MINUTOS * 60);
        }
    }

    private function redirectConError($mensaje)
    {
        // El quiosco no usa el sistema de flashes (no hay breadcrumb): el error
        // viaja por la sesion y lo lee la pantalla bloqueada.
        $_SESSION['kiosco_error'] = $mensaje;

        header('Location: ' . url('kiosco'));
        exit;
    }

    private function json($payload, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}