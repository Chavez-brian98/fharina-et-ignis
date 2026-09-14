<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Producto</h1>
    <a href="<?= url('products') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Información del producto</h2>
    </div>
    <form action="<?= url('products/update/' . $product['id']) ?>" method="POST" enctype="multipart/form-data" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= esc($product['name']) ?>" required>
        </div>

        <div>
            <label for="category_id" class="form-label">Categoría <span class="text-red-500">*</span></label>
            <select id="category_id" name="category_id" class="form-input" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= esc($cat['id']) ?>" <?= (int) $cat['id'] === (int) $product['category_id'] ? 'selected' : '' ?>>
                        <?= esc($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="sale_price" class="form-label">Precio de venta ($) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="form-input" value="<?= esc($product['sale_price']) ?>" required>
        </div>

        <div>
            <label for="production_cost" class="form-label">Costo de producción ($)</label>
            <input type="number" step="0.01" min="0" id="production_cost" name="production_cost" class="form-input" value="<?= esc($product['production_cost']) ?>">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div>
            <label for="stock" class="form-label">Stock</label>
            <input type="number" min="0" id="stock" name="stock" class="form-input" value="<?= esc($product['stock']) ?>">
        </div>

        <div>
            <label for="min_stock" class="form-label">Stock mínimo</label>
            <input type="number" min="0" id="min_stock" name="min_stock" class="form-input" value="<?= esc($product['min_stock']) ?>">
        </div>

        <div>
            <label for="barcode" class="form-label">Código de barras</label>
            <div class="relative">
                <i class="fa-solid fa-barcode absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" id="barcode" name="barcode" maxlength="50" class="form-input pl-10 pr-12"
                       value="<?= esc($product['barcode']) ?>" placeholder="Escanea o escribe el código">
                <button type="button" class="btn-scan-barcode absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-orange-500 hover:bg-orange-50 focus:outline-none transition-colors"
                        title="Escanear con la cámara">
                    <i class="fa-solid fa-camera-retro"></i>
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-1.5">Escanéalo con tu celular usando la cámara.</p>
        </div>

        <div>
            <label for="image_file" class="form-label">Imagen del producto</label>
            <div class="flex items-center gap-4">
                <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= esc($product['image_url']) ?>" alt="Imagen actual" class="w-16 h-16 rounded-xl object-cover ring-1 ring-gray-200 bg-gray-100">
                <?php else: ?>
                    <div class="w-16 h-16 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center text-lg">
                        <i class="fa-regular fa-image"></i>
                    </div>
                <?php endif; ?>
                <input type="file" id="image_file" name="image_file" accept="image/*" class="form-input" placeholder="">
            </div>
            <input type="hidden" name="image_url" value="<?= esc($product['image_url']) ?>">
            <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, WEBP o GIF · máximo 2 MB · se sube al sistema y se muestra en el POS.</p>
        </div>

        <div class="md:col-span-2">
            <label for="description" class="form-label">Descripción</label>
            <textarea id="description" name="description" class="form-input" rows="3"><?= esc($product['description']) ?></textarea>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Actualizar Producto
            </button>
            <a href="<?= url('products') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../partials/barcode_scanner.php'; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>