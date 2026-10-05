<?php

class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function findByEmail($email)
    {
        $query = "SELECT e.id, e.name, e.last_name, e.email, e.password_hash, e.status,
                         e.role, e.profile_photo
                    FROM empleados e
                    WHERE e.email = :email
                      AND e.password_hash IS NOT NULL
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function findById($id)
    {
        $query = "SELECT e.id, e.name, e.last_name, e.email, e.password_hash, e.status,
                         e.role, e.phone, e.address, e.birth_date, e.profile_photo, e.hire_date,
                         e.id_document
                    FROM empleados e
                    WHERE e.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function emailExists($email, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM empleados WHERE email = :email";
        $params = [':email' => $email];

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

    public function updateProfile($id, $name, $last_name, $email, $phone, $address, $profilePhoto, $passwordHash)
    {
        $query = "UPDATE empleados
                    SET name = :name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        address = :address,
                        profile_photo = COALESCE(:profile_photo, profile_photo),
                        password_hash = COALESCE(:password_hash, password_hash)
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto, $profilePhoto === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $passwordHash, $passwordHash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}