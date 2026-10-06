<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<?php $empleados = $empleados ?? []; $tipos = $tipos ?? []; $shift = $shift ?? []; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Asignar Turno</h1>
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

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-calendar-plus text-orange-500 mr-2"></i>Datos del turno</h2>
        <p class="mt-1 text-sm text-gray-500">Un turno por empleado por día. La salida estimada sale del fin del turno.</p>
    </div>

    <form action="<?= url('schedules/store') ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="md:col-span-2">
            <label for="employee_id" class="form-label">Empleado <span class="text-red-500">*</span></label>
            <select id="employee_id" name="employee_id" class="form-input" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($empleados as $e): ?>
                    <option value="<?= (int) $e['id'] ?>"
                        <?= (string) old('employee_id') === (string) $e['id'] ? 'selected' : '' ?>>
                        <?= esc(trim($e['name'] . ' ' . $e['last_name'])) ?> &middot; <?= esc($e['role']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="work_date" class="form-label">Fecha <span class="text-red-500">*</span></label>
            <input type="date" id="work_date" name="work_date" class="form-input"
                   value="<?= esc(old('work_date', $shift['work_date'] ?? '')) ?>" required>
        </div>

        <div>
            <label for="shift_type" class="form-label">Tipo de turno</label>
            <select id="shift_type" name="shift_type" class="form-input">
                <?php foreach ($tipos as $valor => $etiqueta): ?>
                    <option value="<?= esc($valor) ?>"
                        <?= old('shift_type', $shift['shift_type'] ?? 'manana') === $valor ? 'selected' : '' ?>>
                        <?= esc($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="start_time" class="form-label">Hora de inicio <span class="text-red-500">*</span></label>
            <input type="time" id="start_time" name="start_time" class="form-input"
                   value="<?= esc(old('start_time', isset($shift['start_time']) ? substr($shift['start_time'], 0, 5) : '08:00')) ?>" required>
        </div>

        <div>
            <label for="end_time" class="form-label">Hora de fin <span class="text-red-500">*</span></label>
            <input type="time" id="end_time" name="end_time" class="form-input"
                   value="<?= esc(old('end_time', isset($shift['end_time']) ? substr($shift['end_time'], 0, 5) : '16:00')) ?>" required>
            <p class="mt-1 text-xs text-gray-400">Si la hora de fin es menor que la de inicio, el turno pasa de la medianoche.</p>
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="form-label">Notas</label>
            <textarea id="notes" name="notes" rows="2" class="form-input" maxlength="255"
                      placeholder="Ej: cubre también el cierre de caja"><?= esc(old('notes', $shift['notes'] ?? '')) ?></textarea>
        </div>

        <div class="md:col-span-2 flex justify-end gap-3 border-t border-gray-100 pt-4">
            <a href="<?= url('schedules') ?>" class="inline-flex items-center rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                <i class="fa-solid fa-check"></i> Asignar turno
            </button>
        </div>
    </form>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>