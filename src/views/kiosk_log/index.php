<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
// Variables que llegan del KioskLogController::index.
$registros = $registros ?? [];
$resumen = $resumen ?? [];
$desde = $desde ?? '';
$hasta = $hasta ?? '';
$origen = $origen ?? 'kiosco';

$etiquetasOrigen = [
    'kiosco' => 'Quiosco (QR o rostro)',
    'manual' => 'Ajustes manuales',
    'todos' => 'Todas las marcaciones',
];

/**
 * Chip del metodo: si la entrada y la salida se identificaron igual se muestra
 * uno solo, y si difieren se pone "QR → Rostro".
 */
function kioskMetodos($r)
{
    $entrada = $r['check_in_method'] ?? null;
    $salida = $r['check_out_method'] ?? null;

    if ($entrada === null && $salida === null) {
        return ['texto' => '—', 'clase' => 'bg-gray-100 text-gray-500'];
    }
    if ($entrada !== null && $entrada === $salida) {
        return kioskMetodoChip($entrada);
    }

    $texto = ($entrada ? ucfirst($entrada) : '—') . ' → ' . ($salida ? ucfirst($salida) : '—');

    return ['texto' => $texto, 'clase' => 'bg-amber-50 text-amber-700'];
}

function kioskMetodoChip($metodo)
{
    $mapa = [
        'qr' => ['QR', 'bg-blue-50 text-blue-700', 'fa-qrcode'],
        'rostro' => ['Rostro', 'bg-purple-50 text-purple-700', 'fa-face-smile'],
        'manual' => ['Manual', 'bg-gray-100 text-gray-600', 'fa-keyboard'],
    ];
    $m = $mapa[$metodo] ?? [ucfirst((string) $metodo), 'bg-gray-100 text-gray-600', 'fa-circle-question'];

    return ['texto' => $m[0], 'clase' => $m[1], 'icono' => $m[2]];
}
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Registros del Quiosco</h1>
        <p class="text-sm text-gray-500 mt-1">
            Marcaciones de entrada, descanso y salida hechas en el quiosco.
        </p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-700">
        <i class="fa-solid fa-clipboard-user"></i>
        <?= esc(date('d/m/Y', strtotime($desde))) ?> &rarr; <?= esc(date('d/m/Y', strtotime($hasta))) ?>
    </span>
</div>

<!-- Rango de fechas y origen: vuelven a consultar la base -->
<form method="GET" action="<?= url('kiosk_log') ?>"
      class="mb-5 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-4">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="form-label" for="desde">Desde</label>
            <input type="date" id="desde" name="desde" value="<?= esc($desde) ?>" max="<?= esc($hasta) ?>"
                   class="form-input">
        </div>
        <div>
            <label class="form-label" for="hasta">Hasta</label>
            <input type="date" id="hasta" name="hasta" value="<?= esc($hasta) ?>"
                   class="form-input">
        </div>
        <div>
            <label class="form-label" for="origen">Origen</label>
            <select id="origen" name="origen" class="form-input">
                <?php foreach ($etiquetasOrigen as $clave => $texto): ?>
                    <option value="<?= esc($clave) ?>" <?= $origen === $clave ? 'selected' : '' ?>>
                        <?= esc($texto) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="flex-1 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                <i class="fa-solid fa-filter mr-1.5"></i>Aplicar
            </button>
            <a href="<?= url('kiosk_log') ?>"
               class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50">
                Limpiar
            </a>
        </div>
    </div>
    <p class="mt-3 text-xs text-gray-400">
        El rango máximo es de <?= (int) KioskLogController::DIAS_MAXIMOS ?> días.
        Los ajustes que hace Horarios sobre un día cerrado aparecen en «Ajustes manuales».
    </p>
</form>

