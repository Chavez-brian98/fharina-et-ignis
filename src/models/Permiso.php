<?php

/**
 * Permisos: catálogo de módulos, permisos por rol y excepciones por empleado.
 *
 * Resolución (en este orden):
 *   1. Rol con is_admin = 1 → todo permitido.
 *   2. Módulo marcado 'always' (Mi Perfil) → ver + editar siempre.
 *   3. Si el empleado tiene fila en employee_permissions para el módulo, esa
 *      fila manda por completo (puede conceder o quitar).
 *   4. Si no, se hereda de role_permissions del rol.
 *   5. Sin fila en role_permissions → sin acceso.
 */
class Permiso
{
    private static $catalogo = null;
    private static $cache = [];

    private static function db()
    {
        if (!isset($GLOBALS['__db'])) {
            $GLOBALS['__db'] = (new Database())->getConnection();
        }

        return $GLOBALS['__db'];
    }

    /**
     * Catálogo de módulos con permisos (src/config/permisos.php).
     */
    public static function modulos()
    {
        if (self::$catalogo === null) {
            self::$catalogo = require __DIR__ . '/../config/permisos.php';
        }

        return self::$catalogo;
    }

    /**
     * Acciones con permiso y su etiqueta en español.
     */
    public static function acciones()
    {
        return [
            'view' => 'Ver',
            'create' => 'Crear',
            'edit' => 'Editar',
            'delete' => 'Eliminar',
        ];
    }

    /**
     * Agrupa el catálogo por sección, conservando el orden de definición.
     */
    public static function modulosPorGrupo()
    {
        $grupos = [];

        foreach (self::modulos() as $key => $modulo) {
            $grupos[$modulo['group']][$key] = $modulo;
        }

        return $grupos;
    }

