<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php require_once __DIR__ . '/../partials/audit_acciones.php'; ?>

<?php
function auditPerson($item)
{
    if (!empty($item['user_name'])) {
        return trim($item['user_name'] . ' ' . ($item['user_last_name'] ?? ''));
    }
    return '—';
}

function auditValue($value)
{
    if (is_array($value)) {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    if ($value === null || $value === '') {
        return '';
    }
    return (string) $value;
}

/**
 * Construye el HTML del diff de datos para el modal: resalta únicamente los
 * campos que cambiaron, con el valor nuevo en verde y el anterior en gris.
 */
function auditDiffHtml($oldData, $newData, $action)
{
    $old = is_array($oldJson = json_decode((string) $oldData, true)) ? $oldJson : [];
    $new = is_array($newJson = json_decode((string) $newData, true)) ? $newJson : [];

    $old = array_diff_key($old, array_flip(['password_hash', 'has_login']));
    $new = array_diff_key($new, array_flip(['password_hash', 'has_login']));

    $isCreate = in_array($action, ['create', 'sale'], true);
    $isDelete = $action === 'delete';

    $fields = [];
    if ($isCreate) {
        foreach ($new as $key => $value) {
            $fields[$key] = [$key, '', auditValue($value)];
        }
    } elseif ($isDelete) {
        foreach ($old as $key => $value) {
            $fields[$key] = [$key, auditValue($value), ''];
        }
    } else {
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $oldVal = array_key_exists($key, $old) ? auditValue($old[$key]) : '';
            $newVal = array_key_exists($key, $new) ? auditValue($new[$key]) : '';
            if ($oldVal === $newVal) {
                continue;
            }
            $fields[$key] = [$key, $oldVal, $newVal];
        }
    }

    if (empty($fields)) {
        return '<div class="rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-4 text-sm text-gray-500"><i class="fa-solid fa-circle-info mr-2"></i>Sin cambios registrados en este movimiento.</div>';
    }

    $html = '<div class="mt-4 pt-4 border-t border-gray-100 space-y-4">';
    $html .= '<span class="block text-xs font-bold uppercase tracking-wider text-gray-500">Cambios detectados</span>';
    foreach ($fields as [$key, $oldVal, $newVal]) {
        $html .= '<div class="rounded-xl ring-1 ring-gray-200 bg-white shadow-sm shadow-gray-200/40 p-4">'
            . '<span class="block text-xs font-bold uppercase tracking-wider text-gray-600 mb-2 break-words">' . esc($key) . '</span>';

        if ($isCreate) {
            $html .= '<div class="rounded-xl bg-green-50 ring-1 ring-green-200 px-4 py-3">'
                . '<span class="block text-xs font-semibold text-green-700 mb-1"><i class="fa-solid fa-circle-check mr-1"></i>Valor nuevo</span>'
                . '<span class="block text-sm font-semibold text-green-900 leading-snug break-words" style="white-space:pre-wrap;word-break:break-word;">' . esc($newVal === '' ? '—' : $newVal) . '</span>'
                . '</div>';
        } elseif ($isDelete) {
            $html .= '<div class="rounded-xl bg-gray-100 ring-1 ring-gray-200 px-4 py-3">'
                . '<span class="block text-xs font-semibold text-gray-500 mb-1"><i class="fa-solid fa-circle-minus mr-1"></i>Valor anterior</span>'
                . '<span class="block text-sm text-gray-600 leading-snug break-words" style="white-space:pre-wrap;word-break:break-word;">' . esc($oldVal === '' ? '—' : $oldVal) . '</span>'
                . '</div>';
        } else {
            $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">'
                . '<div class="rounded-xl bg-gray-100/90 ring-1 ring-gray-200 px-4 py-3">'
                . '<span class="block text-xs font-semibold text-gray-500 mb-1"><i class="fa-solid fa-arrow-right-to-bracket mr-1"></i>Antes</span>'
                . '<span class="block text-sm text-gray-600 leading-snug break-words" style="white-space:pre-wrap;word-break:break-word;">' . esc($oldVal === '' ? '—' : $oldVal) . '</span>'
                . '</div>'
                . '<div class="rounded-xl bg-green-50 ring-1 ring-green-200 px-4 py-3">'
                . '<span class="block text-xs font-semibold text-green-700 mb-1"><i class="fa-solid fa-circle-check mr-1"></i>Después</span>'
                . '<span class="block text-sm font-semibold text-green-900 leading-snug break-words" style="white-space:pre-wrap;word-break:break-word;">' . esc($newVal === '' ? '—' : $newVal) . '</span>'
                . '</div>'
                . '</div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
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
                    ], JSON_UNESCAPED_UNICODE);
                    $diffHtml = auditDiffHtml($item['old_data'], $item['new_data'], $item['action']);
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
                                    data-detail='<?= esc($detail) ?>'
                                    data-detail-html="<?= esc($diffHtml) ?>">
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