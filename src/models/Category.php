<?php

class Category
{
    private $conn;
    private $table = 'categorias';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT id, name, description, display_order, status
                    FROM " . $this->table . "
                    ORDER BY display_order ASC, id ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT id, name, description, display_order, status
                    FROM " . $this->table . "
                    WHERE id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function create($name, $description, $display_order)
    {
        $query = "INSERT INTO " . $this->table . "(name, description, display_order)
                    VALUES (:name, :description, :display_order);";

        $stmt = $this->conn->prepare($query);
        $cleanName = htmlspecialchars(strip_tags(trim($name)));
        $cleanDescription = htmlspecialchars(strip_tags(trim($description)));

        $stmt->bindParam(':name', $cleanName);
        $stmt->bindParam(':description', $cleanDescription);
        $stmt->bindParam(':display_order', $display_order, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function update($id, $name, $description, $display_order, $status)
    {
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        description = :description,
                        display_order = :display_order,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $cleanName = htmlspecialchars(strip_tags(trim($name)));
        $cleanDescription = htmlspecialchars(strip_tags(trim($description)));

        $stmt->bindParam(':name', $cleanName);
        $stmt->bindParam(':description', $cleanDescription);
        $stmt->bindParam(':display_order', $display_order, PDO::PARAM_INT);
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
        $query = "DELETE FROM " . $this->table . " WHERE id = :id;";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function countProducts($id)
    {
        $query = "SELECT COUNT(*) AS total FROM productos WHERE category_id = :id;";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return (int) $row['total'];
    }
}
