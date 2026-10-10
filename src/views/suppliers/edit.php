<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>
<?php
$value = function ($key, $fallback = '') use ($supplier) {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $fallback;
};
$dias = explode(',', (string) ($supplier['availability_days'] ?? ''));
$currency = setting('currency', '$');
// Filas de "ingredientes que ofrece": si el POST viene (error de validación) se
// repinta lo enviado (nombres escritos); si no, las ofertas guardadas del proveedor.
$fmtPrecio = function ($p) {
    $v = number_format((float) $p, 4, '.', '');
    return rtrim(rtrim($v, '0'), '.') === '' ? '0' : rtrim(rtrim($v, '0'), '.');
};
$postedNames = isset($_POST['ingredient_name']) && is_array($_POST['ingredient_name']) ? $_POST['ingredient_name'] : null;
$ofertasRows = [];
if ($postedNames !== null) {
    $postedPrices = isset($_POST['unit_price']) && is_array($_POST['unit_price']) ? $_POST['unit_price'] : [];
    foreach ($postedNames as $i => $pNombre) {
        $nombre = trim((string) $pNombre);
        if ($nombre === '') {
            continue;
        }
        $unit = '';
        foreach ($ingredients as $ing) {
            if (Ingredient::normalizar($ing['name']) === Ingredient::normalizar($nombre)) {
                $unit = $ing['unit_of_measure'];
                break;
            }
        }
        $ofertasRows[] = [
            'ingredient_name' => $nombre,
            'unit_price' => isset($postedPrices[$i]) ? trim((string) $postedPrices[$i]) : '',
            'unit' => $unit,
        ];
    }
} else {
    foreach ($offers as $o) {
        $ofertasRows[] = [
            'ingredient_name' => $o['ingredient_name'],
            'unit_price' => $fmtPrecio($o['unit_price']),
            'unit' => $o['unit_of_measure'],
        ];
    }
}
// Catálogo para el autocompletado (datalist) y para la pista de unidad en JS.
$ofertasCatalogo = array_map(function ($ing) {
    return ['n' => $ing['name'], 'u' => $ing['unit_of_measure']];
}, $ingredients);
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Proveedor</h1>
    <a href="<?= url('suppliers') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5 flex items-center justify-between gap-4">
        <div>
            <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-truck text-orange-500 mr-2"></i><?= esc($supplier['name']) ?></h2>
            <p class="text-xs text-gray-400 mt-1">
                Registrado el <?= date('d/m/Y', strtotime($supplier['created_at'])) ?>
                &middot; Última actualización <?= date('d/m/Y H:i', strtotime($supplier['updated_at'])) ?>
            </p>
        </div>
        <?php if ($supplier['status'] === 'active'): ?>
            <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-600 ring-1 ring-green-100">Activo</span>
        <?php else: ?>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500 ring-1 ring-gray-200">Inactivo</span>
        <?php endif; ?>
    </div>
    <form action="<?= url('suppliers/update/' . $supplier['id']) ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Empresa <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= $value('name', esc($supplier['name'])) ?>"
                   placeholder="Ej: Harinera Central" required maxlength="120">
        </div>

        <div>
            <label for="tax_id" class="form-label">NIT / RUC</label>
            <input type="text" id="tax_id" name="tax_id" class="form-input" value="<?= $value('tax_id', esc($supplier['tax_id'])) ?>"
                   placeholder="Ej: 0614-2233345-6" maxlength="50">
        </div>

        <div>
            <label for="supplier_type" class="form-label">Tipo de proveedor</label>
            <select id="supplier_type" name="supplier_type" class="form-input">
                <option value="">Selecciona un tipo...</option>
                <?php $tipoActual = $value('supplier_type', $supplier['supplier_type']); ?>
                <?php if ($tipoActual !== '' && !in_array($tipoActual, Supplier::tipos(), true)): ?>
                    <option value="<?= esc($tipoActual) ?>" selected><?= esc($tipoActual) ?></option>
                <?php endif; ?>
                <?php foreach (Supplier::tipos() as $tipo): ?>
                    <option value="<?= esc($tipo) ?>" <?= $tipoActual === $tipo ? 'selected' : '' ?>><?= esc($tipo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="contact" class="form-label">Encargado</label>
            <input type="text" id="contact" name="contact" class="form-input" value="<?= $value('contact', esc($supplier['contact'])) ?>"
                   placeholder="Ej: María López" maxlength="100">
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" class="form-input" value="<?= $value('phone', esc($supplier['phone'])) ?>"
                   placeholder="Ej: 2222-1111" maxlength="20">
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" class="form-input" value="<?= $value('email', esc($supplier['email'])) ?>"
                   placeholder="Ej: ventas@proveedor.com" maxlength="150">
        </div>

        <div class="md:col-span-2">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" class="form-input" value="<?= $value('address', esc($supplier['address'])) ?>"
                   placeholder="Ej: Av. Principal km 4, ruta al puerto" maxlength="255">
        </div>

        <div class="md:col-span-2">
            <label for="supplies" class="form-label">Producto a proveer</label>
            <input type="text" id="supplies" name="supplies" class="form-input" value="<?= $value('supplies', esc($supplier['supplies'])) ?>"
                   placeholder="Ej: Harina de trigo, levadura, kraft" maxlength="255">
            <p class="text-xs text-gray-400 mt-1.5">Separa varios productos con comas. Los ingredientes que ofrece y su precio se registran abajo.</p>
        </div>

        <!-- Ingredientes que ofrece (y su precio) -->
        <div class="md:col-span-2">
            <div class="rounded-xl border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between border-b border-gray-100 bg-gradient-to-r from-orange-50/60 to-white px-5 py-4">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900"><i class="fa-solid fa-basket-shopping text-orange-500 mr-2"></i>Ingredientes que ofrece</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Escribe cualquier producto que entregue este proveedor y el precio por unidad. Si no está en el catálogo, se agrega al guardar.</p>
                    </div>
                    <button type="button" id="addOffer"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-orange-500 px-3 py-1.5 text-xs font-semibold text-white shadow-md shadow-orange-500/25 hover:bg-orange-600 transition-all">
                        <i class="fa-solid fa-plus"></i> Agregar
                    </button>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                            <th class="px-5 py-2.5 font-semibold">Ingrediente</th>
                            <th class="px-5 py-2.5 font-semibold w-52">Precio</th>
                            <th class="px-3 py-2.5 font-semibold w-12"></th>
                        </tr>
                    </thead>
                    <tbody id="offersBody">
                        <?php if (!$ofertasRows): ?>
                            <tr class="offer-empty-row border-b border-gray-50 last:border-0 text-gray-400 text-sm">
                                <td class="px-5 py-4" colspan="3">Este proveedor aún no ha registrado ingredientes ofrecidos.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($ofertasRows as $fila): ?>
                            <tr class="border-b border-gray-50 last:border-0">
                                <td class="px-5 py-3">
                                    <input type="text" name="ingredient_name[]" class="form-input offer-ingredient"
                                           placeholder="Escribe el ingrediente…"
                                           autocomplete="off" maxlength="120"
                                           value="<?= esc($fila['ingredient_name']) ?>">
                                </td>
                                <td class="px-5 py-3">
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                                        <input type="number" name="unit_price[]" step="0.0001" min="0" placeholder="0.00"
                                               value="<?= esc($fila['unit_price']) ?>"
                                               class="form-input offer-price pl-7">
                                    </div>
                                    <p class="text-xs text-gray-400 mt-1.5 offer-unit"><?= $fila['unit'] !== '' ? 'Precio por ' . esc($fila['unit']) : 'Precio por unidad de medida' ?></p>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <button type="button" class="btn-action btn-remove-offer text-red-400 hover:bg-red-50" title="Quitar">
                                        <i class="fa-regular fa-trash-can"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <template id="offerTemplate">
                <tr class="border-b border-gray-50 last:border-0">
                    <td class="px-5 py-3">
                        <input type="text" name="ingredient_name[]" class="form-input offer-ingredient"
                               placeholder="Escribe el ingrediente…"
                               autocomplete="off" maxlength="120">
                    </td>
                    <td class="px-5 py-3">
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><?= esc($currency) ?></span>
                            <input type="number" name="unit_price[]" step="0.0001" min="0" placeholder="0.00"
                                   class="form-input offer-price pl-7">
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5 offer-unit">Precio por unidad de medida</p>
                    </td>
                    <td class="px-3 py-3 text-center">
                        <button type="button" class="btn-action btn-remove-offer text-red-400 hover:bg-red-50" title="Quitar">
                            <i class="fa-regular fa-trash-can"></i>
                        </button>
                    </td>
                </tr>
            </template>
        </div>

        <div class="md:col-span-2">
            <label class="form-label">Días de disponibilidad</label>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Supplier::dias() as $clave => $etiqueta): ?>
                    <label class="cursor-pointer">
                        <input type="checkbox" name="availability_days[]" value="<?= esc($clave) ?>"
                               class="peer sr-only" <?= in_array($clave, $dias, true) ? 'checked' : '' ?>>
                        <span class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-200">
                            <?= esc($etiqueta) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1.5">Días en los que el proveedor entrega o atiende.</p>
        </div>

        <div>
            <label for="payment_terms" class="form-label">Condiciones de pago</label>
            <input type="text" id="payment_terms" name="payment_terms" class="form-input"
                   value="<?= $value('payment_terms', esc($supplier['payment_terms'])) ?>"
                   placeholder="Ej: Contado, 30 días" maxlength="150">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= $value('status', $supplier['status']) === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $value('status', $supplier['status']) === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="form-label">Notas</label>
            <textarea id="notes" name="notes" rows="3" class="form-input resize-y"
                      placeholder="Horario de entrega, requisitos de almacenamiento, etc."><?= $value('notes', esc($supplier['notes'])) ?></textarea>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
            </button>
            <a href="<?= url('suppliers') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<script>
