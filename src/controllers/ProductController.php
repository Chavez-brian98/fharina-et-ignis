<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/AuditLog.php';

class ProductController
{
    private $db;
    private $productModel;
    private $categoryModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->productModel = new Product($db);
        $this->categoryModel = new Category($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $products = $this->productModel->getAll();
        $categories = $this->categoryModel->getAll();
        $title = 'Productos';
        $currentModule = 'products';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Productos', 'url' => null],
        ];

        require_once __DIR__ . '/../views/products/index.php';
    }

    public function create()
    {
        $categories = $this->categoryModel->getAll();
        $title = 'Nuevo Producto';
        $currentModule = 'products';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Productos', 'url' => url('products')],
            ['label' => 'Nuevo', 'url' => null],
        ];

        require_once __DIR__ . '/../views/products/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('products'));
            exit;
        }

        $category_id = (int) ($_POST['category_id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $sale_price = $_POST['sale_price'] ?? '';
        $production_cost = $_POST['production_cost'] ?? '';
        $stock = (int) ($_POST['stock'] ?? 0);
        $min_stock = (int) ($_POST['min_stock'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');

        if (empty($name) || empty($sale_price) || $category_id <= 0) {
            flash('error', 'Debe completar todos los campos obligatorios.');
            header('Location: ' . url('products/create'));
            exit;
        }

        if ($barcode !== '' && $this->productModel->findByBarcode($barcode)) {
            flash('error', 'El código de barras "' . $barcode . '" ya está registrado en otro producto.');
            header('Location: ' . url('products/create'));
            exit;
        }

        $uploaded = upload_image('image_file');
        if ($uploaded === false) {
            header('Location: ' . url('products/create'));
            exit;
        }
        if ($uploaded !== null) {
            $image_url = $uploaded;
        }

        if ($this->productModel->create($category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url, $barcode)) {
            $recordId = (int) $this->db->lastInsertId();
            $new = $this->productModel->getById($recordId);
            $this->auditModel->write('create', 'productos', $recordId, null, $new ?: null, 'Producto creado.');
            flash('success', 'Producto creado correctamente.');
        } else {
            flash('error', 'No se pudo crear el producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }

    public function edit($id)
    {
        $product = $this->productModel->getById($id);
        $categories = $this->categoryModel->getAll();

        if (!$product) {
            flash('error', 'Producto no encontrado.');
            header('Location: ' . url('products'));
            exit;
        }

        $title = 'Editar Producto';
        $currentModule = 'products';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Productos', 'url' => url('products')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/products/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('products'));
            exit;
        }

        $category_id = (int) ($_POST['category_id'] ?? 0);
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $sale_price = $_POST['sale_price'] ?? '';
        $production_cost = $_POST['production_cost'] ?? '';
        $stock = (int) ($_POST['stock'] ?? 0);
        $min_stock = (int) ($_POST['min_stock'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');
        $barcode = trim($_POST['barcode'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($sale_price) || $category_id <= 0) {
            flash('error', 'Debe completar todos los campos obligatorios.');
            header('Location: ' . url('products/edit/' . $id));
            exit;
        }

        if ($barcode !== '' && $this->productModel->findByBarcode($barcode, $id)) {
            flash('error', 'El código de barras "' . $barcode . '" ya está registrado en otro producto.');
            header('Location: ' . url('products/edit/' . $id));
            exit;
        }

        $uploaded = upload_image('image_file');
        if ($uploaded === false) {
            header('Location: ' . url('products/edit/' . $id));
            exit;
        }
        if ($uploaded !== null) {
            $image_url = $uploaded;
        }

        $before = $this->productModel->getById($id);

        if ($this->productModel->update($id, $category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url, $barcode, $status)) {
            $after = $this->productModel->getById($id);
            $this->auditModel->write('update', 'productos', $id, $before, $after ?: null, 'Producto actualizado.');
            flash('success', 'Producto actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }

    public function toggle($id)
    {
        $before = $this->productModel->getById($id);

        if ($this->productModel->toggleStatus($id)) {
            $after = $this->productModel->getById($id);
            $this->auditModel->write('toggle', 'productos', $id, $before, $after ?: null, 'Estado del producto actualizado.');
            flash('success', 'Estado del producto actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado del producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->productModel->getById($id);

        if ($this->productModel->delete($id)) {
            $this->auditModel->write('delete', 'productos', $id, $before ?: null, null, 'Producto eliminado.');
            flash('success', 'Producto eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }
}