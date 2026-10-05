<?php

class Employee
{
    private $conn;
    private $table = 'empleados';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public static function roles()
    {
        return ['administrador', 'cajero', 'mesero', 'produccion', 'domiciliero', 'sub_jefe'];
    }

    public static function roleLabel($role)
    {
        $labels = [
            'administrador' => 'Administrador',
            'cajero' => 'Cajero',
            'mesero' => 'Mesero',
            'produccion' => 'Producción',
            'domiciliero' => 'Domiciliero',
            'sub_jefe' => 'Sub jefe',
        ];

        return $labels[$role] ?? null;
    }

    public function getAll()
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.email, e.role,
                         e.phone, e.address, e.profile_photo, e.birth_date, e.hire_date, e.base_salary, e.status,
                         (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    ORDER BY e.last_name ASC, e.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.email, e.role,
                         e.phone, e.address, e.profile_photo, e.birth_date, e.hire_date, e.base_salary, e.status,
                         (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    WHERE e.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function documentExists($idDocument, $excludeId = null)
    {
        return $this->fieldExists('id_document', $idDocument, $excludeId);
    }

    public function emailExists($email, $excludeId = null)
    {
        return $this->fieldExists('email', $email, $excludeId);
    }

    private function fieldExists($field, $value, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE " . $field . " = :value";
        $params = [':value' => $value];

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

    public function create($name, $last_name, $idDocument, $email, $password_hash, $role, $phone, $address, $birth_date, $hire_date, $baseSalary, $profilePhoto = null)
    {
        $query = "INSERT INTO " . $this->table . "(name, last_name, id_document, email, password_hash, role, phone, address, profile_photo, birth_date, hire_date, base_salary)
                    VALUES (:name, :last_name, :id_document, :email, :password_hash, :role, :phone, :address, :profile_photo, :birth_date, :hire_date, :base_salary);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':role', $role, $role === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':base_salary', $baseSalary);

        return $stmt->execute();
    }

    public function update($id, $name, $last_name, $idDocument, $email, $password_hash, $role, $phone, $address, $birth_date, $hire_date, $baseSalary, $status, $profilePhoto = null)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        last_name = :last_name,
                        id_document = :id_document,
                        email = :email,
                        password_hash = COALESCE(:password_hash, password_hash),
                        role = :role,
                        phone = :phone,
                        address = :address,
                        profile_photo = :profile_photo,
                        birth_date = :birth_date,
                        hire_date = :hire_date,
                        base_salary = :base_salary,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash, $password_hash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':role', $role, $role === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':base_salary', $baseSalary);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function toggleStatus($id)
    {
        $query = "UPDATE " . $this->table . "
                    SET status = IF(status = 'active', 'inactive', 'active')
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete($id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id;");
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}
