<?php

/**
 * Domicilios en línea (tabla deliveries + delivery_locations).
 *
 * Cada pedido a domicilio colocado desde el portal web genera una fila aquí
 * con su propia máquina de estados:
 *
 *   tomado → preparando → en_camino → finalizado
 *
 * El estado siempre avanza (no hay vuelta atrás) y se sincroniza con
 * pedidos.state para que el módulo de Pedidos los muestre de forma coherente:
 *
 *   tomado      → aprobado
 *   preparando  → en_produccion
 *   en_camino   → listo
 *   finalizado  → entregado
 *
 * La posición GPS del domiciliero se reporta desde el navegador (geolocation)
 * cada ~10s: el controlador la guarda aquí (histórico + last_seen_at) y la
 * difunde en vivo vía el relay WebSocket (modelo WsRelay).
 */

class Delivery
{
    private $conn;

    const ESTADOS = ['tomado', 'preparando', 'en_camino', 'finalizado'];

    // pedidos.state al que se sincroniza cada estado del domicilio.
    const PEDIDO_POR_ESTADO = [
        'tomado' => 'aprobado',
        'preparando' => 'en_produccion',
        'en_camino' => 'listo',
        'finalizado' => 'entregado',
    ];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ---------------------------------------------------------------- estados

    public static function estados()
    {
        return [
            'tomado' => 'Pedido tomado',
            'preparando' => 'Preparando su pedido',
            'en_camino' => 'En camino',
            'finalizado' => 'Finalizado',
        ];
    }

    public static function estadoTexto($estado)
    {
        $estados = self::estados();

        return $estados[$estado] ?? $estado;
    }

    public static function estadoColor($estado)
    {
        switch ($estado) {
            case 'tomado': return 'bg-blue-100 text-blue-700';
            case 'preparando': return 'bg-orange-100 text-orange-700';
            case 'en_camino': return 'bg-amber-100 text-amber-700';
            case 'finalizado': return 'bg-green-100 text-green-700';
            default: return 'bg-gray-100 text-gray-600';
        }
    }

    /** Icono Font Awesome para cada estado (pantalla de tracking). */
    public static function estadoIcon($estado)
    {
        switch ($estado) {
            case 'tomado': return 'fa-file-circle-check';
            case 'preparando': return 'fa-fire-burner';
            case 'en_camino': return 'fa-motorcycle';
            case 'finalizado': return 'fa-circle-check';
            default: return 'fa-truck-fast';
        }
    }

    /** El único estado siguiente válido (máquina lineal). */
    public static function proximoEstado($estado)
    {
        $orden = self::ESTADOS;
        $idx = array_search($estado, $orden, true);

        return $idx !== false && isset($orden[$idx + 1]) ? $orden[$idx + 1] : null;
    }

    // ---------------------------------------------------------------- lectura