<!-- Resumen -->
<div class="mb-5 grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Marcaciones</p>
        <p class="mt-1 text-2xl font-bold text-gray-900"><?= (int) $resumen['total'] ?></p>
        <p class="text-xs text-gray-400"><?= (int) $resumen['cerrados'] ?> jornada(s) cerrada(s)</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Empleados</p>
        <p class="mt-1 text-2xl font-bold text-gray-900"><?= (int) $resumen['empleados'] ?></p>
        <p class="text-xs text-gray-400">distintos en el período</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Por QR</p>
        <p class="mt-1 text-2xl font-bold text-blue-600"><?= (int) $resumen['qr'] ?></p>
        <p class="text-xs text-gray-400">entradas con código impreso</p>
    </div>
    <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-4">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Por rostro</p>
        <p class="mt-1 text-2xl font-bold text-purple-600"><?= (int) $resumen['rostro'] ?></p>
        <p class="text-xs text-gray-400">entradas biométricas</p>
    </div>
</div>

<!-- Búsqueda client-side -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por empleado, fecha, turno o notas...">
    </div>
    <div>
        <select id="filterMethod" data-filter="method"
                class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los métodos</option>
            <option value="qr">QR</option>
            <option value="rostro">Rostro</option>
            <option value="manual">Manual</option>
            <option value="mixto">Mixto</option>
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
                    <th class="px-5 py-3 font-semibold">Fecha</th>
                    <th class="px-5 py-3 font-semibold">Entrada</th>
                    <th class="px-5 py-3 font-semibold">Descanso</th>
                    <th class="px-5 py-3 font-semibold">Salida</th>
                    <th class="px-5 py-3 font-semibold">Horas</th>
                    <th class="px-5 py-3 font-semibold">Método</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Detalle</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($registros as $r):
                    $nombre = trim($r['name'] . ' ' . $r['last_name']);
                    $chip = kioskMetodos($r);
                    $metodoClave = $chip['texto'] === 'QR' ? 'qr'
                        : ($chip['texto'] === 'Rostro' ? 'rostro'
                            : ($chip['texto'] === 'Manual' ? 'manual' : 'mixto'));
                    $esHoy = $r['attendance_date'] === Attendance::fechaDeMySQL();

                    $descansoTexto = '—';
                    if ($r['break_start'] !== null) {
                        $descansoTexto = substr($r['break_start'], 0, 5)
                            . ($r['break_end'] !== null ? ' → ' . substr($r['break_end'], 0, 5) : ' (en curso)');
                    }

                    $turnoTexto = ($r['start_time'] && $r['end_time'])
                        ? substr($r['start_time'], 0, 5) . ' → ' . substr($r['end_time'], 0, 5)
                        : 'Sin turno asignado';

                    $detail = json_encode([
                        'Empleado' => $nombre,
                        'Fecha' => date('d/m/Y', strtotime($r['attendance_date'])),
                        'Estado' => Attendance::estadoTexto($r['state']),
                        'Entrada' => ($r['check_in'] ? substr($r['check_in'], 0, 5) : '—')
                            . ' · ' . ($r['check_in_method'] ? Attendance::metodoTexto($r['check_in_method']) : 'sin método'),
                        'Inicio de lonche' => $r['break_start'] ? substr($r['break_start'], 0, 5) : '—',
                        'Fin de lonche' => $r['break_end'] ? substr($r['break_end'], 0, 5) : '—',
                        'Salida' => ($r['check_out'] ? substr($r['check_out'], 0, 5) : '—')
                            . ' · ' . ($r['check_out_method'] ? Attendance::metodoTexto($r['check_out_method']) : 'sin método'),
                        'Horas trabajadas' => $r['horas_texto'],
                        'Tiempo de descanso' => $r['descanso_texto'],
                        'Turno asignado' => $turnoTexto,
                        'Notas' => ($r['notes'] ?? '') !== '' ? $r['notes'] : '—',
                    ], JSON_UNESCAPED_UNICODE);

                    $searchable = strtolower(
                        $nombre . ' ' . $r['attendance_date'] . ' ' . date('d/m/Y', strtotime($r['attendance_date']))
                        . ' ' . Attendance::estadoTexto($r['state'])
                        . ' ' . ($r['notes'] ?? '')
                        . ' ' . $chip['texto'] . ' ' . $turnoTexto
                    );
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-method="<?= esc($metodoClave) ?>"
                    data-search="<?= esc($searchable) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <?php if (!empty($r['profile_photo'])): ?>
                                <img src="<?= esc($r['profile_photo']) ?>" alt=""
                                     class="w-9 h-9 rounded-full object-cover shrink-0">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold shrink-0 text-xs">
                                    <?= esc(strtoupper(substr($r['name'], 0, 1))) ?>
                                </div>
                            <?php endif; ?>
                            <div class="min-w-0">
                                <p class="font-medium text-gray-800 truncate"><?= esc($nombre) ?></p>
                                <?php if (($r['empleado_status'] ?? 'active') !== 'active'): ?>
                                    <span class="text-[10px] font-bold uppercase text-red-500">Inactivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap text-gray-600">
                        <?= esc(date('d/m/Y', strtotime($r['attendance_date']))) ?>
                        <?php if ($esHoy): ?>
                            <span class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">HOY</span>
                        <?php endif; ?>
                        <span class="block text-xs text-gray-400"><?= esc($turnoTexto) ?></span>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap font-semibold <?= $r['check_in'] ? 'text-gray-800' : 'text-gray-300' ?>">
                        <?= $r['check_in'] ? esc(substr($r['check_in'], 0, 5)) : '—' ?>
                        <?php if ($r['check_in_method']): ?>
                            <span class="block text-[10px] font-medium text-gray-400"><?= esc(Attendance::metodoTexto($r['check_in_method'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap <?= $r['break_start'] !== null ? 'text-amber-600' : 'text-gray-300' ?>">
                        <?= esc($descansoTexto) ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap font-semibold <?= $r['check_out'] ? 'text-gray-800' : 'text-gray-300' ?>">
                        <?= $r['check_out'] ? esc(substr($r['check_out'], 0, 5)) : '—' ?>
                        <?php if ($r['check_out_method']): ?>
                            <span class="block text-[10px] font-medium text-gray-400"><?= esc(Attendance::metodoTexto($r['check_out_method'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap font-semibold text-gray-700">
                        <?= esc($r['horas_texto']) ?>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= esc($chip['clase']) ?>">
                            <?php if (!empty($chip['icono'])): ?>
                                <i class="fa-solid <?= esc($chip['icono']) ?>"></i>
                            <?php endif; ?>
                            <?= esc($chip['texto']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= Attendance::estadoColor($r['state']) ?>">
                            <?= esc(Attendance::estadoTexto($r['state'])) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <button type="button" class="btn-action btn-detail" title="Ver detalle"
                                    data-title="Registro de asistencia"
                                    data-icon="fa-clipboard-user"
                                    data-image="<?= esc($r['profile_photo'] ?? '') ?>"
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
        <i class="fa-solid fa-clipboard-user block text-3xl mb-2 text-gray-300"></i>
        No hay registros que coincidan con la búsqueda.
    </p>
</div>

<?php if (!$registros): ?>
    <div class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5 text-sm text-gray-500">
        <i class="fa-solid fa-circle-info mr-1 text-orange-400"></i>
        No hay marcaciones de quiosco entre el <?= esc(date('d/m/Y', strtotime($desde))) ?>
        y el <?= esc(date('d/m/Y', strtotime($hasta))) ?>.
        Recordá que la entrada, la salida y los descansos ya no se marcan
        desde «Mi Asistencia»: sólo desde <span class="font-mono">/kiosco</span>.
    </div>
<?php endif; ?>

<!-- Modal de detalle -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-panel w-full max-w-2xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-xl" id="detailModalTitle">Registro de asistencia</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="px-7 py-6" id="detailModalBody"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
