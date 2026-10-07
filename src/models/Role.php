<?php

class Role
{
    private $conn;
    private $table = 'roles';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT r.id, r.name, r.description, r.is_admin, r.status, r.created_at,
                         (SELECT COUNT(*) FROM empleados e WHERE e.role_id = r.id) AS employees_count,
                         (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id AND rp.can_view = 1) AS modules_count
                    FROM " . $this->table . " r
                    ORDER BY r.is_admin DESC, r.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT r.id, r.name, r.description, r.is_admin, r.status, r.created_at,
                         (SELECT COUNT(*) FROM empleados e WHERE e.role_id = r.id) AS employees_count
                    FROM " . $this->table . " r
                    WHERE r.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function nameExists($name, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE name = :name";
        $params = [':name' => $name];

        if ($excludeId !== null) {
            $query .= " AND id != :id";
            $params[':id'] = $excludeId;
        }

        $query .= ";";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    public function create($name, $description, $isAdmin, $status = 'active')
    {
        $query = "INSERT INTO " . $this->table . "(name, description, is_admin, status)
                    VALUES (:name, :description, :is_admin, :status);";

        $isAdminValue = $isAdmin ? 1 : 0;
        $statusValue = $status === 'inactive' ? 'inactive' : 'active';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':is_admin', $isAdminValue, PDO::PARAM_INT);
        $stmt->bindParam(':status', $statusValue);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function getAllForSelect()
    {
        $query = "SELECT r.id, r.name, r.is_admin
                    FROM " . $this->table . " r
                    WHERE r.status = 'active'
                    ORDER BY r.is_admin DESC, r.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function update($id, $name, $description, $isAdmin, $status)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        description = :description,
                        is_admin = :is_admin,
                        status = :status
                    WHERE id = :id;";

        $isAdminValue = $isAdmin ? 1 : 0;

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':is_admin', $isAdminValue, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function toggleStatus($id)
    {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . " SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countEmployees($id)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM empleados WHERE role_id = :id;");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    /**
     * Cuenta otros roles con acceso total (para no dejar el sistema sin admin).
     */
    public function countOtherAdmins($excludeId)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE is_admin = 1 AND id != :id;"
        );
        $stmt->bindParam(':id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id;");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Roles con clave fija (los precargados) que llevan tilde o mayúscula en la
     * etiqueta. Los roles creados por el usuario se capitalizan y se reemplazan
     * los guiones bajos por espacios.
     */
    private static $labels = [
        'administrador' => 'Administrador',
        'sub_jefe' => 'Sub Jefe',
        'cajero' => 'Cajero',
        'mesero' => 'Mesero',
        'produccion' => 'Producción',
        'domiciliero' => 'Domiciliero',
    ];

    public static function label($name)
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (isset(self::$labels[$name])) {
            return self::$labels[$name];
        }

        return ucfirst(str_replace('_', ' ', $name));
    }
}