<?php

require_once __DIR__ . '/../models/Category.php';

class CategoryController
{
    private $db;
    private $categoryModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->categoryModel = new Category($db);
    }

    public function index()
    {
        $categories = $this->categoryModel->getAll();
        $title = 'Categorías';
        $currentModule = 'categories';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => '/'],
            ['label' => 'Categorías', 'url' => null],
        ];

        require_once __DIR__ . '/../views/categories/index.php';
    }

    public function create()
    {
        $title = 'Nueva Categoría';
        $currentModule = 'categories';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => '/'],
            ['label' => 'Categorías', 'url' => url('categories')],
            ['label' => 'Nueva', 'url' => null],
        ];

        require_once __DIR__ . '/../views/categories/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('categories'));
            exit;
        }

        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $display_order = (int) ($_POST['display_order'] ?? 0);

        if (empty($name)) {
            flash('error', 'El campo nombre es obligatorio.');
            header('Location: ' . url('categories/create'));
            exit;
        }

        if ($this->categoryModel->create($name, $description, $display_order)) {
            flash('success', 'Categoría creada correctamente.');
        } else {
            flash('error', 'No se pudo crear la categoría.');
        }

        header('Location: ' . url('categories'));
        exit;
    }

    public function edit($id)
    {
        $category = $this->categoryModel->getById($id);

        if (!$category) {
            flash('error', 'Categoría no encontrada.');
            header('Location: ' . url('categories'));
            exit;
        }

        $title = 'Editar Categoría';
        $currentModule = 'categories';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => '/'],
            ['label' => 'Categorías', 'url' => url('categories')],
            ['label' => 'Editar', 'url' => null],
        ];

        require_once __DIR__ . '/../views/categories/edit.php';
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('categories'));
            exit;
        }

        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $display_order = (int) ($_POST['display_order'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        if (empty($name)) {
            flash('error', 'El campo nombre es obligatorio.');
            header('Location: ' . url('categories/edit/' . $id));
            exit;
        }

        if ($this->categoryModel->update($id, $name, $description, $display_order, $status)) {
            flash('success', 'Categoría actualizada correctamente.');
        } else {
            flash('error', 'No se pudo actualizar la categoría.');
        }

        header('Location: ' . url('categories'));
        exit;
    }

    public function toggle($id)
    {
        if ($this->categoryModel->toggleStatus($id)) {
            flash('success', 'Estado de la categoría actualizado correctamente.');
        } else {
            flash('error', 'No se pudo cambiar el estado de la categoría.');
        }

        header('Location: ' . url('categories'));
        exit;
    }

    public function delete($id)
    {
        $productCount = $this->categoryModel->countProducts($id);

        if ($productCount > 0) {
            flash('error', 'Esta categoría tiene productos asociados. Reasígnalos o elimínalos primero.');
            header('Location: ' . url('categories'));
            exit;
        }

        if ($this->categoryModel->delete($id)) {
            flash('success', 'Categoría eliminada correctamente.');
        } else {
            flash('error', 'No se pudo eliminar la categoría.');
        }

        header('Location: ' . url('categories'));
        exit;
    }
}