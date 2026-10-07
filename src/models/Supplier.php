<?php

/**
 * Proveedores (tabla `proveedores`). El nombre de la tabla es español legacy;
 * la clase sigue el patrón inglés del resto de modelos (Product, Employee...).
 *
 * `availability_days` guarda las claves de día separadas por coma
 * ("lun,mie,jue"), no las etiquetas, para poder filtrar y ordenar sin depender
 * del texto. Las etiquetas salen de Supplier::dias().
 */
class Supplier
{
    private $conn;
    private $table = 'proveedores';

    /**
     * Días de la semana que atiende o entrega un proveedor.
     */
    public static function dias()
    {
        return [
            'lun' => 'Lunes',
            'mar' => 'Martes',
            'mie' => 'Miércoles',
            'jue' => 'Jueves',
            'vie' => 'Viernes',
            'sab' => 'Sábado',
            'dom' => 'Domingo',
        ];
    }

    /**
     * Tipos de proveedor frecuentes (la columna es VARCHAR: no obliga a
     * restringir la lista si el negocio necesita otra cosa).
     */
    public static function tipos()
    {
        return [
            'Harinera',
            'Lácteos',
            'Azucarera',
            'Frutero',
            'Verdulero',
            'Carnes y embutidos',
            'Embalajes',
            'Insumos',
            'Servicios',
            'Equipamiento',
            'Otros',
        ];
    }

    /**
     * Convierte el POST de checkboxes en el CSV ordenado que se guarda, y en el
     * texto legible para listados y modales.
     */
    public static function normalizarDias($dias)
    {
        $validos = array_keys(self::dias());
        $elegidos = is_array($dias) ? $dias : [];

        // Orden fijo de la semana: aunque el POST llegue en otro orden, la BD
        // queda siempre como "lun,mar,mie,...".
        $ordenados = array_values(array_intersect($validos, $elegidos));

        return implode(',', $ordenados);
    }