    public function getAll($estado = null)
    {
        $sql = "SELECT d.id, d.order_id, d.driver_id, d.state, d.destination_address,
                       d.destination_lat, d.destination_lng, d.tracking_token, d.assigned_at,
                       d.last_seen_at, d.created_at,
                       p.total, p.paid_amount, p.state AS pedido_state, p.order_date,
                       c.id AS client_id, c.client_type, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name,
                       emp.name AS driver_name, emp.last_name AS driver_last_name
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                  JOIN clients c ON c.id = p.client_id
                  LEFT JOIN empleados emp ON emp.id = d.driver_id";
        $params = [];

        if ($estado !== null && $estado !== '' && $estado !== 'todos') {
            $sql .= ' WHERE d.state = :estado';
            $params[':estado'] = $estado;
        }
        $sql .= ' ORDER BY FIELD(d.state, \'tomado\', \'preparando\', \'en_camino\', \'finalizado\'),
                            d.created_at DESC;';

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $sql = "SELECT d.*, p.total, p.paid_amount, p.remaining_balance, p.state AS pedido_state,
                       p.order_date, p.delivery_date, p.notes, p.order_type,
                       c.id AS client_id, c.client_type, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone, c.email, c.address AS client_address,
                       emp.name AS driver_name, emp.last_name AS driver_last_name, emp.id AS driver_id_emp
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                  JOIN clients c ON c.id = p.client_id
                  LEFT JOIN empleados emp ON emp.id = d.driver_id
                 WHERE d.id = :id
                 LIMIT 1;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getByOrderId($orderId)
    {
        $stmt = $this->conn->prepare("SELECT d.*, p.total, p.paid_amount,
                                             c.name AS client_name, c.last_name AS client_last_name,
                                             c.client_type, c.company_name
                                        FROM deliveries d
                                        JOIN pedidos p ON p.id = d.order_id
                                        JOIN clients c ON c.id = p.client_id
                                       WHERE d.order_id = :id
                                       LIMIT 1;");
        $stmt->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function getByToken($token)
    {
        $sql = "SELECT d.*, p.total, p.paid_amount, p.state AS pedido_state, p.order_date,
                       p.delivery_date, p.notes,
                       c.client_type, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone,
                       emp.name AS driver_name, emp.last_name AS driver_last_name
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                  JOIN clients c ON c.id = p.client_id
                  LEFT JOIN empleados emp ON emp.id = d.driver_id
                 WHERE d.tracking_token = :token
                 LIMIT 1;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        return $stmt->fetch();
    }

    /** Envíos activos (no finalizados) asignados a un domiciliero. */
    public function getMisEntregas($driverId)
    {
        $sql = "SELECT d.*, p.total, p.paid_amount, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                  JOIN clients c ON c.id = p.client_id
                 WHERE d.driver_id = :driver AND d.state != 'finalizado'
                 ORDER BY FIELD(d.state, 'tomado', 'preparando', 'en_camino'), d.created_at ASC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':driver', (int) $driverId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Envíos sin domiciliero asignado que alguien puede tomar. */
    public function getDisponibles()
    {
        $sql = "SELECT d.*, p.total, p.paid_amount, c.company_name, c.name AS client_name,
                       c.last_name AS client_last_name, c.phone
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                  JOIN clients c ON c.id = p.client_id
                 WHERE d.driver_id IS NULL AND d.state != 'finalizado'
                 ORDER BY d.created_at ASC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function ultimaPosicion($deliveryId)
    {
        $stmt = $this->conn->prepare("SELECT lat, lng, reported_at FROM delivery_locations
                                       WHERE delivery_id = :id
                                       ORDER BY id DESC LIMIT 1;");
        $stmt->bindValue(':id', (int) $deliveryId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /** Historial de domicilios de un cliente (su página de cuenta). */
    public function getPorCliente($clientId)
    {
        $sql = "SELECT d.id, d.order_id, d.driver_id, d.state, d.destination_address,
                       d.tracking_token, d.created_at, d.finalizado_at,
                       p.total, p.paid_amount, p.state AS pedido_state, p.order_date
                  FROM deliveries d
                  JOIN pedidos p ON p.id = d.order_id
                 WHERE p.client_id = :client
                 ORDER BY d.created_at DESC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':client', (int) $clientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // --------------------------------------------------------------- escritura

    /**
     * Crea el pedido a domicilio completo en una transacción: cabecera en
     * pedidos (recorded_by_employee_id NULL, order_type 'domicilio', estado
     * 'aprobado' = "tomado"), sus líneas y la fila en deliveries con token.
     *
     * Devuelve ['order_id' =>, 'delivery_id' =>, 'token' =>] o false.
     */
    public function createDomicilio($clientId, $deliveryAddress, $lat, $lng, $total, array $items, $notes = '')
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO pedidos
                    (client_id, recorded_by_employee_id, delivery_date, delivery_address, order_type,
                     state, total, paid_amount, remaining_balance, notes)
                    VALUES (:client, NULL, CURDATE(), :dir, 'domicilio',
                            'aprobado', :total, 0, :total, :notes)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':client', (int) $clientId, PDO::PARAM_INT);
            $stmt->bindValue(':dir', $deliveryAddress !== '' ? $deliveryAddress : null);
            $stmt->bindValue(':total', $total);
            $stmt->bindValue(':notes', $notes !== '' ? $notes : null);
            $stmt->execute();

            $orderId = (int) $this->conn->lastInsertId();
            $this->insertarDetalles($orderId, $items);

            $token = bin2hex(random_bytes(24));
            $sql = "INSERT INTO deliveries
                    (order_id, state, destination_lat, destination_lng, destination_address,
                     tracking_token, tomado_at)
                    VALUES (:order, 'tomado', :lat, :lng, :dir, :token, NOW())";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':order', $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':lat', $lat !== null ? $lat : null);
            $stmt->bindValue(':lng', $lng !== null ? $lng : null);
            $stmt->bindValue(':dir', $deliveryAddress !== '' ? $deliveryAddress : 'Sin dirección');
            $stmt->bindValue(':token', $token);
            $stmt->execute();

            $deliveryId = (int) $this->conn->lastInsertId();

            $this->conn->commit();

            return ['order_id' => $orderId, 'delivery_id' => $deliveryId, 'token' => $token];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /** Asigna un domiciliero al envío (una vez). */
    public function asignar($id, $driverId)
    {
        $fila = $this->getById($id);
        if (!$fila || $fila['state'] === 'finalizado') {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE deliveries
                                         SET driver_id = :driver, assigned_at = COALESCE(assigned_at, NOW())
                                       WHERE id = :id;");
        $stmt->bindValue(':driver', (int) $driverId, PDO::PARAM_INT);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Avanza al siguiente estado (máquina lineal) y sincroniza pedidos.state.
     * Devuelve el nuevo estado o false si ya estaba finalizado / inválido.
     */
    public function avanzar($id)
    {
        $fila = $this->getById($id);
        if (!$fila) {
            return false;
        }
        $nuevo = self::proximoEstado($fila['state']);
        if ($nuevo === null) {
            return false;
        }

        $columna = [
            'preparando' => 'preparando_at',
            'en_camino' => 'en_camino_at',
            'finalizado' => 'finalizado_at',
        ][$nuevo];

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("UPDATE deliveries SET state = :estado,
                                                $columna = NOW()
                                          WHERE id = :id;");
            $stmt->bindValue(':estado', $nuevo);
            $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            // Reflejar en pedidos para que /orders lo muestre coherente.
            $stmt = $this->conn->prepare("UPDATE pedidos SET state = :pedido
                                          WHERE id = :order;");
            $stmt->bindValue(':pedido', self::PEDIDO_POR_ESTADO[$nuevo]);
            $stmt->bindValue(':order', (int) $fila['order_id'], PDO::PARAM_INT);
            $stmt->execute();

            $this->conn->commit();

            return $nuevo;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /** Guarda la posición reportada por el navegador del domiciliero. */
    public function registrarPosicion($id, $lat, $lng)
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("INSERT INTO delivery_locations (delivery_id, lat, lng)
                                          VALUES (:id, :lat, :lng);");
            $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmt->bindValue(':lat', $lat);
            $stmt->bindValue(':lng', $lng);
            $stmt->execute();

            $stmt = $this->conn->prepare("UPDATE deliveries SET last_seen_at = NOW()
                                          WHERE id = :id;");
            $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    /**
     * Registra el inicio de un pago web: fila en order_payments (employee_id
     * NULL, paypal_order_id, paypal_capture_id NULL). Se completa después con
     * confirmarPago() cuando PayPal captura. Devuelve el id del pago o false.
     */
    public function registrarPagoIniciado($orderId, $paypalOrderId, $amount)
    {
        try {
            $stmt = $this->conn->prepare("INSERT INTO order_payments
                                          (order_id, employee_id, amount, payment_method,
                                           paypal_order_id, paypal_capture_id)
                                          VALUES (:order, NULL, :monto, 'paypal', :ppo, NULL);");
            $stmt->bindValue(':order', (int) $orderId, PDO::PARAM_INT);
            $stmt->bindValue(':monto', $amount);
            $stmt->bindValue(':ppo', $paypalOrderId !== '' ? $paypalOrderId : null);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Confirma el pago capturado por PayPal contra la fila de order_payments
     * que dejó registrarPagoIniciado (por paypal_order_id) y pone el saldo del
     * pedido en 0. Devuelve el order_id si todo salió bien, si no false.
     */
    public function confirmarPago($paypalOrderId, $captureId, $amount)
    {
        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("SELECT id, order_id FROM order_payments
                                          WHERE paypal_order_id = :ppo LIMIT 1;");
            $stmt->bindParam(':ppo', $paypalOrderId);
            $stmt->execute();
            $pay = $stmt->fetch();
            if (!$pay) {
                $this->conn->rollBack();
                return false;
            }

            $stmt = $this->conn->prepare("UPDATE order_payments
                                             SET paypal_capture_id = :ppc
                                           WHERE id = :pid;");
            $stmt->bindValue(':ppc', $captureId !== '' ? $captureId : null);
            $stmt->bindValue(':pid', (int) $pay['id'], PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("UPDATE pedidos
                                             SET paid_amount = :monto,
                                                 remaining_balance = GREATEST(0, total - :monto)
                                           WHERE id = :order;");
            $stmt->bindValue(':monto', $amount);
            $stmt->bindValue(':order', (int) $pay['order_id'], PDO::PARAM_INT);
            $stmt->execute();

            $this->conn->commit();

            return (int) $pay['order_id'];
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    public function getPagos($orderId)
    {
        $stmt = $this->conn->prepare("SELECT * FROM order_payments WHERE order_id = :id ORDER BY id ASC;");
        $stmt->bindValue(':id', (int) $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // ---------------------------------------------------------------- helpers

    private function insertarDetalles($orderId, array $items)
    {
        $sql = "INSERT INTO order_details
                (order_id, product_id, personalized_description, quantity, unit_price, subtotal)
                VALUES (?, ?, NULL, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $quantity = max(1, (int) $item['quantity']);
            $unitPrice = (float) $item['unit_price'];
            $subtotal = $quantity * $unitPrice;

            $stmt->bindValue(1, (int) $orderId, PDO::PARAM_INT);
            $stmt->bindValue(2, $productId, PDO::PARAM_INT);
            $stmt->bindValue(3, $quantity, PDO::PARAM_INT);
            $stmt->bindValue(4, $unitPrice);
            $stmt->bindValue(5, $subtotal);
            $stmt->execute();
        }
    }

    /** Nombre a mostrar de un cliente (la empresa gana al nombre personal). */
    public static function nombreCliente($cliente)
    {
        if (($cliente['client_type'] ?? '') === 'empresa' && trim((string) ($cliente['company_name'] ?? '')) !== '') {
            return $cliente['company_name'];
        }

        return trim(($cliente['client_name'] ?? '') . ' ' . ($cliente['client_last_name'] ?? ''));
    }
}