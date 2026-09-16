-- ============================================================================
-- Sistema de Gestión de Ventas para Panadería (tiendita)
-- Adaptado a MySQL 8.0 a partir del diseño en database.md
-- Nombres de tablas/columnas en inglés (traducidos del español)
-- Los .sql en /docker-entrypoint-initdb.d se ejecutan en el PRIMER arranque.
-- Para reaplicar: docker compose down -v && docker compose up --build
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS
    receipt_details, merchandise_receipts, purchase_order_details, purchase_orders,
    supplier_price_history, proveedores,
    sale_payments, sale_details, ventas, cash_register_movements, caja,
    cupones, promotion_product, promociones,
    email_notifications, order_tickets, order_status_history, order_payments,
    order_details, pedidos,
    product_inventory_movements, ingredient_inventory_movements,
    production_waste, production_batches, recipe_ingredients, recetas, ingredientes,
    productos, categorias,
    client_segment, client_segments, clients,
    sales_commissions, performance_reviews, attendances, shifts, empleados,
    roles, notificaciones, settings;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- TABLAS "PADRE" (sin dependencias)
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    supplier_type VARCHAR(60),
    contact VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(150),
    address VARCHAR(255),
    payment_terms VARCHAR(150),
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE client_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255)
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    description VARCHAR(255),
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE recetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    batch_yield DECIMAL(10,2) NOT NULL,
    prep_time_min INT,
    bake_time_min INT,
    instructions TEXT
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. CLIENTES
-- ----------------------------------------------------------------------------
CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(150) UNIQUE,
    address VARCHAR(255),
    birth_date DATE,
    registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    INDEX idx_clients_name (last_name, name)
) ENGINE=InnoDB;

CREATE TABLE client_segment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    segment_id INT NOT NULL,
    UNIQUE KEY uq_client_segment (client_id, segment_id),
    CONSTRAINT fk_cs_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_cs_segment FOREIGN KEY (segment_id) REFERENCES client_segments(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 2. EMPLEADOS / RRHH
-- ----------------------------------------------------------------------------
CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    id_document VARCHAR(30) NOT NULL UNIQUE,
    username VARCHAR(50) UNIQUE,
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255),
    role_id INT,
    phone VARCHAR(20),
    address VARCHAR(255),
    birth_date DATE,
    hire_date DATE NOT NULL,
    position VARCHAR(80) NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_empleados_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT chk_empleados_login CHECK (
        (role_id IS NULL AND username IS NULL AND email IS NULL AND password_hash IS NULL)
        OR (role_id IS NOT NULL AND username IS NOT NULL AND email IS NOT NULL AND password_hash IS NOT NULL)
    )
) ENGINE=InnoDB;

CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    shift_type VARCHAR(50),
    CONSTRAINT fk_shifts_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_shifts_emp_date (employee_id, work_date)
) ENGINE=InnoDB;

CREATE TABLE attendances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME,
    check_out TIME,
    state VARCHAR(30) NOT NULL DEFAULT 'presente',
    CONSTRAINT fk_attendances_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_attendances_emp_date (employee_id, attendance_date)
) ENGINE=InnoDB;

CREATE TABLE performance_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    review_date DATE NOT NULL,
    score DECIMAL(3,1) NOT NULL,
    comments TEXT,
    CONSTRAINT fk_reviews_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 5. INGREDIENTES (depende de proveedores) + PRODUCCIÓN
-- ----------------------------------------------------------------------------
CREATE TABLE ingredientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    unit_of_measure VARCHAR(20) NOT NULL,
    current_stock DECIMAL(10,3) NOT NULL DEFAULT 0,
    minimum_stock DECIMAL(10,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(10,4) NOT NULL,
    main_supplier_id INT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_ingredients_supplier FOREIGN KEY (main_supplier_id) REFERENCES proveedores(id),
    INDEX idx_ingredients_stock (current_stock)
) ENGINE=InnoDB;

CREATE TABLE recipe_ingredients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL,
    UNIQUE KEY uq_recipe_ingredient (recipe_id, ingredient_id),
    CONSTRAINT fk_ri_recipe FOREIGN KEY (recipe_id) REFERENCES recetas(id),
    CONSTRAINT fk_ri_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id)
) ENGINE=InnoDB;

