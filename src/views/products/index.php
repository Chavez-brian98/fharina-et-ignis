<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Productos</h1>
    <a href="<?= url('products/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
        <i class="fa-solid fa-plus"></i> Nuevo Producto
    </a>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por nombre, descripción o categoría...">
    </div>
    <div>
        <select id="filterCategory" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todas las categorías</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= esc($cat['id']) ?>"><?= esc($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="flex items-center gap-3">
        <select id="filterStatus" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <option value="active">Activo</option>
            <option value="inactive">Inactivo</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer shrink-0">
            <input type="checkbox" id="filterLowStock" class="search-filter h-4 w-4 rounded border-gray-300 text-orange-500 focus:ring-orange-400">
            <span class="whitespace-nowrap">Stock bajo</span>
        </label>
    </div>
</div>

<!-- Tabla -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Producto</th>
                    <th class="px-5 py-3 font-semibold">Categoría</th>
                    <th class="px-5 py-3 font-semibold">Precio venta</th>
                    <th class="px-5 py-3 font-semibold">Costo</th>
                    <th class="px-5 py-3 font-semibold">Ganancia</th>
                    <th class="px-5 py-3 font-semibold">Stock</th>
                    <th class="px-5 py-3 font-semibold">Mínimo</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($products as $item):
                    $lowStock = $item['stock'] <= $item['min_stock'];
                    $detail = json_encode([
                        'ID' => $item['id'],
                        'Nombre' => $item['name'],
                        'Categoría' => $item['category_name'],
                        'Descripción' => $item['description'] ?: '—',
                        'Precio de venta' => '$' . number_format($item['sale_price'], 2),
                        'Costo de producción' => '$' . number_format($item['production_cost'], 2),
                        'Ganancia' => '$' . number_format($item['sale_price'] - $item['production_cost'], 2),
                        'Stock' => $item['stock'] . ' ud.',
                        'Stock mínimo' => $item['min_stock'] . ' ud.',
                        'Estado' => $item['status'] === 'active' ? 'Activo' : 'Inactivo',
                    ], JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-category="<?= esc($item['category_id']) ?>"
                    data-status="<?= esc($item['status']) ?>"
                    data-lowstock="<?= $lowStock ? 1 : 0 ?>"
                    data-search="<?= esc(strtolower($item['name'] . ' ' . ($item['description'] ?? '') . ' ' . $item['category_name'])) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center shrink-0 ring-1 ring-orange-100 shadow-sm">
                                <i class="fa-solid fa-bread-slice text-sm"></i>
                            </div>
                            <span class="font-semibold text-gray-900"><?= esc($item['name']) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-500"><?= esc($item['category_name']) ?></td>
                    <td class="px-5 py-3.5 font-semibold text-gray-900">$<?= number_format($item['sale_price'], 2) ?></td>
                    <td class="px-5 py-3.5 text-gray-500">$<?= number_format($item['production_cost'], 2) ?></td>
                    <td class="px-5 py-3.5 font-semibold text-green-600">$<?= number_format($item['sale_price'] - $item['production_cost'], 2) ?></td>
                    <td class="px-5 py-3.5">
                        <?php if ($lowStock): ?>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600">
                                <i class="fa-solid fa-triangle-exclamation"></i> <?= esc($item['stock']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-600"><?= esc($item['stock']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-gray-500"><?= esc($item['min_stock']) ?></td>
                    <td class="px-5 py-3.5">
                        <button type="button"
                                class="btn-toggle group inline-flex items-center gap-2.5 rounded-full px-2 py-1 -mx-2 transition-colors hover:bg-gray-100"
                                title="<?= $item['status'] === 'active' ? 'Desactivar producto' : 'Activar producto' ?>"
                                data-url="<?= url('products/toggle/' . $item['id']) ?>"
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
                                    data-image="<?= esc($item['image_url'] ?? '') ?>"
                                    data-icon="fa-bread-slice"
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <a class="btn-action" title="Editar" href="<?= url('products/edit/' . $item['id']) ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <?php if ($item['status'] === 'active'): ?>
                                <button type="button" class="btn-action btn-toggle text-orange-500 hover:bg-orange-50"
                                        title="Desactivar"
                                        data-url="<?= url('products/toggle/' . $item['id']) ?>"
                                        data-name="<?= esc($item['name']) ?>">
                                    <i class="fa-solid fa-toggle-off"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('products/delete/' . $item['id']) ?>"
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
        <i class="fa-solid fa-box-open block text-3xl mb-2 text-gray-300"></i>
        No hay productos que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-4xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-xl" id="detailModalTitle">Detalle del producto</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="px-7 py-6 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>