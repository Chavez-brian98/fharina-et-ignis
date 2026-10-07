<?php

/**
 * Compras (órdenes de compra a proveedores).
 *
 * Tablas del bloque 7 de db/init.sql:
 *   - purchase_orders          cabecera (estado: pendiente/parcial/recibida/cancelada)
 *   - purchase_order_details   líneas (ingrediente, cantidad, precio, subtotal)
 *   - merchandise_receipts     cabecera de recepción de mercancía
 *   - receipt_details          cantidades realmente recibidas por línea
 *   - supplier_price_history   historial de precios por proveedor/ingrediente
 *
 * Nota de diseño: `total` y `subtotal` se calculan siempre desde las líneas
 * (nunca se confían en un valor del POST). La recepción es transaccional y
 * mueve stock, costo e historial de precios en el mismo commit que la cabecera.
 */
class Purchase
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Estados posibles de una orden, con su etiqueta y color de insignia.
     */
    public static function estados()
    {
        return [
            'pendiente' => ['label' => 'Pendiente', 'color' => 'bg-amber-50 text-amber-600'],
            'parcial'   => ['label' => 'Recibida parcial', 'color' => 'bg-sky-50 text-sky-600'],
            'recibida'  => ['label' => 'Recibida', 'color' => 'bg-green-50 text-green-600'],
            'cancelada' => ['label' => 'Cancelada', 'color' => 'bg-gray-100 text-gray-500'],
        ];
    }

    public static function estadoTexto($state)
    {
        $estados = self::estados();

        return isset($estados[$state]) ? $estados[$state]['label'] : $state;
    }

    public static function estadoColor($state)
    {
        $estados = self::estados();

        return isset($estados[$state]) ? $estados[$state]['color'] : 'bg-gray-100 text-gray-500';
    }

    /**
     * Listado con proveedor, número de líneas y recepciones registradas.
     */
    public function getAll()
    {
        $query = "SELECT po.*,
                         s.name AS supplier_name,
                         e.name AS employee_name,
                         e.last_name AS employee_last_name,
                         (SELECT COUNT(*) FROM purchase_order_details d
                           WHERE d.purchase_order_id = po.id) AS items_count,
                         (SELECT COUNT(*) FROM merchandise_receipts r
                           WHERE r.purchase_order_id = po.id) AS receipts_count
                    FROM purchase_orders po
                    JOIN proveedores s ON s.id = po.supplier_id
                    JOIN empleados e ON e.id = po.employee_id
                    ORDER BY po.order_date DESC, po.id DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT po.*,
                    s.name AS supplier_name,
                    s.tax_id AS supplier_tax_id,
                    s.contact AS supplier_contact,
                    e.name AS employee_name,
                    e.last_name AS employee_last_name
               FROM purchase_orders po
               JOIN proveedores s ON s.id = po.supplier_id
               JOIN empleados e ON e.id = po.employee_id
              WHERE po.id = :id
              LIMIT 1;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    /**
     * Líneas de la orden con el nombre y unidad del ingrediente.
     */
    public function getDetails($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT d.*, i.name AS ingredient_name, i.unit_of_measure, i.current_stock
               FROM purchase_order_details d
               JOIN ingredientes i ON i.id = d.ingredient_id
              WHERE d.purchase_order_id = :id
              ORDER BY d.id ASC;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Recepciones registradas para una orden, con su detalle ya resuelto.
     */
    public function getReceipts($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT r.*, e.name AS employee_name, e.last_name AS employee_last_name
               FROM merchandise_receipts r
               JOIN empleados e ON e.id = r.employee_id
              WHERE r.purchase_order_id = :id
              ORDER BY r.receipt_date DESC, r.id DESC;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $receipts = $stmt->fetchAll();

        foreach ($receipts as &$receipt) {
            $detalle = $this->conn->prepare(
                "SELECT rd.*, i.name AS ingredient_name, i.unit_of_measure
                   FROM receipt_details rd
                   JOIN ingredientes i ON i.id = rd.ingredient_id
                  WHERE rd.receipt_id = :id
                  ORDER BY rd.id ASC;"
            );
            $detalle->bindParam(':id', $receipt['id'], PDO::PARAM_INT);
            $detalle->execute();
            $receipt['items'] = $detalle->fetchAll();
        }

        return $receipts;
    }

    /**
     * Cuánto se ha recibido en total por línea (para la vista de recepción).
     */
    public function getReceivedTotals($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT rd.ingredient_id, SUM(rd.received_quantity) AS received
               FROM receipt_details rd
               JOIN merchandise_receipts r ON r.id = rd.receipt_id
              WHERE r.purchase_order_id = :id
              GROUP BY rd.ingredient_id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $totales = [];
        foreach ($stmt->fetchAll() as $row) {
            $totales[(int) $row['ingredient_id']] = (float) $row['received'];
        }

        return $totales;
    }

    public function countReceipts($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT COUNT(*) AS total FROM merchandise_receipts WHERE purchase_order_id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'];
    }

    /**
     * Alta de la orden y sus líneas en una sola transacción.
     * $items = [['ingredient_id' => int, 'quantity' => float, 'unit_price' => float], ...]
     */
    public function create(array $data, array $items)
    {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO purchase_orders
                    (supplier_id, employee_id, order_date, estimated_delivery_date, state, total)
                 VALUES (:supplier_id, :employee_id, :order_date, :estimated_delivery_date, 'pendiente', :total);"
            );
            $stmt->bindValue(':supplier_id', (int) $data['supplier_id'], PDO::PARAM_INT);
            $stmt->bindValue(':employee_id', (int) $data['employee_id'], PDO::PARAM_INT);
            $stmt->bindValue(':order_date', $data['order_date']);

            if (empty($data['estimated_delivery_date'])) {
                $stmt->bindValue(':estimated_delivery_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':estimated_delivery_date', $data['estimated_delivery_date']);
            }
            $stmt->bindValue(':total', $this->totalDe($items));

            $stmt->execute();
            $orderId = (int) $this->conn->lastInsertId();

            $this->insertDetails($orderId, $items);
            $this->conn->commit();

            return $orderId;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Actualiza cabecera y reemplaza todas las líneas. Solo se usa en órdenes
     * sin recepciones (mientras 'pendiente'): así los datos ya aplicados al
     * stock no se contradicen.
     */
    public function update($id, array $data, array $items)
    {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare(
                "UPDATE purchase_orders
                    SET supplier_id = :supplier_id,
                        order_date = :order_date,
                        estimated_delivery_date = :estimated_delivery_date,
                        total = :total
                  WHERE id = :id;"
            );
            $stmt->bindValue(':supplier_id', (int) $data['supplier_id'], PDO::PARAM_INT);
            $stmt->bindValue(':order_date', $data['order_date']);

            if (empty($data['estimated_delivery_date'])) {
                $stmt->bindValue(':estimated_delivery_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':estimated_delivery_date', $data['estimated_delivery_date']);
            }
            $stmt->bindValue(':total', $this->totalDe($items));

            $orderId = (int) $id;
            $stmt->bindParam(':id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            $delete = $this->conn->prepare("DELETE FROM purchase_order_details WHERE purchase_order_id = :id;");
            $delete->bindParam(':id', $orderId, PDO::PARAM_INT);
            $delete->execute();

            $this->insertDetails($orderId, $items);
            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Registra la recepción de mercancía: mueve stock y costo del ingrediente,
     * escribe el historial de precios y deja la orden en 'parcial' o 'recibida'
     * según cuánto se haya recibido.
     *
     * @param array $recibido  id de la línea => cantidad recibida
     * @return array|false ['estado' => ..., 'total_recibido' => ..., 'lineas' => int] o false
     */
    public function receive($orderId, array $recibido, $employeeId, $observations = '')
    {
        $order = $this->getById($orderId);

        if (!$order || in_array($order['state'], ['recibida', 'cancelada'], true)) {
            return false;
        }

        $lineas = $this->getDetails($orderId);

        if (!$lineas) {
            return false;
        }

        $hoy = date('Y-m-d');
        // Lo ya recibido en recepciones anteriores: para decidir si la orden
        // queda completa hay que acumular, no mirar solo este ingreso.
        $previos = $this->getReceivedTotals($orderId);
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO merchandise_receipts (purchase_order_id, employee_id, observations)
                 VALUES (:order_id, :employee_id, :observations);"
            );
            $stmt->bindValue(':order_id', (int) $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':employee_id', (int) $employeeId, PDO::PARAM_INT);

            if (trim((string) $observations) === '') {
                $stmt->bindValue(':observations', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':observations', trim($observations));
            }
            $stmt->execute();
            $receiptId = (int) $this->conn->lastInsertId();

            $insertDetalle = $this->conn->prepare(
                "INSERT INTO receipt_details (receipt_id, ingredient_id, expected_quantity, received_quantity)
                 VALUES (:receipt_id, :ingredient_id, :expected, :received);"
            );

            $movimiento = $this->conn->prepare(
                "INSERT INTO ingredient_inventory_movements
                    (ingredient_id, movement_type, quantity, reason, employee_id, reference)
                 VALUES (:ingredient_id, 'entrada', :qty, :reason, :employee_id, :reference);"
            );

            $precio = $this->conn->prepare(
                "INSERT INTO supplier_price_history (supplier_id, ingredient_id, price, price_date)
                 VALUES (:supplier_id, :ingredient_id, :price, :price_date);"
            );

            $totalRecibido = 0.0;
            $completo = true;

            foreach ($lineas as $linea) {
                $lineId = (int) $linea['id'];
                $esperado = (float) $linea['quantity'];
                $cantidad = isset($recibido[$lineId]) ? (float) $recibido[$lineId] : 0.0;

                if ($cantidad < 0) {
                    $cantidad = 0.0;
                }

                // Acumulado de esta línea: lo de recepciones previas más lo de ahora.
                $acumulado = (float) ($previos[(int) $linea['ingredient_id']] ?? 0) + $cantidad;

                if ($acumulado + 0.0005 < $esperado) {
                    $completo = false;
                }

                $totalRecibido += $cantidad * (float) $linea['unit_price'];

                $insertDetalle->bindValue(':receipt_id', $receiptId, PDO::PARAM_INT);
                $insertDetalle->bindValue(':ingredient_id', (int) $linea['ingredient_id'], PDO::PARAM_INT);
                $insertDetalle->bindValue(':expected', $esperado, PDO::PARAM_STR);
                $insertDetalle->bindValue(':received', $cantidad, PDO::PARAM_STR);
                $insertDetalle->execute();

                if ($cantidad > 0) {
                    $this->aplicarEntrada(
                        (int) $linea['ingredient_id'],
                        $cantidad,
                        (float) $linea['unit_price'],
                        $order['supplier_id'],
                        $orderId,
                        $employeeId,
                        $movimiento,
                        $precio,
                        $hoy
                    );
                }
            }

            $estado = $completo ? 'recibida' : 'parcial';

            $update = $this->conn->prepare("UPDATE purchase_orders SET state = :state WHERE id = :id;");
            $update->bindValue(':state', $estado);
            $update->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
            $update->execute();

            $this->conn->commit();

            return [
                'estado' => $estado,
                'total_recibido' => $totalRecibido,
                'lineas' => count($lineas),
                'receipt_id' => $receiptId,
            ];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Suma el stock, actualiza el costo y deja traza en los dos historiales.
     */
    private function aplicarEntrada($ingredientId, $quantity, $unitCost, $supplierId, $orderId, $employeeId, $movimiento, $precio, $hoy)
    {
        $update = $this->conn->prepare(
            "UPDATE ingredientes
                SET current_stock = current_stock + :qty,
                    unit_cost = :cost
              WHERE id = :id;"
        );
        $update->bindValue(':qty', (float) $quantity, PDO::PARAM_STR);
        $update->bindValue(':cost', (float) $unitCost, PDO::PARAM_STR);
        $update->bindValue(':id', (int) $ingredientId, PDO::PARAM_INT);
        $update->execute();

        $movimiento->bindValue(':ingredient_id', (int) $ingredientId, PDO::PARAM_INT);
        $movimiento->bindValue(':qty', (float) $quantity, PDO::PARAM_STR);
        $movimiento->bindValue(':reason', 'Recepción de compra #' . (int) $orderId);
        $movimiento->bindValue(':employee_id', (int) $employeeId, PDO::PARAM_INT);
        $movimiento->bindValue(':reference', 'Compra #' . (int) $orderId);
        $movimiento->execute();

        $precio->bindValue(':supplier_id', (int) $supplierId, PDO::PARAM_INT);
        $precio->bindValue(':ingredient_id', (int) $ingredientId, PDO::PARAM_INT);
        $precio->bindValue(':price', (float) $unitCost, PDO::PARAM_STR);
        $precio->bindValue(':price_date', $hoy);
        $precio->execute();
    }

    public function cancel($id)
    {
        $stmt = $this->conn->prepare(
            "UPDATE purchase_orders SET state = 'cancelada' WHERE id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Borra la orden y sus líneas. Las FK son RESTRICT, así que las líneas se
     * quitan primero; el controller impide borrar si ya hubo recepciones.
     */
    public function delete($id)
    {
        $this->conn->beginTransaction();

        try {
            $stmt = $this->conn->prepare("DELETE FROM purchase_order_details WHERE purchase_order_id = :id;");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM purchase_orders WHERE id = :id;");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    public function supplierExists($id)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM proveedores WHERE id = :id;");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return (int) $row['total'] > 0;
    }

    private function insertDetails($orderId, array $items)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO purchase_order_details
                (purchase_order_id, ingredient_id, quantity, unit_price, subtotal)
             VALUES (:order_id, :ingredient_id, :quantity, :unit_price, :subtotal);"
        );

        foreach ($items as $item) {
            $subtotal = round((float) $item['quantity'] * (float) $item['unit_price'], 2);

            $stmt->bindValue(':order_id', (int) $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':ingredient_id', (int) $item['ingredient_id'], PDO::PARAM_INT);
            $stmt->bindValue(':quantity', (float) $item['quantity'], PDO::PARAM_STR);
            $stmt->bindValue(':unit_price', (float) $item['unit_price'], PDO::PARAM_STR);
            $stmt->bindValue(':subtotal', $subtotal, PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    private function totalDe(array $items)
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += (float) $item['quantity'] * (float) $item['unit_price'];
        }

        return round($total, 2);
    }
}
