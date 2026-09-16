<?php

class Employee
{
    private $conn;
    private $table = 'empleados';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.username, e.email, e.role_id,
                         e.phone, e.address, e.birth_date, e.hire_date, e.position, e.base_salary, e.status,
                         r.name AS role_name, (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    LEFT JOIN roles r ON r.id = e.role_id
                    ORDER BY e.last_name ASC, e.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.username, e.email, e.role_id,
                         e.phone, e.address, e.birth_date, e.hire_date, e.position, e.base_salary, e.status,
                         r.name AS role_name, (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    LEFT JOIN roles r ON r.id = e.role_id
                    WHERE e.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getAllRoles()
    {
        $stmt = $this->conn->prepare("SELECT id, name FROM roles ORDER BY id ASC;");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function documentExists($idDocument, $excludeId = null)
    {
        return $this->fieldExists('id_document', $idDocument, $excludeId);
    }

    public function emailExists($email, $excludeId = null)
    {
        return $this->fieldExists('email', $email, $excludeId);
    }

    public function usernameExists($username, $excludeId = null)
    {
        return $this->fieldExists('username', $username, $excludeId);
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

    public function create($name, $last_name, $idDocument, $username, $email, $password_hash, $role_id, $phone, $address, $birth_date, $hire_date, $position, $baseSalary)
    {
        $query = "INSERT INTO " . $this->table . "(name, last_name, id_document, username, email, password_hash, role_id, phone, address, birth_date, hire_date, position, base_salary)
                    VALUES (:name, :last_name, :id_document, :username, :email, :password_hash, :role_id, :phone, :address, :birth_date, :hire_date, :position, :base_salary);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':role_id', $role_id, $role_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':base_salary', $baseSalary);

        return $stmt->execute();
    }

    public function update($id, $name, $last_name, $idDocument, $username, $email, $password_hash, $role_id, $phone, $address, $birth_date, $hire_date, $position, $baseSalary, $status)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        last_name = :last_name,
                        id_document = :id_document,
                        username = :username,
                        email = :email,
                        password_hash = COALESCE(:password_hash, password_hash),
                        role_id = :role_id,
                        phone = :phone,
                        address = :address,
                        birth_date = :birth_date,
                        hire_date = :hire_date,
                        position = :position,
                        base_salary = :base_salary,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash, $password_hash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':role_id', $role_id, $role_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':position', $position);
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
