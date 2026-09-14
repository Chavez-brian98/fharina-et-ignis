<?php

class Dashboard
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getSalesToday()
    {
        $query = "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS count
                    FROM ventas
                    WHERE state = 'completada' AND DATE(sale_date) = CURDATE();";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getSalesMonth()
    {
        $query = "SELECT COALESCE(SUM(total), 0) AS total, COUNT(*) AS count
                    FROM ventas
                    WHERE state = 'completada'
                      AND YEAR(sale_date) = YEAR(CURDATE())
                      AND MONTH(sale_date) = MONTH(CURDATE());";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getLastDaysSales($days = 14)
    {
        $days = (int) $days;

        $query = "SELECT DATE(sale_date) AS sale_date, COALESCE(SUM(total), 0) AS total
                    FROM ventas
                    WHERE state = 'completada'
                      AND sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
                    GROUP BY DATE(sale_date)
                    ORDER BY sale_date ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getSalesByCategory()
    {
        $query = "SELECT c.name, COALESCE(SUM(sd.subtotal), 0) AS total
                    FROM sale_details sd
                    INNER JOIN productos p ON p.id = sd.product_id
                    INNER JOIN categorias c ON c.id = p.category_id
                    INNER JOIN ventas v ON v.id = sd.sale_id AND v.state = 'completada'
                    GROUP BY c.id, c.name
                    ORDER BY total DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getTopProducts($limit = 5)
    {
        $limit = (int) $limit;

        $query = "SELECT p.name, COALESCE(SUM(sd.quantity), 0) AS quantity, COALESCE(SUM(sd.subtotal), 0) AS total
                    FROM sale_details sd
                    INNER JOIN productos p ON p.id = sd.product_id
                    INNER JOIN ventas v ON v.id = sd.sale_id AND v.state = 'completada'
                    GROUP BY p.id, p.name
                    ORDER BY quantity DESC
                    LIMIT :limit;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getLowStockProducts()
    {
        $query = "SELECT p.id, p.name, p.stock, p.min_stock, c.name AS category_name
                    FROM productos p
                    INNER JOIN categorias c ON c.id = p.category_id
                    WHERE p.status = 'active' AND p.stock <= p.min_stock
                    ORDER BY (p.min_stock - p.stock) DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getPendingOrders()
    {
        $query = "SELECT COUNT(*) AS count
                    FROM pedidos
                    WHERE state IN ('pendiente', 'aprobado', 'en_produccion', 'listo');";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getOrdersByState()
    {
        $query = "SELECT state, COUNT(*) AS count
                    FROM pedidos
                    GROUP BY state
                    ORDER BY count DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getTotalProducts()
    {
        $query = "SELECT COUNT(*) AS count FROM productos WHERE status = 'active';";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getTotalCategories()
    {
        $query = "SELECT COUNT(*) AS count FROM categorias WHERE status = 'active';";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetch();
    }
}