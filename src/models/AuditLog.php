<?php

class AuditLog
{
    private $conn;
    private $table = 'audit_logs';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Registra una acción en la bitácora. Cualquier fallo se ignora en silencio:
     * la auditoría nunca debe tumbar una operación legítima.
     */
    public function write($action, $tableName = null, $recordId = null, $oldData = null, $newData = null, $description = null, $userId = null)
    {
        if ($userId === null) {
            $userId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
        }

        $oldJson = is_array($oldData) && !empty($oldData) ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
        $newJson = is_array($newData) && !empty($newData) ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;
        $createdAt = date('Y-m-d H:i:s');

        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO " . $this->table . "
                    (user_id, action, table_name, record_id, old_data, new_data, description, ip_address, created_at)
                 VALUES (:user_id, :action, :table_name, :record_id, :old_data, :new_data, :description, :ip_address, :created_at);"
            );
            $stmt->bindParam(':user_id', $userId, $userId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':table_name', $tableName, $tableName === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':record_id', $recordId, $recordId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':old_data', $oldJson, $oldJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':new_data', $newJson, $newJson === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':description', $description);
            $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
            $stmt->bindParam(':ip_address', $ip, $ip === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindParam(':created_at', $createdAt);
            $stmt->execute();
        } catch (PDOException $e) {
            // Silencioso: la bitácora no debe romper la operación.
        }
    }

    /**
     * Bitácora de una caja concreta: acciones sobre la fila `caja` más los
     * movimientos de efectivo (que se registran en su propia tabla pero llevan
     * el id de la caja en new_data.caja).
     */
    public function getCajaTimeline($cajaId)
    {
        $cajaId = (int) $cajaId;
        $cajaIdTexto = (string) $cajaId;

        $stmt = $this->conn->prepare(
            "SELECT l.id, l.action, l.description, l.old_data, l.new_data, l.created_at,
                    e.name AS user_name, e.last_name AS user_last_name
               FROM " . $this->table . " l
               LEFT JOIN empleados e ON e.id = l.user_id
              WHERE (l.table_name = 'caja' AND l.record_id = :id1)
                 OR (l.table_name = 'cash_register_movements'
                     AND JSON_UNQUOTE(JSON_EXTRACT(l.new_data, '$.caja')) = :id2)
              ORDER BY l.id ASC;"
        );
        // bindParam exige variables por referencia: se castean antes.
        $stmt->bindParam(':id1', $cajaId, PDO::PARAM_INT);
        $stmt->bindParam(':id2', $cajaIdTexto);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getAll($limit = 300)
    {
        $query = "SELECT l.id, l.user_id, l.action, l.table_name, l.record_id,
                         l.old_data, l.new_data, l.description, l.ip_address, l.created_at,
                         e.name AS user_name, e.last_name AS user_last_name
                    FROM " . $this->table . " l
                    LEFT JOIN empleados e ON e.id = l.user_id
                   ORDER BY l.id DESC
                   LIMIT " . (int) $limit . ";";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getActions()
    {
        $stmt = $this->conn->prepare("SELECT DISTINCT action FROM " . $this->table . " ORDER BY action ASC;");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getTables()
    {
        $stmt = $this->conn->prepare("SELECT DISTINCT table_name FROM " . $this->table . " WHERE table_name IS NOT NULL AND table_name <> '' ORDER BY table_name ASC;");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}