<?php

/**
 * Cajas (tabla `caja`). Cada caja pertenece al empleado que la abrió y sólo
 * puede haber una abierta por empleado a la vez (lo garantiza el UNIQUE
 * uq_cash_open_per_employee sobre la columna virtual state_open).
 */
class CashRegister
{
    private $conn;
    private $table = 'caja';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Listado de cajas con el efectivo esperado calculado en SQL:
     * fondo + ventas en efectivo + ingresos extra - retiros.
     */
    public function getAll($limit = 200)
    {
        $query = "SELECT c.*,
                         ao.name AS opening_name, ao.last_name AS opening_last_name,
                         ac.name AS closing_name, ac.last_name AS closing_last_name,
                         ar.name AS reopen_name, ar.last_name AS reopen_last_name,
                         IFNULL((SELECT SUM(sp.amount) FROM ventas v
                                  JOIN sale_payments sp ON sp.sale_id = v.id
                                 WHERE v.cash_register_id = c.id
                                   AND sp.payment_method = 'efectivo'
                                   AND v.state = 'completada'), 0) AS cash_sales,
                         IFNULL((SELECT SUM(CASE WHEN m.movement_type = 'ingreso_extra' THEN m.amount ELSE -m.amount END)
                                   FROM cash_register_movements m
                                  WHERE m.cash_register_id = c.id), 0) AS cash_movements,
                         (SELECT COUNT(*) FROM ventas v
                           WHERE v.cash_register_id = c.id AND v.state = 'completada') AS sales_count,
                         (c.initial_amount
                            + IFNULL((SELECT SUM(sp.amount) FROM ventas v
                                        JOIN sale_payments sp ON sp.sale_id = v.id
                                       WHERE v.cash_register_id = c.id
                                         AND sp.payment_method = 'efectivo'
                                         AND v.state = 'completada'), 0)
                            + IFNULL((SELECT SUM(CASE WHEN m.movement_type = 'ingreso_extra' THEN m.amount ELSE -m.amount END)
                                        FROM cash_register_movements m
                                       WHERE m.cash_register_id = c.id), 0)) AS expected_cash
                    FROM " . $this->table . " c
                    JOIN empleados ao ON ao.id = c.opening_employee_id
                    LEFT JOIN empleados ac ON ac.id = c.closing_employee_id
                    LEFT JOIN empleados ar ON ar.id = c.reopened_by
                   ORDER BY c.cash_date DESC, c.opening_time DESC
                   LIMIT " . (int) $limit . ";";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cajas de un empleado (historial) con el mismo efectivo esperado.
     */
    public function getByEmployee($employeeId, $limit = 60)
    {
        $employeeId = (int) $employeeId;

        $query = "SELECT * FROM (
                    SELECT c.*,
                           ao.name AS opening_name, ao.last_name AS opening_last_name,
                           ac.name AS closing_name, ac.last_name AS closing_last_name,
                           ar.name AS reopen_name, ar.last_name AS reopen_last_name,
                           IFNULL((SELECT SUM(sp.amount) FROM ventas v
                                    JOIN sale_payments sp ON sp.sale_id = v.id
                                   WHERE v.cash_register_id = c.id
                                     AND sp.payment_method = 'efectivo'
                                     AND v.state = 'completada'), 0) AS cash_sales,
                           IFNULL((SELECT SUM(CASE WHEN m.movement_type = 'ingreso_extra' THEN m.amount ELSE -m.amount END)
                                     FROM cash_register_movements m
                                    WHERE m.cash_register_id = c.id), 0) AS cash_movements,
                           (SELECT COUNT(*) FROM ventas v
                             WHERE v.cash_register_id = c.id AND v.state = 'completada') AS sales_count,
                           (c.initial_amount
                              + IFNULL((SELECT SUM(sp.amount) FROM ventas v
                                          JOIN sale_payments sp ON sp.sale_id = v.id
                                         WHERE v.cash_register_id = c.id
                                           AND sp.payment_method = 'efectivo'
                                           AND v.state = 'completada'), 0)
                              + IFNULL((SELECT SUM(CASE WHEN m.movement_type = 'ingreso_extra' THEN m.amount ELSE -m.amount END)
                                          FROM cash_register_movements m
                                         WHERE m.cash_register_id = c.id), 0)) AS expected_cash
                      FROM " . $this->table . " c
                      JOIN empleados ao ON ao.id = c.opening_employee_id
                      LEFT JOIN empleados ac ON ac.id = c.closing_employee_id
                      LEFT JOIN empleados ar ON ar.id = c.reopened_by
                     WHERE c.opening_employee_id = :id
                     ORDER BY c.cash_date DESC, c.opening_time DESC
                     LIMIT " . (int) $limit . ") t
                   ORDER BY t.cash_date DESC, t.opening_time DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT c.*,
                         ao.name AS opening_name, ao.last_name AS opening_last_name,
                         ac.name AS closing_name, ac.last_name AS closing_last_name,
                         ar.name AS reopen_name, ar.last_name AS reopen_last_name
                    FROM " . $this->table . " c
                    JOIN empleados ao ON ao.id = c.opening_employee_id
                    LEFT JOIN empleados ac ON ac.id = c.closing_employee_id
                    LEFT JOIN empleados ar ON ar.id = c.reopened_by
                   WHERE c.id = :id
                   LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    /**
     * Caja abierta de un empleado (o null si no tiene ninguna).
     */
    public function getOpenByEmployee($employeeId)
    {
        $employeeId = (int) $employeeId;

        $stmt = $this->conn->prepare(
            "SELECT id, opening_employee_id, cash_date, opening_time, initial_amount, state
               FROM " . $this->table . "
              WHERE opening_employee_id = :id AND state = 'abierta'
              LIMIT 1;"
        );
        $stmt->bindParam(':id', $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: null;
    }

    /**
     * Cajas abiertas (para el panel de supervisión).
     */
    public function getOpen()
    {
        $stmt = $this->conn->prepare(
            "SELECT c.*, e.name AS employee_name, e.last_name AS employee_last_name, r.name AS role
               FROM " . $this->table . " c
               JOIN empleados e ON e.id = c.opening_employee_id
               JOIN roles r ON r.id = e.role_id
              WHERE c.state = 'abierta'
              ORDER BY c.opening_time ASC;"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Detalle del arqueo: efectivo esperado y desglose de ventas por método.
     */
    public function getSummary($id)
    {
        $register = $this->getById($id);

        if (!$register) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT sp.payment_method, SUM(sp.amount) AS total, COUNT(DISTINCT v.id) AS sales
               FROM ventas v
               JOIN sale_payments sp ON sp.sale_id = v.id
              WHERE v.cash_register_id = :id AND v.state = 'completada'
              GROUP BY sp.payment_method
              ORDER BY sp.payment_method;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $register['payments'] = $stmt->fetchAll();

        $cash = 0.0;
        $card = 0.0;
        $transfer = 0.0;
        $salesCount = 0;

        foreach ($register['payments'] as $row) {
            $amount = (float) $row['total'];
            $salesCount = max($salesCount, (int) $row['sales']);

            if ($row['payment_method'] === 'efectivo') {
                $cash += $amount;
            } elseif ($row['payment_method'] === 'tarjeta') {
                $card += $amount;
            } else {
                $transfer += $amount;
            }
        }

        $stmt = $this->conn->prepare(
            "SELECT SUM(CASE WHEN movement_type = 'ingreso_extra' THEN amount ELSE 0 END) AS ingresos,
                    SUM(CASE WHEN movement_type = 'retiro' THEN amount ELSE 0 END) AS retiros,
                    COUNT(*) AS total_movimientos
               FROM cash_register_movements
              WHERE cash_register_id = :id;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $movements = $stmt->fetch() ?: [];
        $in = (float) ($movements['ingresos'] ?? 0);
        $out = (float) ($movements['retiros'] ?? 0);

        $register['cash_sales'] = $cash;
        $register['cash_movements'] = round($in - $out, 2);
        $register['extra_in'] = $in;
        $register['withdrawals'] = $out;
        $register['sales_count'] = $salesCount;
        $register['card_sales'] = $card;
        $register['transfer_sales'] = $transfer;
        $register['expected_cash'] = round((float) $register['initial_amount'] + $cash + $in - $out, 2);

        return $register;
    }

    /**
     * Abre una caja para un empleado. Devuelve false si ya tenía una abierta
     * (el UNIQUE de la base también lo garantiza).
     *
     * `reopened_by` queda NULL en una apertura normal: esa columna solo
     * registra quién reabrió una caja previamente cerrada.
     */
    /**
     * Ventas de una caja con su desglose de pagos y sus artículos, para la
     * vista de detalle. Cada venta trae `payments` e `items`.
     */
    public function getSales($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT v.id, v.sale_date, v.subtotal, v.total_discount, v.tax, v.total,
                    v.payment_method, v.state,
                    e.name AS employee_name, e.last_name AS employee_last_name,
                    COALESCE(c.name, '') AS client_name, COALESCE(c.last_name, '') AS client_last_name,
                    (SELECT COUNT(*) FROM sale_details sd WHERE sd.sale_id = v.id) AS items_count
               FROM ventas v
               LEFT JOIN empleados e ON e.id = v.employee_id
               LEFT JOIN clients c ON c.id = v.client_id
              WHERE v.cash_register_id = :id
              ORDER BY v.sale_date ASC, v.id ASC;"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $ventas = $stmt->fetchAll();

        if (!$ventas) {
            return [];
        }

        $ids = array_map(function ($row) {
            return (int) $row['id'];
        }, $ventas);

        // Placeholders reales (enteros casteados) para el IN().
        $marcas = implode(',', array_fill(0, count($ids), '?'));

        $pagos = $this->conn->prepare(
            "SELECT sale_id, payment_method, SUM(amount) AS amount
               FROM sale_payments
              WHERE sale_id IN ($marcas)
              GROUP BY sale_id, payment_method
              ORDER BY sale_id, payment_method;"
        );
        $pagos->execute($ids);

        $porVenta = [];

        foreach ($pagos->fetchAll() as $pago) {
            $porVenta[(int) $pago['sale_id']][] = $pago;
        }

        $items = $this->conn->prepare(
            "SELECT sd.sale_id, sd.quantity, sd.unit_price, sd.discount, sd.subtotal,
                    p.name AS product_name
               FROM sale_details sd
               LEFT JOIN productos p ON p.id = sd.product_id
              WHERE sd.sale_id IN ($marcas)
              ORDER BY sd.sale_id ASC, sd.id ASC;"
        );
        $items->execute($ids);

        $itemsPorVenta = [];

        foreach ($items->fetchAll() as $item) {
            $itemsPorVenta[(int) $item['sale_id']][] = $item;
        }

        foreach ($ventas as $i => $venta) {
            $sid = (int) $venta['id'];
            $ventas[$i]['payments'] = $porVenta[$sid] ?? [];
            $ventas[$i]['items'] = $itemsPorVenta[$sid] ?? [];
        }

        return $ventas;
    }

    /**
     * Totales por método de pago de todas las ventas completadas de la caja,
     * con el mismo criterio que usa getSummary().
     */
    public function getSalesTotals($id)
    {
        $totales = $this->conn->prepare(
            "SELECT sp.payment_method, SUM(sp.amount) AS amount, COUNT(DISTINCT v.id) AS sales
               FROM ventas v
               JOIN sale_payments sp ON sp.sale_id = v.id
              WHERE v.cash_register_id = :id AND v.state = 'completada'
              GROUP BY sp.payment_method
              ORDER BY sp.payment_method;"
        );
        $totales->bindParam(':id', $id, PDO::PARAM_INT);
        $totales->execute();

        return $totales->fetchAll();
    }

    public function open($employeeId, $initialAmount)
    {
        $employeeId = (int) $employeeId;
        $initialAmount = round((float) $initialAmount, 2);

        $stmt = $this->conn->prepare(
            "INSERT INTO " . $this->table . "
                (opening_employee_id, cash_date, opening_time, initial_amount, state)
             VALUES (:employee_id, CURDATE(), CURTIME(), :initial_amount, 'abierta');"
        );
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindParam(':initial_amount', $initialAmount);

        return $stmt->execute() ? (int) $this->conn->lastInsertId() : false;
    }

    /**
     * Cierra la caja con el conteo físico. El efectivo esperado se recalcula
     * dentro de la transacción y difference = físico - esperado.
     */
    public function close($id, $physicalAmount, $closingEmployeeId = null)
    {
        $summary = $this->getSummary($id);

        if (!$summary) {
            return false;
        }

        $physical = round((float) $physicalAmount, 2);
        $expected = round((float) $summary['expected_cash'], 2);
        $difference = round($physical - $expected, 2);
        $closer = $closingEmployeeId === null ? $summary['opening_employee_id'] : (int) $closingEmployeeId;

        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
                SET state = 'cerrada',
                    closing_employee_id = :closing_employee_id,
                    closing_time = CURTIME(),
                    system_final_amount = :system_final_amount,
                    physical_final_amount = :physical_final_amount,
                    difference = :difference
              WHERE id = :id AND state = 'abierta';"
        );
        $stmt->bindParam(':closing_employee_id', $closer, PDO::PARAM_INT);
        $stmt->bindValue(':system_final_amount', $expected);
        $stmt->bindValue(':physical_final_amount', $physical);
        $stmt->bindValue(':difference', $difference);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Reabre una caja cerrada (requiere motivo; queda auditado por el controlador).
     */
    public function reopen($id, $reason, $employeeId)
    {
        // bindParam exige variables por referencia: se castean antes.
        $id = (int) $id;
        $reason = (string) $reason;
        $employeeId = (int) $employeeId;

        $stmt = $this->conn->prepare(
            "UPDATE " . $this->table . "
                SET state = 'abierta',
                    closing_employee_id = NULL,
                    closing_time = NULL,
                    system_final_amount = NULL,
                    physical_final_amount = NULL,
                    difference = NULL,
                    reopened_by = :reopened_by,
                    reopen_reason = :reason,
                    reopen_count = reopen_count + 1
              WHERE id = :id AND state = 'cerrada';"
        );
        $stmt->bindParam(':reopened_by', $employeeId, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute() && $stmt->rowCount() > 0;
    }

    /**
     * Elimina una caja cerrada. Las ventas y movimientos quedan huérfanos en
     * memoria, por eso sólo se permite sobre cajas cerradas sin ventas.
     */
    public function delete($id)
    {
        $stmt = $this->conn->prepare(
            "DELETE FROM " . $this->table . "
              WHERE id = :id AND state = 'cerrada'
                AND NOT EXISTS (SELECT 1 FROM ventas v WHERE v.cash_register_id = :id2);"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Total vendido (todas las ventas completadas) de una caja, por método.
     * El total se suma sobre `ventas` y no sobre el join con `sale_payments`
     * para no contarlo una vez por cada pago de una venta dividida.
     */
    public function salesTotal($id)
    {
        $stmt = $this->conn->prepare(
            "SELECT IFNULL(SUM(v.total), 0) AS total,
                    (SELECT IFNULL(SUM(sp.amount), 0) FROM sale_payments sp
                       JOIN ventas v2 ON v2.id = sp.sale_id
                      WHERE v2.cash_register_id = :id AND v2.state = 'completada'
                        AND sp.payment_method = 'efectivo') AS efectivo
               FROM ventas v
              WHERE v.cash_register_id = :id AND v.state = 'completada';"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch() ?: [];

        return [
            'total' => round((float) ($row['total'] ?? 0), 2),
            'efectivo' => round((float) ($row['efectivo'] ?? 0), 2),
        ];
    }
}
