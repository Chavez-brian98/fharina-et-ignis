<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$fecha = $fecha ?? Attendance::fechaDeMySQL();
$turnos = $turnos ?? [];
$sinTurno = $sinTurno ?? [];
$resumen = $resumen ?? [];
$grilla = $grilla ?? ['dias' => [], 'filas' => []];
$puedeAsignar = $puedeAsignar ?? false;
$puedeEditar = $puedeEditar ?? false;

$esHoy = $fecha === Attendance::fechaDeMySQL();
$prev = date('Y-m-d', strtotime($fecha . ' -1 day'));
$next = date('Y-m-d', strtotime($fecha . ' +1 day'));

// Indice de la semana para date('w'): 0 = domingo.
$diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

// Columnas de filtro: estado derivado del registro de hoy.
$filtros = [
    'todos' => 'Todos',
    'puntual' => 'En turno',
    'tarde' => 'Entró tarde',
    'cerrado' => 'Jornada cerrada',
    'ausente' => 'No ha entrado',
];
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Horarios</h1>
        <p class="text-sm text-gray-500 mt-1">
            Turnos asignados y control de asistencia por día
        </p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('schedules/pdf?desde=' . urlencode($prev) . '&hasta=' . urlencode($next) . '&dias=1,2,3,4,5,6') ?>"
           class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50"
           title="Descargar el horario del día en PDF">
            <i class="fa-solid fa-file-pdf text-red-500"></i> PDF del día
        </a>
        <?php if ($puedeAsignar): ?>
            <a href="<?= url('schedules/rango') ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-4 py-2.5 text-sm font-bold text-orange-700 transition-colors hover:bg-orange-100"
               title="Asignar el mismo turno a varios empleados durante un rango de fechas">
                <i class="fa-solid fa-layer-group"></i> Por rango
            </a>
            <a href="<?= url('schedules/create?fecha=' . urlencode($fecha)) ?>"
               class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                <i class="fa-solid fa-plus"></i> Asignar Turno
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Selector de fecha -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
    <div class="flex items-center gap-2">
        <a href="<?= url('schedules?fecha=' . urlencode($prev)) ?>" class="btn-action"><i class="fa-solid fa-chevron-left"></i></a>
        <input type="date" id="fechaPicker" value="<?= esc($fecha) ?>"
               class="form-input w-auto text-sm font-semibold">
        <a href="<?= url('schedules?fecha=' . urlencode($next)) ?>" class="btn-action"><i class="fa-solid fa-chevron-right"></i></a>
        <?php if (!$esHoy): ?>
            <a href="<?= url('schedules') ?>" class="ml-1 text-xs font-semibold text-orange-600 hover:underline">Hoy</a>
        <?php endif; ?>
    </div>
    <p class="text-sm font-semibold <?= $esHoy ? 'text-orange-600' : 'text-gray-500' ?>">
        <?= $esHoy ? 'Hoy' : esc(date('d/m/Y', strtotime($fecha))) ?>
    </p>
</div>

<!-- PDF del período -->
<?php
// Personal del roster + los que no tienen turno = todos los empleados activos.
$personal = [];
foreach ($turnos as $t) {
    $personal[(int) $t['employee_id']] = trim($t['name'] . ' ' . $t['last_name']);
}
foreach ($sinTurno as $e) {
    $personal[(int) $e['id']] = trim($e['name'] . ' ' . $e['last_name']);
}
ksort($personal);

// Por defecto el PDF sale con el rango de la semana visible.
$iniSemana = $grilla['dias'][0] ?? $prev;
$finSemana = end($grilla['dias']) ?: $next;

// Checkboxes de días para el PDF: la semana que se está viendo.
$diasTurno = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];
$diasSel = [];
foreach ($diasTurno as $w => $label) {
    if (in_array($w, $grilla['dias'] === [] ? [] : array_map('intval', array_map(function ($d) {
        return date('w', strtotime($d));
    }, $grilla['dias'])), true)) {
        $diasSel[] = $w;
    }
}
if (!$diasSel) {
    $diasSel = [1, 2, 3, 4, 5];
}

