<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<?php
$hoy = date('Y-m-d');
$tipos = Promocion::tipos();
$tipoInicial = old('tipo', 'descuento_volumen');
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nueva Promoción</h1>
    <a href="<?= url('promotions') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (!$products): ?>
    <div class="max-w-4xl rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-800">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
        No hay productos registrados. <a href="<?= url('products/create') ?>" class="font-semibold underline">Crea uno primero</a>.
    </div>
<?php else: ?>

<form action="<?= url('promotions/store') ?>" method="POST" id="promoForm">
    <div class="max-w-5xl space-y-5">
        <!-- Datos -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-green-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-tags text-green-500 mr-2"></i>Datos de la promoción</h2>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="P. ej. Semana del pan, 2x1 en pan dulce..."
                           value="<?= old('name') ?>" maxlength="120" required>
                </div>

                <div class="md:col-span-2">
                    <label for="tipo" class="form-label">Tipo de promoción <span class="text-red-500">*</span></label>
                    <select id="tipo" name="tipo" class="form-input" required>
                        <?php foreach ($tipos as $clave => $t): ?>
                            <option value="<?= esc($clave) ?>" <?= $tipoInicial === $clave ? 'selected' : '' ?>>
                                <?= esc($t['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p id="tipoAyuda" class="text-xs text-gray-400 mt-1.5"><?= esc($tipos[$tipoInicial]['ayuda'] ?? '') ?></p>
                </div>

                <div id="porcentajeGroup">
                    <label for="discount_percentage" class="form-label">Descuento (%) <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">%</span>
                        <input type="number" id="discount_percentage" name="discount_percentage" step="0.01" min="1" max="100"
                               class="form-input pr-8" value="<?= old('discount_percentage', '10') ?>">
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Es el descuento que el <strong>punto de venta</strong> aplica automáticamente a esos productos.</p>
                </div>

                <div>
                    <label for="status" class="form-label">Estado</label>
                    <div class="flex gap-3">
                        <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm font-semibold text-gray-700 cursor-pointer has-[:checked]:border-green-400 has-[:checked]:bg-green-50">
                            <input type="radio" name="status" value="active" class="accent-green-500" <?= old('status', 'active') === 'active' ? 'checked' : '' ?>> Activa
                        </label>
                        <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3.5 py-2.5 text-sm font-semibold text-gray-700 cursor-pointer has-[:checked]:border-gray-300 has-[:checked]:bg-gray-50">
                            <input type="radio" name="status" value="inactive" class="accent-gray-500" <?= old('status') === 'inactive' ? 'checked' : '' ?>> Inactiva
                        </label>
                    </div>
                </div>

                <div>
                    <label for="start_date" class="form-label">Desde <span class="text-red-500">*</span></label>
                    <input type="date" id="start_date" name="start_date" class="form-input" value="<?= old('start_date', $hoy) ?>" required>
                </div>

                <div>
                    <label for="end_date" class="form-label">Hasta <span class="text-red-500">*</span></label>
                    <input type="date" id="end_date" name="end_date" class="form-input" value="<?= old('end_date', $hoy) ?>" required>
                </div>

                <div>
                    <label for="start_time" class="form-label">Hora de inicio</label>
                    <input type="time" id="start_time" name="start_time" class="form-input" value="<?= old('start_time') ?>">
                    <p class="text-xs text-gray-400 mt-1.5">Para happy hour. Opcional.</p>
                </div>

                <div>
                    <label for="end_time" class="form-label">Hora de fin</label>
                    <input type="time" id="end_time" name="end_time" class="form-input" value="<?= old('end_time') ?>">
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-green-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-green-500 mr-2"></i>Productos incluidos</h2>
                <p class="text-xs text-gray-500 mt-1">La promoción aplica solo a los productos seleccionados.</p>
            </div>
            <div class="px-6 py-5">
                <div class="relative mb-4">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" id="productsSearch"
                           class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
                           placeholder="Buscar producto por nombre...">
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-80 overflow-y-auto pr-1">
                    <?php foreach ($products as $product): ?>
                        <label class="product-item flex items-center gap-3 rounded-xl border border-gray-200 px-3.5 py-3 cursor-pointer hover:border-green-300 hover:bg-green-50/50 transition-colors"
                               data-search="<?= esc(strtolower(trim($product['name'] . ' ' . ($product['barcode'] ?? '')))) ?>">
                            <input type="checkbox" name="product_ids[]" value="<?= (int) $product['id'] ?>"
                                   class="w-4 h-4 rounded accent-green-500 shrink-0">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?= esc($product['image_url']) ?>" alt="" class="w-9 h-9 rounded-lg object-cover shrink-0">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-lg bg-gray-100 text-gray-400 flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-cake-candles text-xs"></i>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 truncate"><?= esc($product['name']) ?></p>
                                <p class="text-xs text-gray-400"><?= setting('currency', '$') ?><?= number_format((float) $product['sale_price'], 2) ?></p>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-green-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-green-500/25 hover:bg-green-600 hover:shadow-green-500/40 hover:-translate-y-px transition-all">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Promoción
                </button>
                <a href="<?= url('promotions') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
            </div>
            <p class="text-xs text-gray-400">Los cupones de una promoción se administran desde la fila de la lista.</p>
        </div>
    </div>
</form>

<script>
(function () {
    const select = document.getElementById('tipo');
    const porcentajeGroup = document.getElementById('porcentajeGroup');
    const ayuda = document.getElementById('tipoAyuda');
    const ayudaTxt = <?= json_encode(array_map(fn($t) => $t['ayuda'], $tipos), JSON_UNESCAPED_UNICODE) ?>;
    const usaPorcentaje = <?= json_encode(array_map(fn($t) => (bool) $t['usa_porcentaje'], $tipos), JSON_UNESCAPED_UNICODE) ?>;

    function actualizarTipo() {
        const tipo = select.value;
        ayuda.textContent = ayudaTxt[tipo] || '';
        porcentajeGroup.style.display = usaPorcentaje[tipo] ? '' : 'none';
    }

    select.addEventListener('change', actualizarTipo);
    actualizarTipo();

    const search = document.getElementById('productsSearch');

    search.addEventListener('input', function () {
        const termino = search.value.trim().toLowerCase();

        document.querySelectorAll('.product-item').forEach(function (item) {
            item.style.display = item.dataset.search.indexOf(termino) !== -1 ? '' : 'none';
        });
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>