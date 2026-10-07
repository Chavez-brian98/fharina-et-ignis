<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$empleados = $empleados ?? [];
$tipos = $tipos ?? [];
$shift = $shift ?? [];
$asistencia = $asistencia ?? null;
$employeeId = (int) ($shift['employee_id'] ?? 0);
$nombre = $shift['nombre'] ?? '';
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Turno</h1>
    <a href="<?= url('schedules?fecha=' . urlencode($shift['work_date'] ?? '')) ?>"
       class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl space-y-6">

    <!-- Datos del turno -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
        <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
            <h2 class="font-semibold text-gray-900">
                <i class="fa-solid fa-pen-to-square text-orange-500 mr-2"></i>Datos del turno
            </h2>
            <p class="mt-1 text-sm text-gray-500">
                <?= esc(date('d/m/Y', strtotime($shift['work_date'] ?? 'now'))) ?>
                &middot; <?= esc(Shift::tipoTexto($shift['shift_type'] ?? null)) ?>
            </p>
        </div>

        <form action="<?= url('schedules/update/' . (int) ($shift['id'] ?? 0)) ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label for="employee_id" class="form-label">Empleado <span class="text-red-500">*</span></label>
                <select id="employee_id" name="employee_id" class="form-input" required>
                    <?php foreach ($empleados as $e): ?>
                        <option value="<?= (int) $e['id'] ?>" <?= (int) $e['id'] === $employeeId ? 'selected' : '' ?>>
                            <?= esc(trim($e['name'] . ' ' . $e['last_name'])) ?> &middot; <?= esc($e['role']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="work_date" class="form-label">Fecha <span class="text-red-500">*</span></label>
                <input type="date" id="work_date" name="work_date" class="form-input"
                       value="<?= esc($shift['work_date'] ?? '') ?>" required>
            </div>

            <div>
                <label for="shift_type" class="form-label">Tipo de turno</label>
                <select id="shift_type" name="shift_type" class="form-input">
                    <?php foreach ($tipos as $valor => $etiqueta): ?>
                        <option value="<?= esc($valor) ?>" <?= ($shift['shift_type'] ?? '') === $valor ? 'selected' : '' ?>>
                            <?= esc($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="start_time" class="form-label">Hora de inicio <span class="text-red-500">*</span></label>
                <input type="time" id="start_time" name="start_time" class="form-input"
                       value="<?= esc(substr($shift['start_time'] ?? '08:00:00', 0, 5)) ?>" required>
            </div>

            <div>
                <label for="end_time" class="form-label">Hora de fin <span class="text-red-500">*</span></label>
                <input type="time" id="end_time" name="end_time" class="form-input"
                       value="<?= esc(substr($shift['end_time'] ?? '16:00:00', 0, 5)) ?>" required>
            </div>

            <div class="md:col-span-2">
                <label for="notes" class="form-label">Notas</label>
                <textarea id="notes" name="notes" rows="2" class="form-input" maxlength="255"><?= esc($shift['notes'] ?? '') ?></textarea>
            </div>

            <div class="md:col-span-2 flex justify-end gap-3 border-t border-gray-100 pt-4">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                    <i class="fa-solid fa-check"></i> Guardar cambios
                </button>
            </div>
        </form>
    </div>

    <!-- Ajuste de la asistencia de ese dia -->
    <?php if (puede('schedules', 'edit')): ?>
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-5">
                <h2 class="font-semibold text-gray-900">
                    <i class="fa-solid fa-user-clock text-orange-500 mr-2"></i>Asistencia de este día
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Corrección manual: para fechar días pasados o corregir una marcación.
                </p>
            </div>

            <form action="<?= url('schedules/attendance/' . $employeeId) ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="attendance_date" value="<?= esc($shift['work_date'] ?? '') ?>">

                <div>
                    <label for="check_in" class="form-label">Hora de entrada</label>
                    <input type="time" id="check_in" name="check_in" class="form-input"
                           value="<?= esc($asistencia && $asistencia['check_in'] ? substr($asistencia['check_in'], 0, 5) : '') ?>">
                </div>

                <div>
                    <label for="check_out" class="form-label">Hora de salida</label>
                    <input type="time" id="check_out" name="check_out" class="form-input"
                           value="<?= esc($asistencia && $asistencia['check_out'] ? substr($asistencia['check_out'], 0, 5) : '') ?>">
                </div>

                <div>
                    <label for="state" class="form-label">Estado</label>
                    <select id="state" name="state" class="form-input">
                        <?php foreach (Attendance::estados() as $valor => $etiqueta): ?>
                            <option value="<?= esc($valor) ?>" <?= ($asistencia['state'] ?? 'presente') === $valor ? 'selected' : '' ?>>
                                <?= esc($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="notes_a" class="form-label">Notas</label>
                    <input type="text" id="notes_a" name="notes" class="form-input" maxlength="255"
                           value="<?= esc($asistencia['notes'] ?? '') ?>">
                </div>

                <div class="md:col-span-2 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                    <?php if ($asistencia && $asistencia['id']): ?>
                        <button type="button" class="btn-action btn-delete" title="Eliminar el registro"
                                data-url="<?= url('schedules/attendanceDelete/' . (int) $asistencia['id']) ?>"
                                data-name="la asistencia del <?= esc(date('d/m/Y', strtotime($shift['work_date'] ?? 'now'))) ?>">
                            <i class="fa-solid fa-trash"></i> Eliminar registro
                        </button>
                    <?php else: ?>
                        <span class="text-sm text-gray-400">Todavía no hay registro de asistencia para este día.</span>
                    <?php endif; ?>

                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gray-800 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-gray-900">
                        <i class="fa-solid fa-check"></i> Guardar asistencia
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>