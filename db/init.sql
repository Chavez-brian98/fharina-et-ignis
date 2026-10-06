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
    product_images,
    client_segment, client_segments, clients,
    sales_commissions, performance_reviews, empleado_faces, attendances, shifts, empleados,
    notificaciones, settings;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- TABLAS "PADRE" (sin dependencias)
-- ----------------------------------------------------------------------------
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    tax_id VARCHAR(50),
    supplier_type VARCHAR(60),
    contact VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(150),
    address VARCHAR(255),
    supplies VARCHAR(255),
    availability_days VARCHAR(60),
    payment_terms VARCHAR(150),
    notes TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_proveedores_name (name),
    INDEX idx_proveedores_status (status)
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
    id_document VARCHAR(30) UNIQUE,
    client_type ENUM('persona','empresa') NOT NULL DEFAULT 'persona',
    company_name VARCHAR(150),
    phone VARCHAR(20),
    email VARCHAR(150) UNIQUE,
    address VARCHAR(255),
    profile_photo VARCHAR(500),
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
-- Catálogo de roles administrable (módulo Roles y Permisos). El rol del
-- empleado es una FK; los permisos viven en role_permissions (4 acciones por
-- módulo) y employee_permissions (excepciones por empleado que ganan al rol).
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255),
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE empleados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    id_document VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(150) UNIQUE,
    password_hash VARCHAR(255),
    role_id INT NOT NULL,
    phone VARCHAR(20),
    address VARCHAR(255),
    profile_photo VARCHAR(500),
    -- Token del codigo QR personal de asistencia. Se genera bajo demanda
    -- (Employee::qrToken) y no es una credencial de inicio de sesion.
    qr_token VARCHAR(64) NULL,
    birth_date DATE,
    hire_date DATE NOT NULL,
    base_salary DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_empleados_login CHECK (
        (email IS NULL AND password_hash IS NULL)
        OR (email IS NOT NULL AND password_hash IS NOT NULL)
    ),
    CONSTRAINT fk_empleados_role FOREIGN KEY (role_id) REFERENCES roles(id),
    UNIQUE KEY uq_empleados_qr (qr_token)
) ENGINE=InnoDB;

