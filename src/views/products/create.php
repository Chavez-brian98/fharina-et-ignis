<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nuevo Producto</h1>
    <a href="<?= url('products') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Información del producto</h2>
    </div>
    <form action="<?= url('products/store') ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= old('name') ?>" required>
        </div>

        <div>
            <label for="category_id" class="form-label">Categoría <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" class="form-input" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= esc($cat['id']) ?>" <?= (int) old('category_id') === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= esc($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="sale_price" class="form-label">Precio de venta ($) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-input" value="<?= old('sale_price') ?>" required>
        </div>

        <div>
            <label for="production_cost" class="form-label">Costo de producción ($)</label>
            <input type="number" step="0.01" min="0" id="production_cost" name="production_cost" class="form-input" value="<?= old('production_cost', '0.00') ?>">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div>
            <label for="stock" class="form-label">Stock</label>
            <input type="number" min="0" id="stock" name="stock" class="form-input" value="<?= old('stock', '0') ?>">
        </div>

        <div>
            <label for="min_stock" class="form-label">Stock mínimo</label>
            <input type="number" min="0" id="min_stock" name="min_stock" class="form-input" value="<?= old('min_stock', '0') ?>">
        </div>

        <div>
            <label for="image_url" class="form-label">URL de imagen</label>
            <input type="url" id="image_url" name="image_url" class="form-input" value="<?= old('image_url') ?>" placeholder="https://...">
        </div>

        <div class="md:col-span-2">
            <label for="description" class="form-label">Descripción</label>
            <textarea id="description" name="description" class="form-input" rows="3"><?= old('description') ?></textarea>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Producto
            </button>
            <a href="<?= url('products') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>