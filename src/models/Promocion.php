<?php

/**
 * Promociones y cupones (tabla promociones + promotion_product + cupones).
 *
 * El módulo administra las promociones; el POS sigue aplicando SOLO el
 * descuento por porcentaje (Discount::getProductDiscount de Sale). Por eso:
 *   - descuento_volumen / happy_hour / campana_temporal guardan porcentaje.
 *   - dos_por_uno y cupon NO guardan porcentaje (se dejan NULL en la BD para
 *     que el punto de venta no las aplique por accidente) y se administran en
 *     sus propias pantallas del módulo.
 */
class Promocion
{
    private $conn;

    const TIPOS = ['descuento_volumen', 'dos_por_uno', 'happy_hour', 'cupon', 'campana_temporal'];

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ------------------------------------------------------------------ tipos

    public static function tipos()
    {
        return [
            'descuento_volumen' => [
                'label' => 'Descuento por porcentaje',
                'ayuda' => 'Descuento % sobre el precio de venta. Es la única promo que el punto de venta aplica automáticamente.',
                'usa_porcentaje' => true,
            ],
            'dos_por_uno' => [
                'label' => '2x1 (lleve 2, pague 1)',
                'ayuda' => 'Promoción 2x1 sobre los productos elegidos. Se administra aquí; el punto de venta no la cobra automáticamente.',
                'usa_porcentaje' => false,
            ],
            'happy_hour' => [
                'label' => 'Happy hour',
                'ayuda' => '% de descuento dentro de un rango de horas del día.',
                'usa_porcentaje' => true,
            ],
            'cupon' => [
                'label' => 'Cupón de descuento',
                'ayuda' => 'Código de cupón que se entrega a clientes. Se administra aquí; el punto de venta no lo aplica automáticamente.',
                'usa_porcentaje' => false,
            ],
            'campana_temporal' => [
                'label' => 'Campaña temporal',
                'ayuda' => '% de descuento por un rango de fechas (semana del pan, temporada, etc.).',
                'usa_porcentaje' => true,
            ],
        ];
    }

    public static function tipoTexto($tipo)
    {
        $tipos = self::tipos();

        return $tipos[$tipo]['label'] ?? $tipo;
    }

    public static function usaPorcentaje($tipo)
    {
        $tipos = self::tipos();

        return (bool) ($tipos[$tipo]['usa_porcentaje'] ?? false);
    }

    // ---------------------------------------------------------------- lectura

