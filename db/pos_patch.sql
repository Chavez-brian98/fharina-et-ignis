SET NAMES utf8mb4;

-- Pagos por venta (permite pagos mixtos: mitad efectivo, mitad tarjeta, etc.)
CREATE TABLE IF NOT EXISTS sale_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    INDEX idx_fp_sale (sale_id),
    CONSTRAINT fk_fp_sale FOREIGN KEY (sale_id) REFERENCES ventas(id)
) ENGINE=InnoDB;

-- El POS puede registrar ventas sin que exista aun un módulo de caja/empleados
ALTER TABLE ventas MODIFY cash_register_id INT NULL;
ALTER TABLE ventas MODIFY employee_id INT NULL;

-- Promoción demo vinculada a producto (descuento visible en las cards del POS)
INSERT INTO promociones (id, name, promotion_type, discount_percentage, start_date, end_date, status) VALUES
(2, '10% Pastel de Chocolate', 'campana_temporal', 10.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO promotion_product (promotion_id, product_id) VALUES (1, 3), (2, 4);