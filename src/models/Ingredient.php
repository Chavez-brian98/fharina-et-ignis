<?php

/**
 * Ingredientes (tabla `ingredientes`).
 *
 * Solo expone lo que necesita el módulo de Compras: el listado para los selects
 * de las líneas de pedido y el precio vigente. El CRUD completo de inventario
 * (que también usará ingredient_inventory_movements) todavía no existe; cuando
 * se construya, este modelo es el lugar donde ampliarlo.
 */
class Ingredient
{
    private $conn;
    private $table = 'ingredientes';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Ingredientes con su unidad, stock y último precio conocido por proveedor.
     * `price` sale de ingredient_price_history (precio más reciente) y, si el
     * ingrediente tiene proveedor principal, `unit_cost` es su costo actual.
     */
    public function getAll($soloActivos = true)
    {
        $where = $soloActivos ? "WHERE i.status = 'active'" : '';

        $query = "SELECT i.*, p.name AS supplier_name,
                         (SELECT sph.price
                            FROM supplier_price_history sph
                           WHERE sph.ingredient_id = i.id
                           ORDER BY sph.price_date DESC, sph.id DESC
                           LIMIT 1) AS last_price
                    FROM " . $this->table . " i
                    LEFT JOIN proveedores p ON p.id = i.main_supplier_id
                    " . $where . "
                    ORDER BY i.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT i.*, p.name AS supplier_name
               FROM " . $this->table . " i
               LEFT JOIN proveedores p ON p.id = i.main_supplier_id
              WHERE i.id = :id
              LIMIT 1;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    public function exists($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    public function isActive($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM " . $this->table . "
              WHERE id = :id AND status = 'active';"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }
}
