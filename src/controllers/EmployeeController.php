<?php

require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/Permiso.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Shift.php';
require_once __DIR__ . '/../models/EmpleadoFace.php';

class EmployeeController
{
    private $db;
    private $employeeModel;
    private $roleModel;
    private $auditModel;
    private $shiftModel;
    private $faceModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->employeeModel = new Employee($db);
        $this->roleModel = new Role($db);
        $this->auditModel = new AuditLog($db);
        $this->shiftModel = new Shift($db);
        $this->faceModel = new EmpleadoFace($db);
    }

    public function index()
    {
        $employees = $this->employeeModel->getAll();
        $roles = $this->roleModel->getAll();

        $this->decoraQrYBiometria($employees);
        $semana = $this->semanaActual();

        // Un solo rango para toda la tabla: el modal de cada fila solo busca su
        // semana en el array, en vez de una consulta por empleado.
        $turnosSemana = [];
        foreach ($this->shiftModel->rango($semana['desde'], $semana['hasta']) as $t) {
            $turnosSemana[(int) $t['employee_id']][] = $t;
        }

        $title = 'Empleados';
        $currentModule = 'employees';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Empleados', 'url' => null],
        ];

        require_once __DIR__ . '/../views/employees/index.php';
    }

public function create()
    {
        $roles = $this->roleModel->getAllForSelect();
        $esAdmin = Permiso::esAdminActual();
        $rostro = null;
        $title = 'Nuevo Empleado';
        $currentModule = 'employees';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Empleados', 'url' => url('employees')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/employees/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('employees'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $idDocument = trim($_POST['id_document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;
        $hire_date = trim($_POST['hire_date'] ?? '');
        $baseSalary = $_POST['base_salary'] ?? '';

        if ($name === '' || $last_name === '' || $idDocument === '' || $hire_date === '' || $baseSalary === '') {
            flash('error', 'Los campos nombre, apellido, DUI, fecha de contratación y salario base son obligatorios.');
            header('Location: ' . url('employees/create'));
            exit;
        }

        if (!validar_dui($idDocument)) {
            flash('error', 'El DUI debe tener el formato 00000000-0 (8 dígitos, guion y un dígito).');
            header('Location: ' . url('employees/create'));
            exit;
        }

        if ($baseSalary < 0) {
            flash('error', 'El salario base no puede ser negativo.');
            header('Location: ' . url('employees/create'));
            exit;
        }

        if ($this->employeeModel->documentExists($idDocument)) {
            flash('error', 'Ya existe un empleado registrado con ese documento.');
            header('Location: ' . url('employees/create'));
            exit;
        }

        $loginError = null;
        $login = $this->resolveLogin(null, true, $loginError);

        if ($login === false) {
            flash('error', $loginError);
            header('Location: ' . url('employees/create'));
            exit;
        }

        if ($login['email'] !== null && $this->employeeModel->emailExists($login['email'])) {
            flash('error', 'Ya existe una cuenta con ese correo electrónico.');
            header('Location: ' . url('employees/create'));
            exit;
        }

        $profilePhoto = upload_image('profile_photo');

        if ($profilePhoto === false) {
            header('Location: ' . url('employees/create'));
            exit;
        }

        // Un alta siempre "cambia" la foto: si vino descriptor, es el rostro de
        // esta foto recién subida.
        $fotoCambio = $profilePhoto !== null;

        if ($this->employeeModel->create($name, $last_name, $idDocument, $login['email'], $login['password_hash'], $login['role_id'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $baseSalary, $profilePhoto)) {
            $recordId = (int) $this->db->lastInsertId();
            $new = $this->employeeModel->getById($recordId);
            $this->auditModel->write('create', 'empleados', $recordId, null, $new ?: null, 'Empleado creado.');

            $mensajeBiometria = $this->aplicaBiometria($recordId, $fotoCambio);

            flash('success', $mensajeBiometria ?: 'Empleado creado correctamente. Ya podés ajustar sus permisos desde el ícono del escudo.');
        } else {
            flash('error', 'No se pudo crear el empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    public function edit($id)
    {
        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            flash('error', 'Empleado no encontrado.');
            header('Location: ' . url('employees'));
            exit;
        }

        $roles = $this->roleModel->getAll();
        $esAdmin = Permiso::esAdminActual();
        $rostro = $esAdmin ? $this->faceModel->deEmpleado($id) : null;
        $title = 'Editar Empleado';
        $currentModule = 'employees';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Empleados', 'url' => url('employees')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/employees/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('employees'));
            exit;
        }

        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            flash('error', 'Empleado no encontrado.');
            header('Location: ' . url('employees'));
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $idDocument = trim($_POST['id_document'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $birth_date = trim($_POST['birth_date'] ?? '');
        $birth_date = $birth_date !== '' ? $birth_date : null;
        $hire_date = trim($_POST['hire_date'] ?? '');
        $baseSalary = $_POST['base_salary'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $last_name === '' || $idDocument === '' || $hire_date === '' || $baseSalary === '') {
            flash('error', 'Los campos nombre, apellido, DUI, fecha de contratación y salario base son obligatorios.');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        if (!validar_dui($idDocument)) {
            flash('error', 'El DUI debe tener el formato 00000000-0 (8 dígitos, guion y un dígito).');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        if ($baseSalary < 0) {
            flash('error', 'El salario base no puede ser negativo.');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        if ($this->employeeModel->documentExists($idDocument, $id)) {
            flash('error', 'Ya existe otro empleado registrado con ese documento.');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        $loginError = null;
        $login = $this->resolveLogin($employee, false, $loginError);

        if ($login === false) {
            flash('error', $loginError);
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        if ($login['email'] !== null && $this->employeeModel->emailExists($login['email'], $id)) {
            flash('error', 'Ya existe otra cuenta con ese correo electrónico.');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        $profilePhoto = upload_image('profile_photo');

        if ($profilePhoto === false) {
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        $fotoPrevia = $employee['profile_photo'] ?? null;
        $quitarFoto = isset($_POST['remove_profile_photo']);
        $fotoCambio = $profilePhoto !== null || ($quitarFoto && $fotoPrevia);

        if ($profilePhoto === null) {
            $profilePhoto = $quitarFoto ? null : ($fotoPrevia ?: null);
        }

        if ($this->employeeModel->update($id, $name, $last_name, $idDocument, $login['email'], $login['password_hash'], $login['role_id'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $baseSalary, $status, $profilePhoto)) {
            $after = $this->employeeModel->getById($id);
            $this->auditModel->write('update', 'empleados', $id, $employee, $after ?: null, 'Empleado actualizado.');

            $mensajeBiometria = $this->aplicaBiometria($id, $fotoCambio);

            flash('success', $mensajeBiometria ?: 'Empleado actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    /**
     * Guarda desde el modal de la lista el rol del empleado y su matriz de
     * permisos específicos (checkboxes). Un módulo con «Bloquear» marcado queda
     * denegado por completo; sin marcar nada, el módulo se hereda del rol.
 */
public function updatePermisos($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('employees'));
            exit;
        }

        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            flash('error', 'Empleado no encontrado.');
            header('Location: ' . url('employees'));
            exit;
        }

        $roleId = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;

        if ($roleId === null || $this->roleModel->getById($roleId) === false) {
            flash('error', 'El rol seleccionado no existe.');
            header('Location: ' . url('employees'));
            exit;
        }

        if ((int) ($employee['role_is_admin'] ?? 0) === 1 && (int) $employee['role_id'] !== $roleId) {
            flash('error', 'No se puede cambiar el rol de un administrador sin antes quitarle el acceso total.');
            header('Location: ' . url('employees'));
            exit;
        }

        $antes = Permiso::permisosDeEmpleado($id);
        $this->employeeModel->setRole($id, $roleId);

        // El modal envía la matriz sólo si se llegó a mostrarla (p. ej. no se
        // manda cuando el rol elegido es de acceso total): si no llegó, las
        // excepciones guardadas se dejan intactas.
        $hayMatriz = ($_POST['perm_matrix'] ?? '') === '1';

        if ($hayMatriz && (int) ($employee['role_is_admin'] ?? 0) !== 1) {
            Permiso::guardarPermisosDeEmpleado($id, $this->permisosDesdeChecks());
        }

        $despues = $hayMatriz ? Permiso::permisosDeEmpleado($id) : $antes;

        $cambioRol = (int) $employee['role_id'] !== $roleId;

        if ($cambioRol || $antes !== $despues) {
            $nuevo = $this->employeeModel->getById($id);
            $this->auditModel->write(
                'update',
                'employee_permissions',
                $id,
                ['rol' => $employee['role'], 'permisos' => self::describeOverrides($antes)],
                ['rol' => $nuevo['role'] ?? $employee['role'], 'permisos' => self::describeOverrides($despues)],
                'Rol y permisos actualizados desde la matriz del empleado.'
            );
            flash('success', 'Permisos de «' . trim($employee['name'] . ' ' . $employee['last_name']) . '» actualizados.');
        } else {
            flash('info', 'No hubo cambios en los permisos de «' . trim($employee['name'] . ' ' . $employee['last_name']) . '».');
        }

        header('Location: ' . url('employees'));
        exit;
    }

/**
     * Traduce la matriz de checkboxes del modal a la estructura de overrides:
     * «Bloquear» marcado = módulo denegado; alguna acción marcada = permiso
     * específico concedido; nada marcado = se hereda del rol (no hay fila).
     * Un módulo sin «Ver» marcado no genera fila: las otras acciones dependen de
     * Ver, así que se ignoran en vez de bloquear el módulo por error.
     */
    private function permisosDesdeChecks()
    {
        $map = [];

        foreach (Permiso::modulos() as $key => $modulo) {
            if (isset($_POST['bloqueo_' . $key])) {
                $map[$key] = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
                continue;
            }

            if (!isset($_POST['perm_' . $key . '_view'])) {
                continue;
            }

            $perm = ['view' => true];

            foreach (['create', 'edit', 'delete'] as $accion) {
                $perm[$accion] = isset($_POST['perm_' . $key . '_' . $accion]);
            }

            $map[$key] = $perm;
        }

        return $map;
    }

    private function resolveLogin($existing, $isCreate, &$errorMessage)
    {
        $roleId = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;

        if ($roleId === null || $this->roleModel->getById($roleId) === false) {
            $errorMessage = 'Selecciona un rol válido para el empleado.';
            return false;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' && $password === '') {
            return ['email' => null, 'password_hash' => null, 'role_id' => $roleId];
        }

        if ($email === '') {
            $errorMessage = 'Para la cuenta de acceso completa el correo electrónico.';
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMessage = 'El correo electrónico no es válido.';
            return false;
        }

        if ($isCreate && $password === '') {
            $errorMessage = 'Ingresa una contraseña para crear la cuenta de acceso.';
            return false;
        }

        if ($password !== '' && strlen($password) < 6) {
            $errorMessage = 'La contraseña debe tener al menos 6 caracteres.';
            return false;
        }

        return [
            'email' => $email,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'role_id' => $roleId,
        ];
    }

    /**
     * Convierte los overrides a texto legible para la bitácora.
     */
    private static function describeOverrides(array $overrides)
    {
        $catalog = Permiso::modulos();
        $acciones = Permiso::acciones();
        $descripcion = [];

        foreach ($overrides as $module => $perm) {
            $label = $catalog[$module]['label'] ?? $module;
            $concedidas = [];

            foreach ($acciones as $action => $accionLabel) {
                if (!empty($perm[$action])) {
                    $concedidas[] = $accionLabel;
                }
            }

            $descripcion[$label] = $concedidas ? implode(', ', $concedidas) : 'Sin acceso';
        }

        return $descripcion;
    }

    public function toggle($id)
    {
        $before = $this->employeeModel->getById($id);

        if ($this->employeeModel->toggleStatus($id)) {
            $after = $this->employeeModel->getById($id);
            $this->auditModel->write('toggle', 'empleados', $id, $before, $after ?: null, 'Estado del empleado actualizado.');
            flash('success', 'Estado del empleado actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->employeeModel->getById($id);

        if ($this->employeeModel->delete($id)) {
            $this->auditModel->write('delete', 'empleados', $id, $before ?: null, null, 'Empleado eliminado.');
            flash('success', 'Empleado eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el empleado. Asegúrate de que no tenga turnos, asistencias, ventas u otros registros asociados.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    // ==================================================================
    // QR de asistencia
    // ==================================================================

    /**
     * Emite un token nuevo para el empleado e invalida el QR anterior.
     *
     * Reservado a administradores: el gate de index.php ya exige el permiso
     * 'edit' del módulo (qrRegenerate -> edit en Permiso::accionDeRuta), pero
     * rotar el QR de un compañero no es una edición de ficha, así que se
     * comprueba tambien el rol administrador.
     */
    public function qrRegenerate($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('employees'));
            exit;
        }

        if (!$this->esAdmin()) {
            flash('error', 'Solo un administrador puede generar el QR de un empleado.');
            header('Location: ' . url('employees'));
            exit;
        }

        $employee = $this->employeeModel->getById($id);

        if (!$employee) {
            flash('error', 'Empleado no encontrado.');
            header('Location: ' . url('employees'));
            exit;
        }

        $this->employeeModel->regenerateQr($id);

        // En la bitácora solo va "había token / hay token nuevo": el valor viejo
        // es una credencial que acaba de morir y el nuevo es la que sirve.
        $this->auditModel->write(
            'update',
            'empleados',
            $id,
            ['qr_token' => '(emitido previamente)'],
            ['qr_token' => '(nuevo)'],
            'QR de asistencia regenerado.'
        );

        flash('success', 'QR generado. El código anterior ya no sirve.');
        header('Location: ' . url('employees'));
        exit;
    }

    // ==================================================================
    // Biometría: enrolment facial desde la foto del empleado
    // ==================================================================

    /**
     * Aplica el descriptor que viaja en el formulario del empleado y, si la foto
     * cambió sin regenerarlo, descarta el rostro viejo: un embedding describes
     * una cara, y si la foto ya es otra el vector guardado apunta a otra persona.
     *
     * Todo esto es exclusivo de administradores: un supervisor con permiso de
     * edición no puede escribir en empleado_faces.
     *
     * @param int  $employeeId
     * @param bool $fotoCambio si el alta/edición reemplaza o quita la foto
     * @return string|null mensaje para el usuario, o null si no había nada que hacer
     */
    private function aplicaBiometria($employeeId, $fotoCambio)
    {
        if (!$this->esAdmin()) {
            // Sin permisos de admin se ignoran los campos en silencio: no se
            // avisa por la UI y el servidor no los honra.
            return null;
        }

        $antesRostro = $this->faceModel->deEmpleado($employeeId);
        $antesBitacora = $antesRostro
            ? ['model' => $antesRostro['model'], 'quality' => $antesRostro['quality']]
            : null;

        if (isset($_POST['remove_face'])) {
            if ($antesRostro) {
                $this->faceModel->eliminar($employeeId);
                $this->auditModel->write(
                    'face_delete',
                    'empleados',
                    $employeeId,
                    ['model' => $antesRostro['model']],
                    null,
                    'Datos biométricos eliminados desde la ficha del empleado.'
                );
                return 'Biometría eliminada. El empleado volverá a marcar solo con su QR.';
            }
            return null;
        }

        $descriptor = EmpleadoFace::normalizar($_POST['descriptor'] ?? null);

        if ($descriptor !== null) {
            $calidad = isset($_POST['quality']) && is_numeric($_POST['quality'])
                ? (float) $_POST['quality']
                : null;

            $this->faceModel->registrar($employeeId, $descriptor, $calidad);

            $this->auditModel->write(
                'face_enroll',
                'empleados',
                $employeeId,
                $antesBitacora,
                // NUNCA el vector: es el dato biométrico en sí.
                ['model' => EmpleadoFace::MODELO, 'quality' => $calidad],
                'Rostro enrolado para el quiosco de asistencia.'
            );

            return null;
        }

        //Foto nueva + rostro viejo = descriptor obsoleto.
        if ($fotoCambio && $antesRostro) {
            $this->faceModel->eliminar($employeeId);
            $this->auditModel->write(
                'face_delete',
                'empleados',
                $employeeId,
                ['model' => $antesRostro['model']],
                null,
                'Datos biométricos descartados al cambiar la foto de perfil.'
            );

            return 'La foto cambió, así que la biometría anterior quedó descartada. Generala de nuevo para poder marcar con el rostro.';
        }

        return null;
    }

    // ==================================================================
    // Helpers
    // ==================================================================

    /** ¿El usuario en sesión es administrador? */
    private function esAdmin()
    {
        return Permiso::esAdminActual();
    }

    /**
     * Lunes y domingo de la semana en curso, sobre el reloj de MySQL.
     * Misma regla que usa ScheduleController::semanaDe().
     */
    private function semanaActual()
    {
        $d = new DateTime(Attendance::fechaDeMySQL());
        $lunes = clone $d;
        $lunes->modify('-' . (($d->format('w') == 0 ? 6 : $d->format('w')) - 1) . ' days');
        $domingo = clone $lunes;
        $domingo->modify('+6 days');

        return [
            'desde' => $lunes->format('Y-m-d'),
            'hasta' => $domingo->format('Y-m-d'),
        ];
    }

    /**
     * Agrega a cada fila lo que necesitan los modales: si tiene rostro y si
     * tiene QR emitido. El token NO se genera acá (serían N UPDATE por visita);
     * si falta, la vista ofrece emitirlo.
     */
    private function decoraQrYBiometria(array &$employees)
    {
        foreach ($employees as $i => $emp) {
            $rostro = $this->faceModel->deEmpleado((int) $emp['id']);

            $employees[$i]['face_enrolled'] = $rostro !== null;
            $employees[$i]['face_created_at'] = $rostro['created_at'] ?? null;
            $employees[$i]['face_quality'] = $rostro['quality'] ?? null;
        }
    }
}
