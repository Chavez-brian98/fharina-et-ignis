<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Categorías</h1>
    <a href="<?= url('categories/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
        <i class="fa-solid fa-plus"></i> Nueva Categoría
    </a>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por nombre o descripción...">
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
                    <th class="px-5 py-3 font-semibold">Categoría</th>
                    <th class="px-5 py-3 font-semibold">Descripción</th>
                    <th class="px-5 py-3 font-semibold">Orden</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($categories as $item):
                    $detail = json_encode([
                        'ID' => $item['id'],
                        'Nombre' => $item['name'],
                        'Descripción' => $item['description'] ?: '—',
                        'Orden de visualización' => $item['display_order'],
                        'Estado' => $item['status'] === 'active' ? 'Activo' : 'Inactivo',
                    ], JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= esc($item['status']) ?>"
                    data-search="<?= esc(strtolower($item['name'] . ' ' . ($item['description'] ?? ''))) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center shrink-0 ring-1 ring-orange-100 shadow-sm">
                                <i class="fa-solid fa-tags text-sm"></i>
                            </div>
                            <span class="font-semibold text-gray-900"><?= esc($item['name']) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-gray-500"><?= esc($item['description']) ?></td>
                    <td class="px-5 py-3.5 text-gray-600"><?= esc($item['display_order']) ?></td>
                    <td class="px-5 py-3.5">
                        <?php if ($item['status'] === 'active'): ?>
                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-600">Activo</span>
                        <?php else: ?>
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="btn-action btn-detail" title="Ver detalle"
                                    data-title="<?= esc($item['name']) ?>"
                                    data-icon="fa-tags"
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <a class="btn-action" title="Editar" href="<?= url('categories/edit/' . $item['id']) ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <?php if ($item['status'] === 'active'): ?>
                                <button type="button" class="btn-action btn-toggle text-orange-500 hover:bg-orange-50"
                                        title="Desactivar"
                                        data-url="<?= url('categories/toggle/' . $item['id']) ?>"
                                        data-name="<?= esc($item['name']) ?>">
                                    <i class="fa-solid fa-toggle-off"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('categories/delete/' . $item['id']) ?>"
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
        <i class="fa-solid fa-tags block text-3xl mb-2 text-gray-300"></i>
        No hay categorías que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg" id="detailModalTitle">Detalle de la categoría</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>