<?php

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/Category.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/Notificacion.php';

class ProductController
{
    private $db;
    private $productModel;
    private $categoryModel;
    private $auditModel;
    private $notifModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->productModel = new Product($db);
        $this->categoryModel = new Category($db);
        $this->auditModel = new AuditLog($db);
        $this->notifModel = new Notificacion($db);
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

        $galleryUrls = upload_gallery('gallery_images');
        if ($galleryUrls === false) {
            header('Location: ' . url('products/create'));
            exit;
        }

        if ($this->productModel->create($category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url, $barcode)) {
            $recordId = (int) $this->db->lastInsertId();
            if ($galleryUrls) {
                $this->productModel->setGallery($recordId, $galleryUrls);
            }
            $new = $this->productModel->getById($recordId);
            $this->auditModel->write('create', 'productos', $recordId, null, $new ?: null, 'Producto creado.');
            $this->notificarStockBajo($new ?: []);
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
        $gallery = $this->productModel->getGallery($id);
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

        $removedGallery = array_map('trim', $_POST['remove_gallery'] ?? []);
        $removedGallery = array_filter($removedGallery);
        $currentGallery = $this->productModel->getGallery($id);
        $keptGallery = [];
        foreach ($currentGallery as $item) {
            if (!in_array($item['image_url'], $removedGallery, true)) {
                $keptGallery[] = $item['image_url'];
            }
        }

        $galleryUrls = upload_gallery('gallery_images');
        if ($galleryUrls === false) {
            header('Location: ' . url('products/edit/' . $id));
            exit;
        }

        foreach ($removedGallery as $oldUrl) {
            if (strncmp($oldUrl, '/uploads/', 9) === 0) {
                $path = __DIR__ . '/../public' . $oldUrl;
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $before = $this->productModel->getById($id);

        if ($this->productModel->update($id, $category_id, $name, $description, $sale_price, $production_cost, $stock, $min_stock, $image_url, $barcode, $status)) {
            $finalGallery = array_merge($keptGallery, $galleryUrls);
            $this->productModel->setGallery($id, $finalGallery);
            $after = $this->productModel->getById($id);
            $this->auditModel->write('update', 'productos', $id, $before, $after ?: null, 'Producto actualizado.');
            $this->notificarStockBajo($after ?: []);
            flash('success', 'Producto actualizado correctamente.');
        } else {
            flash('error', 'No se pudo actualizar el producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }

    /** Crea o resuelve la notificación de stock bajo según el nuevo inventario. */
    private function notificarStockBajo($product)
    {
        if (!$product || ($product['status'] ?? 'active') !== 'active') {
            return;
        }

        $productId = (int) $product['id'];
        $stock = (int) $product['stock'];
        $minStock = (int) $product['min_stock'];

        if ($stock <= $minStock) {
            if ($this->notifModel->existe('stock_bajo', 'producto', $productId)) {
                return;
            }

            $this->notifModel->crear(
                'stock_bajo',
                'Stock bajo',
                'Stock bajo en "' . $product['name'] . '" — quedan ' . max($stock, 0) . ' unidades.',
                'producto',
                $productId
            );
        } else {
            $this->notifModel->resolver('stock_bajo', 'producto', $productId);
        }
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
        $galleryBefore = $before ? $this->productModel->getGallery($id) : [];

        if ($before && $this->productModel->delete($id)) {
            $urls = [$before['image_url'] ?? ''];
            foreach ($galleryBefore as $gal) {
                $urls[] = $gal['image_url'];
            }
            foreach ($urls as $oldUrl) {
                if (strncmp($oldUrl, '/uploads/', 9) === 0) {
                    $path = __DIR__ . '/../public' . $oldUrl;
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
            $this->auditModel->write('delete', 'productos', $id, $before ?: null, null, 'Producto eliminado.');
            flash('success', 'Producto eliminado correctamente.');
        } else {
            flash('error', 'No se pudo eliminar el producto.');
        }

        header('Location: ' . url('products'));
        exit;
    }
}