-- Permisos por rol: una fila por módulo con las 4 acciones.
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_role_module (role_id, module),
    INDEX idx_rp_role (role_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Excepciones por empleado: si existe fila para un módulo, esa fila define
-- por completo sus permisos en ese módulo (puede conceder o quitar acceso).
CREATE TABLE employee_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    module VARCHAR(50) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_emp_module (employee_id, module),
    INDEX idx_ep_emp (employee_id),
    CONSTRAINT fk_ep_emp FOREIGN KEY (employee_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    work_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    shift_type VARCHAR(50),
    notes VARCHAR(255),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_shifts_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    UNIQUE KEY uq_shift_emp_date (employee_id, work_date),
    INDEX idx_shifts_date (work_date)
) ENGINE=InnoDB;

-- Registro de entrada/salida. check_in/check_out guardan la hora; el metodo
-- (manual/qr/rostro) deja constancia de como se registro cada momento.
-- break_start/break_end modelan un descanso por jornada; para varios
-- descansos habria que pasar a una tabla attendance_breaks.
-- registered_by deja trazabilidad del ajuste manual hecho por un supervisor.
CREATE TABLE attendances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in TIME NULL,
    check_out TIME NULL,
    break_start TIME NULL,
    break_end TIME NULL,
    state VARCHAR(30) NOT NULL DEFAULT 'presente',
    check_in_method ENUM('manual','qr','rostro') NULL,
    check_out_method ENUM('manual','qr','rostro') NULL,
    notes VARCHAR(255),
    registered_by INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendances_employee FOREIGN KEY (employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_attendances_registro FOREIGN KEY (registered_by) REFERENCES empleados(id) ON DELETE SET NULL,
    UNIQUE KEY uq_attendance_emp_date (employee_id, attendance_date),
    INDEX idx_attendances_date (attendance_date)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ROSTROS (reconocimiento facial del quiosco)
-- ----------------------------------------------------------------------------
-- descriptor = vector de 128 flotantes que FaceRecognitionNet produce en el
-- navegador; se guarda como JSON. NO es una foto: es la medicion matematica
-- que el modelo usa para comparar, y por eso alcanza con uno por empleado.
-- 'model' queda escrito para poder invalidar los rostros si se cambia la red
-- (los embeddings de redes distintas no son comparables entre si).
CREATE TABLE empleado_faces (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    descriptor JSON NOT NULL,
    model VARCHAR(80) NOT NULL,
    quality DECIMAL(5,4) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_faces_employee FOREIGN KEY (employee_id) REFERENCES empleados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_face_employee (employee_id),
    INDEX idx_faces_model (model)
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
-- 4b. GALERÍA DE FOTOS DE PRODUCTOS (depende de productos)
-- ----------------------------------------------------------------------------
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES productos(id) ON DELETE CASCADE,
    INDEX idx_product_images_product (product_id)
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
    reopened_by INT NULL,
    reopen_reason VARCHAR(255) NULL,
    reopen_count INT NOT NULL DEFAULT 0,
    -- Vale 1 solo si la caja está abierta y NULL si está cerrada; con el UNIQUE de
    -- abajo MySQL rechaza (1062) una segunda caja abierta del mismo empleado.
    state_open INT GENERATED ALWAYS AS (IF(state = 'abierta', 1, NULL)) VIRTUAL,
    CONSTRAINT fk_cash_opening_employee FOREIGN KEY (opening_employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_cash_closing_employee FOREIGN KEY (closing_employee_id) REFERENCES empleados(id),
    CONSTRAINT fk_cash_reopened_by FOREIGN KEY (reopened_by) REFERENCES empleados(id),
    INDEX idx_cash_state (state),
    INDEX idx_cash_date (cash_date)
) ENGINE=InnoDB;

-- Un empleado no puede tener dos cajas abiertas a la vez (varias cajas cerradas
-- del mismo día sí se permiten: eso cubre el caso de los turnos).
ALTER TABLE caja
    ADD CONSTRAINT uq_cash_open_per_employee UNIQUE (opening_employee_id, state_open);

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
    -- Dirección de entrega del pedido. NULL = se recoge en tienda.
    delivery_address VARCHAR(255) NULL,
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
-- 14. MENSAJES DE CONTACTO DEL SITIO PÚBLICO
-- ----------------------------------------------------------------------------
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- 15. BITÁCORA (auditoría de acciones en el sistema)
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NULL,
    record_id INT UNSIGNED NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    description VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_table (table_name),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES empleados(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- SEED DATA (datos de ejemplo)
-- ----------------------------------------------------------------------------
INSERT INTO proveedores (name, tax_id, supplier_type, contact, phone, email, address, supplies, availability_days, payment_terms, notes) VALUES
('Harinera Central', '0614-2233345-6', 'Harinera', 'María López', '2222-1111', 'ventas@harineracentral.com', 'Av. Principal km 4, ruta al puerto', 'Harina de trigo, levadura, kraft', 'lun,mar,mie,jue,vie', '30 días', 'Entrega en la panadería antes de las 8:00 a.m.'),
('Lácteos Don Pepe', '0614-5567788-2', 'Lácteos', 'Pedro Martínez', '2333-2222', 'info@lacteosdonpepe.com', 'Carretera al puerto, km 2', 'Leche, mantequilla, queso crema', 'mar,jue,sab', 'Contado', 'Refrigeración propia: dejar en cámara fría.');

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
(1, 2, 'Pan Dulce Clásico', 'Pan esponjoso y suave con un toque de azúcar y canela, ideal para acompañar un café o un chocolate caliente', 1.50, 0.60, 150, 20, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=1200&q=80', '7701234567890'),
(1, 2, 'Concha', 'Nuestro clásico pan dulce con una cobertura crujiente de azúcar, horneado fresco cada mañana', 2.00, 0.80, 10, 25, 'https://images.unsplash.com/photo-1509365465985-25d11c17e812?auto=format&fit=crop&w=1200&q=80', NULL),
(2, 1, 'Pan de Queso', 'Pan salado recién horneado con un delicioso relleno de queso fundido por dentro', 2.50, 1.00, 80, 15, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=80', NULL),
(3, NULL, 'Pastel de Chocolate', 'Pastel de chocolate con ganache sedoso y capas esponjosas: el favorito para tus celebraciones', 35.00, 18.00, 3, 5, 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=1200&q=80', NULL);

-- Galería de fotos de ejemplo (URLs mientras no haya subida propia)
INSERT INTO product_images (product_id, image_url, sort_order) VALUES
(1, 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=1200&q=80', 1),
(1, 'https://images.unsplash.com/photo-1587248720327-8eb72564be1e?auto=format&fit=crop&w=1200&q=80', 2),
(2, 'https://images.unsplash.com/photo-1509365465985-25d11c17e812?auto=format&fit=crop&w=1200&q=80', 1),
(2, 'https://images.unsplash.com/photo-1483695028939-5bb13f8648b0?auto=format&fit=crop&w=1200&q=80', 2),
(3, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=80', 1),
(3, 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=1200&q=80', 2),
(4, 'https://images.unsplash.com/photo-1488477181946-6428a0291777?auto=format&fit=crop&w=1200&q=80', 1),
(4, 'https://images.unsplash.com/photo-1578985545062-69928b1d9587?auto=format&fit=crop&w=1200&q=80', 2);

INSERT INTO client_segments (name, description) VALUES
('Frecuentes', 'Clientes que compran al menos una vez por semana'),
('VIP', 'Clientes de alto valor');

INSERT INTO clients (name, last_name, id_document, client_type, company_name, phone, email, address) VALUES
('Ana', 'García', '01234567-8', 'persona', NULL, '5555-0001', 'ana.garcia@mail.com', 'Zona 1, Ciudad'),
('Luis', 'Pérez', '02345678-9', 'persona', NULL, '5555-0002', 'luis.perez@mail.com', 'Zona 10, Ciudad'),
('Panadería del Valle', '', '03678901-2', 'empresa', 'Panadería del Valle', '5555-0003', 'ventas@valle.com', 'Zona 4, Ciudad');

INSERT INTO client_segment (client_id, segment_id) VALUES (1, 1), (2, 2);

-- ----------------------------------------------------------------------------
-- ROLES Y PERMISOS SEED
-- ----------------------------------------------------------------------------
INSERT INTO roles (id, name, description, is_admin, status) VALUES
(1, 'administrador', 'Acceso total a todos los módulos y acciones.', 1, 'active'),
(2, 'sub_jefe', 'Supervisión de operaciones, catálogo y reportes (sin configuración).', 0, 'active'),
(3, 'cajero', 'Acceso al punto de venta y clientes.', 0, 'active'),
(4, 'mesero', 'Atención en salón: punto de venta, clientes y consulta de catálogo.', 0, 'active'),
(5, 'produccion', 'Producción e inventario: recetas, ingredientes y stock.', 0, 'active'),
(6, 'domiciliero', 'Entregas a domicilio y consulta de pedidos.', 0, 'active');

-- role_permissions: una fila por módulo y rol. Los módulos que no aparecen
-- quedan en "sin acceso" para ese rol (no hace falta fila con todo en 0).
-- (mod, view, create, edit, delete)
INSERT INTO role_permissions (role_id, module, can_view, can_create, can_edit, can_delete) VALUES
-- Sub jefe: casi todo, salvo configuración/roles/empleados.
(2, 'dashboard', 1, 0, 0, 0),
(2, 'pos', 1, 1, 0, 0),
(2, 'clients', 1, 1, 1, 0),
(2, 'cash_register', 1, 1, 1, 0),
(2, 'products', 1, 1, 1, 0),
(2, 'categories', 1, 1, 1, 0),
(2, 'inventory', 1, 0, 0, 0),
(2, 'production', 1, 0, 0, 0),
(2, 'suppliers', 1, 1, 1, 1),
(2, 'purchases', 1, 1, 1, 1),
(2, 'orders', 1, 1, 1, 1),
(2, 'promotions', 1, 1, 1, 1),
(2, 'reports', 1, 0, 0, 0),
(2, 'statistics', 1, 0, 0, 0),
-- Horarios: sub jefe asigna turnos y corrige la asistencia del equipo.
-- ('attendance' es 'always' y no necesita fila propia: todos la tienen.)
(2, 'schedules', 1, 1, 1, 1),
-- Registros del Quiosco: solo lo mira el sub jefe y el administrador
-- (is_admin hace bypass, no necesita fila). Lectura pura.
(2, 'kiosk_log', 1, 0, 0, 0),
-- Cajero: solo POS y clientes (+ su propio perfil y dashboard).
(3, 'dashboard', 1, 0, 0, 0),
(3, 'pos', 1, 1, 0, 0),
(3, 'clients', 1, 1, 1, 0),
(3, 'cash_register', 1, 1, 0, 0),
(3, 'profile', 1, 0, 1, 0),
-- Mesero: salón; consulta catálogo sin modificarlo.
(4, 'dashboard', 1, 0, 0, 0),
(4, 'pos', 1, 1, 0, 0),
(4, 'clients', 1, 1, 1, 0),
(4, 'products', 1, 0, 0, 0),
(4, 'categories', 1, 0, 0, 0),
(4, 'profile', 1, 0, 1, 0),
-- Producción: catálogo + inventario/producción (consulta y edición de productos).
(5, 'products', 1, 1, 1, 0),
(5, 'categories', 1, 0, 0, 0),
(5, 'inventory', 1, 1, 1, 1),
(5, 'production', 1, 0, 0, 0),
(5, 'suppliers', 1, 0, 0, 0),
-- Compras: producción consulta y puede EDITAR (necesario para registrar la
-- recepción de mercancía), pero no crea ni elimina órdenes.
(5, 'purchases', 1, 0, 1, 0),
(5, 'profile', 1, 0, 1, 0),
-- Domiciliero: pedidos y consulta de clientes.
(6, 'dashboard', 1, 0, 0, 0),
(6, 'pos', 1, 1, 0, 0),
(6, 'orders', 1, 1, 1, 0),
(6, 'clients', 1, 0, 0, 0),
(6, 'profile', 1, 0, 1, 0),
-- Horarios: los demas roles solo usan 'Mi Asistencia' (modulo 'always').
-- Un cajero puede VER el roster del dia, pero no asignar turnos ni corregir
-- la marcacion de un companero.
(3, 'schedules', 1, 0, 0, 0);

-- ----------------------------------------------------------------------------
-- DASHBOARD SEED (empleados, promociones, caja, ventas, pedidos)
-- ----------------------------------------------------------------------------
-- Los empleados con credenciales de acceso (email/password_hash + role_id)
-- El acceso es solo con el correo; como nombre se usa name + last_name.
INSERT INTO empleados (id, name, last_name, id_document, email, password_hash, role_id, phone, address, birth_date, hire_date, base_salary) VALUES
(1, 'Carlos', 'Ramírez', '00000001-1', 'carlos.ramirez@bakery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, '5555-0101', 'Zona 5, Ciudad', '1990-03-15', '2023-05-01', 1200.00),
(2, 'María', 'González', '00000002-2', 'maria.gonzalez@bakery.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, '5555-0102', 'Zona 3, Ciudad', '1995-07-22', '2023-06-15', 1000.00),
(3, 'BRIAN JOSUE CHAVEZ RECINOS', 'Administrador', '00000003-3', 'admin@ignis.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NULL, NULL, NULL, '2023-01-01', 1500.00);

-- Compras de ejemplo: una recibida (con su historial de precios), una parcial y
-- una pendiente, para que el módulo muestre los tres estados de un vistazo.
INSERT INTO purchase_orders (supplier_id, employee_id, order_date, estimated_delivery_date, state, total) VALUES
(1, 3, DATE_SUB(CURDATE(), INTERVAL 9 DAY), DATE_SUB(CURDATE(), INTERVAL 7 DAY), 'recibida', 105.00),
(2, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'parcial', 100.00),
(1, 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'pendiente', 70.00);

INSERT INTO purchase_order_details (purchase_order_id, ingredient_id, quantity, unit_price, subtotal) VALUES
(1, 1, 60.000, 1.2500, 75.00),
(1, 2, 6.000, 5.0000, 30.00),
(2, 3, 25.000, 4.5000, 112.50),
(3, 1, 40.000, 1.3000, 52.00),
(3, 2, 6.000, 3.0000, 18.00);

-- La recepción de la orden 1 llegó completa; la de la 2 solo trae 20 de 25 kg.
INSERT INTO merchandise_receipts (purchase_order_id, employee_id, receipt_date, observations) VALUES
(1, 3, DATE_SUB(NOW(), INTERVAL 7 DAY), 'Factura 0001-1234, empaque íntegro.'),
(2, 3, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Faltaron 5 kg; el proveedor repone mañana.');

INSERT INTO receipt_details (receipt_id, ingredient_id, expected_quantity, received_quantity) VALUES
(1, 1, 60.000, 60.000),
(1, 2, 6.000, 6.000),
(2, 3, 25.000, 20.000);

-- Historial de precios que dejó cada recepción (el de la orden 3 aún no llega).
INSERT INTO supplier_price_history (supplier_id, ingredient_id, price, price_date) VALUES
(1, 1, 1.2500, DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(1, 2, 5.0000, DATE_SUB(CURDATE(), INTERVAL 7 DAY)),
(2, 3, 4.5000, DATE_SUB(CURDATE(), INTERVAL 1 DAY));

INSERT INTO ingredient_inventory_movements (ingredient_id, movement_type, quantity, reason, employee_id, reference) VALUES
(1, 'entrada', 60.000, 'Recepción de compra #1', 3, 'Compra #1'),
(2, 'entrada', 6.000, 'Recepción de compra #1', 3, 'Compra #1'),
(3, 'entrada', 20.000, 'Recepción de compra #2', 3, 'Compra #2');

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

-- Pagos de las ventas demo. Las ventas en efectivo incluyen el monto recibido
-- para que el ticket muestre un cambio realista (vuelto por dar billetes).
INSERT INTO sale_payments (sale_id, payment_method, amount) VALUES
(1, 'efectivo', 20.00),
(2, 'tarjeta', 18.00),
(3, 'efectivo', 25.00),
(4, 'efectivo', 30.00),
(5, 'tarjeta', 28.00),
(6, 'efectivo', 50.00),
(7, 'efectivo', 24.00),
(8, 'efectivo', 50.00),
(9, 'efectivo', 40.00),
(10, 'efectivo', 30.00),
(11, 'efectivo', 50.00),
(12, 'efectivo', 50.00),
(13, 'efectivo', 50.00),
(14, 'efectivo', 50.00),
(15, 'tarjeta', 35.00),
(16, 'efectivo', 50.00),
(17, 'efectivo', 50.00),
(18, 'efectivo', 50.00),
(19, 'efectivo', 50.00),
(20, 'efectivo', 50.00),
(21, 'efectivo', 50.00),
(22, 'efectivo', 50.00),
(23, 'efectivo', 50.00),
(24, 'efectivo', 100.00),
(25, 'efectivo', 50.00),
(26, 'efectivo', 100.00),
(27, 'efectivo', 100.00),
(28, 'efectivo', 100.00),
(29, 'tarjeta', 70.00),
(30, 'efectivo', 100.00),
(31, 'efectivo', 100.00),
(32, 'efectivo', 100.00),
(33, 'efectivo', 100.00),
(34, 'efectivo', 100.00),
(35, 'efectivo', 100.00),
(36, 'tarjeta', 105.00),
(37, 'efectivo', 100.00),
(38, 'efectivo', 100.00),
(39, 'efectivo', 100.00),
(40, 'efectivo', 100.00),
(41, 'efectivo', 100.00),
(42, 'transferencia', 140.00);

INSERT INTO pedidos (id, client_id, recorded_by_employee_id, order_date, delivery_date, delivery_address, state, rejection_reason, total, paid_amount, remaining_balance, notes) VALUES
(1, 1, 3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'Zona 1, Ciudad (casa de Ana)', 'pendiente', NULL, 350.00, 100.00, 250.00, 'Pastel de bodas para 50 personas'),
(2, 2, 3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_ADD(CURDATE(), INTERVAL 1 DAY), NULL, 'aprobado', NULL, 120.00, 120.00, 0.00, 'Pedido para cumpleaños'),
(3, 1, 3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'Zona 1, Ciudad (casa de Ana)', 'en_produccion', NULL, 210.00, 100.00, 110.00, 'Torta especial de chocolate'),
(4, 2, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), CURDATE(), NULL, 'listo', NULL, 45.00, 45.00, 0.00, 'Listo para recoger'),
(5, 1, 3, DATE_SUB(CURDATE(), INTERVAL 8 DAY), DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Zona 1, Ciudad (casa de Ana)', 'entregado', NULL, 80.00, 80.00, 0.00, 'Entregado a domicilio'),
(6, 2, 3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL, 'rechazado', 'Cliente canceló el pedido', 100.00, 0.00, 0.00, 'Cancelado por el cliente');

INSERT INTO order_details (order_id, product_id, personalized_description, quantity, unit_price, subtotal) VALUES
(1, 4, 'Pastel de bodas', 1, 350.00, 350.00),
(2, 3, 'Panes surtidos', 48, 2.50, 120.00),
(3, 4, 'Torta de chocolate grande', 1, 210.00, 210.00),
(4, 3, 'Panes surtidos', 18, 2.50, 45.00),
(5, 3, 'Panes de queso', 32, 2.50, 80.00),
(6, 4, 'Pastel de chocolate', 1, 100.00, 100.00);

INSERT INTO notificaciones (destination_employee_id, notification_type, title, message, reference_type, reference_id) VALUES
(3, 'stock_bajo', 'Stock bajo', 'El producto "Concha" está por debajo del stock mínimo.', 'producto', 2),
(3, 'pedido_listo', 'Pedido listo', 'El pedido #4 está listo para recoger.', 'pedido', 4),
-- Sin destinatario = visible para todos los empleados (la campana de notificaciones).
(NULL, 'pedido_nuevo', 'Nuevo pedido', 'Ana García reservó el pedido #1 (entrega en 2 días).', 'pedido', 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('system_name', 'Panadería'),
('business_name', 'Panadería'),
('address', 'Av. Central #123, Centro'),
('phone', '5555-1234'),
('currency', '$'),
('tax_rate', '0'),
('cash_register_base', '125.00'),
('ticket_footer', '¡Gracias por su compra!'),
('system_logo', ''),
('login_photo', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=80'),
('primary_color', '#f97316'),
-- Zona horaria del negocio. TODO el sistema razona en esta zona: los
-- contenedores corren en UTC, asi que sin esto las marcaciones de la tarde
-- caen en el dia siguiente. Cambiala en Configuracion si el sistema sale a
-- otro pais.
('timezone', 'America/El_Salvador'),
-- Clave del quiosco de asistencia (/kiosco). Es de la DEMO: cambiala en
-- Configuracion antes de usarlo de verdad.
('kiosk_key', 'ignis-2026');

-- ----------------------------------------------------------------------------
-- HORARIOS Y ASISTENCIA (ejemplo)
-- ----------------------------------------------------------------------------
-- Turnos de la semana pasada y de la que viene. Se siembran los turnos de HOY
-- pero NO marcaciones de hoy: el seed no sabe a que hora se levanta el
-- servidor, y una entrada "futura" se veria raro. Las marcaciones demo van
-- solo para dias ya cerrados.
INSERT INTO shifts (employee_id, work_date, start_time, end_time, shift_type, notes) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:00:00', '15:00:00', 'manana', 'Cubre preparacion'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, CURDATE(), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '07:00:00', '15:00:00', 'manana', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '12:00:00', '20:00:00', 'tarde', 'Cierra caja'),
(2, CURDATE(), '12:00:00', '20:00:00', 'tarde', NULL),
(2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '12:00:00', '20:00:00', 'tarde', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:00:00', '19:00:00', 'cerrada', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '10:00:00', '19:00:00', 'cerrada', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:00:00', '19:00:00', 'cerrada', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '10:00:00', '19:00:00', 'cerrada', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00', '19:00:00', 'cerrada', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '10:00:00', '19:00:00', 'cerrada', 'Reviso inventario'),
(3, CURDATE(), '10:00:00', '19:00:00', 'cerrada', NULL);

-- Asistencias de dias ya cerrados, mezclando puntual, tarde, descanso, permiso y
-- ausencia. Hoy queda sin marcar a proposito.
INSERT INTO attendances
    (employee_id, attendance_date, check_in, check_out, break_start, break_end,
     state, check_in_method, check_out_method, notes) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '06:55:00', '15:04:00', '11:00:00', '11:30:00', 'presente', 'qr', 'qr', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '07:22:00', '15:10:00', '11:00:00', '11:30:00', 'presente', 'qr', 'qr', 'Entro con trafico'),
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '06:51:00', '14:58:00', '11:00:00', '11:30:00', 'presente', 'manual', 'manual', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '06:55:00', '15:01:00', NULL, NULL, 'presente', 'qr', 'qr', NULL),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:10:00', '15:30:00', '11:30:00', '12:00:00', 'permiso', 'qr', 'qr', 'Autorizacion del sub jefe'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '06:58:00', '15:03:00', '11:00:00', '11:30:00', 'presente', 'qr', 'qr', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '11:55:00', '20:07:00', '16:00:00', '16:30:00', 'presente', 'qr', 'qr', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '12:31:00', '20:12:00', '16:00:00', '16:45:00', 'presente', 'qr', 'qr', 'Llego tarde por transporte'),
(2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), NULL, NULL, NULL, NULL, 'ausente', NULL, NULL, 'No reporto'),
(2, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '11:58:00', '20:02:00', '16:00:00', '16:30:00', 'presente', 'qr', 'qr', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '12:05:00', '20:15:00', '16:00:00', '16:30:00', 'presente', 'qr', 'qr', NULL),
(2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '11:49:00', '20:20:00', '16:00:00', '16:30:00', 'presente', 'manual', 'manual', 'Cierre de caja'),
(3, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '09:58:00', '19:05:00', '14:00:00', '14:30:00', 'presente', 'qr', 'qr', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '09:40:00', '19:00:00', '14:00:00', '14:30:00', 'presente', 'qr', 'qr', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '10:12:00', '19:22:00', '14:00:00', '14:30:00', 'presente', 'qr', 'qr', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '09:55:00', '19:01:00', '14:00:00', '14:30:00', 'presente', 'qr', 'qr', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:03:00', '19:10:00', '14:00:00', '14:30:00', 'presente', 'qr', 'qr', NULL),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '09:47:00', '19:35:00', '14:00:00', '15:00:00', 'presente', 'qr', 'qr', 'Inventario y cierre');