CREATE TABLE production_batches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipe_id INT NOT NULL,
    assigned_employee_id INT NOT NULL,
    batch_date DATE NOT NULL,
    batch_count INT NOT NULL,
    prep_start_time TIME,
    rest_start_time TIME,
    bake_start_time TIME,
    bake_end_time TIME,
    state ENUM('planificada','en_reposo','horneando','finalizada','cancelada') NOT NULL DEFAULT 'planificada',
    observations TEXT,
    CONSTRAINT fk_batches_recipe FOREIGN KEY (recipe_id) REFERENCES recetas(id),
    CONSTRAINT fk_batches_employee FOREIGN KEY (assigned_employee_id) REFERENCES empleados(id),
    INDEX idx_batches_date (batch_date)
) ENGINE=InnoDB;

CREATE TABLE production_waste (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    waste_date DATE NOT NULL,
    CONSTRAINT fk_waste_batch FOREIGN KEY (batch_id) REFERENCES production_batches(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 4. PRODUCTOS (depende de categorias y recetas)
-- ----------------------------------------------------------------------------
CREATE TABLE productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    recipe_id INT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT,
    sale_price DECIMAL(10,2) NOT NULL,
    production_cost DECIMAL(10,2) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 0,
    image_url VARCHAR(255),
    barcode VARCHAR(50),
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categorias(id),
    CONSTRAINT fk_products_recipe FOREIGN KEY (recipe_id) REFERENCES recetas(id),
    INDEX idx_products_category (category_id),
    UNIQUE KEY idx_products_barcode (barcode)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. INVENTARIO (depende de ingredientes, empleados, productos)
-- ----------------------------------------------------------------------------
CREATE TABLE ingredient_inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    movement_type ENUM('entrada','salida','ajuste','merma') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(255),
    employee_id INT NOT NULL,
    reference VARCHAR(100),
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_iim_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id),
    CONSTRAINT fk_iim_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_iim_date (movement_date)
) ENGINE=InnoDB;

CREATE TABLE product_inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('entrada','salida','ajuste','merma') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(255),
    employee_id INT NOT NULL,
    reference VARCHAR(100),
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pim_product FOREIGN KEY (product_id) REFERENCES productos(id),
    CONSTRAINT fk_pim_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_pim_date (movement_date)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 7. COMPRAS (depende de proveedores, ingredientes, empleados)
-- ----------------------------------------------------------------------------
CREATE TABLE supplier_price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    price DECIMAL(10,4) NOT NULL,
    price_date DATE NOT NULL,
    CONSTRAINT fk_sph_supplier FOREIGN KEY (supplier_id) REFERENCES proveedores(id),
    CONSTRAINT fk_sph_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id),
    INDEX idx_sph_price (supplier_id, ingredient_id, price_date)
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    employee_id INT NOT NULL,
    order_date DATE NOT NULL,
    estimated_delivery_date DATE,
    state ENUM('pendiente','parcial','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES proveedores(id),
    CONSTRAINT fk_po_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_po_state (state)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_price DECIMAL(10,4) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_pod_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_pod_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id)
) ENGINE=InnoDB;

CREATE TABLE merchandise_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT NOT NULL,
    employee_id INT NOT NULL,
    receipt_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observations TEXT,
    CONSTRAINT fk_receipts_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_receipts_employee FOREIGN KEY (employee_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

CREATE TABLE receipt_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    receipt_id INT NOT NULL,
    ingredient_id INT NOT NULL,
    expected_quantity DECIMAL(10,3) NOT NULL,
    received_quantity DECIMAL(10,3) NOT NULL,
    CONSTRAINT fk_rd_receipt FOREIGN KEY (receipt_id) REFERENCES merchandise_receipts(id),
    CONSTRAINT fk_rd_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 9. PROMOCIONES (depende de productos, clients)
-- ----------------------------------------------------------------------------
CREATE TABLE promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    promotion_type ENUM('descuento_volumen','dos_por_uno','happy_hour','cupon','campana_temporal') NOT NULL,
    discount_percentage DECIMAL(5,2),
    discount_amount DECIMAL(10,2),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    INDEX idx_promotions_dates (start_date, end_date),
    INDEX idx_promotions_status (status)
) ENGINE=InnoDB;