// Ofertas de ingredientes: agregar/quitar filas y sugerir la unidad escribiendo.
// El producto se escribe como texto; si coincide con el catálogo se muestra su
// unidad, si no, se aclara que se agregará al guardar (nunca se rechaza).
(function () {
    const body = document.getElementById('offersBody');
    const template = document.getElementById('offerTemplate');
    const addBtn = document.getElementById('addOffer');
    if (!body || !template || !addBtn) return;

    const CATALOGO = <?= json_encode($ofertasCatalogo, JSON_UNESCAPED_UNICODE) ?>;

    function normalizar(texto) {
        return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function realRows() {
        return Array.prototype.filter.call(body.querySelectorAll('tr'), function (row) {
            return !row.classList.contains('offer-empty-row');
        });
    }

    function repintarUnidades(row) {
        const input = row.querySelector('.offer-ingredient');
        const hint = row.querySelector('.offer-unit');
        const precio = row.querySelector('.offer-price');
        if (!input || !hint) return;

        const valor = input.value.trim();
        if (valor === '') {
            hint.textContent = 'Precio por unidad de medida';
            if (precio) precio.classList.remove('border-red-300');
            return;
        }

        const objetivo = normalizar(valor);
        const match = CATALOGO.find(function (ing) {
            return normalizar(ing.n) === objetivo;
        });

        if (match) {
            hint.textContent = 'Precio por ' + match.u;
            if (precio) precio.classList.remove('border-red-300');
        } else {
            hint.textContent = 'Se agregará al catálogo al guardar';
            if (precio) precio.classList.remove('border-red-300');
        }
    }

    body.addEventListener('input', function (event) {
        const input = event.target.closest('.offer-ingredient');
        if (input) repintarUnidades(input.closest('tr'));
    });

    body.addEventListener('click', function (event) {
        const remove = event.target.closest('.btn-remove-offer');
        if (!remove) return;
        const filas = realRows();
        if (filas.length > 1) {
            remove.closest('tr').remove();
        } else {
            const row = remove.closest('tr');
            row.querySelector('.offer-ingredient').value = '';
            row.querySelector('.offer-price').value = '';
            repintarUnidades(row);
        }
    });

    addBtn.addEventListener('click', function () {
        body.querySelectorAll('.offer-empty-row').forEach(function (row) { row.remove(); });
        const row = body.appendChild(template.content.cloneNode(true));
        repintarUnidades(row);
    });

    realRows().forEach(repintarUnidades);
})();
</script>