<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$hoy = date('Y-m-d');
$puedeCrear = puede('promotions', 'create');
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Promociones</h1>
        <p class="text-sm text-gray-400 mt-1">Descuentos por porcentaje, 2x1 y cupones sobre productos.</p>
    </div>
    <?php if ($puedeCrear): ?>
        <a href="<?= url('promotions/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
            <i class="fa-solid fa-plus"></i> Nueva Promoción
        </a>
    <?php endif; ?>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-1 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar promoción...">
    </div>
    <div>
        <select data-filter="type" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los tipos</option>
            <?php foreach (Promocion::tipos() as $clave => $t): ?>
                <option value="<?= esc($clave) ?>"><?= esc($t['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <select id="filterStatus" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <option value="active">Activa</option>
            <option value="inactive">Inactiva</option>
        </select>
    </div>
</div>

<!-- Tabla -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Promoción</th>
                    <th class="px-5 py-3 font-semibold">Descuento</th>
                    <th class="px-5 py-3 font-semibold">Vigencia</th>
                    <th class="px-5 py-3 font-semibold text-center">Productos</th>
                    <th class="px-5 py-3 font-semibold">Cupón</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($promotions as $item):
                    $vigente = $item['status'] === 'active'
                        && $item['start_date'] <= $hoy && $hoy <= $item['end_date'];

                    if ($item['promotion_type'] === 'dos_por_uno') {
                        $descuento = '<span class="font-bold text-red-600">2x1</span>';
                    } elseif ($item['promotion_type'] === 'cupon') {
                        $descuento = '<span class="text-gray-500">Cupón</span>';
                    } elseif ($item['discount_percentage'] !== null) {
                        $descuento = '<span class="font-bold text-green-600">-' . number_format((float) $item['discount_percentage'], 0) . '%</span>';
                    } else {
                        $descuento = '<span class="text-gray-300">—</span>';
                    }

                    $horario = '';
                    if ($item['start_time'] || $item['end_time']) {
                        $horario = ' · ' . substr((string) $item['start_time'], 0, 5) . '–' . substr((string) $item['end_time'], 0, 5);
                    }
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= $item['status'] === 'active' ? 'active' : 'inactive' ?>"
                    data-type="<?= esc($item['promotion_type']) ?>"
                    data-search="<?= esc(strtolower(trim($item['name'] . ' ' . Promocion::tipoTexto($item['promotion_type']) . ' ' . ($item['first_code'] ?? '')))) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-green-100 to-green-50 text-green-600 flex items-center justify-center shrink-0 ring-1 ring-green-100 shadow-sm">
                                <i class="fa-solid fa-percent text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-900 block truncate"><?= esc($item['name']) ?></span>
                                <span class="text-xs text-gray-400"><?= esc(Promocion::tipoTexto($item['promotion_type'])) ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5"><?= $descuento ?></td>
                    <td class="px-5 py-3.5">
                        <span class="text-gray-600 block whitespace-nowrap"><?= date('d/m/Y', strtotime($item['start_date'])) ?> → <?= date('d/m/Y', strtotime($item['end_date'])) ?><?= esc($horario) ?></span>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $vigente ? 'bg-green-50 text-green-600' : 'bg-gray-100 text-gray-500' ?>">
                            <?= $vigente ? 'Vigente' : 'Fuera de rango' ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="inline-flex items-center justify-center min-w-[30px] px-2 h-7 rounded-lg bg-gray-100 text-gray-700 text-xs font-bold"><?= (int) $item['product_count'] ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <?php if (!empty($item['first_code'])): ?>
                            <span class="inline-flex items-center gap-1 rounded-lg bg-blue-50 border border-blue-100 px-2 py-1 font-mono text-xs font-bold text-blue-700">
                                <i class="fa-solid fa-ticket text-[10px]"></i><?= esc($item['first_code']) ?>
                            </span>
                            <?php if ((int) $item['coupons_count'] > 1): ?>
                                <span class="text-xs text-gray-400 ml-1">+<?= (int) $item['coupons_count'] - 1 ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <button type="button"
                                class="btn-toggle group inline-flex items-center gap-2.5 rounded-full px-2 py-1 -mx-2 transition-colors hover:bg-gray-100"
                                title="<?= $item['status'] === 'active' ? 'Desactivar promoción' : 'Activar promoción' ?>"
                                data-url="<?= url('promotions/toggle/' . $item['id']) ?>"
                                data-name="<?= esc($item['name']) ?>"
                                data-state="<?= esc($item['status']) ?>">
                            <span class="<?= $item['status'] === 'active' ? 'text-green-600' : 'text-gray-400' ?> text-xs font-semibold transition-colors">
                                <?= $item['status'] === 'active' ? 'Activa' : 'Inactiva' ?>
                            </span>
                            <span class="<?= $item['status'] === 'active' ? 'bg-green-500' : 'bg-gray-300' ?> relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors">
                                <span class="<?= $item['status'] === 'active' ? 'translate-x-[18px]' : 'translate-x-[2px]' ?> inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"></span>
                            </span>
                        </button>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <a class="btn-action" title="Editar" href="<?= url('promotions/edit/' . $item['id']) ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <?php if ($item['promotion_type'] === 'cupon' || $item['coupons_count'] > 0): ?>
                                <a class="btn-action text-blue-500 hover:bg-blue-50" title="Cupones" href="<?= url('promotions/cupones/' . $item['id']) ?>">
                                    <i class="fa-solid fa-ticket"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!$item['coupons_count']): ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('promotions/delete/' . $item['id']) ?>"
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
        <i class="fa-solid fa-percent block text-3xl mb-2 text-gray-300"></i>
        No hay promociones que coincidan con la búsqueda.
    </p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>