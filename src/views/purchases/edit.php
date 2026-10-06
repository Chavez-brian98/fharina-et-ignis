<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$currency = setting('currency', '$');

// Cuánto se lleva recibido por línea, para mostrar el pendiente de cada una.
$recibidoPorIngrediente = $receivedTotals;
$totalRecibido = 0.0;

foreach ($details as $detalle) {
    $totalRecibido += (float) ($recibidoPorIngrediente[(int) $detalle['ingredient_id']] ?? 0)
        * (float) $detalle['unit_price'];
}
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Compra #<?= (int) $id ?></h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= esc($purchase['supplier_name']) ?> ·
            <?= esc(date('d/m/Y', strtotime($purchase['order_date']))) ?> ·
            <span class="rounded-full <?= esc(Purchase::estadoColor($purchase['state'])) ?> px-2 py-0.5 text-xs font-semibold">
                <?= esc(Purchase::estadoTexto($purchase['state'])) ?>
            </span>
        </p>
    </div>
    <a href="<?= url('purchases') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-5xl space-y-5">
    <form action="<?= url('purchases/update/' . $id) ?>" method="POST" id="purchaseUpdate"
          class="<?= $editable ? 'space-y-5' : 'space-y-5' ?>">
        <!-- Datos de la orden -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-clipboard-list text-orange-500 mr-2"></i>Datos de la orden</h2>
                <?php if (!$editable): ?>
                    <span class="rounded-full bg-gray-100 text-gray-500 px-2.5 py-1 text-xs font-semibold">
                        <?= $purchase['state'] === 'pendiente'
                            ? 'No editable: ya tiene recepciones'
                            : 'No editable: orden ' . esc(Purchase::estadoTexto($purchase['state'])) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="px-6 py-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label for="supplier_id" class="form-label">Proveedor</label>
                    <select id="supplier_id" name="supplier_id" class="form-input" <?= $editable ? '' : 'disabled' ?>>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int) $supplier['id'] ?>"
                                    <?= (int) $supplier['id'] === (int) $purchase['supplier_id'] ? 'selected' : '' ?>>
                                <?= esc($supplier['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="order_date" class="form-label">Fecha de la orden</label>
                    <input type="date" id="order_date" name="order_date" class="form-input"
                           value="<?= esc($purchase['order_date']) ?>" <?= $editable ? '' : 'disabled' ?>>
                </div>
                <div>
                    <label for="estimated_delivery_date" class="form-label">Entrega estimada</label>
                    <input type="date" id="estimated_delivery_date" name="estimated_delivery_date" class="form-input"
                           value="<?= esc($purchase['estimated_delivery_date'] ?: '') ?>" <?= $editable ? '' : 'disabled' ?>>
                </div>
            </div>
        </div>

        <!-- Ingredientes -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Ingredientes de la orden</h2>
                <?php if ($editable): ?>
                    <button type="button" id="addLine"
                            class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-3.5 py-2 text-xs font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                        <i class="fa-solid fa-plus"></i> Agregar ingrediente
                    </button>
                <?php endif; ?>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                            <th class="px-5 py-3 font-semibold w-16">#</th>
                            <th class="px-5 py-3 font-semibold">Ingrediente</th>
                            <th class="px-5 py-3 font-semibold w-32">Cantidad</th>
                            <th class="px-5 py-3 font-semibold w-40">Precio unitario</th>
                            <th class="px-5 py-3 font-semibold w-36">Subtotal</th>
                            <?php if ($editable): ?><th class="px-5 py-3 font-semibold w-14"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="linesBody">
                        <?php foreach ($details as $i => $detalle): ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-3 text-gray-400 text-xs font-semibold line-number"><?= $i + 1 ?></td>
                                <td class="px-5 py-3">
                                    <select name="ingredient_id[]" class="form-input line-ingredient" required <?= $editable ? '' : 'disabled' ?>>
                                        <option value="">Selecciona...</option>
                                        <?php foreach ($ingredients as $ingredient):
                                            $precio = $ingredient['last_price'] !== null
                                                ? (float) $ingredient['last_price']
                                                : (float) $ingredient['unit_cost'];
                                        ?>
                                            <option value="<?= (int) $ingredient['id'] ?>"
                                                    data-unit="<?= esc($ingredient['unit_of_measure']) ?>"
                                                    data-price="<?= esc(number_format($precio, 4, '.', '')) ?>"
                                                    data-stock="<?= esc(number_format((float) $ingredient['current_stock'], 3, '.', '')) ?>"
                                                    <?= (int) $ingredient['id'] === (int) $detalle['ingredient_id'] ? 'selected' : '' ?>>
                                                <?= esc($ingredient['name']) ?> (<?= esc($ingredient['unit_of_measure']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" name="quantity[]" step="0.001" min="0"
                                           value="<?= esc(number_format((float) $detalle['quantity'], 3, '.', '')) ?>"
                                           class="form-input line-qty" required <?= $editable ? '' : 'readonly disabled' ?>>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                                        <input type="number" name="unit_price[]" step="0.0001" min="0"
                                               value="<?= esc(number_format((float) $detalle['unit_price'], 4, '.', '')) ?>"
                                               class="form-input line-price pl-7" required <?= $editable ? '' : 'readonly disabled' ?>>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-900 font-semibold line-subtotal">
                                    <?= esc($currency . number_format((float) $detalle['subtotal'], 2)) ?>
                                </td>
                                <?php if ($editable): ?>
                                    <td class="px-5 py-3 text-center">
                                        <button type="button" class="btn-action btn-remove text-red-400 hover:bg-red-50" title="Quitar línea">
                                            <i class="fa-regular fa-trash-can"></i>
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($editable): ?>
                <template id="lineTemplate">
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="px-5 py-3 text-gray-400 text-xs font-semibold line-number"></td>
                        <td class="px-5 py-3">
                            <select name="ingredient_id[]" class="form-input line-ingredient" required>
                                <option value="">Selecciona...</option>
                                <?php foreach ($ingredients as $ingredient):
                                    $precio = $ingredient['last_price'] !== null
                                        ? (float) $ingredient['last_price']
                                        : (float) $ingredient['unit_cost'];
                                ?>
                                    <option value="<?= (int) $ingredient['id'] ?>"
                                            data-unit="<?= esc($ingredient['unit_of_measure']) ?>"
                                            data-price="<?= esc(number_format($precio, 4, '.', '')) ?>"
                                            data-stock="<?= esc(number_format((float) $ingredient['current_stock'], 3, '.', '')) ?>">
                                        <?= esc($ingredient['name']) ?> (<?= esc($ingredient['unit_of_measure']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="px-5 py-3">
                            <input type="number" name="quantity[]" step="0.001" min="0" placeholder="0" class="form-input line-qty" required>
                        </td>
                        <td class="px-5 py-3">
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                                <input type="number" name="unit_price[]" step="0.0001" min="0" placeholder="0.00" class="form-input line-price pl-7" required>
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
            <?php endif; ?>

            <div class="border-t border-gray-100 px-6 py-4 flex justify-end">
                <div class="text-right">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Total</p>
                    <p id="totalLabel" class="text-2xl font-bold text-orange-500">
                        <?= esc($currency . number_format((float) $purchase['total'], 2)) ?>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($editable): ?>
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 px-6 py-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                    </button>
                    <a href="<?= url('purchases') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Volver</a>
                </div>
                <p class="text-xs text-gray-400">Al guardar se reemplazan todas las líneas de la orden.</p>
            </div>
        <?php endif; ?>
    </form>

    <!-- Recepción de mercancía -->
    <?php if (in_array($purchase['state'], ['pendiente', 'parcial'], true)): ?>
        <div class="rounded-2xl border border-orange-200 bg-white shadow-xl shadow-orange-100 overflow-hidden">
            <div class="border-b border-orange-100 bg-gradient-to-r from-orange-50 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box-open text-orange-500 mr-2"></i>Recibir mercancía</h2>
                <p class="text-xs text-gray-500 mt-1">Registra lo que realmente llegó. Suma el stock, actualiza el costo del ingrediente y guarda el historial de precios.</p>
            </div>

            <form action="<?= url('purchases/receive/' . $id) ?>" method="POST" class="px-6 py-5">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                                <th class="px-3 py-3 font-semibold">Ingrediente</th>
                                <th class="px-3 py-3 font-semibold text-right">Pedido</th>
                                <th class="px-3 py-3 font-semibold text-right">Ya recibido</th>
                                <th class="px-3 py-3 font-semibold text-right">Pendiente</th>
                                <th class="px-3 py-3 font-semibold w-40 text-right">Recibido ahora</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $detalle):
                                $ingredienteId = (int) $detalle['ingredient_id'];
                                $yaRecibido = (float) ($recibidoPorIngrediente[$ingredienteId] ?? 0);
                                $faltante = max(0, (float) $detalle['quantity'] - $yaRecibido);
                            ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-3 py-3 text-gray-900">
                                        <?= esc($detalle['ingredient_name']) ?>
                                        <span class="text-xs text-gray-400">(<?= esc($detalle['unit_of_measure']) ?>)</span>
                                    </td>
                                    <td class="px-3 py-3 text-right text-gray-600">
                                        <?= esc(number_format((float) $detalle['quantity'], 3, '.', '')) ?>
                                    </td>
                                    <td class="px-3 py-3 text-right text-gray-600">
                                        <?= esc(number_format($yaRecibido, 3, '.', '')) ?>
                                    </td>
                                    <td class="px-3 py-3 text-right font-semibold <?= $faltante > 0 ? 'text-amber-600' : 'text-green-600' ?>">
                                        <?= esc(number_format($faltante, 3, '.', '')) ?>
                                    </td>
                                    <td class="px-3 py-3">
                                        <input type="number" name="received[<?= (int) $detalle['id'] ?>]" step="0.001" min="0"
                                               value="<?= esc(number_format($faltante, 3, '.', '')) ?>"
                                               class="form-input text-right">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-5">
                    <label for="observations" class="form-label">Observaciones</label>
                    <textarea id="observations" name="observations" rows="2" maxlength="500" class="form-input resize-y"
                              placeholder="Factura, estado del empaque, faltantes..."></textarea>
                </div>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-green-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-green-500/25 hover:bg-green-600 hover:shadow-green-500/40 hover:-translate-y-px transition-all">
                        <i class="fa-solid fa-box-open"></i> Registrar Recepción
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <!-- Recepciones registradas -->
    <?php if ($receipts): ?>
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-green-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-clipboard-check text-green-500 mr-2"></i>Recepciones registradas</h2>
                <p class="text-xs text-gray-500 mt-1">
                    Total recibido: <strong><?= esc($currency . number_format($totalRecibido, 2)) ?></strong>
                    de <strong><?= esc($currency . number_format((float) $purchase['total'], 2)) ?></strong>.
                </p>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($receipts as $recibo): ?>
                    <details class="group px-6 py-4">
                        <summary class="cursor-pointer list-none flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <i class="fa-solid fa-box-open text-green-500"></i>
                                <div class="min-w-0">
                                    <span class="font-semibold text-gray-900 block truncate">Recepción #<?= (int) $recibo['id'] ?></span>
                                    <span class="text-xs text-gray-400">
                                        <?= esc(date('d/m/Y H:i', strtotime($recibo['receipt_date']))) ?> ·
                                        <?= esc(trim($recibo['employee_name'] . ' ' . $recibo['employee_last_name'])) ?>
                                    </span>
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-down text-gray-300 group-open:rotate-180 transition-transform"></i>
                        </summary>
                        <div class="mt-3 pl-8">
                            <?php if ($recibo['observations']): ?>
                                <p class="text-xs text-gray-500 mb-2 italic"><?= esc($recibo['observations']) ?></p>
                            <?php endif; ?>
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-gray-400 uppercase tracking-wider">
                                        <th class="py-1.5 font-semibold">Ingrediente</th>
                                        <th class="py-1.5 font-semibold text-right">Esperado</th>
                                        <th class="py-1.5 font-semibold text-right">Recibido</th>
                                        <th class="py-1.5 font-semibold text-right">Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recibo['items'] as $item):
                                        $diferencia = (float) $item['received_quantity'] - (float) $item['expected_quantity'];
                                    ?>
                                        <tr class="border-t border-gray-50">
                                            <td class="py-1.5 text-gray-700"><?= esc($item['ingredient_name']) ?></td>
                                            <td class="py-1.5 text-right text-gray-500"><?= esc(number_format((float) $item['expected_quantity'], 3, '.', '')) ?></td>
                                            <td class="py-1.5 text-right font-semibold text-gray-900"><?= esc(number_format((float) $item['received_quantity'], 3, '.', '')) ?></td>
                                            <td class="py-1.5 text-right <?= abs($diferencia) < 0.0005 ? 'text-gray-400' : ($diferencia < 0 ? 'text-amber-600' : 'text-green-600') ?>">
                                                <?= esc(($diferencia > 0 ? '+' : '') . number_format($diferencia, 3, '.', '')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Acciones sobre la orden -->
    <?php if (in_array($purchase['state'], ['pendiente', 'parcial'], true)): ?>
        <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="btn-action btn-toggle px-4 py-2.5 rounded-xl text-red-500 hover:bg-red-50"
                    title="Cancelar la orden"
                    data-url="<?= url('purchases/cancel/' . $id) ?>"
                    data-name="la orden de compra #<?= (int) $id ?>">
                <i class="fa-solid fa-ban"></i> Cancelar orden
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    const CURRENCY = <?= json_encode($currency, JSON_UNESCAPED_UNICODE) ?>;
    const body = document.getElementById('linesBody');
    const totalLabel = document.getElementById('totalLabel');
    const editable = <?= $editable ? 'true' : 'false' ?>;

    function money(value) {
        return CURRENCY + Number(value || 0).toFixed(2);
    }

    function recalc() {
        let total = 0;

        body.querySelectorAll('tr').forEach(function (row, index) {
            const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
            const price = parseFloat(row.querySelector('.line-price').value) || 0;

            row.querySelector('.line-subtotal').textContent = money(qty * price);
            row.querySelector('.line-number').textContent = index + 1;
            total += qty * price;
        });

        if (totalLabel) totalLabel.textContent = money(total);
    }

    body.addEventListener('input', recalc);

    if (editable) {
        const template = document.getElementById('lineTemplate');

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
                row.querySelector('.line-ingredient').value = '';
                row.querySelector('.line-qty').value = '';
                row.querySelector('.line-price').value = '';
            }
            recalc();
        });

        body.addEventListener('change', function (event) {
            const select = event.target.closest('.line-ingredient');
            if (!select) return;

            const option = select.options[select.selectedIndex];

            if (option && option.value) {
                select.closest('tr').querySelector('.line-price').value =
                    parseFloat(option.dataset.price || '0').toFixed(4);
            }
            recalc();
        });
    }

    recalc();
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
