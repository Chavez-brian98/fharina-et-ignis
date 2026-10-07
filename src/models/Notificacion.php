<?php

/**
 * Notificaciones internas del sistema (la campana del layout).
 *
 * Una fila con destination_employee_id NULL es "para todos" (la ven todos los
 * empleados); con un id solo la ve ese empleado. Las escribe el sistema cuando
 * pasa algo relevante: stock bajo, pedido nuevo, cambio de estado de un pedido.
 * La campana de sidebar.php las lista y ofrece marcarlas como leídas.
 */
class Notificacion
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Crea una notificación. $destinoEmpleadoId null = visible para todos.
     */
    public function crear($tipo, $titulo, $mensaje, $referenciaTipo = null, $referenciaId = null, $destinoEmpleadoId = null)
    {
        $sql = "INSERT INTO notificaciones
                (destination_employee_id, notification_type, title, message, reference_type, reference_id)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, $destinoEmpleadoId !== null ? (int) $destinoEmpleadoId : null, PDO::PARAM_INT);
        $stmt->bindValue(2, $tipo);
        $stmt->bindValue(3, $titulo);
        $stmt->bindValue(4, $mensaje);
        $stmt->bindValue(5, $referenciaTipo);
        $stmt->bindValue(6, $referenciaId !== null ? (int) $referenciaId : null, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Notificaciones que ve un empleado: las globales (NULL) más las propias.
     */
    public function para($employeeId, $limite = 30)
    {
        $sql = "SELECT id, destination_employee_id, notification_type, title, message,
                       reference_type, reference_id, readed, creation_date
                  FROM notificaciones
                 WHERE destination_employee_id IS NULL OR destination_employee_id = :id
                 ORDER BY creation_date DESC, id DESC
                 LIMIT :limite";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $employeeId, PDO::PARAM_INT);
        $stmt->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Cantidad de no leídas del empleado (badge de la campana).
     */
    public function noLeidas($employeeId)
    {
        $sql = "SELECT COUNT(*) AS total FROM notificaciones
                 WHERE readed = 0
                   AND (destination_employee_id IS NULL OR destination_employee_id = :id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $employeeId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
    }

    /**
     * Marca una notificación como leída (solo si le corresponde verla).
     */
    public function marcarLeida($id, $employeeId)
    {
        $sql = "UPDATE notificaciones SET readed = 1
                 WHERE id = :id
                   AND (destination_employee_id IS NULL OR destination_employee_id = :emp)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->bindValue(':emp', (int) $employeeId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Marca todo lo visible del empleado como leído.
     */
    public function marcarTodasLeidas($employeeId)
    {
        $sql = "UPDATE notificaciones SET readed = 1
                 WHERE readed = 0
                   AND (destination_employee_id IS NULL OR destination_employee_id = :id)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $employeeId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * ¿Ya existe una notificación del tipo y referencia? Con $soloSinLeer se
     * usa para no spamear las no leídas (p. ej. un stock bajo repetido).
     */
    public function existe($tipo, $referenciaTipo, $referenciaId, $soloSinLeer = true)
    {
        $sql = "SELECT COUNT(*) AS total FROM notificaciones
                 WHERE notification_type = :tipo
                   AND reference_type = :reftipo
                   AND reference_id = :refid"
            . ($soloSinLeer ? " AND readed = 0" : "");
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':reftipo', $referenciaTipo);
        $stmt->bindValue(':refid', (int) $referenciaId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetch()['total'] > 0;
    }

    /**
     * Borra las notificaciones de un tipo/referencia. Se usa para "resolver" un
     * aviso cuando deja de ser cierto (p. ej. el producto fue surtido).
     */
    public function resolver($tipo, $referenciaTipo, $referenciaId)
    {
        $sql = "DELETE FROM notificaciones
                 WHERE notification_type = :tipo
                   AND reference_type = :reftipo
                   AND reference_id = :refid";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':reftipo', $referenciaTipo);
        $stmt->bindValue(':refid', (int) $referenciaId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Destino al que apunta una notificación, para hacer clicable su fila.
     * Devuelve null cuando no hay referencia (no se navega a ningún lado).
     */
    public static function enlace($tipo, $id)
    {
        if ($tipo === 'pedido' && $id !== null) {
            return url('orders/show/' . (int) $id);
        }
        if ($tipo === 'producto' && $id !== null) {
            return url('products/edit/' . (int) $id);
        }

        return null;
    }
}