<?php

/**
 * Pedidos con reserva (tabla pedidos + order_details + order_status_history).
 *
 * Un pedido es una reserva a futuro: el cliente encarga productos con
 * antelación y se le entregan en una fecha, con o sin dirección de envío
 * (delivery_address NULL = se recoge en tienda). El seguimiento vive en
 * order_status_history y el detalle muestra la línea de tiempo.
 *
 * Transiciones permitidas (solo hacia adelante):
 *   pendiente → aprobado → en_produccion → listo → entregado
 *   pendiente / aprobado → rechazado | cancelado
 */
class Order
{
    private $conn;

    const ESTADOS = ['pendiente', 'aprobado', 'en_produccion', 'listo', 'entregado', 'rechazado', 'cancelado'];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ---------------------------------------------------------------- estados

    public static function estados()
    {
        return [
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'en_produccion' => 'En producción',
            'listo' => 'Listo',
            'entregado' => 'Entregado',
            'rechazado' => 'Rechazado',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function estadoTexto($estado)
    {
        $estados = self::estados();

        return $estados[$estado] ?? $estado;
    }

    /** Clases de insignia (badge) para el estado. */
    public static function estadoColor($estado)
    {
        switch ($estado) {
            case 'pendiente': return 'bg-amber-100 text-amber-700';
            case 'aprobado': return 'bg-blue-100 text-blue-700';
            case 'en_produccion': return 'bg-orange-100 text-orange-700';
            case 'listo': return 'bg-emerald-100 text-emerald-700';
            case 'entregado': return 'bg-green-100 text-green-700';
            case 'rechazado': return 'bg-red-100 text-red-700';
            case 'cancelado': return 'bg-gray-100 text-gray-600';
            default: return 'bg-gray-100 text-gray-600';
        }
    }

    /** Estados a los que se puede pasar desde cada estado. */
    public static function transiciones()
    {
        return [
            'pendiente' => ['aprobado', 'rechazado', 'cancelado'],
            'aprobado' => ['en_produccion', 'rechazado', 'cancelado'],
            'en_produccion' => ['listo'],
            'listo' => ['entregado'],
            'entregado' => [],
            'rechazado' => [],
            'cancelado' => [],
        ];
    }

    public static function transicionesDesde($estado)
    {
        $transiciones = self::transiciones();

        return $transiciones[$estado] ?? [];
    }

    /** Nombre a mostrar de un cliente: la empresa gana al nombre personal. */
    public static function nombreCliente($cliente)
    {
        if (($cliente['client_type'] ?? '') === 'empresa' && trim((string) ($cliente['company_name'] ?? '')) !== '') {
            return $cliente['company_name'];
        }

        return trim(($cliente['client_name'] ?? '') . ' ' . ($cliente['client_last_name'] ?? ''));
    }

    // ---------------------------------------------------------------- lectura

    /**
     * Lista de pedidos con filtros opcionales. Por defecto el controlador pide
     * los que aún no vencieron (delivery_date >= hoy) para que la vista se
     * centre en las entregas futuras.
     */
    public function getAll($desde = null, $hasta = null, $estado = null)
    {
        $sql = "SELECT p.id, p.order_type, p.order_date, p.delivery_date, p.delivery_address, p.state,
                       p.total, p.paid_amount, p.remaining_balance, p.rejection_reason,
                       c.id AS client_id, c.client_type, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone
                  FROM pedidos p
                  JOIN clients c ON c.id = p.client_id";
        $where = [];
        $params = [];

        if ($desde !== null && $desde !== '') {
            $where[] = 'p.delivery_date >= :desde';
            $params[':desde'] = $desde;
        }
        if ($hasta !== null && $hasta !== '') {
            $where[] = 'p.delivery_date <= :hasta';
            $params[':hasta'] = $hasta;
        }
        if ($estado !== null && $estado !== '' && in_array($estado, self::ESTADOS, true)) {
            $where[] = 'p.state = :estado';
            $params[':estado'] = $estado;
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY p.delivery_date ASC, p.id DESC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** Cabecera del pedido con datos del cliente y de quien lo registró. */
    public function getById($id)
    {
        $sql = "SELECT p.*, c.client_type, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone, c.email, c.address AS client_address,
                       e.name AS emp_name, e.last_name AS emp_last_name
FROM pedidos p
                   JOIN clients c ON c.id = p.client_id
                   LEFT JOIN empleados e ON e.id = p.recorded_by_employee_id
                  WHERE p.id = :id
                  LIMIT 1;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /** Líneas del pedido con el producto resuelto. */
    public function getDetalles($orderId)
    {
        $sql = "SELECT od.id, od.product_id, od.personalized_description, od.quantity,
                       od.unit_price, od.subtotal, pr.name AS product_name, pr.image_url
                  FROM order_details od
                  JOIN productos pr ON pr.id = od.product_id
                 WHERE od.order_id = :id
                 ORDER BY od.id ASC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Línea de tiempo del seguimiento (order_status_history). */
    public function getHistorial($orderId)
    {
        $sql = "SELECT h.id, h.previous_state, h.new_state, h.comment, h.change_date,
                       e.name AS emp_name, e.last_name AS emp_last_name
                  FROM order_status_history h
                  JOIN empleados e ON e.id = h.employee_id
                 WHERE h.order_id = :id
                 ORDER BY h.change_date ASC, h.id ASC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countPayments($orderId)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) AS total FROM order_payments WHERE order_id = :id");
        $stmt->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
    }

    // --------------------------------------------------------------- escritura

    /**
     * Crea el pedido en una transacción y deja el historial con su estado
     * inicial 'pendiente'. Devuelve el id nuevo o false.
     *
     * $items es un array de ['product_id', 'quantity', 'unit_price',
     * 'description'] (description = personalised_description, texto libre,
     * p. ej. «Pastel de bodas con letras doradas»).
     */
    public function create($clientId, $recordedByEmployeeId, $deliveryDate, $deliveryAddress, $notes, $total, array $items)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO pedidos
                    (client_id, recorded_by_employee_id, delivery_date, delivery_address,
                     state, total, paid_amount, remaining_balance, notes)
                    VALUES (?, ?, ?, ?, 'pendiente', ?, 0, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, (int) $clientId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int) $recordedByEmployeeId, PDO::PARAM_INT);
            $stmt->bindValue(3, $deliveryDate);
            $stmt->bindValue(4, $deliveryAddress !== '' ? $deliveryAddress : null);
            $stmt->bindValue(5, $total);
            $stmt->bindValue(6, $total);
            $stmt->bindValue(7, $notes !== '' ? $notes : null);
            $stmt->execute();

            $orderId = (int) $this->conn->lastInsertId();
            $this->insertarDetalles($orderId, $items);
            $this->insertarHistorial($orderId, $recordedByEmployeeId, null, 'pendiente', 'Pedido creado.');

            $this->conn->commit();

            return $orderId;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Actualiza la cabecera y el detalle de un pedido todavía editable
     * (pendiente o aprobado, sin recepción de pagos). El total se recalcula
     * desde las líneas; abonos no existen todavía, así que el saldo es igual al
     * total y de ahí no se toca al editar las líneas si ya hubiera pagos.
     */
    public function update($id, $clientId, $deliveryDate, $deliveryAddress, $notes, $total, array $items)
    {
        $antes = $this->getById($id);

        if (!$antes || !in_array($antes['state'], ['pendiente', 'aprobado'], true)) {
            return false;
        }

        if ($this->countPayments($id) > 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE pedidos
                       SET client_id = ?, delivery_date = ?, delivery_address = ?,
                           total = ?, remaining_balance = ?, notes = ?
                     WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, (int) $clientId, PDO::PARAM_INT);
            $stmt->bindValue(2, $deliveryDate);
            $stmt->bindValue(3, $deliveryAddress !== '' ? $deliveryAddress : null);
            $stmt->bindValue(4, $total);
            $stmt->bindValue(5, $total);
            $stmt->bindValue(6, $notes !== '' ? $notes : null);
            $stmt->bindValue(7, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM order_details WHERE order_id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->insertarDetalles($id, $items);

            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Cambia el estado validando la transición. Devuelve ['ok' => bool, 'fila' =>
     * fila actualizada] para que el controlador pueda auditar con el detalle.
     */
    public function cambiarEstado($id, $nuevoEstado, $employeeId, $comment)
    {
        $antes = $this->getById($id);

        if (!$antes) {
            return ['ok' => false, 'fila' => null];
        }

        if (!in_array($nuevoEstado, self::transicionesDesde($antes['state']), true)) {
            return ['ok' => false, 'fila' => null];
        }

        try {
            $this->conn->beginTransaction();

            if ($nuevoEstado === 'rechazado') {
                $sql = "UPDATE pedidos SET state = ?, rejection_reason = ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(1, $nuevoEstado);
                $stmt->bindValue(2, $comment !== '' ? $comment : null);
                $stmt->bindValue(3, (int) $id, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $sql = "UPDATE pedidos SET state = ?, rejection_reason = NULL WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindValue(1, $nuevoEstado);
                $stmt->bindValue(2, (int) $id, PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->insertarHistorial($id, $employeeId, $antes['state'], $nuevoEstado, $comment);

            $this->conn->commit();

            return ['ok' => true, 'fila' => $this->getById($id)];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return ['ok' => false, 'fila' => null];
        }
    }

    /**
     * Borra un pedido. Solo se permite en 'pendiente' y sin pagos registrados:
     * un pedido avanzado ya tiene seguimiento, y las FKs de order_payments /
     * order_tickets bloquearían el borrado de todos modos.
     */
    public function delete($id)
    {
        $antes = $this->getById($id);

        if (!$antes || $antes['state'] !== 'pendiente' || $this->countPayments($id) > 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("DELETE FROM order_status_history WHERE order_id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM order_details WHERE order_id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM pedidos WHERE id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();
            $borrado = $stmt->rowCount() > 0;

            $this->conn->commit();

            return $borrado;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    // ---------------------------------------------------------------- helpers

    private function insertarDetalles($orderId, array $items)
    {
        $sql = "INSERT INTO order_details
                (order_id, product_id, personalized_description, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = max(1, (int) $item['quantity']);
            $unitPrice = (float) $item['unit_price'];
            $subtotal = $quantity * $unitPrice;
            $description = trim((string) ($item['description'] ?? ''));

            $stmt->bindValue(1, (int) $orderId, PDO::PARAM_INT);
            $stmt->bindValue(2, $productId, PDO::PARAM_INT);
            $stmt->bindValue(3, $description !== '' ? $description : null);
            $stmt->bindValue(4, $quantity, PDO::PARAM_INT);
            $stmt->bindValue(5, $unitPrice);
            $stmt->bindValue(6, $subtotal);
            $stmt->execute();
        }
    }

    private function insertarHistorial($orderId, $employeeId, $estadoAnterior, $estadoNuevo, $comment)
    {
        $sql = "INSERT INTO order_status_history
                (order_id, employee_id, previous_state, new_state, comment)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $orderId, PDO::PARAM_INT);
        $stmt->bindValue(2, (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(3, $estadoAnterior !== null ? $estadoAnterior : null);
        $stmt->bindValue(4, $estadoNuevo);
        $stmt->bindValue(5, $comment !== '' ? $comment : null);

        return $stmt->execute();
    }
}