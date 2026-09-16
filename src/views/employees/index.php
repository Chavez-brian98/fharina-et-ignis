<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Empleados</h1>
    <a href="<?= url('employees/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
        <i class="fa-solid fa-plus"></i> Nuevo Empleado
    </a>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por nombre, DUI, cargo, correo o usuario...">
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
                    <th class="px-5 py-3 font-semibold">Empleado</th>
                    <th class="px-5 py-3 font-semibold">DUI</th>
                    <th class="px-5 py-3 font-semibold">Cargo</th>
                    <th class="px-5 py-3 font-semibold">Teléfono</th>
                    <th class="px-5 py-3 font-semibold">Salario base</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($employees as $item):
                    $fullName = $item['name'] . ($item['last_name'] ? ' ' . $item['last_name'] : '');
                    $detail = json_encode([
                        'ID' => $item['id'],
                        'Nombre' => $fullName,
                        'DUI' => $item['id_document'],
                        'Cargo' => $item['position'],
                        'Usuario' => $item['username'] ?: '—',
                        'Correo' => $item['email'] ?: '—',
                        'Rol' => $item['role_name'] ? ucfirst($item['role_name']) : 'Sin cuenta de acceso',
                        'Teléfono' => $item['phone'] ?: '—',
                        'Dirección' => $item['address'] ?: '—',
                        'Fecha de nacimiento' => $item['birth_date'] ? date('d/m/Y', strtotime($item['birth_date'])) : '—',
                        'Fecha de contratación' => date('d/m/Y', strtotime($item['hire_date'])),
                        'Salario base' => '$' . number_format($item['base_salary'], 2),
                        'Estado' => $item['status'] === 'active' ? 'Activo' : 'Inactivo',
                    ], JSON_UNESCAPED_UNICODE);
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= esc($item['status']) ?>"
                    data-search="<?= esc(strtolower($fullName . ' ' . $item['id_document'] . ' ' . $item['position'] . ' ' . ($item['phone'] ?? '') . ' ' . ($item['username'] ?? '') . ' ' . ($item['email'] ?? '') . ' ' . ($item['role_name'] ?? ''))) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center shrink-0 ring-1 ring-orange-100 shadow-sm">
                                <i class="fa-solid fa-user-tie text-sm"></i>
                            </div>
                            <span class="font-semibold text-gray-900"><?= esc($fullName) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="font-mono text-xs font-semibold text-gray-700 bg-gray-100 rounded-md px-2 py-1"><?= esc($item['id_document']) ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-600"><?= esc($item['position']) ?></span>
                    </td>
                    <td class="px-5 py-3.5 text-gray-600"><?= esc($item['phone']) ?: '—' ?></td>
                    <td class="px-5 py-3.5 font-semibold text-gray-900">$<?= number_format($item['base_salary'], 2) ?></td>
                    <td class="px-5 py-3.5">
                        <button type="button"
                                class="btn-toggle group inline-flex items-center gap-2.5 rounded-full px-2 py-1 -mx-2 transition-colors hover:bg-gray-100"
                                title="<?= $item['status'] === 'active' ? 'Desactivar empleado' : 'Activar empleado' ?>"
                                data-url="<?= url('employees/toggle/' . $item['id']) ?>"
                                data-name="<?= esc($fullName) ?>"
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
                                    data-title="<?= esc($fullName) ?>"
                                    data-icon="fa-user-tie"
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                            <a class="btn-action" title="Editar" href="<?= url('employees/edit/' . $item['id']) ?>">
                                <i class="fa-regular fa-pen-to-square"></i>
                            </a>
                            <?php if ($item['status'] === 'active'): ?>
                                <button type="button" class="btn-action btn-toggle text-orange-500 hover:bg-orange-50"
                                        title="Desactivar"
                                        data-url="<?= url('employees/toggle/' . $item['id']) ?>"
                                        data-name="<?= esc($fullName) ?>">
                                    <i class="fa-solid fa-toggle-off"></i>
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar"
                                        data-url="<?= url('employees/delete/' . $item['id']) ?>"
                                        data-name="<?= esc($fullName) ?>">
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
        <i class="fa-solid fa-user-tie block text-3xl mb-2 text-gray-300"></i>
        No hay empleados que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-3xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg" id="detailModalTitle">Detalle del empleado</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>