CREATE TABLE promotion_product (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    product_id INT NOT NULL,
    UNIQUE KEY uq_promotion_product (promotion_id, product_id),
    CONSTRAINT fk_pp_promotion FOREIGN KEY (promotion_id) REFERENCES promociones(id),
    CONSTRAINT fk_pp_product FOREIGN KEY (product_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE cupones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NOT NULL,
    client_id INT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    max_uses INT NOT NULL DEFAULT 1,
    current_uses INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_coupons_promotion FOREIGN KEY (promotion_id) REFERENCES promociones(id),
    CONSTRAINT fk_coupons_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 10. CAJA (depende de empleados)
-- ----------------------------------------------------------------------------
CREATE TABLE caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opening_employee_id INT NOT NULL,
    closing_employee_id INT NULL,
    cash_date DATE NOT NULL,
    opening_time TIME NOT NULL,
    initial_amount DECIMAL(10,2) NOT NULL,
    closing_time TIME,
    system_final_amount DECIMAL(10,2),
    physical_final_amount DECIMAL(10,2),
    difference DECIMAL(10,2),
    state ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
    CONSTRAINT fk_cash_opening_employee FOREIGN KEY (opening_employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_cash_closing_employee FOREIGN KEY (closing_employee_id) REFERENCES empleados(id),
    INDEX idx_cash_state (state),
    INDEX idx_cash_date (cash_date)
) ENGINE=InnoDB;

CREATE TABLE cash_register_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NOT NULL,
    employee_id INT NOT NULL,
    movement_type ENUM('ingreso_extra','retiro') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_register FOREIGN KEY (cash_register_id) REFERENCES caja(id),
    CONSTRAINT fk_crm_employee FOREIGN KEY (employee_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 11. VENTAS (depende de caja, clients, empleados, promociones)
-- ----------------------------------------------------------------------------
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NULL,
    client_id INT NULL,
    employee_id INT NULL,
    promotion_id INT NULL,
    sale_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    subtotal DECIMAL(10,2) NOT NULL,
    total_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    state ENUM('completada','anulada') NOT NULL DEFAULT 'completada',
    CONSTRAINT fk_sales_cash FOREIGN KEY (cash_register_id) REFERENCES caja(id),
    CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_sales_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_sales_promotion FOREIGN KEY (promotion_id) REFERENCES promociones(id),
    INDEX idx_sales_date (sale_date),
    INDEX idx_sales_state (state)
) ENGINE=InnoDB;

CREATE TABLE sale_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_sd_sale FOREIGN KEY (sale_id) REFERENCES ventas(id),
    CONSTRAINT fk_sd_product FOREIGN KEY (product_id) REFERENCES productos(id)
) ENGINE=InnoDB;

-- Pagos por venta (permite pagos mixtos: mitad efectivo, mitad tarjeta, etc.)
CREATE TABLE sale_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    INDEX idx_fp_sale (sale_id),
    CONSTRAINT fk_fp_sale FOREIGN KEY (sale_id) REFERENCES ventas(id)
) ENGINE=InnoDB;

-- comisiones depende de ventas
CREATE TABLE sales_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    sale_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    commission_date DATE NOT NULL,
    CONSTRAINT fk_commissions_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_commissions_sale FOREIGN KEY (sale_id) REFERENCES ventas(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 8. PEDIDOS PERSONALIZADOS (depende de clients, empleados, productos)
-- ----------------------------------------------------------------------------
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    recorded_by_employee_id INT NOT NULL,
    order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivery_date DATE NOT NULL,
    state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
    rejection_reason VARCHAR(255),
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT,
    CONSTRAINT fk_orders_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_orders_employee FOREIGN KEY (recorded_by_employee_id) REFERENCES empleados(id),
    INDEX idx_orders_state (state),
    INDEX idx_orders_date (order_date)
) ENGINE=InnoDB;

