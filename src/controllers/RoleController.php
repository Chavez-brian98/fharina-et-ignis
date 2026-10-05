<?php

require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/../models/Permiso.php';
require_once __DIR__ . '/../models/AuditLog.php';

class RoleController
{
    private $db;
    private $roleModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->roleModel = new Role($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $roles = $this->roleModel->getAll();
        $title = 'Roles y Permisos';
        $currentModule = 'roles';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Roles y Permisos', 'url' => null],
        ];

        require_once __DIR__ . '/../views/roles/index.php';
    }

    public function create()
    {
        $rolePermissions = [];
        $role = [
            'id' => null,
            'name' => '',
            'description' => '',
            'is_admin' => 0,
            'status' => 'active',
        ];

        $title = 'Nuevo Rol';
        $currentModule = 'roles';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Roles y Permisos', 'url' => url('roles')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/roles/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('roles'));
            exit;
        }

        $name = $this->cleanName($_POST['name'] ?? '');
        $description = $this->cleanText($_POST['description'] ?? '');
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        $status = ($_POST['status'] ?? '') === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            flash('error', 'El nombre del rol es obligatorio.');
            header('Location: ' . url('roles/create'));
            exit;
        }

        if ($this->roleModel->nameExists($name)) {
            flash('error', 'Ya existe un rol registrado con ese nombre.');
            header('Location: ' . url('roles/create'));
            exit;
        }

        if ($this->roleModel->create($name, $description, $isAdmin, $status)) {
            $roleId = (int) $this->db->lastInsertId();
            Permiso::guardarPermisosDeRol($roleId, $this->permissionsFromPost());
            $new = $this->roleModel->getById($roleId);
            $this->auditModel->write('create', 'roles', $roleId, null, $new ?: null, 'Rol creado: ' . $name);
            flash('success', 'Rol creado correctamente.');
        } else {
            flash('error', 'No se pudo crear el rol.');
        }

        header('Location: ' . url('roles'));
        exit;
    }

    public function edit($id)
    {
        $role = $this->roleModel->getById($id);

        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: ' . url('roles'));
            exit;
        }

        $rolePermissions = Permiso::permisosDeRol($id);

        $title = 'Editar Rol';
        $currentModule = 'roles';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Roles y Permisos', 'url' => url('roles')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/roles/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('roles'));
            exit;
        }

        $role = $this->roleModel->getById($id);

        if (!$role) {
            flash('error', 'Rol no encontrado.');
            header('Location: ' . url('roles'));
            exit;
        }

        $name = $this->cleanName($_POST['name'] ?? '');
        $description = $this->cleanText($_POST['description'] ?? '');
        $isAdmin = isset($_POST['is_admin']) ? 1 : 0;
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if ($name === '') {
            flash('error', 'El nombre del rol es obligatorio.');
            header('Location: ' . url('roles/edit/' . $id));
            exit;
        }

        if ($this->roleModel->nameExists($name, $id)) {
            flash('error', 'Ya existe otro rol registrado con ese nombre.');
            header('Location: ' . url('roles/edit/' . $id));
            exit;
        }

        if (!$isAdmin && (int) $role['is_admin'] === 1 && $this->roleModel->countOtherAdmins($id) === 0) {
            flash('error', 'No se puede quitar el acceso total: debe quedar al menos un rol administrador.');
            header('Location: ' . url('roles/edit/' . $id));
            exit;
        }

        if ($this->roleModel->update($id, $name, $description, $isAdmin, $status)) {
            // Con is_admin los permisos no aplican (bypass), pero se guardan
            // igual para que al quitar la marca el rol ya tenga un base válido.
            Permiso::guardarPermisosDeRol($id, $this->permissionsFromPost());
            $after = $this->roleModel->getById($id);
            $this->auditModel->write('update', 'roles', $id, $role, $after ?: null, 'Rol actualizado: ' . $name);
            flash('success', 'Rol actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el rol.');
        }

        header('Location: ' . url('roles'));
        exit;
    }

    /**
     * Guarda la matriz de checkboxes del modal de permisos de un rol.
     */
    public function updatePermisos($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('roles'));
            exit;
        }

        $before = $this->roleModel->getById($id);

        if (!$before) {
            flash('error', 'Rol no encontrado.');
            header('Location: ' . url('roles'));
            exit;
        }

        if ((int) $before['is_admin'] === 1) {
            flash('error', 'Ese rol tiene acceso total: sus permisos no se pueden limitar.');
            header('Location: ' . url('roles'));
            exit;
        }

        $antes = Permiso::permisosDeRol($id);

        if (Permiso::guardarPermisosDeRol($id, $this->permissionsFromPost())) {
            $despues = Permiso::permisosDeRol($id);

            if ($antes !== $despues) {
                $this->auditModel->write(
                    'update',
                    'role_permissions',
                    $id,
                    ['rol' => $before['name'], 'permisos' => $this->describePermissions($antes)],
                    ['rol' => $before['name'], 'permisos' => $this->describePermissions($despues)],
                    'Permisos actualizados desde la matriz del rol: ' . $before['name']
                );
            }

            flash('success', 'Permisos de «' . Role::label($before['name']) . '» actualizados.');
        } else {
            flash('error', 'No se pudieron guardar los permisos del rol.');
        }

        header('Location: ' . url('roles'));
        exit;
    }

    public function toggle($id)
    {
        $before = $this->roleModel->getById($id);

        if ($before && (int) $before['is_admin'] === 1) {
            flash('error', 'El rol administrador no se puede desactivar.');
            header('Location: ' . url('roles'));
            exit;
        }

        if ($this->roleModel->toggleStatus($id)) {
            $after = $this->roleModel->getById($id);
            $this->auditModel->write('toggle', 'roles', $id, $before ?: null, $after ?: null, 'Estado del rol actualizado: ' . ($before['name'] ?? ''));
            flash('success', 'Estado del rol actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del rol.');
        }

        header('Location: ' . url('roles'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->roleModel->getById($id);

        if (!$before) {
            flash('error', 'Rol no encontrado.');
            header('Location: ' . url('roles'));
            exit;
        }

        if ((int) $before['is_admin'] === 1) {
            flash('error', 'El rol administrador no se puede eliminar.');
            header('Location: ' . url('roles'));
            exit;
        }

        if ($this->roleModel->countEmployees($id) > 0) {
            flash('error', 'No se puede eliminar un rol que tiene empleados asignados. Reasigna sus empleados primero.');
            header('Location: ' . url('roles'));
            exit;
        }

        if ($this->roleModel->delete($id)) {
            $this->auditModel->write('delete', 'roles', $id, $before, null, 'Rol eliminado: ' . $before['name']);
            flash('success', 'Rol eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el rol.');
        }

        header('Location: ' . url('roles'));
        exit;
    }

    /**
     * Lee los checks del grid de permisos del formulario de roles.
     */
    private function permissionsFromPost()
    {
        $map = [];
        $acciones = Permiso::acciones();

        foreach (Permiso::modulos() as $key => $modulo) {
            $perm = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];

            foreach ($acciones as $action => $label) {
                $perm[$action] = isset($_POST['perm_' . $key . '_' . $action]);
            }

            $map[$key] = $perm;
        }

        return $map;
    }

    /**
 * Traduce una matriz de permisos a texto legible para la bitácora.
 */
    private function describePermissions(array $map)
    {
        $catalog = Permiso::modulos();
        $acciones = Permiso::acciones();
        $texto = [];

        foreach ($map as $modulo => $accionesMap) {
            $marcadas = [];
            foreach ($acciones as $accion => $label) {
                if (!empty($accionesMap[$accion])) {
                    $marcadas[] = $label;
                }
            }
            if (empty($marcadas)) {
                continue;
            }
            $texto[] = ($catalog[$modulo]['label'] ?? $modulo) . ': ' . implode(', ', $marcadas);
        }

        return empty($texto) ? 'Sin permisos' : implode(' | ', $texto);
    }

    private function cleanName($value)
    {
        $clean = strtolower(trim(strip_tags($value)));
        $clean = preg_replace('/[^a-z0-9_]+/', '_', $clean);

        return trim($clean, '_');
    }

    private function cleanText($value)
    {
        $clean = trim(strip_tags($value));

        return $clean !== '' ? $clean : null;
    }
}