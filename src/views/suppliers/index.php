<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$tipos = [];
foreach ($suppliers as $item) {
    if (!empty($item['supplier_type'])) {
        $tipos[$item['supplier_type']] = true;
    }
}
$tipos = array_keys($tipos);
sort($tipos);

$currency = setting('currency', '$');
$fmtPrecio = function ($p) {
    $v = number_format((float) $p, 4, '.', '');
    return rtrim(rtrim($v, '0'), '.') === '' ? '0' : rtrim(rtrim($v, '0'), '.');
};
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Proveedores</h1>
    <a href="<?= url('suppliers/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
        <i class="fa-solid fa-plus"></i> Nuevo Proveedor
    </a>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-1 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar proveedor...">
    </div>
    <div>
        <select id="filterType" data-filter="type" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los tipos</option>
            <?php foreach ($tipos as $tipo): ?>
                <option value="<?= esc($tipo) ?>"><?= esc($tipo) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <select id="filterStatus" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <option value="active">Activo</option>
            <option value="inactive">Inactivo</option>
        </select>
    </div>
</div>

<!-- Tabla -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Empresa</th>
                    <th class="px-5 py-3 font-semibold">Encargado</th>
                    <th class="px-5 py-3 font-semibold">Producto a proveer</th>
                    <th class="px-5 py-3 font-semibold">Teléfono</th>
                    <th class="px-5 py-3 font-semibold">Días</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($suppliers as $item):
                    $dias = Supplier::diasTexto($item['availability_days']);
                    $ofertas = $offersBySupplier[(int) $item['id']] ?? [];
                    $ofertasTexto = '';
                    foreach ($ofertas as $oferta) {
                        $linea = $oferta['ingredient_name'] . ' — ' . $currency . $fmtPrecio($oferta['unit_price']) . '/' . $oferta['unit_of_measure'];
                        $ofertasTexto .= ($ofertasTexto === '' ? '' : "\n") . $linea;
                    }
                    $nombresOfertas = implode(' ', array_column($ofertas, 'ingredient_name'));
                    $summary = json_encode([
                        'Tipo' => $item['supplier_type'] ?: '—',
                        'Encargado' => $item['contact'] ?: '—',
                        'Teléfono' => $item['phone'] ?: '—',
                        'Correo' => $item['email'] ?: '—',
                    ], JSON_UNESCAPED_UNICODE);
                    $detail = json_encode([
                        'NIT / RUC' => $item['tax_id'] ?: '—',
                        'Dirección' => $item['address'] ?: '—',
                        'Producto a proveer' => $item['supplies'] ?: '—',
                        'Ingredientes y precios' => $ofertasTexto !== '' ? $ofertasTexto : '—',
                        'Días de disponibilidad' => $dias !== '' ? $dias : '—',
                        'Condiciones de pago' => $item['payment_terms'] ?: '—',
                        'Ingredientes que provee' => (int) $item['ingredients_count'],
                        'Pedidos de compra' => (int) $item['orders_count'],
                        'Notas' => $item['notes'] ?: '—',
                        'Fecha de registro' => date('d/m/Y', strtotime($item['created_at'])),
                        'Última actualización' => date('d/m/Y H:i', strtotime($item['updated_at'])),
                        'Estado' => $item['status'] === 'active' ? 'Activo' : 'Inactivo',
                    ], JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= esc($item['status']) ?>"
                    data-type="<?= esc($item['supplier_type']) ?>"
                    data-search="<?= esc(strtolower(trim($item['name'] . ' ' . ($item['supplier_type'] ?? '') . ' ' . ($item['contact'] ?? '') . ' ' . ($item['phone'] ?? '') . ' ' . ($item['email'] ?? '') . ' ' . ($item['address'] ?? '') . ' ' . ($item['supplies'] ?? '') . ' ' . ($nombresOfertas ?? '') . ' ' . ($dias ?? '')))) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center shrink-0 ring-1 ring-orange-100 shadow-sm">
                                <i class="fa-solid fa-truck text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-900 block truncate"><?= esc($item['name']) ?></span>
                                <span class="text-xs text-gray-400"><?= esc($item['supplier_type'] ?: 'Sin tipo') ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600"><?= esc($item['contact'] ?: '—') ?></td>
                    <td class="px-5 py-3.5 text-gray-500 max-w-[220px] truncate" title="<?= esc($item['supplies']) ?>">
                        <?= esc($item['supplies'] ?: '—') ?>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap"><?= esc($item['phone'] ?: '—') ?></td>
                    <td class="px-5 py-3.5 text-gray-500">
                        <?php if ($dias !== ''): ?>
                            <span class="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-600 whitespace-nowrap">
                                <?= esc($dias) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <button type="button"
                                class="btn-toggle group inline-flex items-center gap-2.5 rounded-full px-2 py-1 -mx-2 transition-colors hover:bg-gray-100"
                                title="<?= $item['status'] === 'active' ? 'Desactivar proveedor' : 'Activar proveedor' ?>"
                                data-url="<?= url('suppliers/toggle/' . $item['id']) ?>"
                                data-name="<?= esc($item['name']) ?>"
                                data-state="<?= esc($item['status']) ?>">
                            <span class="<?= $item['status'] === 'active' ? 'text-green-600' : 'text-gray-400' ?> text-xs font-semibold transition-colors">
                                <?= $item['status'] === 'active' ? 'Activo' : 'Inactivo' ?>
                            </span>
                            <span class="<?= $item['status'] === 'active' ? 'bg-green-500' : 'bg-gray-300' ?> relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                <span class="<?= $item['status'] === 'active' ? 'translate-x-[18px]' : 'translate-x-[2px]' ?> inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"></span>
                            </span>
                        </button>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="btn-action btn-detail" title="Ver detalle"
                                    data-title="<?= esc($item['name']) ?>"
                                    data-icon="fa-truck"
                                    data-summary='<?= esc($summary) ?>'
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <a class="btn-action" title="Editar" href="<?= url('suppliers/edit/' . $item['id']) ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <?php if ($item['status'] === 'active'): ?>
                                <button type="button" class="btn-action btn-toggle text-orange-500 hover:bg-orange-50"
                                        title="Desactivar"
                                        data-url="<?= url('suppliers/toggle/' . $item['id']) ?>"
                                        data-name="<?= esc($item['name']) ?>">
                                    <i class="fa-solid fa-toggle-off"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('suppliers/delete/' . $item['id']) ?>"
                                        data-name="<?= esc($item['name']) ?>">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p id="emptyState" class="hidden text-center text-sm text-gray-400 py-10">
        <i class="fa-solid fa-truck block text-3xl mb-2 text-gray-300"></i>
        No hay proveedores que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-2xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg" id="detailModalTitle">Detalle del proveedor</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>