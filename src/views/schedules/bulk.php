<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<?php
$empleados = $empleados ?? [];
$tipos = $tipos ?? [];
$desde = $desde ?? date('Y-m-d');
$hasta = $hasta ?? date('Y-m-d', strtotime($desde . ' +6 days'));
$seleccionados = $seleccionados ?? [];

// 1 = lunes ... 7 = domingo. Se guardan como claves de date('w') (0 = domingo).
$diasSemana = [
    1 => ['label' => 'Lun', 'w' => 1],
    2 => ['label' => 'Mar', 'w' => 2],
    3 => ['label' => 'Mié', 'w' => 3],
    4 => ['label' => 'Jue', 'w' => 4],
    5 => ['label' => 'Vie', 'w' => 5],
    6 => ['label' => 'Sáb', 'w' => 6],
    0 => ['label' => 'Dom', 'w' => 0],
];
$diasPorDefecto = isset($_POST['dias']) ? (array) $_POST['dias'] : ['1', '2', '3', '4', '5'];

// Para el buscador: minúsculas y sin acentos, así "maria" encuentra a "María".
// El JS hace la misma normalización (NFD) del lado del navegador.
$paraBuscar = function ($texto) {
    $t = mb_strtolower(trim($texto), 'UTF-8');

    return strtr($t, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
};
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Asignar Turnos por Rango</h1>
    <a href="<?= url('schedules') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<?php if (!$empleados): ?>
    <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center shadow-lg shadow-gray-200/50">
        <i class="fa-solid fa-users-slash mb-3 text-3xl text-gray-300"></i>
        <p class="text-gray-500">No hay empleados activos para asignar turnos.</p>
    </div>
<?php else: ?>

<div class="max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-layer-group text-orange-500 mr-2"></i>Turno repetido por período</h2>
        <p class="mt-1 text-sm text-gray-500">
            El mismo turno para varios empleados entre dos fechas, sólo en los días de la semana que marques.
        </p>
    </div>

    <form action="<?= url('schedules/bulk') ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Empleados -->
        <div class="md:col-span-2">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <label class="form-label mb-0">Empleados <span class="text-red-500">*</span></label>
                <div class="flex items-center gap-3 text-xs font-semibold">
                    <button type="button" id="marcarTodos" class="text-orange-600 hover:underline">Marcar todos</button>
                    <button type="button" id="marcarNinguno" class="text-gray-400 hover:underline">Ninguno</button>
                </div>
            </div>

            <div class="relative mb-2">
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" id="buscarEmpleado" autocomplete="off" class="form-input pl-9"
                       placeholder="Buscar empleado por nombre&hellip;"
                       aria-label="Buscar empleado por nombre">
            </div>

            <p id="empHint" class="mb-2 text-xs text-gray-400"></p>

            <div id="empLista" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 p-3">
                <?php foreach ($empleados as $e): ?>
                    <?php $empId = (int) $e['id']; ?>
                    <?php $empNombre = trim($e['name'] . ' ' . $e['last_name']); ?>
                    <label class="empItem flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-gray-50 cursor-pointer"
                           data-buscar="<?= esc($paraBuscar($empNombre)) ?>">
                        <input type="checkbox" name="empleado_ids[]" value="<?= $empId ?>" class="empCheck accent-orange-500"
                            <?= in_array($empId, array_map('intval', $seleccionados), true) ? 'checked' : '' ?>>
                        <span class="truncate"><?= esc($empNombre) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="empVacio" class="mt-2 hidden text-xs text-gray-400">Ningún empleado coincide con la búsqueda.</p>
        </div>

        <!-- Rango -->
        <div>
            <label for="desde" class="form-label">Desde <span class="text-red-500">*</span></label>
            <input type="date" id="desde" name="desde" class="form-input" value="<?= esc(old('desde', $desde)) ?>" required>
        </div>
        <div>
            <label for="hasta" class="form-label">Hasta <span class="text-red-500">*</span></label>
            <input type="date" id="hasta" name="hasta" class="form-input" value="<?= esc(old('hasta', $hasta)) ?>" required>
        </div>

        <!-- Días de la semana -->
        <div class="md:col-span-2">
            <span class="form-label">Días de la semana <span class="text-red-500">*</span></span>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($diasSemana as $clave => $info): ?>
                    <label class="diaCheck flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50">
                        <input type="checkbox" name="dias[]" value="<?= $info['w'] ?>" class="sr-only"
                            <?= in_array((string) $info['w'], array_map('strval', $diasPorDefecto), true) ? 'checked' : '' ?>>
                        <span><?= esc($info['label']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="mt-2 text-xs text-gray-400">
                Sólo se crean turnos en los días marcados dentro del rango. Las fechas ya asignadas se saltan
                (o se actualizan si marcás "sobrescribir").
            </p>
        </div>

        <!-- Turno -->
        <div>
            <label for="shift_type" class="form-label">Tipo de turno</label>
            <select id="shift_type" name="shift_type" class="form-input">
                <?php foreach ($tipos as $valor => $etiqueta): ?>
                    <option value="<?= esc($valor) ?>" <?= old('shift_type', 'manana') === $valor ? 'selected' : '' ?>>
                        <?= esc($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="start_time" class="form-label">Inicio <span class="text-red-500">*</span></label>
                <input type="time" id="start_time" name="start_time" class="form-input" value="<?= esc(old('start_time', '08:00')) ?>" required>
            </div>
            <div>
                <label for="end_time" class="form-label">Fin <span class="text-red-500">*</span></label>
                <input type="time" id="end_time" name="end_time" class="form-input" value="<?= esc(old('end_time', '16:00')) ?>" required>
            </div>
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="form-label">Notas</label>
            <textarea id="notes" name="notes" rows="2" class="form-input" maxlength="255"
                      placeholder="Ej: cubre también el cierre de caja"><?= esc(old('notes')) ?></textarea>
        </div>

        <div class="md:col-span-2">
            <label class="flex items-start gap-2 rounded-xl bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                <input type="checkbox" name="sobrescribir" value="1" class="mt-0.5 accent-amber-500" <?= old('sobrescribir') ? 'checked' : '' ?>>
                <span>
                    Sobrescribir los turnos que ya existan en el rango.
                    <span class="block text-xs text-amber-700">Sin esto, los días ya asignados se dejan como están.</span>
                </span>
            </label>
        </div>

        <!-- Vista previa -->
        <div class="md:col-span-2 rounded-xl border border-dashed border-orange-200 bg-orange-50/50 px-4 py-3">
            <p class="text-xs font-bold uppercase tracking-wide text-orange-600">
                Se asignarán <span id="previaTotal">0</span> turnos
            </p>
            <p class="mt-0.5 text-xs text-gray-500" id="previaDetalle">
                Elegí empleados, fechas y días de la semana.
            </p>
        </div>

        <div class="md:col-span-2 flex flex-wrap justify-end gap-3 border-t border-gray-100 pt-4">
            <a href="<?= url('schedules') ?>" class="inline-flex items-center rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                <i class="fa-solid fa-layer-group"></i> Asignar a todos
            </button>
        </div>
    </form>
</div>

<script>
// Cuántos empleados se sugieren cuando el buscador está vacío.
var SUGERENCIAS = 8;

function itemsEmpleado() {
    return [].slice.call(document.querySelectorAll('.empItem'));
}

function normalizar(t) {
    // Mismo criterio que el data-buscar del servidor: sin acentos y sin ñ.
    return t.toLowerCase()
        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/ñ/g, 'n');
}

// Filtra en vivo. Sin texto no se muestra la lista entera: se ofrecen unas
// sugerencias para que el formulario no arranque con un muro de nombres.
function filtrarEmpleados() {
    var q = normalizar(document.getElementById('buscarEmpleado').value).replace(/\s+/g, ' ');
    var items = itemsEmpleado();
    var visibles = 0;

    items.forEach(function (item, i) {
        var check = item.querySelector('.empCheck');
        // Sin búsqueda se ofrecen sugerencias, pero un empleado ya
        // preseleccionado (o.checked) nunca se esconde: si se llega desde la
        // agenda de la semana, tiene que verse marcado.
        var coincide = q === ''
            ? (i < SUGERENCIAS || check.checked)
            : item.dataset.buscar.indexOf(q) !== -1;
        item.classList.toggle('hidden', !coincide);
        if (coincide) { visibles++; }
    });

    var hint = document.getElementById('empHint');
    var total = items.length;
    var marcados = document.querySelectorAll('.empCheck:checked').length;

    if (q === '') {
        hint.textContent = visibles < total
            ? 'Mostrando ' + visibles + ' sugerencia(s) de ' + total + ' empleados. Escribí para buscar entre todos.'
            : total + ' empleados activos.';
    } else {
        hint.textContent = visibles + ' coincidencia(s) para "' + q + '".';
    }

    hint.textContent += marcados ? ' — ' + marcados + ' seleccionado(s).' : '';
    document.getElementById('empVacio').classList.toggle('hidden', visibles !== 0);
}

document.getElementById('buscarEmpleado').addEventListener('input', filtrarEmpleados);

// Selección rápida sobre lo que se está viendo, no sobre la lista entera:
// si estás filtrando "maría", "marcar todos" marca a María y no a los 40 demás.
document.getElementById('marcarTodos').addEventListener('click', function () {
    itemsEmpleado().forEach(function (item) {
        if (!item.classList.contains('hidden')) { item.querySelector('.empCheck').checked = true; }
    });
    filtrarEmpleados();
    recalcularPrevia();
});
document.getElementById('marcarNinguno').addEventListener('click', function () {
    document.querySelectorAll('.empCheck').forEach(function (c) { c.checked = false; });
    filtrarEmpleados();
    recalcularPrevia();
});

// Pintar los días elegidos.
function pintarDias() {
    document.querySelectorAll('.diaCheck').forEach(function (l) {
        var c = l.querySelector('input');
        l.classList.toggle('bg-orange-500', c.checked);
        l.classList.toggle('text-white', c.checked);
        l.classList.toggle('border-orange-500', c.checked);
    });
}

// Vista previa: cuántos turnos se crearán.
function recalcularPrevia() {
    var empleados = document.querySelectorAll('.empCheck:checked').length;
    var dias = document.querySelectorAll('.diaCheck input:checked').length;
    var desde = document.getElementById('desde').value;
    var hasta = document.getElementById('hasta').value;
    var detalle = document.getElementById('previaDetalle');

    if (!desde || !hasta || hasta < desde) {
        document.getElementById('previaTotal').textContent = '0';
        detalle.textContent = desde && hasta ? 'La fecha final es anterior a la inicial.' : 'Elegí el rango de fechas.';
        pintarDias();
        return;
    }

    var total = 0;
    var cursor = new Date(desde + 'T00:00:00');
    var fin = new Date(hasta + 'T00:00:00');
    while (cursor <= fin) {
        if (dias > 0 && dias < 7 && diasSemanaSel().indexOf(cursor.getDay()) === -1) {
            cursor.setDate(cursor.getDate() + 1);
            continue;
        }
        total += empleados;
        cursor.setDate(cursor.getDate() + 1);
    }

    document.getElementById('previaTotal').textContent = total;
    detalle.textContent = empleados + ' empleado(s) × ' + (dias || 0) + ' día(s) de la semana'
        + (desde === hasta ? ' en 1 fecha' : ' del ' + desde + ' al ' + hasta);
    pintarDias();
}

function diasSemanaSel() {
    return [].slice.call(document.querySelectorAll('.diaCheck input:checked')).map(function (i) {
        return parseInt(i.value, 10);
    });
}

document.querySelectorAll('.empCheck, .diaCheck input').forEach(function (el) {
    el.addEventListener('change', recalcularPrevia);
});
['desde', 'hasta'].forEach(function (id) {
    document.getElementById(id).addEventListener('change', recalcularPrevia);
});
recalcularPrevia();
filtrarEmpleados();
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>