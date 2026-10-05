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
        $query = "SELECT id, name, last_name, id_document, client_type, company_name, phone, email, address,
                         profile_photo, birth_date, registration_date, status
                    FROM " . $this->table . "
                    ORDER BY last_name ASC, name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT id, name, last_name, id_document, client_type, company_name, phone, email, address,
                         profile_photo, birth_date, registration_date, status
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
        return $this->fieldExists('email', $email, $excludeId);
    }

    public function documentExists($idDocument, $excludeId = null)
    {
        return $this->fieldExists('id_document', $idDocument, $excludeId);
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

    public function create($name, $last_name, $idDocument, $clientType, $companyName, $phone, $email, $address, $birth_date, $profilePhoto = null)
    {
        $query = "INSERT INTO " . $this->table . "(name, last_name, id_document, client_type, company_name, phone, email, address, profile_photo, birth_date)
                    VALUES (:name, :last_name, :id_document, :client_type, :company_name, :phone, :email, :address, :profile_photo, :birth_date);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':client_type', $clientType);
        $stmt->bindParam(':company_name', $companyName);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
        $stmt->bindParam(':birth_date', $birth_date);

        return $stmt->execute();
    }

    public function update($id, $name, $last_name, $idDocument, $clientType, $companyName, $phone, $email, $address, $birth_date, $status, $profilePhoto = null)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        last_name = :last_name,
                        id_document = :id_document,
                        client_type = :client_type,
                        company_name = :company_name,
                        phone = :phone,
                        email = :email,
                        address = :address,
                        profile_photo = :profile_photo,
                        birth_date = :birth_date,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':client_type', $clientType);
        $stmt->bindParam(':company_name', $companyName);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
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