CREATE TABLE order_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    personalized_description VARCHAR(255),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_od_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_od_product FOREIGN KEY (product_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE order_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    employee_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    payment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_op_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_op_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    INDEX idx_op_date (payment_date)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    employee_id INT NOT NULL,
    previous_state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NULL,
    new_state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NOT NULL,
    comment VARCHAR(255),
    change_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_osh_employee FOREIGN KEY (employee_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

CREATE TABLE order_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    ticket_code VARCHAR(30) NOT NULL UNIQUE,
    ticket_type ENUM('confirmacion','entrega','rechazo') NOT NULL,
    document_url VARCHAR(255),
    generation_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_order FOREIGN KEY (order_id) REFERENCES pedidos(id)
) ENGINE=InnoDB;

CREATE TABLE email_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NULL,
    client_id INT NOT NULL,
    notification_type ENUM('pedido_aprobado','pedido_entregado','pedido_rechazado') NOT NULL,
    recipient_email VARCHAR(150) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    send_state ENUM('pendiente','enviado','fallido') NOT NULL DEFAULT 'pendiente',
    attempts INT NOT NULL DEFAULT 0,
    sent_date TIMESTAMP NULL,
    CONSTRAINT fk_en_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_en_client FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 12. NOTIFICACIONES INTERNAS (depende de empleados)
-- ----------------------------------------------------------------------------
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_employee_id INT NULL,
    notification_type VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_type VARCHAR(60),
    reference_id INT,
    readed BOOLEAN NOT NULL DEFAULT FALSE,
    creation_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_employee FOREIGN KEY (destination_employee_id) REFERENCES empleados(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 13. CONFIGURACIÓN DEL SISTEMA (clave/valor)
-- ----------------------------------------------------------------------------
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(60) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- SEED DATA (datos de ejemplo)
-- ----------------------------------------------------------------------------
INSERT INTO roles (name, description) VALUES
('administrador', 'Acceso total al sistema'),
('cajero', 'Gestiona ventas y caja'),
('panadero', 'Gestiona producción');

INSERT INTO proveedores (name, supplier_type, contact, phone, email, payment_terms) VALUES
('Harinera Central', 'Harinera', 'María López', '2222-1111', 'ventas@harineracentral.com', '30 días'),
('Lácteos Don Pepe', 'Lácteos', 'Pedro Martínez', '2333-2222', 'info@lacteosdonpepe.com', 'Contado');

INSERT INTO ingredientes (name, unit_of_measure, current_stock, minimum_stock, unit_cost, main_supplier_id) VALUES
('Harina de trigo', 'kg', 50, 20, 1.20, 1),
('Levadura', 'kg', 10, 5, 3.50, 1),
('Mantequilla', 'kg', 15, 5, 4.00, 2),
('Azúcar', 'kg', 20, 10, 0.90, NULL);

INSERT INTO recetas (name, batch_yield, prep_time_min, bake_time_min, instructions) VALUES
('Receta Pan Tradicional', 20, 90, 30, 'Mezclar ingredientes, amasar, reposar y hornear.'),
('Receta Pan Dulce', 15, 120, 25, 'Mezclar, amasar con azúcar y mantequilla, hornear.');

INSERT INTO recipe_ingredients (recipe_id, ingredient_id, quantity, unit_of_measure) VALUES
(1, 1, 10.0, 'kg'),
(1, 2, 0.5, 'kg'),
(2, 1, 8.0, 'kg'),
(2, 3, 2.0, 'kg'),
(2, 4, 3.0, 'kg');

INSERT INTO categorias (name, description, display_order) VALUES
('Panes dulces', 'Panes con azúcar y rellenos', 1),
('Panes salados', 'Panes con queso y embutidos', 2),
('Repostería', 'Pasteles y postres', 3);

INSERT INTO productos (category_id, recipe_id, name, description, sale_price, production_cost, stock, min_stock, image_url, barcode) VALUES
(1, 2, 'Pan Dulce Clásico', 'Pan esponjoso con azúcar y canela', 1.50, 0.60, 150, 20, NULL, '7701234567890'),
(1, 2, 'Concha', 'Pan dulce con cobertura crujiente', 2.00, 0.80, 10, 25, NULL, NULL),
(2, 1, 'Pan de Queso', 'Pan salado relleno de queso', 2.50, 1.00, 80, 15, NULL, NULL),
(3, NULL, 'Pastel de Chocolate', 'Pastel de chocolate con ganache', 35.00, 18.00, 3, 5, NULL, NULL);

INSERT INTO client_segments (name, description) VALUES
('Frecuentes', 'Clientes que compran al menos una vez por semana'),
('VIP', 'Clientes de alto valor');

INSERT INTO clients (name, last_name, phone, email, address) VALUES
('Ana', 'García', '5555-0001', 'ana.garcia@mail.com', 'Zona 1, Ciudad'),
('Luis', 'Pérez', '5555-0002', 'luis.perez@mail.com', 'Zona 10, Ciudad');

INSERT INTO client_segment (client_id, segment_id) VALUES (1, 1), (2, 2);

-- ----------------------------------------------------------------------------
-- DASHBOARD SEED (empleados, promociones, caja, ventas, pedidos)
-- ----------------------------------------------------------------------------
-- Los empleados con credenciales de acceso (role_id/username/email/password_hash)
INSERT INTO empleados (id, name, last_name, id_document, username, email, password_hash, role_id, phone, address, birth_date, hire_date, position, base_salary) VALUES
(1, 'Carlos', 'Ramírez', 'PAN-0001', 'ramirez', 'carlos.ramirez@bakery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, '5555-0101', 'Zona 5, Ciudad', '1990-03-15', '2023-05-01', 'Panadero', 1200.00),
(2, 'María', 'González', 'CAJ-0001', 'cajero', 'maria.gonzalez@bakery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, '5555-0102', 'Zona 3, Ciudad', '1995-07-22', '2023-06-15', 'Cajero', 1000.00),
(3, 'BRIAN JOSUE CHAVEZ RECINOS', 'Administrador', 'ADM-0001', 'BRIAN JOSUE CHAVEZ RECINOS', 'admin@ignis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, NULL, '2023-01-01', 'Administrador', 1500.00);

INSERT INTO promociones (id, name, promotion_type, discount_percentage, start_date, end_date, status) VALUES
(1, '2x1 Pan de Queso', 'dos_por_uno', 50.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active'),
(2, '10% Pastel de Chocolate', 'campana_temporal', 10.00, DATE_SUB(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'active');

-- Promociones aplicadas a productos (descuento que muestra el POS en las cards)
INSERT INTO promotion_product (promotion_id, product_id) VALUES (1, 3), (2, 4);

-- Caja de los últimos 14 días (la de hoy queda abierta)
INSERT INTO caja (opening_employee_id, closing_employee_id, cash_date, opening_time, initial_amount, closing_time, system_final_amount, physical_final_amount, difference, state) VALUES
(2, 2, DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:00:00', 200.00, '21:00:00', 258.00, 258.00, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:00:00', 250.00, '21:00:00', 332.50, 332.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:00:00', 220.00, '21:00:00', 300.50, 300.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:00:00', 280.00, '21:00:00', 377.50, 377.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:00:00', 240.00, '21:00:00', 342.00, 342.00, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:00:00', 260.00, '21:00:00', 380.00, 380.00, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:00:00', 230.00, '21:00:00', 362.50, 362.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:00:00', 300.00, '21:00:00', 445.50, 445.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:00:00', 270.00, '21:00:00', 431.50, 431.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:00:00', 290.00, '21:00:00', 477.00, 477.00, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', 310.00, '21:00:00', 506.00, 506.00, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', 320.00, '21:00:00', 564.50, 564.50, 0.00, 'cerrada'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', 330.00, '21:00:00', 565.00, 565.00, 0.00, 'cerrada'),
(2, NULL, CURDATE(), '08:00:00', 340.00, NULL, 310.50, NULL, NULL, 'abierta');

-- Ventas (3 por día, últimos 14 días). caja id 1..13 = días previos, caja id 14 = hoy.
INSERT INTO ventas (id, cash_register_id, client_id, employee_id, promotion_id, sale_date, subtotal, total_discount, tax, total, payment_method, state) VALUES
(1, 1, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '08:30:00'), 17.50, 0, 0, 17.50, 'efectivo', 'completada'),
(2, 1, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '13:15:00'), 18.00, 0, 0, 18.00, 'tarjeta', 'completada'),
(3, 1, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 13 DAY), '18:45:00'), 22.50, 0, 0, 22.50, 'efectivo', 'completada'),
(4, 2, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '08:40:00'), 27.50, 0, 0, 27.50, 'efectivo', 'completada'),
(5, 2, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '13:30:00'), 28.00, 0, 0, 28.00, 'tarjeta', 'completada'),
(6, 2, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '18:20:00'), 27.00, 0, 0, 27.00, 'efectivo', 'completada'),
(7, 3, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '08:25:00'), 24.00, 0, 0, 24.00, 'efectivo', 'completada'),
(8, 3, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '13:45:00'), 24.00, 0, 0, 24.00, 'transferencia', 'completada'),
(9, 3, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 11 DAY), '18:50:00'), 32.50, 0, 0, 32.50, 'efectivo', 'completada'),
(10, 4, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '08:35:00'), 30.00, 0, 0, 30.00, 'efectivo', 'completada'),
(11, 4, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '13:10:00'), 30.00, 0, 0, 30.00, 'tarjeta', 'completada'),
(12, 4, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 10 DAY), '18:40:00'), 37.50, 0, 0, 37.50, 'efectivo', 'completada'),
(13, 5, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '08:20:00'), 34.00, 0, 0, 34.00, 'efectivo', 'completada'),
(14, 5, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '13:25:00'), 33.00, 0, 0, 33.00, 'tarjeta', 'completada'),
(15, 5, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '18:30:00'), 35.00, 0, 0, 35.00, 'efectivo', 'completada'),
(16, 6, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '08:50:00'), 42.50, 0, 0, 42.50, 'efectivo', 'completada'),
(17, 6, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '13:35:00'), 40.00, 0, 0, 40.00, 'tarjeta', 'completada'),
(18, 6, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '18:10:00'), 37.50, 0, 0, 37.50, 'efectivo', 'completada'),
(19, 7, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '08:15:00'), 39.00, 0, 0, 39.00, 'efectivo', 'completada'),
(20, 7, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '13:40:00'), 47.50, 0, 0, 47.50, 'tarjeta', 'completada'),
(21, 7, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '18:55:00'), 46.00, 0, 0, 46.00, 'efectivo', 'completada'),
(22, 8, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '08:30:00'), 48.00, 0, 0, 48.00, 'efectivo', 'completada'),
(23, 8, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '13:20:00'), 45.00, 0, 0, 45.00, 'tarjeta', 'completada'),
(24, 8, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '18:35:00'), 52.50, 0, 0, 52.50, 'efectivo', 'completada'),
(25, 9, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '08:45:00'), 48.00, 0, 0, 48.00, 'efectivo', 'completada'),
(26, 9, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '13:05:00'), 56.00, 0, 0, 56.00, 'tarjeta', 'completada'),
(27, 9, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 5 DAY), '18:25:00'), 57.50, 0, 0, 57.50, 'efectivo', 'completada'),
(28, 10, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:10:00'), 60.00, 0, 0, 60.00, 'efectivo', 'completada'),
(29, 10, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '13:50:00'), 70.00, 0, 0, 70.00, 'tarjeta', 'completada'),
(30, 10, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 4 DAY), '18:15:00'), 57.00, 0, 0, 57.00, 'efectivo', 'completada'),
(31, 11, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:55:00'), 66.00, 0, 0, 66.00, 'efectivo', 'completada'),
(32, 11, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '13:45:00'), 60.00, 0, 0, 60.00, 'tarjeta', 'completada'),
(33, 11, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 3 DAY), '18:40:00'), 70.00, 0, 0, 70.00, 'efectivo', 'completada'),
(34, 12, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:20:00'), 72.00, 0, 0, 72.00, 'efectivo', 'completada'),
(35, 12, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '13:15:00'), 67.50, 0, 0, 67.50, 'tarjeta', 'completada'),
(36, 12, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '18:45:00'), 105.00, 0, 0, 105.00, 'transferencia', 'completada'),
(37, 13, 1, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:30:00'), 80.00, 0, 0, 80.00, 'efectivo', 'completada'),
(38, 13, 2, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '13:20:00'), 80.00, 0, 0, 80.00, 'tarjeta', 'completada'),
(39, 13, NULL, 2, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 1 DAY), '18:50:00'), 75.00, 0, 0, 75.00, 'efectivo', 'completada'),
(40, 14, 1, 2, NULL, TIMESTAMP(CURDATE(), '08:30:00'), 88.00, 0, 0, 88.00, 'efectivo', 'completada'),
(41, 14, 2, 2, NULL, TIMESTAMP(CURDATE(), '13:15:00'), 82.50, 0, 0, 82.50, 'tarjeta', 'completada'),
(42, 14, NULL, 2, NULL, TIMESTAMP(CURDATE(), '18:45:00'), 140.00, 0, 0, 140.00, 'efectivo', 'completada');

