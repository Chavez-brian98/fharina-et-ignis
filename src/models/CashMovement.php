<?php

/**
 * Ingresos extra y retiros de una caja abierta (cash_register_movements).
 */
class CashMovement
{
    private $conn;
    private $table = 'cash_register_movements';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getByRegister($cashRegisterId)
    {
        $stmt = $this->conn->prepare(
            "SELECT m.*, e.name, e.last_name
               FROM " . $this->table . " m
               JOIN empleados e ON e.id = m.employee_id
              WHERE m.cash_register_id = :id
              ORDER BY m.movement_date DESC, m.id DESC;"
        );
        $stmt->bindParam(':id', $cashRegisterId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create($cashRegisterId, $employeeId, $type, $amount, $reason)
    {
        // bindParam exige variables reales por referencia: los valores se
        // calculan antes en variables locales.
        $cashRegisterId = (int) $cashRegisterId;
        $employeeId = (int) $employeeId;
        $type = (string) $type;
        $amount = round((float) $amount, 2);
        $reason = (string) $reason;

        $stmt = $this->conn->prepare(
            "INSERT INTO " . $this->table . "
                (cash_register_id, employee_id, movement_type, amount, reason)
             VALUES (:cash_register_id, :employee_id, :movement_type, :amount, :reason);"
        );
        $stmt->bindParam(':cash_register_id', $cashRegisterId, PDO::PARAM_INT);
        $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
        $stmt->bindParam(':movement_type', $type);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':reason', $reason);

        return $stmt->execute() ? (int) $this->conn->lastInsertId() : false;
    }
}
