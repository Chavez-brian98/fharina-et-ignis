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

    /**
     * Resuelve un nombre de ingrediente (texto escrito por el usuario) contra el
     * catálogo de ingredientes activos. Compara insensible a mayúsculas y a
     * acentos para que "Harina de trigo", "harina de trigo" o "harina de trigó"
     * apunten al mismo registro. Devuelve la fila o null.
     */
    public function findByName($name)
    {
        $objetivo = self::normalizar((string) $name);

        if ($objetivo === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT id, name, unit_of_measure FROM " . $this->table . "
              WHERE status = 'active'
              ORDER BY name ASC;"
        );
        $stmt->execute();

        foreach ($stmt->fetchAll() as $ingrediente) {
            if (self::normalizar($ingrediente['name']) === $objetivo) {
                return $ingrediente;
            }
        }

        return null;
    }

    /**
     * Normaliza para comparar: minúsculas, sin acentos ni ñ. Reutilizada por el
     * servidor (findByName) y por las vistas al volver a pintar el formulario.
     */
    /**
     * Resuelve el ingrediente que coincide con el nombre escrito (insensible a
     * mayúsculas y a acentos) o lo crea en el catálogo si no existe. Es la
     * puerta para que un proveedor pueda ofrecer CUALQUIER producto sin que el
     * sistema lo rechace: si el nombre no está, se da de alta con una unidad de
     * medida por defecto y costo 0 (Compras lo sobrescribe al recibir).
     *
     * Si el nombre ya existe pero está inactivo, se reutiliza reactivándolo
     * (evita chocar con la UNIQUE de `name`).
     */
    public function obtenerOCrear($nombre, $unidad = 'unidad')
    {
        $objetivo = self::normalizar((string) $nombre);

        if ($objetivo === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT id, name, unit_of_measure, status FROM " . $this->table . ";"
        );
        $stmt->execute();

        foreach ($stmt->fetchAll() as $ingrediente) {
            if (self::normalizar($ingrediente['name']) === $objetivo) {
                if ($ingrediente['status'] !== 'active') {
                    $act = $this->conn->prepare(
                        "UPDATE " . $this->table . " SET status = 'active' WHERE id = :id;"
                    );
                    $act->bindValue(':id', (int) $ingrediente['id'], PDO::PARAM_INT);
                    $act->execute();

                    $ingrediente['status'] = 'active';
                }

                return $ingrediente;
            }
        }

        $nuevoNombre = mb_convert_case(trim((string) $nombre), MB_CASE_TITLE, 'UTF-8');
        $ins = $this->conn->prepare(
            "INSERT INTO " . $this->table . "
               (name, unit_of_measure, current_stock, minimum_stock, unit_cost, status)
             VALUES (:name, :unidad, 0, 0, 0, 'active');"
        );
        $ins->bindParam(':name', $nuevoNombre);
        $ins->bindParam(':unidad', $unidad, PDO::PARAM_STR);
        $ins->execute();

        return [
            'id' => (int) $this->conn->lastInsertId(),
            'name' => $nuevoNombre,
            'unit_of_measure' => $unidad,
            'status' => 'active',
        ];
    }

    public static function normalizar($texto)
    {
        $texto = mb_strtolower(trim((string) $texto), 'UTF-8');
        $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'];

        return strtr($texto, $mapa);
    }

    /**
     * Ingredientes activos para los selects (ofertas de proveedores).
     */
    public function getAllForSelect()
    {
        $stmt = $this->conn->prepare(
            "SELECT id, name, unit_of_measure FROM " . $this->table . "
              WHERE status = 'active'
              ORDER BY name ASC;"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