INSERT INTO sale_details (sale_id, product_id, quantity, unit_price, discount, subtotal) VALUES
(1, 3, 7, 2.50, 0, 17.50),
(2, 2, 9, 2.00, 0, 18.00),
(3, 1, 15, 1.50, 0, 22.50),
(4, 3, 11, 2.50, 0, 27.50),
(5, 2, 14, 2.00, 0, 28.00),
(6, 1, 18, 1.50, 0, 27.00),
(7, 2, 12, 2.00, 0, 24.00),
(8, 1, 16, 1.50, 0, 24.00),
(9, 3, 13, 2.50, 0, 32.50),
(10, 2, 15, 2.00, 0, 30.00),
(11, 1, 20, 1.50, 0, 30.00),
(12, 3, 15, 2.50, 0, 37.50),
(13, 2, 17, 2.00, 0, 34.00),
(14, 1, 22, 1.50, 0, 33.00),
(15, 4, 1, 35.00, 0, 35.00),
(16, 3, 17, 2.50, 0, 42.50),
(17, 2, 20, 2.00, 0, 40.00),
(18, 1, 25, 1.50, 0, 37.50),
(19, 1, 26, 1.50, 0, 39.00),
(20, 3, 19, 2.50, 0, 47.50),
(21, 2, 23, 2.00, 0, 46.00),
(22, 2, 24, 2.00, 0, 48.00),
(23, 1, 30, 1.50, 0, 45.00),
(24, 3, 21, 2.50, 0, 52.50),
(25, 1, 32, 1.50, 0, 48.00),
(26, 2, 28, 2.00, 0, 56.00),
(27, 3, 23, 2.50, 0, 57.50),
(28, 2, 30, 2.00, 0, 60.00),
(29, 4, 2, 35.00, 0, 70.00),
(30, 1, 38, 1.50, 0, 57.00),
(31, 2, 33, 2.00, 0, 66.00),
(32, 1, 40, 1.50, 0, 60.00),
(33, 3, 28, 2.50, 0, 70.00),
(34, 2, 36, 2.00, 0, 72.00),
(35, 1, 45, 1.50, 0, 67.50),
(36, 4, 3, 35.00, 0, 105.00),
(37, 2, 40, 2.00, 0, 80.00),
(38, 3, 32, 2.50, 0, 80.00),
(39, 1, 50, 1.50, 0, 75.00),
(40, 2, 44, 2.00, 0, 88.00),
(41, 1, 55, 1.50, 0, 82.50),
(42, 4, 4, 35.00, 0, 140.00);

