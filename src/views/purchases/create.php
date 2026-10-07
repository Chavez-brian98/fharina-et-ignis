<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<?php
$currency = setting('currency', '$');
$hoy = date('Y-m-d');
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nueva Compra</h1>
    <a href="<?= url('purchases') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (!$suppliers): ?>
    <div class="max-w-4xl rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-800">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
        No hay proveedores activos. <a href="<?= url('suppliers/create') ?>" class="font-semibold underline">Crea uno primero</a>.
    </div>
<?php elseif (!$ingredients): ?>
    <div class="max-w-4xl rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-800">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
        No hay ingredientes activos registrados en el sistema.
    </div>
<?php else: ?>

<form action="<?= url('purchases/store') ?>" method="POST" id="purchaseForm">
    <div class="max-w-5xl space-y-5">
        <!-- Datos de la orden -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-clipboard-list text-orange-500 mr-2"></i>Datos de la orden</h2>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="supplier_id" class="form-label">Proveedor <span class="text-red-500">*</span></label>
                    <select id="supplier_id" name="supplier_id" class="form-input" required>
                        <option value="">Selecciona un proveedor...</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int) $supplier['id'] ?>" <?= old('supplier_id') === (string) $supplier['id'] ? 'selected' : '' ?>>
                                <?= esc($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="order_date" class="form-label">Fecha de la orden <span class="text-red-500">*</span></label>
                    <input type="date" id="order_date" name="order_date" class="form-input"
                           value="<?= old('order_date', $hoy) ?>" required>
                </div>

                <div>
                    <label for="estimated_delivery_date" class="form-label">Entrega estimada</label>
                    <input type="date" id="estimated_delivery_date" name="estimated_delivery_date" class="form-input"
                           value="<?= old('estimated_delivery_date') ?>">
                    <p class="text-xs text-gray-400 mt-1.5">Opcional.</p>
                </div>
            </div>
        </div>

        <!-- Ingredientes -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Ingredientes</h2>
                <button type="button" id="addLine"
                        class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-3.5 py-2 text-xs font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-plus"></i> Agregar ingrediente
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                            <th class="px-6 py-3 font-semibold w-16">#</th>
                            <th class="px-5 py-3 font-semibold">Ingrediente</th>
                            <th class="px-5 py-3 font-semibold w-32">Cantidad</th>
                            <th class="px-5 py-3 font-semibold w-40">Precio unitario</th>
                            <th class="px-5 py-3 font-semibold w-36">Subtotal</th>
                            <th class="px-5 py-3 font-semibold w-14"></th>
                        </tr>
                    </thead>
                    <tbody id="linesBody"></tbody>
                </table>
            </div>

            <template id="lineTemplate">
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="px-6 py-3 text-gray-400 text-xs font-semibold line-number"></td>
                    <td class="px-5 py-3">
                        <select name="ingredient_id[]" class="form-input line-ingredient" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($ingredients as $ingredient):
                                $precio = $ingredient['last_price'] !== null ? (float) $ingredient['last_price'] : (float) $ingredient['unit_cost'];
                            ?>
                                <option value="<?= (int) $ingredient['id'] ?>"
                                        data-unit="<?= esc($ingredient['unit_of_measure']) ?>"
                                        data-price="<?= esc(number_format($precio, 4, '.', '')) ?>"
                                        data-stock="<?= esc(number_format((float) $ingredient['current_stock'], 3, '.', '')) ?>">
                                    <?= esc($ingredient['name']) ?> (<?= esc($ingredient['unit_of_measure']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-400 mt-1 line-stock"></p>
                    </td>
                    <td class="px-5 py-3">
                        <input type="number" name="quantity[]" step="0.001" min="0" placeholder="0"
                               class="form-input line-qty" required>
                    </td>
                    <td class="px-5 py-3">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                            <input type="number" name="unit_price[]" step="0.0001" min="0" placeholder="0.00"
                                   class="form-input line-price pl-7" required>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-900 font-semibold line-subtotal"><?= esc($currency) ?>0.00</td>
                    <td class="px-5 py-3 text-center">
                        <button type="button" class="btn-action btn-remove text-red-400 hover:bg-red-50" title="Quitar línea">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            </template>
        </div>

        <!-- Total y botones -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 px-6 py-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Orden
                    </button>
                    <a href="<?= url('purchases') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
                </div>
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Total de la orden</p>
                    <p id="totalLabel" class="text-3xl font-bold text-orange-500"><?= esc($currency) ?>0.00</p>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-3">Al guardar, la orden queda <strong>pendiente</strong>. Recibir la mercancía desde la orden mueve el stock de los ingredientes y guarda el historial de precios.</p>
        </div>
    </div>
</form>

<script>
(function () {
    const CURRENCY = <?= json_encode($currency, JSON_UNESCAPED_UNICODE) ?>;
    const OFFER_PRICES = <?= json_encode($offerPrices, JSON_UNESCAPED_UNICODE) ?> || {};
    const supplierSelect = document.getElementById('supplier_id');
    const body = document.getElementById('linesBody');
    const template = document.getElementById('lineTemplate');
    const totalLabel = document.getElementById('totalLabel');

    function money(value) {
        return CURRENCY + Number(value || 0).toFixed(2);
    }

    // Precio que un proveedor ofrece por un ingrediente, si está configurado.
    function precioOfrecido(supplierId, ingredientId) {
        if (!supplierId || !OFFER_PRICES[supplierId]) return null;
        const p = OFFER_PRICES[supplierId][ingredientId];
        return (p === undefined || p === null) ? null : Number(p);
    }

    function recalc() {
        let total = 0;

        body.querySelectorAll('tr').forEach(function (row, index) {
            const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
            const price = parseFloat(row.querySelector('.line-price').value) || 0;
            const subtotal = qty * price;

            row.querySelector('.line-subtotal').textContent = money(subtotal);
            row.querySelector('.line-number').textContent = index + 1;

            total += subtotal;
        });

        totalLabel.textContent = money(total);
    }

    function addLine() {
        const row = template.content.cloneNode(true);
        body.appendChild(row);
        filtrarLineasPorProveedor();
        recalc();
    }

    // Ids de los ingredientes que el proveedor seleccionado vende, o null si aún
    // no hay proveedor (entonces la lista no se restringe).
    function ingredientesDelProveedor() {
        const sup = supplierSelect ? supplierSelect.value : '';
        if (!sup || !OFFER_PRICES[sup]) return null;
        return Object.keys(OFFER_PRICES[sup]);
    }

    // Deja en cada línea únicamente los ingredientes que el proveedor vende
    // (los registrados en el módulo Proveedores). Si una línea ya tenía un
    // ingrediente que ese proveedor no ofrece, se limpia dicha línea.
    function filtrarLineasPorProveedor() {
        const permitidos = ingredientesDelProveedor();
        if (permitidos === null) return;

        body.querySelectorAll('tr').forEach(function (row) {
            const select = row.querySelector('.line-ingredient');
            if (!select) return;

            const actual = select.value;
            let i = select.options.length;
            while (i--) {
                const opt = select.options[i];
                if (opt.value !== '' && permitidos.indexOf(opt.value) === -1) {
                    select.removeChild(opt);
                }
            }

            if (actual !== '' && permitidos.indexOf(actual) === -1) {
                select.value = '';
                const precio = row.querySelector('.line-price');
                const stock = row.querySelector('.line-stock');
                if (precio) precio.value = '';
                if (stock) stock.textContent = '';
            }
        });
    }

    // Al cambiar de proveedor se aplican sus precios ofrecidos a las líneas que
    // ya tienen ingrediente elegido.
    function precargarPreciosDeOferta() {
        const sup = supplierSelect ? supplierSelect.value : '';
        body.querySelectorAll('tr').forEach(function (row) {
            const select = row.querySelector('.line-ingredient');
            const option = select ? select.options[select.selectedIndex] : null;
            if (!option || !option.value) return;
            const oferta = precioOfrecido(sup, option.value);
            if (oferta !== null) {
                row.querySelector('.line-price').value = oferta.toFixed(4);
            }
        });
        recalc();
    }

    function sincronizarProveedor() {
        filtrarLineasPorProveedor();
        precargarPreciosDeOferta();
    }

    if (supplierSelect) {
        supplierSelect.addEventListener('change', sincronizarProveedor);
    }

    body.addEventListener('input', recalc);

    // Al elegir ingrediente se precarga el precio conocido (o el que ofrece el
    // proveedor seleccionado, que tiene prioridad) y se muestra el stock actual
    // para poder compararlo con lo pedido.
    body.addEventListener('change', function (event) {
        const select = event.target.closest('.line-ingredient');
        const row = select ? select.closest('tr') : null;

        if (select && row) {
            const option = select.options[select.selectedIndex];

            if (option && option.value) {
                const oferta = precioOfrecido(supplierSelect ? supplierSelect.value : '', option.value);
                row.querySelector('.line-price').value =
                    (oferta !== null ? oferta : parseFloat(option.dataset.price || '0')).toFixed(4);

                const stock = parseFloat(option.dataset.stock || '0');
                const qty = parseFloat(row.querySelector('.line-qty').value || '0');
                row.querySelector('.line-stock').textContent =
                    'Stock actual: ' + Number(stock).toLocaleString('es-SV', { maximumFractionDigits: 3 }) + ' ' + option.dataset.unit;

                const ofertaDoc = oferta !== null ? ' — precio ofrecido por el proveedor' : '';
                if (qty > 0 && qty > stock) {
                    row.querySelector('.line-stock').textContent += ' — la cantidad supera el stock';
                }
                row.querySelector('.line-stock').textContent += ofertaDoc;
            } else {
                row.querySelector('.line-stock').textContent = '';
            }
        }

        recalc();
    });

    body.addEventListener('click', function (event) {
        const remove = event.target.closest('.btn-remove');

        if (remove) {
            const rows = body.querySelectorAll('tr');

            // Nunca dejamos la tabla sin al menos una línea.
            if (rows.length > 1) {
                remove.closest('tr').remove();
                recalc();
            } else {
                const row = remove.closest('tr');
                row.querySelector('.line-ingredient').value = '';
                row.querySelector('.line-qty').value = '';
                row.querySelector('.line-price').value = '';
                row.querySelector('.line-stock').textContent = '';
                recalc();
            }
        }
    });

    document.getElementById('addLine').addEventListener('click', addLine);

    addLine();
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
