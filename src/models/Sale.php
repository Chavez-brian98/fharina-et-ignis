<?php

class Sale
{
    private $conn;
    private $table = 'ventas';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    /**
     * Catálogo para el POS: productos activos con categoría y descuento
     * vigente (de promociones activas). Incluye el precio final con descuento.
     */
    public function getCatalog()
    {
        $query = "SELECT p.id, p.name, p.description, p.sale_price, p.stock, p.image_url, p.barcode,
                         p.category_id, c.name AS category_name,
                         (SELECT MAX(pr.discount_percentage)
                            FROM promociones pr
                            JOIN promotion_product pp ON pp.promotion_id = pr.id
                           WHERE pp.product_id = p.id
                             AND pr.status = 'active'
                             AND CURDATE() BETWEEN pr.start_date AND pr.end_date) AS discount_percent
                    FROM productos p
                    INNER JOIN categorias AS c ON p.category_id = c.id
                    WHERE p.status = 'active'
                    ORDER BY c.display_order ASC, p.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        foreach ($rows as $i => $row) {
            $percent = (float) ($row['discount_percent'] ?? 0);
            $price = (float) $row['sale_price'];
            $rows[$i]['discount_percent'] = $percent;
            $rows[$i]['final_price'] = round($price * (1 - $percent / 100), 2);
        }

        return $rows;
    }

    /**
     * Descuento vigente (porcentaje) de un producto para hoy.
     */
    public function getProductDiscount($productId)
    {
        $query = "SELECT MAX(pr.discount_percentage) AS discount_percent
                    FROM promociones pr
                    JOIN promotion_product pp ON pp.promotion_id = pr.id
                   WHERE pp.product_id = :product_id
                     AND pr.status = 'active'
                     AND CURDATE() BETWEEN pr.start_date AND pr.end_date;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? (float) $row['discount_percent'] : 0.0;
    }