    public static function diasTexto($csv)
    {
        $csv = trim((string) $csv);

        if ($csv === '') {
            return '';
        }

        $etiquetas = self::dias();
        $salida = [];

        foreach (explode(',', $csv) as $clave) {
            $clave = trim($clave);
            if (isset($etiquetas[$clave])) {
                $salida[] = $etiquetas[$clave];
            }
        }

        return implode(', ', $salida);
    }

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT p.*,
                         (SELECT COUNT(*) FROM ingredientes i
                           WHERE i.main_supplier_id = p.id AND i.status = 'active') AS ingredients_count,
                         (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = p.id) AS orders_count
                    FROM " . $this->table . " p
                   ORDER BY p.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT p.*,
                         (SELECT COUNT(*) FROM ingredientes i
                           WHERE i.main_supplier_id = p.id AND i.status = 'active') AS ingredients_count,
                         (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = p.id) AS orders_count
                    FROM " . $this->table . " p
                   WHERE p.id = :id
                   LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    /**
     * Proveedores activos para los selects (ingredientes, pedidos de compra).
     */
    public function getAllForSelect()
    {
        $stmt = $this->conn->prepare(
            "SELECT id, name FROM " . $this->table . "
              WHERE status = 'active'
              ORDER BY name ASC;"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Ofertas de un solo proveedor (para la vista de edición): líneas con el
     * nombre del ingrediente, su unidad de medida y el precio ofrecido.
     */
    public function getOffers($supplierId)
    {
        $stmt = $this->conn->prepare(
            "SELECT si.supplier_id, si.ingredient_id, si.unit_price,
                    i.name AS ingredient_name, i.unit_of_measure
               FROM supplier_ingredients si
               JOIN ingredientes i ON i.id = si.ingredient_id
              WHERE si.supplier_id = :supplier_id
              ORDER BY i.name ASC;"
        );
        $stmt->bindParam(':supplier_id', $supplierId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Todas las ofertas agrupadas por proveedor para el listado:
     * [supplier_id => [fila, fila, ...]].
     */
    public function getOffersBySupplier()
    {
        $stmt = $this->conn->prepare(
            "SELECT si.supplier_id, si.ingredient_id, si.unit_price,
                    i.name AS ingredient_name, i.unit_of_measure
               FROM supplier_ingredients si
               JOIN ingredientes i ON i.id = si.ingredient_id
              ORDER BY si.supplier_id ASC, i.name ASC;"
        );
        $stmt->execute();

        $agrupadas = [];

        foreach ($stmt->fetchAll() as $fila) {
            $agrupadas[(int) $fila['supplier_id']][] = $fila;
        }

        return $agrupadas;
    }

    /**
     * Mapa [supplier_id][ingredient_id] => unit_price, para que el módulo de
     * Compras precargue el precio que ofrece cada proveedor al armar una orden.
     */
    public function getOffersPriceMap()
    {
        $stmt = $this->conn->prepare(
            "SELECT supplier_id, ingredient_id, unit_price
               FROM supplier_ingredients;"
        );
        $stmt->execute();

        $mapa = [];

        foreach ($stmt->fetchAll() as $fila) {
            $mapa[(int) $fila['supplier_id']][(int) $fila['ingredient_id']] = (float) $fila['unit_price'];
        }

        return $mapa;
    }

    /**
     * Reemplaza las ofertas de un proveedor (DELETE + INSERT transaccional).
     * Cada elemento de $ofertas es ['ingredient_id' => int, 'unit_price' => float].
     */
    public function saveOffers($supplierId, array $ofertas)
    {
        $supplierId = (int) $supplierId;

        try {
            $this->conn->beginTransaction();

            $stmtDel = $this->conn->prepare(
                "DELETE FROM supplier_ingredients WHERE supplier_id = :supplier_id;"
            );
            $stmtDel->bindParam(':supplier_id', $supplierId, PDO::PARAM_INT);
            $stmtDel->execute();

            if ($ofertas) {
                $stmtIns = $this->conn->prepare(
                    "INSERT INTO supplier_ingredients (supplier_id, ingredient_id, unit_price)
                     VALUES (:supplier_id, :ingredient_id, :unit_price);"
                );
                $stmtIns->bindParam(':supplier_id', $supplierId, PDO::PARAM_INT);

                foreach ($ofertas as $oferta) {
                    $ingredientId = (int) $oferta['ingredient_id'];
                    $precio = (float) $oferta['unit_price'];

                    $stmtIns->bindParam(':ingredient_id', $ingredientId, PDO::PARAM_INT);
                    $stmtIns->bindParam(':unit_price', $precio);
                    $stmtIns->execute();
                }
            }

            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * La empresa no se repite; el correo también es único cuando viene.
     */
    public function nameExists($name, $excludeId = null)
    {
        return $this->exists('name', $name, $excludeId);
    }

    public function emailExists($email, $excludeId = null)
    {
        if (trim((string) $email) === '') {
            return false;
        }

        return $this->exists('email', $email, $excludeId);
    }

    private function exists($column, $value, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM " . $this->table . "
                   WHERE " . $column . " = :value";
        $params = [':value' => trim((string) $value)];

        if ($excludeId !== null) {
            $query .= " AND id != :id";
            $params[':id'] = (int) $excludeId;
        }

        $stmt = $this->conn->prepare($query . ';');
        $stmt->execute($params);
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    public function create(array $data)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO " . $this->table . "
                (name, tax_id, supplier_type, contact, phone, email, address,
                 supplies, availability_days, payment_terms, notes, status)
             VALUES (:name, :tax_id, :supplier_type, :contact, :phone, :email, :address,
                     :supplies, :availability_days, :payment_terms, :notes, :status);"
        );

        $this->bind($stmt, $data);

        return $stmt->execute();
    }

    public function update($id, array $data)
    {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
                SET name = :name,
                    tax_id = :tax_id,
                    supplier_type = :supplier_type,
                    contact = :contact,
                    phone = :phone,
                    email = :email,
                    address = :address,
                    supplies = :supplies,
                    availability_days = :availability_days,
                    payment_terms = :payment_terms,
                    notes = :notes,
                    status = :status
              WHERE id = :id;"
        );

        $this->bind($stmt, $data);

        $id = (int) $id;
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Los bindParam exigen variables por referencia, así que todo se normaliza
     * antes en variables locales.
     */
    private function bind($stmt, array $data)
    {
        $text = ['name', 'tax_id', 'supplier_type', 'contact', 'phone', 'email',
            'address', 'supplies', 'availability_days', 'payment_terms', 'notes'];

        foreach ($text as $field) {
            $value = isset($data[$field]) ? trim(strip_tags((string) $data[$field])) : '';
            $value = mb_substr($value, 0, $field === 'notes' ? 2000 : 255);

            if ($value === '') {
                $stmt->bindValue(':' . $field, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':' . $field, $value);
            }
        }

        $status = isset($data['status']) && $data['status'] === 'inactive' ? 'inactive' : 'active';
        $stmt->bindValue(':status', $status);
    }

    public function toggleStatus($id)
    {
        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
                SET status = IF(status = 'active', 'inactive', 'active')
              WHERE id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM " . $this->table . " WHERE id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Un proveedor no se puede borrar si algo lo referencia: ingredientes que
     * lo tienen como proveedor principal, pedidos de compra, historial de
     * precios u ofertas de ingredientes. Devuelve el detalle para poder avisar
     * en el flash.
     */
    public function countReferences($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT
                (SELECT COUNT(*) FROM ingredientes WHERE main_supplier_id = :id1) AS ingredientes,
                (SELECT COUNT(*) FROM purchase_orders WHERE supplier_id = :id2) AS pedidos,
                (SELECT COUNT(*) FROM supplier_price_history WHERE supplier_id = :id3) AS precios,
                (SELECT COUNT(*) FROM supplier_ingredients WHERE supplier_id = :id4) AS ofertas;"
        );
        $stmt->bindParam(':id1', $id, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $id, PDO::PARAM_INT);
        $stmt->bindParam(':id3', $id, PDO::PARAM_INT);
        $stmt->bindParam(':id4', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'ingredientes' => (int) ($row['ingredientes'] ?? 0),
            'pedidos' => (int) ($row['pedidos'] ?? 0),
            'precios' => (int) ($row['precios'] ?? 0),
            'ofertas' => (int) ($row['ofertas'] ?? 0),
        ];
    }
}