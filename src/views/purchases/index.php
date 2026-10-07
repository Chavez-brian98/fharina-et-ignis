<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
// Un poco de contexto para los cards de resumen de arriba.
$totales = ['pendiente' => 0, 'parcial' => 0, 'recibida' => 0, 'cancelada' => 0];
$sumaPendiente = 0.0;
$sumaRecibida = 0.0;

foreach ($purchases as $item) {
    if (isset($totales[$item['state']])) {
        $totales[$item['state']]++;
    }
    if ($item['state'] === 'pendiente' || $item['state'] === 'parcial') {
        $sumaPendiente += (float) $item['total'];
    }
    if ($item['state'] === 'recibida') {
        $sumaRecibida += (float) $item['total'];
    }
}

$currency = setting('currency', '$');
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Compras</h1>
    <a href="<?= url('purchases/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
        <i class="fa-solid fa-plus"></i> Nueva Compra
    </a>
</div>

<!-- Resumen -->
<div class="mb-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
        <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Pendientes</p>
        <p class="mt-1 text-2xl font-bold text-gray-900"><?= (int) $totales['pendiente'] ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= esc($currency . number_format($sumaPendiente, 2)) ?> en curso</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
        <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Recibidas parciales</p>
        <p class="mt-1 text-2xl font-bold text-sky-600"><?= (int) $totales['parcial'] ?></p>
        <p class="text-xs text-gray-400 mt-0.5">faltan mercancía</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
        <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Recibidas</p>
        <p class="mt-1 text-2xl font-bold text-green-600"><?= (int) $totales['recibida'] ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= esc($currency . number_format($sumaRecibida, 2)) ?> procesados</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
        <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Canceladas</p>
        <p class="mt-1 text-2xl font-bold text-gray-400"><?= (int) $totales['cancelada'] ?></p>
        <p class="text-xs text-gray-400 mt-0.5">descartadas</p>
    </div>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-1 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar compra, proveedor o ingrediente...">
    </div>
    <div>
        <select id="filterState" data-filter="state" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <?php foreach (Purchase::estados() as $clave => $meta): ?>
                <option value="<?= esc($clave) ?>"><?= esc($meta['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <select id="filterSupplier" data-filter="supplier" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los proveedores</option>
            <?php foreach ($suppliers as $supplier): ?>
                <option value="<?= esc($supplier['id']) ?>"><?= esc($supplier['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Tabla -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Orden</th>
                    <th class="px-5 py-3 font-semibold">Proveedor</th>
                    <th class="px-5 py-3 font-semibold">Fecha</th>
                    <th class="px-5 py-3 font-semibold">Entrega estimada</th>
                    <th class="px-5 py-3 font-semibold">Líneas</th>
                    <th class="px-5 py-3 font-semibold">Total</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($purchases as $item):
                    $detail = json_encode([
                        'Proveedor' => $item['supplier_name'],
                        'Registró' => trim($item['employee_name'] . ' ' . $item['employee_last_name']),
                        'Fecha de la orden' => date('d/m/Y', strtotime($item['order_date'])),
                        'Entrega estimada' => $item['estimated_delivery_date']
                            ? date('d/m/Y', strtotime($item['estimated_delivery_date']))
                            : '—',
                        'Líneas' => (int) $item['items_count'],
                        'Recepciones registradas' => (int) $item['receipts_count'],
                        'Total' => $currency . number_format((float) $item['total'], 2),
                        'Estado' => Purchase::estadoTexto($item['state']),
                    ], JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= esc($item['state']) ?>"
                    data-state="<?= esc($item['state']) ?>"
                    data-supplier="<?= esc($item['supplier_id']) ?>"
                    data-search="<?= esc(strtolower(trim('#' . $item['id'] . ' ' . $item['supplier_name'] . ' ' . Purchase::estadoTexto($item['state'])))) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center shrink-0 ring-1 ring-orange-100 shadow-sm">
                                <i class="fa-solid fa-clipboard-list text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-900 block truncate">Compra #<?= (int) $item['id'] ?></span>
                                <span class="text-xs text-gray-400"><?= esc(trim($item['employee_name'] . ' ' . $item['employee_last_name'])) ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600"><?= esc($item['supplier_name']) ?></td>
                    <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap"><?= esc(date('d/m/Y', strtotime($item['order_date']))) ?></td>
                    <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">
                        <?= $item['estimated_delivery_date'] ? esc(date('d/m/Y', strtotime($item['estimated_delivery_date']))) : '—' ?>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600"><?= (int) $item['items_count'] ?></td>
                    <td class="px-5 py-3.5 text-gray-900 font-semibold whitespace-nowrap">
                        <?= esc($currency . number_format((float) $item['total'], 2)) ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="rounded-full <?= esc(Purchase::estadoColor($item['state'])) ?> px-2.5 py-1 text-xs font-semibold whitespace-nowrap">
                            <?= esc(Purchase::estadoTexto($item['state'])) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="btn-action btn-detail" title="Ver detalle"
                                    data-title="Compra #<?= (int) $item['id'] ?>"
                                    data-icon="fa-clipboard-list"
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <?php if ($item['state'] === 'pendiente'): ?>
                                <a class="btn-action" title="Editar" href="<?= url('purchases/edit/' . $item['id']) ?>">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (in_array($item['state'], ['pendiente', 'parcial'], true)): ?>
                                <a class="btn-action btn-toggle text-green-500 hover:bg-green-50"
                                   title="Recibir mercancía"
                                   href="<?= url('purchases/edit/' . $item['id']) ?>">
                                    <i class="fa-solid fa-box-open"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($item['state'] !== 'cancelada' && $item['state'] !== 'recibida'): ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('purchases/delete/' . $item['id']) ?>"
                                        data-name="la orden de compra #<?= (int) $item['id'] ?>">
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
        <i class="fa-solid fa-clipboard-list block text-3xl mb-2 text-gray-300"></i>
        No hay compras que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-2xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg" id="detailModalTitle">Detalle de la compra</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
