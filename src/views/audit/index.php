<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$actionMeta = [
    'login'        => ['label' => 'Inicio de sesión', 'color' => 'bg-blue-50 text-blue-600', 'icon' => 'fa-right-to-bracket'],
    'login_failed' => ['label' => 'Acceso fallido', 'color' => 'bg-red-50 text-red-600', 'icon' => 'fa-triangle-exclamation'],
    'logout'       => ['label' => 'Cierre de sesión', 'color' => 'bg-gray-100 text-gray-500', 'icon' => 'fa-right-from-bracket'],
    'create'       => ['label' => 'Creación', 'color' => 'bg-green-50 text-green-600', 'icon' => 'fa-plus'],
    'update'       => ['label' => 'Modificación', 'color' => 'bg-amber-50 text-amber-600', 'icon' => 'fa-pen'],
    'toggle'       => ['label' => 'Cambio de estado', 'color' => 'bg-violet-50 text-violet-600', 'icon' => 'fa-toggle-on'],
    'delete'       => ['label' => 'Eliminación', 'color' => 'bg-red-50 text-red-600', 'icon' => 'fa-trash-can'],
    'sale'         => ['label' => 'Venta', 'color' => 'bg-teal-50 text-teal-600', 'icon' => 'fa-cart-shopping'],
];

function auditActionMeta($action, $meta)
{
    return $meta[$action] ?? ['label' => $action, 'color' => 'bg-orange-50 text-orange-600', 'icon' => 'fa-circle-info'];
}

function auditPerson($item)
{
    if (!empty($item['user_name'])) {
        return trim($item['user_name'] . ' ' . ($item['user_last_name'] ?? ''));
    }
    return '—';
}

function auditChanges($json)
{
    if ($json === null || $json === '') {
        return '—';
    }
    $array = json_decode($json, true);
    if (!is_array($array) || empty($array)) {
        return '—';
    }
    $lines = [];
    foreach ($array as $key => $value) {
        if ($key === 'password_hash' || $key === 'has_login') {
            continue;
        }
        $display = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $lines[] = $key . ': ' . $display;
    }
    return implode("\n", $lines);
}
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Bitácora</h1>
    <span class="inline-flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-500">
        <i class="fa-solid fa-clipboard-list"></i> Historial de acciones del sistema
    </span>
</div>

<!-- Búsqueda y filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por persona, acción, módulo o descripción...">
    </div>
    <div>
        <select id="filterAction" data-filter="action" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todas las acciones</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= esc($action) ?>"><?= esc(auditActionMeta($action, $actionMeta)['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <select id="filterTable" data-filter="table" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todas las tablas</option>
            <?php foreach ($tables as $table): ?>
                <option value="<?= esc($table) ?>"><?= esc($table) ?></option>
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
                    <th class="px-5 py-3 font-semibold">Fecha y hora</th>
                    <th class="px-5 py-3 font-semibold">Acción</th>
                    <th class="px-5 py-3 font-semibold">Tabla / Registro</th>
                    <th class="px-5 py-3 font-semibold">Persona</th>
                    <th class="px-5 py-3 font-semibold">IP</th>
                    <th class="px-5 py-3 font-semibold">Descripción</th>
                    <th class="px-5 py-3 font-semibold text-right">Detalle</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($auditLogs as $item):
                    $meta = auditActionMeta($item['action'], $actionMeta);
                    $person = auditPerson($item);
                    $detail = json_encode([
                        'ID' => $item['id'],
                        'Acción' => $meta['label'],
                        'Tabla afectada' => $item['table_name'] ?: '—',
                        'Registro' => $item['record_id'] !== null ? '#' . $item['record_id'] : '—',
                        'Persona' => $person,
                        'Dirección IP' => $item['ip_address'] ?: '—',
                        'Fecha y hora' => date('d/m/Y H:i:s', strtotime($item['created_at'])),
                        'Datos anteriores' => auditChanges($item['old_data']),
                        'Datos nuevos' => auditChanges($item['new_data']),
                    ], JSON_UNESCAPED_UNICODE);
                    $searchable = strtolower(
                        $person . ' ' . $meta['label'] . ' ' . ($item['table_name'] ?? '')
                        . ' ' . ($item['description'] ?? '')
                        . ' ' . ($item['record_id'] ?? '')
                    );
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-action="<?= esc($item['action']) ?>"
                    data-table="<?= esc($item['table_name']) ?>"
                    data-search="<?= esc($searchable) ?>">
                    <td class="px-5 py-3.5 whitespace-nowrap text-gray-500">
                        <span class="font-medium text-gray-700"><?= esc(date('d/m/Y', strtotime($item['created_at']))) ?></span>
                        <span class="block text-xs text-gray-400"><?= esc(date('H:i:s', strtotime($item['created_at']))) ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= esc($meta['color']) ?>">
                            <i class="fa-solid <?= esc($meta['icon']) ?>"></i> <?= esc($meta['label']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <?php if (!empty($item['table_name'])): ?>
                            <span class="font-mono text-xs font-semibold text-gray-700 bg-gray-100 rounded-md px-2 py-1"><?= esc($item['table_name']) ?></span>
                            <?php if ($item['record_id'] !== null): ?>
                                <span class="text-gray-400 text-xs ml-1">#<?= esc($item['record_id']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 text-gray-700 font-medium"><?= esc($person) ?></td>
                    <td class="px-5 py-3.5 text-gray-500"><?= esc($item['ip_address'] ?: '—') ?></td>
                    <td class="px-5 py-3.5 max-w-xs text-gray-600">
                        <?= esc($item['description'] ?: '—') ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="btn-action btn-detail" title="Ver detalle"
                                    data-title="Detalle de la acción"
                                    data-icon="fa-clipboard-list"
                                    data-detail='<?= esc($detail) ?>'>
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p id="emptyState" class="hidden text-center text-sm text-gray-400 py-10">
        <i class="fa-solid fa-clipboard-list block text-3xl mb-2 text-gray-300"></i>
        No hay registros que coincidan con la búsqueda.
    </p>
</div>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal" data-plain="1">
    <div class="modal-panel w-full max-w-4xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-xl" id="detailModalTitle">Detalle de la acción</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="px-7 py-6 bg-white" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>