<?php

require_once __DIR__ . '/../models/Employee.php';

class EmployeeController
{
    private $db;
    private $employeeModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->employeeModel = new Employee($db);
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
        $roles = $this->employeeModel->getAllRoles();
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
        $position = trim($_POST['position'] ?? '');
        $baseSalary = $_POST['base_salary'] ?? '';

        if ($name === '' || $last_name === '' || $idDocument === '' || $hire_date === '' || $position === '' || $baseSalary === '') {
            flash('error', 'Los campos nombre, apellido, DUI, fecha de contratación, cargo y salario base son obligatorios.');
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

        if ($login['username'] !== null && $this->employeeModel->usernameExists($login['username'])) {
            flash('error', 'Ya existe una cuenta con ese nombre de usuario.');
            header('Location: ' . url('employees/create'));
            exit;
        }

        if ($this->employeeModel->create($name, $last_name, $idDocument, $login['username'], $login['email'], $login['password_hash'], $login['role_id'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $position, $baseSalary)) {
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

        $roles = $this->employeeModel->getAllRoles();
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
        $position = trim($_POST['position'] ?? '');
        $baseSalary = $_POST['base_salary'] ?? '';
        $status = $_POST['status'] ?? 'active';

        if ($name === '' || $last_name === '' || $idDocument === '' || $hire_date === '' || $position === '' || $baseSalary === '') {
            flash('error', 'Los campos nombre, apellido, DUI, fecha de contratación, cargo y salario base son obligatorios.');
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

        if ($login['username'] !== null && $this->employeeModel->usernameExists($login['username'], $id)) {
            flash('error', 'Ya existe otra cuenta con ese nombre de usuario.');
            header('Location: ' . url('employees/edit/' . $id));
            exit;
        }

        if ($this->employeeModel->update($id, $name, $last_name, $idDocument, $login['username'], $login['email'], $login['password_hash'], $login['role_id'], $phone ?: null, $address ?: null, $birth_date, $hire_date, $position, $baseSalary, $status)) {
            flash('success', 'Empleado actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    private function resolveLogin($existing, $isCreate, &$errorMessage)
    {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $roleId = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;

        $hasAccount = $existing && !empty($existing['has_login']);
        $wantsAccount = $username !== '' || $email !== '' || $password !== '' || $roleId !== null;

        if (!$hasAccount && !$wantsAccount) {
            return ['username' => null, 'email' => null, 'password_hash' => null, 'role_id' => null];
        }

        if ($email === '' || $username === '' || $roleId === null) {
            $errorMessage = 'Para la cuenta de acceso completa nombre de usuario, correo electrónico y rol.';
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
            'username' => $username,
            'email' => $email,
            'password_hash' => $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
            'role_id' => $roleId,
        ];
    }

    public function toggle($id)
    {
        if ($this->employeeModel->toggleStatus($id)) {
            flash('success', 'Estado del empleado actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del empleado.');
        }

        header('Location: ' . url('employees'));
        exit;
    }

    public function delete($id)
    {
        if ($this->employeeModel->delete($id)) {
            flash('success', 'Empleado eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el empleado. Asegúrate de que no tenga turnos, asistencias, ventas u otros registros asociados.');
        }

        header('Location: ' . url('employees'));
        exit;
    }
}
