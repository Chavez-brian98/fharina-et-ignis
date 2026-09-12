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
    sale_details, ventas, cash_register_movements, caja,
    cupones, promotion_product, promociones,
    email_notifications, order_tickets, order_status_history, order_payments,
    order_details, pedidos,
    product_inventory_movements, ingredient_inventory_movements,
    production_waste, production_batches, recipe_ingredients, recetas, ingredientes,
    productos, categorias,
    client_segment, client_segments, clients,
    sales_commissions, performance_reviews, attendances, shifts, empleados,
    users, roles, notificaciones;
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
    phone VARCHAR(20),
    address VARCHAR(255),
    birth_date DATE,
    hire_date DATE NOT NULL,
    position VARCHAR(80) NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
-- 1. SEGURIDAD / USUARIOS (depende de empleados)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NULL,
    role_id INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_employee FOREIGN KEY (employee_id) REFERENCES empleados(id)
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
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categorias(id),
    CONSTRAINT fk_products_recipe FOREIGN KEY (recipe_id) REFERENCES recetas(id),
    INDEX idx_products_category (category_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 6. INVENTARIO (depende de ingredientes, users, productos)
-- ----------------------------------------------------------------------------
CREATE TABLE ingredient_inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ingredient_id INT NOT NULL,
    movement_type ENUM('entrada','salida','ajuste','merma') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(255),
    user_id INT NOT NULL,
    reference VARCHAR(100),
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_iim_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredientes(id),
    CONSTRAINT fk_iim_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_iim_date (movement_date)
) ENGINE=InnoDB;

CREATE TABLE product_inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('entrada','salida','ajuste','merma') NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    reason VARCHAR(255),
    user_id INT NOT NULL,
    reference VARCHAR(100),
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pim_product FOREIGN KEY (product_id) REFERENCES productos(id),
    CONSTRAINT fk_pim_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_pim_date (movement_date)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 7. COMPRAS (depende de proveedores, ingredientes, users)
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
    user_id INT NOT NULL,
    order_date DATE NOT NULL,
    estimated_delivery_date DATE,
    state ENUM('pendiente','parcial','recibida','cancelada') NOT NULL DEFAULT 'pendiente',
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES proveedores(id),
    CONSTRAINT fk_po_user FOREIGN KEY (user_id) REFERENCES users(id),
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
    user_id INT NOT NULL,
    receipt_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observations TEXT,
    CONSTRAINT fk_receipts_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
    CONSTRAINT fk_receipts_user FOREIGN KEY (user_id) REFERENCES users(id)
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
-- 10. CAJA (depende de users)
-- ----------------------------------------------------------------------------
CREATE TABLE caja (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opening_user_id INT NOT NULL,
    closing_user_id INT NULL,
    cash_date DATE NOT NULL,
    opening_time TIME NOT NULL,
    initial_amount DECIMAL(10,2) NOT NULL,
    closing_time TIME,
    system_final_amount DECIMAL(10,2),
    physical_final_amount DECIMAL(10,2),
    difference DECIMAL(10,2),
    state ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
    CONSTRAINT fk_cash_opening_user FOREIGN KEY (opening_user_id) REFERENCES users(id),
    CONSTRAINT fk_cash_closing_user FOREIGN KEY (closing_user_id) REFERENCES users(id),
    INDEX idx_cash_state (state),
    INDEX idx_cash_date (cash_date)
) ENGINE=InnoDB;

CREATE TABLE cash_register_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NOT NULL,
    user_id INT NOT NULL,
    movement_type ENUM('ingreso_extra','retiro') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    movement_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_crm_register FOREIGN KEY (cash_register_id) REFERENCES caja(id),
    CONSTRAINT fk_crm_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 11. VENTAS (depende de caja, clients, empleados, promociones)
-- ----------------------------------------------------------------------------
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cash_register_id INT NOT NULL,
    client_id INT NULL,
    employee_id INT NOT NULL,
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
-- 8. PEDIDOS PERSONALIZADOS (depende de clients, users, productos)
-- ----------------------------------------------------------------------------
CREATE TABLE pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    recorded_by_user_id INT NOT NULL,
    order_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivery_date DATE NOT NULL,
    state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
    rejection_reason VARCHAR(255),
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0,
    notes TEXT,
    CONSTRAINT fk_orders_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_orders_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id),
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
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    payment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_op_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_op_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_op_date (payment_date)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    previous_state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NULL,
    new_state ENUM('pendiente','aprobado','en_produccion','listo','entregado','rechazado','cancelado') NOT NULL,
    comment VARCHAR(255),
    change_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_osh_order FOREIGN KEY (order_id) REFERENCES pedidos(id),
    CONSTRAINT fk_osh_user FOREIGN KEY (user_id) REFERENCES users(id)
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
-- 12. NOTIFICACIONES INTERNAS (depende de users)
-- ----------------------------------------------------------------------------
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    destination_user_id INT NULL,
    notification_type VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    message VARCHAR(255) NOT NULL,
    reference_type VARCHAR(60),
    reference_id INT,
    readed BOOLEAN NOT NULL DEFAULT FALSE,
    creation_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (destination_user_id) REFERENCES users(id)
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

INSERT INTO productos (category_id, recipe_id, name, description, sale_price, production_cost, stock, min_stock, image_url) VALUES
(1, 2, 'Pan Dulce Clásico', 'Pan esponjoso con azúcar y canela', 1.50, 0.60, 150, 20, NULL),
(1, 2, 'Concha', 'Pan dulce con cobertura crujiente', 2.00, 0.80, 10, 25, NULL),
(2, 1, 'Pan de Queso', 'Pan salado relleno de queso', 2.50, 1.00, 80, 15, NULL),
(3, NULL, 'Pastel de Chocolate', 'Pastel de chocolate con ganache', 35.00, 18.00, 3, 5, NULL);

INSERT INTO client_segments (name, description) VALUES
('Frecuentes', 'Clientes que compran al menos una vez por semana'),
('VIP', 'Clientes de alto valor');

INSERT INTO clients (name, last_name, phone, email, address) VALUES
('Ana', 'García', '5555-0001', 'ana.garcia@mail.com', 'Zona 1, Ciudad'),
('Luis', 'Pérez', '5555-0002', 'luis.perez@mail.com', 'Zona 10, Ciudad');

INSERT INTO client_segment (client_id, segment_id) VALUES (1, 1), (2, 2);
