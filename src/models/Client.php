<?php

class Client
{
    private $conn;
    private $table = 'clients';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT id, name, last_name, phone, email, address, birth_date, registration_date, status
                    FROM " . $this->table . "
                    ORDER BY last_name ASC, name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT id, name, last_name, phone, email, address, birth_date, registration_date, status
                    FROM " . $this->table . "
                    WHERE id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function emailExists($email, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE email = :email";
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

    public function create($name, $last_name, $phone, $email, $address, $birth_date)
    {
        $query = "INSERT INTO " . $this->table . "(name, last_name, phone, email, address, birth_date)
                    VALUES (:name, :last_name, :phone, :email, :address, :birth_date);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':birth_date', $birth_date);

        return $stmt->execute();
    }

    public function update($id, $name, $last_name, $phone, $email, $address, $birth_date, $status)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        last_name = :last_name,
                        phone = :phone,
                        email = :email,
                        address = :address,
                        birth_date = :birth_date,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':birth_date', $birth_date);
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
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("DELETE FROM client_segment WHERE client_id = :id;");
            $stmt->execute([':id' => $id]);

            $stmt = $this->conn->prepare("UPDATE cupones SET client_id = NULL WHERE client_id = :id;");
            $stmt->execute([':id' => $id]);

            $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id;");
            $stmt->execute([':id' => $id]);
            $deleted = $stmt->rowCount() > 0;

            $this->conn->commit();
            return $deleted;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return false;
        }
    }
}