INSERT INTO pedidos (id, client_id, recorded_by_employee_id, order_date, delivery_date, state, rejection_reason, total, paid_amount, remaining_balance, notes) VALUES
(1, 1, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'pendiente', NULL, 350.00, 100.00, 250.00, 'Pastel de bodas para 50 personas'),
(2, 2, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), 'aprobado', NULL, 120.00, 120.00, 0.00, 'Pedido para cumpleaños'),
(3, 1, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'en_produccion', NULL, 210.00, 100.00, 110.00, 'Torta especial de chocolate'),
(4, 2, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), CURDATE(), 'listo', NULL, 45.00, 45.00, 0.00, 'Listo para recoger'),
(5, 1, 3, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'entregado', NULL, 80.00, 80.00, 0.00, 'Entregado a domicilio'),
(6, 2, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'rechazado', 'Cliente canceló el pedido', 100.00, 0.00, 0.00, 'Cancelado por el cliente');

INSERT INTO order_details (order_id, product_id, personalized_description, quantity, unit_price, subtotal) VALUES
(1, 4, 'Pastel de bodas', 1, 350.00, 350.00),
(2, 3, 'Panes surtidos', 48, 2.50, 120.00),
(3, 4, 'Torta de chocolate grande', 1, 210.00, 210.00),
(4, 3, 'Panes surtidos', 18, 2.50, 45.00),
(5, 3, 'Panes de queso', 32, 2.50, 80.00),
(6, 4, 'Pastel de chocolate', 1, 100.00, 100.00);

INSERT INTO notificaciones (destination_employee_id, notification_type, title, message, reference_type, reference_id) VALUES
(3, 'stock_bajo', 'Stock bajo', 'El producto "Concha" está por debajo del stock mínimo.', 'producto', 2),
(3, 'pedido_listo', 'Pedido listo', 'El pedido #4 está listo para recoger.', 'pedido', 4);

INSERT INTO settings (setting_key, setting_value) VALUES
('system_name', 'Panadería'),
('business_name', 'Panadería'),
('address', 'Av. Central #123, Centro'),
('phone', '5555-1234'),
('currency', '$'),
('tax_rate', '0'),
('ticket_footer', '¡Gracias por su compra!'),
('system_logo', ''),
('login_photo', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=80');
