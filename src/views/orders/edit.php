<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<?php $currency = setting('currency', '$'); ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Editar Pedido #<?= (int) $id ?></h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= esc(Order::estadoTexto($order['state'])) ?> ·
            Entrega <?= esc(date('d/m/Y', strtotime($order['delivery_date']))) ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('orders/show/' . $id) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-route"></i> Ver seguimiento
        </a>
    </div>
</div>

<form action="<?= url('orders/update/' . $id) ?>" method="POST" id="orderUpdate">
    <div class="max-w-5xl space-y-5">
        <!-- Datos de la reserva -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-cake-candles text-orange-500 mr-2"></i>Datos de la reserva</h2>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="client_id" class="form-label">Cliente</label>
                    <select id="client_id" name="client_id" class="form-input">
                        <?php foreach ($clients as $client):
                            $nombre = trim($client['name'] . ' ' . $client['last_name']);
                            if ($client['client_type'] === 'empresa' && trim($client['company_name']) !== '') {
                                $nombre = $client['company_name'] . ' — ' . $nombre;
                            }
                        ?>
                            <option value="<?= (int) $client['id'] ?>"
                                    data-address="<?= esc($client['address'] ?? '') ?>"
                                    <?= (int) $client['id'] === (int) $order['client_id'] ? 'selected' : '' ?>>
                                <?= esc($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="delivery_date" class="form-label">Fecha de entrega</label>
                    <input type="date" id="delivery_date" name="delivery_date" class="form-input"
                           value="<?= esc($order['delivery_date']) ?>" min="<?= esc(date('Y-m-d')) ?>">
                </div>

                <div class="md:col-span-2">
                    <label for="delivery_address" class="form-label">Dirección de envío</label>
                    <div class="flex gap-2">
                        <input type="text" id="delivery_address" name="delivery_address" class="form-input flex-1"
                               placeholder="Dejarlo vacío = el cliente recoge en tienda"
                               value="<?= esc($order['delivery_address'] ?? '') ?>" maxlength="255">
                        <button type="button" id="copyClientAddress" class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors" title="Usar la dirección guardada del cliente">
                            <i class="fa-solid fa-house-user text-orange-500"></i> Usar la del cliente
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Si está vacía, el pedido queda para recoger en tienda.</p>
                </div>

                <div class="md:col-span-2">
                    <label for="notes" class="form-label">Notas</label>
                    <textarea id="notes" name="notes" rows="2" class="form-input"><?= esc($order['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Productos -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Productos</h2>
                <button type="button" id="addLine"
                        class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-3.5 py-2 text-xs font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-plus"></i> Agregar producto
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                            <th class="px-6 py-3 font-semibold w-16">#</th>
                            <th class="px-5 py-3 font-semibold">Producto</th>
                            <th class="px-5 py-3 font-semibold w-48">Descripción / personalización</th>
                            <th class="px-5 py-3 font-semibold w-28">Cantidad</th>
                            <th class="px-5 py-3 font-semibold w-36">Precio unitario</th>
                            <th class="px-5 py-3 font-semibold w-32">Subtotal</th>
                            <th class="px-5 py-3 font-semibold w-14"></th>
                        </tr>
                    </thead>
                    <tbody id="linesBody">
                        <?php foreach ($details as $i => $detalle): ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-6 py-3 text-gray-400 text-xs font-semibold line-number"><?= $i + 1 ?></td>
                                <td class="px-5 py-3">
                                    <select name="product_id[]" class="form-input line-product" required>
                                        <option value="">Selecciona...</option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?= (int) $product['id'] ?>"
                                                    data-price="<?= esc(number_format((float) $product['sale_price'], 2, '.', '')) ?>"
                                                    <?= (int) $product['id'] === (int) $detalle['product_id'] ? 'selected' : '' ?>>
                                                <?= esc($product['name']) ?> — <?= esc($currency) ?><?= number_format((float) $product['sale_price'], 2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-5 py-3">
                                    <input type="text" name="description[]" class="form-input" placeholder="P. ej. pastel de chocolate"
                                           value="<?= esc($detalle['personalized_description'] ?? '') ?>">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" name="quantity[]" step="1" min="1"
                                           value="<?= (int) $detalle['quantity'] ?>"
                                           class="form-input line-qty" required>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                                        <input type="number" name="unit_price[]" step="0.01" min="0"
                                               value="<?= esc(number_format((float) $detalle['unit_price'], 2, '.', '')) ?>"
                                               class="form-input line-price pl-7" required>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-900 font-semibold line-subtotal">
                                    <?= esc($currency . number_format((float) $detalle['subtotal'], 2)) ?>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    <button type="button" class="btn-action btn-remove text-red-400 hover:bg-red-50" title="Quitar línea">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <template id="lineTemplate">
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="px-6 py-3 text-gray-400 text-xs font-semibold line-number"></td>
                    <td class="px-5 py-3">
                        <select name="product_id[]" class="form-input line-product" required>
                            <option value="">Selecciona...</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= (int) $product['id'] ?>"
                                        data-price="<?= esc(number_format((float) $product['sale_price'], 2, '.', '')) ?>">
                                    <?= esc($product['name']) ?> — <?= esc($currency) ?><?= number_format((float) $product['sale_price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-5 py-3">
                        <input type="text" name="description[]" class="form-input" placeholder="P. ej. pastel de chocolate">
                    </td>
                    <td class="px-5 py-3">
                        <input type="number" name="quantity[]" step="1" min="1" placeholder="1"
                               class="form-input line-qty" required>
                    </td>
                    <td class="px-5 py-3">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                            <input type="number" name="unit_price[]" step="0.01" min="0" placeholder="0.00"
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

            <div class="border-t border-gray-100 px-6 py-4 flex justify-end">
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Total</p>
                    <p id="totalLabel" class="text-2xl font-bold text-orange-500">
                        <?= esc($currency . number_format((float) $order['total'], 2)) ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
                <a href="<?= url('orders/show/' . $id) ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
            </div>
            <p class="text-xs text-gray-400">Al guardar se reemplazan todas las líneas del pedido.</p>
        </div>
    </div>
</form>

<script>
(function () {
    const CURRENCY = <?= json_encode($currency, JSON_UNESCAPED_UNICODE) ?>;
    const body = document.getElementById('linesBody');
    const template = document.getElementById('lineTemplate');
    const totalLabel = document.getElementById('totalLabel');

    function money(value) {
        return CURRENCY + Number(value || 0).toFixed(2);
    }

    function recalc() {
        let total = 0;

        body.querySelectorAll('tr').forEach(function (row, index) {
            const qty = parseInt(row.querySelector('.line-qty').value, 10) || 0;
            const price = parseFloat(row.querySelector('.line-price').value) || 0;

            row.querySelector('.line-subtotal').textContent = money(qty * price);
            row.querySelector('.line-number').textContent = index + 1;
            total += qty * price;
        });

        totalLabel.textContent = money(total);
    }

    body.addEventListener('input', recalc);

    document.getElementById('addLine').addEventListener('click', function () {
        body.appendChild(template.content.cloneNode(true));
        recalc();
    });

    body.addEventListener('click', function (event) {
        const remove = event.target.closest('.btn-remove');
        if (!remove) return;

        const rows = body.querySelectorAll('tr');

        if (rows.length > 1) {
            remove.closest('tr').remove();
        } else {
            const row = remove.closest('tr');
            row.querySelector('.line-product').value = '';
            row.querySelector('.line-qty').value = '';
            row.querySelector('.line-price').value = '';
        }
        recalc();
    });

    body.addEventListener('change', function (event) {
        const select = event.target.closest('.line-product');
        if (!select) return;

        const option = select.options[select.selectedIndex];

        if (option && option.value) {
            select.closest('tr').querySelector('.line-price').value =
                parseFloat(option.dataset.price || '0').toFixed(2);
        }
        recalc();
    });

    document.getElementById('copyClientAddress').addEventListener('click', function () {
        const select = document.getElementById('client_id');
        const option = select.options[select.selectedIndex];

        document.getElementById('delivery_address').value = option && option.dataset.address ? option.dataset.address : '';
    });

    recalc();
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>