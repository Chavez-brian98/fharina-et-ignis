<?php

require_once __DIR__ . '/../models/Employee.php';
require_once __DIR__ . '/../models/AuditLog.php';

class EmployeeController
{
    private $db;
    private $employeeModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->employeeModel = new Employee($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $employees = $this->employeeModel->getAll();
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
        $roles = Employee::roles();
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

        if ($this->employeeModel->create($name, $last_name, $idDocument, $login['email'], $login['password_hash'], $login['role'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $baseSalary, $profilePhoto)) {
            $recordId = (int) $this->db->lastInsertId();
            $new = $this->employeeModel->getById($recordId);
            $this->auditModel->write('create', 'empleados', $recordId, null, $new ?: null, 'Empleado creado.');
            flash('success', 'Empleado creado correctamente.');
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

        $roles = Employee::roles();
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

        if ($profilePhoto === null) {
            $profilePhoto = isset($_POST['remove_profile_photo']) ? null : (($employee['profile_photo'] ?? null) ?: null);
        }

        if ($this->employeeModel->update($id, $name, $last_name, $idDocument, $login['email'], $login['password_hash'], $login['role'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $baseSalary, $status, $profilePhoto)) {
            $after = $this->employeeModel->getById($id);
            $this->auditModel->write('update', 'empleados', $id, $employee, $after ?: null, 'Empleado actualizado.');
            flash('success', 'Empleado actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    private function resolveLogin($existing, $isCreate, &$errorMessage)
    {
        $role = isset($_POST['role']) && $_POST['role'] !== '' ? $_POST['role'] : null;

        if ($role === null || !in_array($role, Employee::roles(), true)) {
            $errorMessage = 'Selecciona un rol válido para el empleado.';
            return false;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' && $password === '') {
            return ['email' => null, 'password_hash' => null, 'role' => $role];
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
            'role' => $role,
        ];
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
}