    public function getAll()
    {
        $sql = "SELECT pr.*,
                       (SELECT COUNT(*) FROM promotion_product pp WHERE pp.promotion_id = pr.id) AS product_count,
                       (SELECT COUNT(*) FROM cupones c WHERE c.promotion_id = pr.id) AS coupons_count,
                       (SELECT c.code FROM cupones c WHERE c.promotion_id = pr.id AND c.status = 'active' ORDER BY c.id DESC LIMIT 1) AS first_code
                  FROM promociones pr
                 ORDER BY pr.start_date DESC, pr.id DESC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $sql = "SELECT pr.*,
                       (SELECT COUNT(*) FROM promotion_product pp WHERE pp.promotion_id = pr.id) AS product_count,
                       (SELECT COUNT(*) FROM cupones c WHERE c.promotion_id = pr.id) AS coupons_count
                  FROM promociones pr
                 WHERE pr.id = :id
                 LIMIT 1;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /** Productos ligados a la promoción (id del producto + datos para mostrar). */
    public function getProductos($promotionId)
    {
        $sql = "SELECT pp.product_id, pr.name, pr.sale_price, pr.image_url, pr.status, pr.stock, pr.min_stock
                  FROM promotion_product pp
                  JOIN productos pr ON pr.id = pp.product_id
                 WHERE pp.promotion_id = :id
                 ORDER BY pr.name ASC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $promotionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------- escritura

    public function create($name, $tipo, $discountPercentage, $startDate, $endDate, $startTime, $endTime, $status, array $productIds)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "INSERT INTO promociones
                    (name, promotion_type, discount_percentage, start_date, end_date, start_time, end_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, trim($name));
            $stmt->bindValue(2, $tipo);
            $stmt->bindValue(3, self::usaPorcentaje($tipo) ? $discountPercentage : null);
            $stmt->bindValue(4, $startDate);
            $stmt->bindValue(5, $endDate);
            $stmt->bindValue(6, $startTime !== '' ? $startTime : null);
            $stmt->bindValue(7, $endTime !== '' ? $endTime : null);
            $stmt->bindValue(8, $status);
            $stmt->execute();

            $promotionId = (int) $this->conn->lastInsertId();
            $this->ligarProductos($promotionId, $productIds);

            $this->conn->commit();

            return $promotionId;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    public function update($id, $name, $tipo, $discountPercentage, $startDate, $endDate, $startTime, $endTime, $status, array $productIds)
    {
        try {
            $this->conn->beginTransaction();

            $sql = "UPDATE promociones
                       SET name = ?, promotion_type = ?, discount_percentage = ?,
                           start_date = ?, end_date = ?, start_time = ?, end_time = ?, status = ?
                     WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, trim($name));
            $stmt->bindValue(2, $tipo);
            $stmt->bindValue(3, self::usaPorcentaje($tipo) ? $discountPercentage : null);
            $stmt->bindValue(4, $startDate);
            $stmt->bindValue(5, $endDate);
            $stmt->bindValue(6, $startTime !== '' ? $startTime : null);
            $stmt->bindValue(7, $endTime !== '' ? $endTime : null);
            $stmt->bindValue(8, $status);
            $stmt->bindValue(9, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM promotion_product WHERE promotion_id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->ligarProductos($id, $productIds);

            $this->conn->commit();

            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();

            return false;
        }
    }

    public function toggleStatus($id)
    {
        $sql = "UPDATE promociones SET status = IF(status = 'active', 'inactive', 'active') WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Borra la promoción. Se bloquea si tiene cupones (se pierde el control de
     * usos) y las FK de ventas lo impiden si ya se usó. No hay NULL de ventas
     * que arrastrar: promotion_product se borra, y si un `ventas` referencia la
     * promo la BD lo rechaza con RESTRICT.
     */
    public function delete($id)
    {
        $antes = $this->getById($id);

        if (!$antes || (int) $antes['coupons_count'] > 0) {
            return false;
        }

        try {
            $this->conn->beginTransaction();

            $stmt = $this->conn->prepare("DELETE FROM promotion_product WHERE promotion_id = ?");
            $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $this->conn->prepare("DELETE FROM promociones WHERE id = ?");
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

    // ----------------------------------------------------------------- cupones

    public function cuponesDePromocion($promotionId)
    {
        $sql = "SELECT c.id, c.code, c.max_uses, c.current_uses, c.status, c.client_id,
                       c.client_id IS NOT NULL AS tiene_cliente,
                       (c.client_id IS NOT NULL AND cu.client_type = 'empresa'
                         AND trim(COALESCE(cu.company_name, '')) <> '') AS es_empresa,
                       cu.company_name, cu.name AS client_name, cu.last_name AS client_last_name
                  FROM cupones c
                  LEFT JOIN clients cu ON cu.id = c.client_id
                 WHERE c.promotion_id = :id
                 ORDER BY c.id DESC;";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', (int) $promotionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Cliente (solo id) de un cupón. */
    public function generarCodigo($len = 8)
    {
        // Códigos legibles, sin ambigüedad visual (nada de 0/O, 1/I/l).
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $codigo = '';

        for ($i = 0; $i < $len; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $codigo;
    }

    /**
     * Genera un cupón. Devuelve el id nuevo o false (código duplicado / error).
     */
    public function crearCupon($promotionId, $code, $clientId, $maxUses)
    {
        try {
            $sql = "INSERT INTO cupones (promotion_id, client_id, code, max_uses) VALUES (?, ?, ?, ?)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(1, (int) $promotionId, PDO::PARAM_INT);
            $stmt->bindValue(2, $clientId > 0 ? $clientId : null, PDO::PARAM_INT);
            $stmt->bindValue(3, mb_strtoupper(trim($code)));
            $stmt->bindValue(4, max(1, (int) $maxUses), PDO::PARAM_INT);
            $stmt->execute();

            return (int) $this->conn->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function eliminarCupon($promotionId, $cuponId)
    {
        $sql = "DELETE FROM cupones WHERE id = ? AND promotion_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(1, (int) $cuponId, PDO::PARAM_INT);
        $stmt->bindValue(2, (int) $promotionId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    // ---------------------------------------------------------------- helpers

    private function ligarProductos($promotionId, array $productIds)
    {
        $sql = "INSERT INTO promotion_product (promotion_id, product_id) VALUES (?, ?)";
        $stmt = $this->conn->prepare($sql);

        foreach (array_unique(array_map('intval', $productIds)) as $productId) {
            $stmt->bindValue(1, (int) $promotionId, PDO::PARAM_INT);
            $stmt->bindValue(2, (int) $productId, PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}