$qsPdf = static function (array $over = []) use ($iniSemana, $finSemana, $diasSel) {
    $q = array_merge([
        'desde' => $iniSemana,
        'hasta' => $finSemana,
        'dias' => implode(',', $diasSel),
    ], $over);

    return url('schedules/pdf') . '?' . http_build_query($q);
};
?>
<div class="mb-6 rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div>
            <h2 class="font-semibold text-gray-900">
                <i class="fa-solid fa-file-arrow-down text-orange-500 mr-2"></i>Descargar horarios (PDF)
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">
                Genera el cuadrante del período para enviarlo a los empleados o imprimirlo.
            </p>
        </div>
        <a href="<?= esc($qsPdf()) ?>" class="text-xs font-semibold text-orange-600 hover:underline">
            <i class="fa-solid fa-download mr-1"></i>Descargar de esta semana
        </a>
    </div>

    <form action="<?= url('schedules/pdf') ?>" method="GET" class="grid grid-cols-2 md:grid-cols-4 gap-3 items-end">
        <input type="hidden" name="formato" id="pdfFormato" value="matriz">
        <div>
            <label for="pdfDesde" class="form-label">Desde</label>
            <input type="date" id="pdfDesde" name="desde" class="form-input" value="<?= esc($iniSemana) ?>">
        </div>
        <div>
            <label for="pdfHasta" class="form-label">Hasta</label>
            <input type="date" id="pdfHasta" name="hasta" class="form-input" value="<?= esc($finSemana) ?>">
        </div>
        <div>
            <label for="pdfEmpleado" class="form-label">Empleado</label>
            <select id="pdfEmpleado" name="empleado" class="form-input">
                <option value="0">Todos</option>
                <?php foreach ($personal as $empId => $nombre): ?>
                    <option value="<?= (int) $empId ?>"><?= esc($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <span class="form-label">Formato</span>
            <select class="form-input" onchange="document.getElementById('pdfFormato').value = this.value">
                <option value="matriz">Cuadrante (calendario)</option>
                <option value="detalle">Listado día por día</option>
            </select>
        </div>

        <div class="col-span-2 md:col-span-4 flex flex-wrap items-center gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Días:</span>
                <?php foreach ($diasTurno as $w => $label): ?>
                    <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600">
                        <input type="checkbox" name="dias[]" value="<?= $w ?>" class="pdfDia accent-orange-500"
                            <?= in_array($w, $diasSel, true) ? 'checked' : '' ?>>
                        <?= esc($label) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="ml-auto inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                <i class="fa-solid fa-file-pdf"></i> Generar PDF
            </button>
        </div>
    </form>
</div>

<!-- Resumen -->
<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
    <?php
    $tarjetas = [
        ['Turnos asignados', $resumen['asignados'] ?? 0, 'fa-calendar-day', 'text-gray-900'],
        ['Trabajando ahora', $resumen['en_turno'] ?? 0, 'fa-circle-play', 'text-green-600'],
        ['Jornadas cerradas', $resumen['cerrados'] ?? 0, 'fa-circle-check', 'text-blue-600'],
        ['Entraron tarde', $resumen['tarde'] ?? 0, 'fa-clock', 'text-amber-600'],
        ['No han entrado', $resumen['ausentes'] ?? 0, 'fa-user-slash', 'text-red-500'],
    ];
    foreach ($tarjetas as [$etiqueta, $valor, $icono, $color]): ?>
        <div class="rounded-2xl border border-gray-200 bg-white px-5 py-4 shadow-lg shadow-gray-200/50">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400"><?= esc($etiqueta) ?></p>
            <p class="mt-1 text-2xl font-bold <?= $color ?>">
                <i class="fa-solid <?= esc($icono) ?> mr-1 text-sm"></i><?= (int) $valor ?>
            </p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Roster -->
<div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-4">
        <h2 class="font-semibold text-gray-900">
            <i class="fa-solid fa-users text-orange-500 mr-2"></i>Personal del día
        </h2>
        <div class="search-filter flex flex-wrap items-center gap-2">
            <select class="search-filter text-sm" data-filter="estado">
                <option value="">Todos los estados</option>
                <?php foreach ($filtros as $valor => $etiqueta): ?>
                    <option value="<?= esc($valor) ?>"><?= esc($etiqueta) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-6 py-3 font-semibold">Empleado</th>
                    <th class="px-4 py-3 font-semibold">Turno</th>
                    <th class="px-4 py-3 font-semibold">Entrada</th>
                    <th class="px-4 py-3 font-semibold">Salida</th>
                    <th class="px-4 py-3 font-semibold">Horas</th>
                    <th class="px-6 py-3 font-semibold">Estado</th>
                    <th class="px-6 py-3 text-right font-semibold">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody" class="divide-y divide-gray-100">
                <?php foreach ($turnos as $t):
                    $entro = $t['check_in'] !== null;
                    $cerrado = $t['check_out'] !== null;
                    if (!$entro) {
                        $estadoFiltro = 'ausente';
                    } elseif ($t['tarde']) {
                        $estadoFiltro = 'tarde';
                    } elseif ($cerrado) {
                        $estadoFiltro = 'cerrado';
                    } else {
                        $estadoFiltro = 'puntual';
                    }
                    $nombre = trim($t['name'] . ' ' . $t['last_name']);
                    $busqueda = strtolower($nombre . ' ' . ($t['shift_type'] ?? '') . ' ' . $estadoFiltro);
                ?>
                    <tr data-search="<?= esc($busqueda) ?>" data-estado="<?= esc($estadoFiltro) ?>">
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <?php if ($t['profile_photo'] ?? null): ?>
                                    <img src="<?= esc($t['profile_photo']) ?>" alt=""
                                         class="h-9 w-9 rounded-full object-cover">
                                <?php else: ?>
                                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <i class="fa-solid fa-user"></i>
                                    </span>
                                <?php endif; ?>
                                <div>
                                    <p class="font-semibold text-gray-800"><?= esc($nombre) ?></p>
                                    <?php if ($t['employee_status'] !== 'active'): ?>
                                        <p class="text-xs text-red-500">Empleado inactivo</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            <?= esc(substr($t['start_time'], 0, 5)) ?> &ndash; <?= esc(substr($t['end_time'], 0, 5)) ?>
                            <span class="block text-xs text-gray-400"><?= esc(Shift::tipoTexto($t['shift_type'])) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($entro): ?>
                                <span class="font-semibold text-gray-700"><?= esc(substr($t['check_in'], 0, 5)) ?></span>
                                <?php if ($t['tarde']): ?>
                                    <span class="block text-xs font-semibold text-red-500">
                                        +<?= esc(Attendance::horasMinutos($t['min_tarde'])) ?>
                                    </span>
                                <?php endif; ?>
                                <span class="block text-xs text-gray-400">
                                    <i class="fa-solid <?= esc(Attendance::metodoIcon($t['check_in_method'])) ?>"></i>
                                    <?= esc(Attendance::metodoTexto($t['check_in_method'])) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-300">â€”</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($cerrado): ?>
                                <span class="font-semibold text-gray-700"><?= esc(substr($t['check_out'], 0, 5)) ?></span>
                            <?php elseif ($t['en_descanso']): ?>
                                <span class="text-xs font-semibold text-amber-600">En descanso</span>
                            <?php else: ?>
                                <span class="text-gray-300">â€”</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 font-semibold text-gray-700"><?= esc($t['horas_texto']) ?></td>
                        <td class="px-6 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= Attendance::estadoColor($t['state']) ?>">
                                <?= esc(Attendance::estadoTexto($t['state'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <?php if ($puedeEditar): ?>
                                    <a href="<?= url('schedules/edit/' . (int) $t['id']) ?>" class="btn-action" title="Editar turno">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <button type="button" class="btn-action btn-delete" title="Eliminar turno"
                                            data-url="<?= url('schedules/delete/' . (int) $t['id']) ?>"
                                            data-name="el turno de <?= esc($nombre) ?> del <?= esc(date('d/m/Y', strtotime($fecha))) ?>">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!$turnos): ?>
        <div id="emptyState" class="px-6 py-12 text-center text-gray-400">
            No hay turnos asignados para esta fecha.
        </div>
    <?php endif; ?>
</div>

<!-- Empleados activos sin turno en esta fecha -->
<?php if ($sinTurno): ?>
    <div class="mb-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5">
        <h3 class="text-sm font-bold text-gray-700">
            <i class="fa-solid fa-user-slash mr-1 text-gray-400"></i>
            Sin turno asignado (<?= count($sinTurno) ?>)
        </h3>
        <div class="mt-3 flex flex-wrap gap-2">
            <?php foreach ($sinTurno as $e): ?>
                <?php $n = trim($e['name'] . ' ' . $e['last_name']); ?>
                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600">
                    <?= esc($n) ?>
                    <?php if ($puedeAsignar): ?>
                        <a href="<?= url('schedules/create?fecha=' . urlencode($fecha)) ?>" class="text-orange-600 hover:underline" title="Asignar turno">
                            <i class="fa-solid fa-plus"></i>
                        </a>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Semana -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="border-b border-gray-100 px-6 py-4">
        <h2 class="font-semibold text-gray-900">
            <i class="fa-solid fa-calendar-week text-orange-500 mr-2"></i>Agenda de la semana
        </h2>
        <p class="mt-0.5 text-xs text-gray-400">
            Del <?= esc(date('d/m/Y', strtotime($grilla['dias'][0] ?? $fecha))) ?>
            al <?= esc(date('d/m/Y', strtotime(end($grilla['dias']) ?: $fecha))) ?>
        </p>
    </div>

    <?php if (!$grilla['filas']): ?>
        <div class="px-6 py-12 text-center text-gray-400">No hay turnos en esta semana.</div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
<thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
<tr>
                    <th class="px-6 py-3 font-semibold">Empleado</th>
                    <?php foreach ($grilla['dias'] as $d): ?>
                        <th class="px-3 py-3 text-center font-semibold <?= $d === $fecha ? 'text-orange-600' : '' ?>">
                            <?= esc($diasSemana[date('w', strtotime($d))] ?? '') ?>
                            <span class="block text-[10px] font-normal text-gray-400"><?= esc(date('d/m', strtotime($d))) ?></span>
                        </th>
                    <?php endforeach; ?>
                    <th class="px-3 py-3 text-right font-semibold">Semana</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($grilla['filas'] as $emp): ?>
                        <tr>
                            <td class="whitespace-nowrap px-6 py-3 font-semibold text-gray-700"><?= esc($emp['nombre']) ?></td>
                            <?php foreach ($grilla['dias'] as $d): ?>
                                <?php $celda = $emp['celdas'][$d] ?? null; ?>
                                <td class="px-3 py-3 text-center">
                                    <?php if ($celda): ?>
                                        <span class="inline-block rounded-lg bg-orange-50 px-2 py-1 text-xs font-semibold text-orange-700">
                                            <?= esc(substr($celda['start_time'], 0, 5)) ?><br>
                                            <span class="font-normal"><?= esc(substr($celda['end_time'], 0, 5)) ?></span>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-200">&middot;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                <?php
                                // Edita la semana completa de esta fila: el
                                // formulario de rango ya llega con el empleado
                                // y las fechas de la semana que se está viendo.
                                $urlSemana = url('schedules/rango?empleado=' . (int) $emp['employee_id']
                                    . '&desde=' . urlencode($iniSemana)
                                    . '&hasta=' . urlencode($finSemana));
                                ?>
                                <a href="<?= $urlSemana ?>" class="btn-action" title="Editar la semana de <?= esc($emp['nombre']) ?>">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
// Redirige al cambiar la fecha.
document.getElementById('fechaPicker').addEventListener('change', function () {
    window.location.href = <?= json_encode(url('schedules?fecha=')) ?> + encodeURIComponent(this.value);
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>