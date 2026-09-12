<?php

class Product
{
    private $conn;
    private $table = 'productos';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT p.id, p.name, p.description, p.sale_price, p.production_cost,
                         p.stock, p.min_stock, p.image_url, p.status, p.category_id,
                         c.name AS category_name
                    FROM " . $this->table . " AS p
                    INNER JOIN categorias AS c ON p.category_id = c.id
                    ORDER BY p.id DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT p.id, p.name, p.description, p.sale_price, p.production_cost,
                         p.stock, p.min_stock, p.image_url, p.status, p.category_id, p.recipe_id
                    FROM " . $this->table . " AS p
                    WHERE p.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function create($category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url)
    {
        $query = "INSERT INTO " . $this->table . "
                    (category_id, name, description, sale_price, production_cost, stock, min_stock, image_url)
                    VALUES (:category_id, :name, :description, :sale_price, :production_cost, :stock, :min_stock, :image_url);";

        $stmt = $this->conn->prepare($query);
        $cleanName = htmlspecialchars(strip_tags(trim($name)));
        $cleanDescription = htmlspecialchars(strip_tags(trim($description ?? '')));
        $cleanImage = htmlspecialchars(strip_tags(trim($image_url ?? '')));

        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $cleanName);
        $stmt->bindParam(':description', $cleanDescription);
        $stmt->bindParam(':sale_price', $sale_price);
        $stmt->bindParam(':production_cost', $production_cost);
        $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
        $stmt->bindParam(':min_stock', $min_stock, PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $cleanImage);

        return $stmt->execute();
    }

    public function update($id, $category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url, $status)
    {
        $query = "UPDATE " . $this->table . "
                    SET category_id = :category_id,
                        name = :name,
                        description = :description,
                        sale_price = :sale_price,
                        production_cost = :production_cost,
                        stock = :stock,
                        min_stock = :min_stock,
                        image_url = :image_url,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $cleanName = htmlspecialchars(strip_tags(trim($name)));
        $cleanDescription = htmlspecialchars(strip_tags(trim($description ?? '')));
        $cleanImage = htmlspecialchars(strip_tags(trim($image_url ?? '')));

        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $cleanName);
        $stmt->bindParam(':description', $cleanDescription);
        $stmt->bindParam(':sale_price', $sale_price);
        $stmt->bindParam(':production_cost', $production_cost);
        $stmt->bindParam(':stock', $stock, PDO::PARAM_INT);
        $stmt->bindParam(':min_stock', $min_stock, PDO::PARAM_INT);
        $stmt->bindParam(':image_url', $cleanImage);
        $stmt->bindParam(':status', $status);

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
}