    /**
     * Crea la venta (cabecera + detalle + pagos), descuenta stock, en transacción.
     *
     * @param array $items     [['product_id'=>int, 'quantity'=>int], ...]
     * @param array $payments  [['method'=>'efectivo'|'tarjeta'|'transferencia', 'amount'=>float], ...]
     * @param float $taxRate   Porcentaje de impuesto (0 si no aplica)
     * @throws Exception Si algún producto no existe, no hay stock o el pago no cubre el total
     * @return int ID de la venta creada
     */
    public function createSale(array $items, array $payments, $taxRate = 0.0)
    {
        if (empty($items)) {
            throw new Exception('El carrito está vacío.');
        }
        if (empty($payments)) {
            throw new Exception('Debe registrar al menos un método de pago.');
        }

        $allowedMethods = ['efectivo', 'tarjeta', 'transferencia'];
        foreach ($payments as $pay) {
            if (!in_array($pay['method'], $allowedMethods, true)) {
                throw new Exception('Método de pago no válido.');
            }
            if ((float) $pay['amount'] <= 0) {
                throw new Exception('El monto de pago debe ser mayor a cero.');
            }
        }

        $this->conn->beginTransaction();

        try {
            $details = [];
            $subtotal = 0.0;
            $totalDiscount = 0.0;

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $qty = (int) $item['quantity'];

                if ($qty < 1) {
                    throw new Exception('La cantidad de un producto no es válida.');
                }

                $stmt = $this->conn->prepare(
                    "SELECT name, sale_price, stock
                       FROM productos
                      WHERE id = :id AND status = 'active'
                      LIMIT 1;"
                );
                $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
                $stmt->execute();
                $product = $stmt->fetch();

                if (!$product) {
                    throw new Exception('Un producto del carrito ya no existe o está inactivo.');
                }
                if ($qty > (int) $product['stock']) {
                    throw new Exception('Stock insuficiente para "' . $product['name'] . '".');
                }

                $unitPrice = round((float) $product['sale_price'], 2);
                $percent = $this->getProductDiscount($productId);
                $unitFinal = round($unitPrice * (1 - $percent / 100), 2);

                $lineSubtotal = round($unitPrice * $qty, 2);
                $lineDiscount = round($unitFinal * $qty * -1, 2) + $lineSubtotal;

                $details[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'unit_price' => $unitFinal,
                    'discount' => $lineDiscount,
                    'subtotal' => round($unitFinal * $qty, 2),
                    'name' => $product['name'],
                ];

                $subtotal += $lineSubtotal;
                $totalDiscount += $lineDiscount;
            }

            $subtotal = round($subtotal, 2);
            $totalDiscount = round($totalDiscount, 2);
            $tax = round(($subtotal - $totalDiscount) * ((float) $taxRate / 100), 2);
            $total = round($subtotal - $totalDiscount + $tax, 2);

            $paid = array_sum(array_map(function ($p) {
                return (float) $p['amount'];
            }, $payments));

            if ($paid < $total - 0.005) {
                throw new Exception('El monto pagado no cubre el total de la venta.');
            }

            $paymentMethod = count($payments) === 1
                ? $payments[0]['method']
                : 'mixto';

            $stmt = $this->conn->prepare(
                "INSERT INTO " . $this->table . "
                    (cash_register_id, client_id, employee_id, promotion_id,
                     subtotal, total_discount, tax, total, payment_method, state)
                 VALUES (NULL, NULL, NULL, NULL, :subtotal, :total_discount, :tax, :total, :payment_method, 'completada');"
            );
            $stmt->bindParam(':subtotal', $subtotal);
            $stmt->bindParam(':total_discount', $totalDiscount);
            $stmt->bindParam(':tax', $tax);
            $stmt->bindParam(':total', $total);
            $stmt->bindParam(':payment_method', $paymentMethod);
            $stmt->execute();
            $saleId = (int) $this->conn->lastInsertId();

            $stmt = $this->conn->prepare(
                "INSERT INTO sale_details (sale_id, product_id, quantity, unit_price, discount, subtotal)
                 VALUES (:sale_id, :product_id, :quantity, :unit_price, :discount, :subtotal);"
            );
            foreach ($details as $d) {
                $stmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
                $stmt->bindParam(':product_id', $d['product_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantity', $d['quantity'], PDO::PARAM_INT);
                $stmt->bindParam(':unit_price', $d['unit_price']);
                $stmt->bindParam(':discount', $d['discount']);
                $stmt->bindParam(':subtotal', $d['subtotal']);
                $stmt->execute();
            }

            $stmt = $this->conn->prepare(
                "INSERT INTO sale_payments (sale_id, payment_method, amount)
                 VALUES (:sale_id, :payment_method, :amount);"
            );
            foreach ($payments as $p) {
                $amount = round((float) $p['amount'], 2);
                if ($amount <= 0) {
                    continue;
                }
                $stmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
                $stmt->bindParam(':payment_method', $p['method']);
                $stmt->bindParam(':amount', $amount);
                $stmt->execute();
            }

            $stmt = $this->conn->prepare(
                "UPDATE productos SET stock = stock - :qty
                  WHERE id = :id AND stock >= :qty;"
            );
            foreach ($details as $d) {
                $stmt->bindParam(':qty', $d['quantity'], PDO::PARAM_INT);
                $stmt->bindParam(':id', $d['product_id'], PDO::PARAM_INT);
                $stmt->execute();
                if ($stmt->rowCount() === 0) {
                    throw new Exception('No se pudo descontar el stock de "' . $d['name'] . '".');
                }
            }

            $this->conn->commit();
            return $saleId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    /**
     * Datos completos de una venta para el ticket.
     */
    public function getTicketData($saleId)
    {
        $stmt = $this->conn->prepare(
            "SELECT id, sale_date, subtotal, total_discount, tax, total, payment_method
               FROM " . $this->table . "
              WHERE id = :id AND state = 'completada'
              LIMIT 1;"
        );
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale = $stmt->fetch();

        if (!$sale) {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT sd.product_id, sd.quantity, sd.unit_price, sd.discount, sd.subtotal, p.name
               FROM sale_details sd
               JOIN productos p ON p.id = sd.product_id
              WHERE sd.sale_id = :id
              ORDER BY sd.id ASC;"
        );
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale['details'] = $stmt->fetchAll();

        $stmt = $this->conn->prepare(
            "SELECT payment_method, amount
               FROM sale_payments
              WHERE sale_id = :id
              ORDER BY id ASC;"
        );
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $sale['payments'] = $stmt->fetchAll();

        return $sale;
    }
}