    /**
     * Clave del módulo que atiende una clase de controlador (o null).
     */
    public static function moduloDeControlador($class)
    {
        if (!is_string($class) || $class === '') {
            return null;
        }

        foreach (self::modulos() as $key => $modulo) {
            if (isset($modulo['controller']) && $modulo['controller'] === $class) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Traduce una acción del controlador a la acción que exige permiso.
     */
    public static function accionDeRuta($action)
    {
        switch ($action) {
            case 'store':
                return 'create';
            case 'update':
            case 'edit':
                return 'edit';
            case 'delete':
            case 'toggle':
                return 'delete';
            // Caja: abrir/movimientos Crean, cerrar es operativo (Ver) y
            // reabrir exige el mismo permiso que editar.
            case 'open':
            case 'movement':
                return 'create';
            case 'reopen':
                return 'edit';
            // Compras: recibir mercancía mueve stock y precios, así que exige
            // el mismo permiso que editar la orden.
            case 'receive':
                return 'edit';
            // Cancelar descarta la orden: mismo permiso que eliminarla.
            case 'cancel':
                return 'delete';
            // Horarios: asignar un turno es crear. El ajuste manual de la
            // asistencia de otro empleado es editar, y borrarla es eliminar.
            case 'assign':
                return 'create';
            // Ajustar a mano la marcacion de OTRO empleado es editar, no ver:
            // sin este caso caeria en el default 'view' y un rol de solo
            // lectura podria corregir el registro de un compañero.
            case 'attendance':
                return 'edit';
            case 'attendanceDelete':
                return 'delete';
            // Asignacion masiva por rango: crea turnos, asi que va con create.
            case 'bulk':
                return 'create';
            // Marcaciones propias: el modulo 'attendance' es 'always', asi que
            // el permiso efectivo no depende del rol. Se mapean a edit para que
            // un rol sin 'view' explicito no quede bloqueado.
            case 'checkin':
            case 'checkout':
            case 'breakStart':
            case 'breakEnd':
            case 'regenerateQr':
                return 'edit';

            // Empleados: emitir un QR nuevo invalida el anterior, asi que va con
            // edit. Sin este caso cae en el default 'view' y un rol de solo
            // lectura podria rotar el QR de un compañero.
            case 'qrRegenerate':
                return 'edit';

            // Pedidos: mover el estado (aprobado->en produccion->... ) es una
            // edicion del seguimiento; sin el caso caeria en 'view' y un rol de
            // solo lectura podria avanzar pedidos.
            case 'estado':
                return 'edit';

            // Promociones: generar/eliminar un cupon de una promocion equivale
            // a crear/eliminar sobre el modulo (el prefijo no los captura).
            case 'generarCupon':
                return 'create';
            case 'eliminarCupon':
                return 'delete';

            // Domicilios: tomar/avanzar el estado (tomado->preparando->en_camino
            // ->finalizado) y reportar la posicion GPS del domiciliero son
            // ediciones; sin estos casos un rol de solo lectura podria despachar
            // pedidos y mover el marcador del mapa.
            case 'asignar':
            case 'avanzar':
            case 'share':
            case 'stopShare':
            case 'location':
                return 'edit';
        }

        // Acciones compuestas que guardan datos sensibles desde un modal,
        // por ejemplo updatePermisos: exigen el mismo permiso que edit.
        if (strpos($action, 'update') === 0 || strpos($action, 'edit') === 0 || strpos($action, 'reopen') === 0) {
            return 'edit';
        }
        if (strpos($action, 'delete') === 0 || strpos($action, 'toggle') === 0) {
            return 'delete';
        }
        if (strpos($action, 'store') === 0 || strpos($action, 'create') === 0) {
            return 'create';
        }

        return 'view';
    }

    /**
     * ¿El usuario en sesión puede ver/crear/editar/eliminar en ese módulo?
     */
    public static function puede($module, $action = 'view')
    {
        if (empty($_SESSION['user']['id'])) {
            return false;
        }

        $map = self::permisosEfectivos((int) $_SESSION['user']['id']);

        return !empty($map[$module][$action]);
    }

    /**
     * Matriz de permisos efectivos del empleado: módulo => [acción => bool].
     */
    public static function permisosEfectivos($employeeId)
    {
        $employeeId = (int) $employeeId;

        if (isset(self::$cache[$employeeId])) {
            return self::$cache[$employeeId];
        }

        $db = self::db();
        $map = [];

        foreach (self::modulos() as $key => $modulo) {
            $map[$key] = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
        }

        try {
            $stmt = $db->prepare("SELECT e.role_id, r.is_admin, r.status AS role_status
                                    FROM empleados e
                                    JOIN roles r ON r.id = e.role_id
                                    WHERE e.id = :id
                                    LIMIT 1;");
            $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch();

            if ($row && ((int) $row['is_admin'] === 1 || $row['role_status'] === 'active')) {
                if ((int) $row['is_admin'] === 1) {
                    foreach ($map as $key => &$acciones) {
                        $acciones = ['view' => true, 'create' => true, 'edit' => true, 'delete' => true];
                    }
                    unset($acciones);
                } else {
                    $roleId = (int) $row['role_id'];
                    $stmt = $db->prepare("SELECT module, can_view, can_create, can_edit, can_delete
                                            FROM role_permissions
                                            WHERE role_id = :role_id;");
                    $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
                    $stmt->execute();

                    foreach ($stmt->fetchAll() as $perm) {
                        if (!isset($map[$perm['module']])) {
                            continue;
                        }
                        $map[$perm['module']] = [
                            'view' => (int) $perm['can_view'] === 1,
                            'create' => (int) $perm['can_create'] === 1,
                            'edit' => (int) $perm['can_edit'] === 1,
                            'delete' => (int) $perm['can_delete'] === 1,
                        ];
                    }
                }
            }
        } catch (PDOException $e) {
            // Sin datos de permisos se deja la matriz en ceros (sin acceso).
        }

        $stmt = $db->prepare("SELECT module, can_view, can_create, can_edit, can_delete
                                FROM employee_permissions
                                WHERE employee_id = :id;");
        $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $perm) {
            if (!isset($map[$perm['module']])) {
                continue;
            }
            $map[$perm['module']] = [
                'view' => (int) $perm['can_view'] === 1,
                'create' => (int) $perm['can_create'] === 1,
                'edit' => (int) $perm['can_edit'] === 1,
                'delete' => (int) $perm['can_delete'] === 1,
            ];
        }

        foreach (self::modulos() as $key => $modulo) {
            if (!empty($modulo['always'])) {
                $map[$key]['view'] = true;
                $map[$key]['edit'] = true;
            }
        }

        self::$cache[$employeeId] = $map;

        return $map;
    }

    /**
     * Permisos guardados de un rol: módulo => [acción => bool].
     */
    public static function permisosDeRol($roleId)
    {
        $roleId = (int) $roleId;
        $stmt = self::db()->prepare("SELECT module, can_view, can_create, can_edit, can_delete
                                        FROM role_permissions
                                        WHERE role_id = :role_id;");
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        $map = [];

        foreach ($stmt->fetchAll() as $perm) {
            $map[$perm['module']] = [
                'view' => (int) $perm['can_view'] === 1,
                'create' => (int) $perm['can_create'] === 1,
                'edit' => (int) $perm['can_edit'] === 1,
                'delete' => (int) $perm['can_delete'] === 1,
            ];
        }

        return $map;
    }

    /**
     * Mapa completo (todos los módulos) de lo que otorga un rol: sus
     * role_permissions más los módulos marcados como `always` (Perfil).
     * Un rol inactivo no otorga nada.
     */
    public static function permisosBaseDeRol($roleId, $activo = true)
    {
        $map = [];

        foreach (self::modulos() as $key => $modulo) {
            $map[$key] = ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
        }

        if ($activo) {
            foreach (self::permisosDeRol($roleId) as $modulo => $acciones) {
                if (isset($map[$modulo])) {
                    $map[$modulo] = $acciones;
                }
            }
        }

        foreach (self::modulos() as $key => $modulo) {
            if (!empty($modulo['always'])) {
                $map[$key]['view'] = true;
                $map[$key]['edit'] = true;
            }
        }

        return $map;
    }

    /**
     * Reemplaza de forma transaccional todos los permisos de un rol.
     * $map viene con el formato de permisosDeRol().
     */
    public static function guardarPermisosDeRol($roleId, array $map)
    {
        $db = self::db();
        $roleId = (int) $roleId;
        $db->beginTransaction();

        try {
            $delete = $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id;");
            $delete->bindParam(':role_id', $roleId, PDO::PARAM_INT);
            $delete->execute();

            $insert = $db->prepare(
                "INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete)
                 VALUES (:role_id, :module, :view, :create, :edit, :del);"
            );

            foreach (self::modulos() as $key => $modulo) {
                $perm = $map[$key] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];
                $view = !empty($perm['view']) ? 1 : 0;
                $create = !empty($perm['view']) && !empty($perm['create']) ? 1 : 0;
                $edit = !empty($perm['view']) && !empty($perm['edit']) ? 1 : 0;
                $del = !empty($perm['view']) && !empty($perm['delete']) ? 1 : 0;

                if (!$view && !$create && !$edit && !$del) {
                    continue;
                }

                $insert->bindParam(':role_id', $roleId, PDO::PARAM_INT);
                $insert->bindParam(':module', $key);
                $insert->bindParam(':view', $view, PDO::PARAM_INT);
                $insert->bindParam(':create', $create, PDO::PARAM_INT);
                $insert->bindParam(':edit', $edit, PDO::PARAM_INT);
                $insert->bindParam(':del', $del, PDO::PARAM_INT);
                $insert->execute();
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            return false;
        }

        self::limpiarCache();

        return true;
    }

    /**
     * Excepciones guardadas de un empleado: módulo => [acción => bool].
     */
    public static function permisosDeEmpleado($employeeId)
    {
        $employeeId = (int) $employeeId;
        $stmt = self::db()->prepare("SELECT module, can_view, can_create, can_edit, can_delete
                                        FROM employee_permissions
                                        WHERE employee_id = :id;");
        $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        $map = [];

        foreach ($stmt->fetchAll() as $perm) {
            $map[$perm['module']] = [
                'view' => (int) $perm['can_view'] === 1,
                'create' => (int) $perm['can_create'] === 1,
                'edit' => (int) $perm['can_edit'] === 1,
                'delete' => (int) $perm['can_delete'] === 1,
            ];
        }

        return $map;
    }

    /**
     * Reemplaza de forma transaccional las excepciones de un empleado.
     * Solo se guardan los módulos con alguna decisión explícita ("Permitir" o
     * "Denegar"). Una fila completamente en ceros significa que el empleado no
     * tiene ese módulo, aunque su rol sí lo tenga.
     */
    public static function guardarPermisosDeEmpleado($employeeId, array $map)
    {
        $db = self::db();
        $employeeId = (int) $employeeId;
        $db->beginTransaction();

        try {
            $delete = $db->prepare("DELETE FROM employee_permissions WHERE employee_id = :id;");
            $delete->bindParam(':id', $employeeId, PDO::PARAM_INT);
            $delete->execute();

            $insert = $db->prepare(
                "INSERT INTO employee_permissions (employee_id, module, can_view, can_create, can_edit, can_delete)
                 VALUES (:id, :module, :view, :create, :edit, :del);"
            );

            foreach (self::modulos() as $key => $modulo) {
                if (!isset($map[$key])) {
                    continue;
                }
                $perm = $map[$key];
                $view = !empty($perm['view']) ? 1 : 0;
                $create = $view && !empty($perm['create']) ? 1 : 0;
                $edit = $view && !empty($perm['edit']) ? 1 : 0;
                $del = $view && !empty($perm['delete']) ? 1 : 0;

                $insert->bindParam(':id', $employeeId, PDO::PARAM_INT);
                $insert->bindParam(':module', $key);
                $insert->bindParam(':view', $view, PDO::PARAM_INT);
                $insert->bindParam(':create', $create, PDO::PARAM_INT);
                $insert->bindParam(':edit', $edit, PDO::PARAM_INT);
                $insert->bindParam(':del', $del, PDO::PARAM_INT);
                $insert->execute();
            }

            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            return false;
        }

        self::limpiarCache();

        return true;
    }

    /**
     * Módulos a los que un empleado tiene acceso de lectura.
     */
    public static function modulosAccesibles($employeeId)
    {
        $map = self::permisosEfectivos($employeeId);
        $modulos = [];

        foreach ($map as $key => $acciones) {
            if (!empty($acciones['view'])) {
                $modulos[] = $key;
            }
        }

        return $modulos;
    }

    /**
     * ¿El usuario en sesión tiene el rol marcado como administrador?
     */
    public static function esAdminActual()
    {
        if (empty($_SESSION['user']['id'])) {
            return false;
        }

        $userId = (int) $_SESSION['user']['id'];
        $stmt = self::db()->prepare("SELECT r.is_admin
                                        FROM empleados e
                                        JOIN roles r ON r.id = e.role_id
                                        WHERE e.id = :id
                                        LIMIT 1;");
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row && (int) $row['is_admin'] === 1;
    }

    public static function limpiarCache()
    {
        self::$cache = [];
    }
}