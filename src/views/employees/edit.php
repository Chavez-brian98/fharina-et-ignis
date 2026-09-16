<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Empleado</h1>
    <a href="<?= url('employees') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-user-tie text-orange-500 mr-2"></i>Información del empleado</h2>
    </div>
    <form action="<?= url('employees/update/' . $employee['id']) ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= esc($employee['name']) ?>" required>
        </div>

        <div>
            <label for="last_name" class="form-label">Apellido <span class="text-red-500">*</span></label>
            <input type="text" id="last_name" name="last_name" class="form-input" value="<?= esc($employee['last_name']) ?>" required>
        </div>

        <div>
            <label for="id_document" class="form-label">DUI <span class="text-red-500">*</span></label>
            <input type="text" id="id_document" name="id_document" maxlength="30" class="form-input" value="<?= esc($employee['id_document']) ?>" required placeholder="Ej: 12345678-9 (9 dígitos, guion antes del último)">
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" maxlength="20" class="form-input" value="<?= esc($employee['phone']) ?>">
        </div>

        <div class="md:col-span-2">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" class="form-input" value="<?= esc($employee['address']) ?>">
        </div>

        <div>
            <label for="birth_date" class="form-label">Fecha de nacimiento</label>
            <input type="date" id="birth_date" name="birth_date" class="form-input" value="<?= esc($employee['birth_date']) ?>">
        </div>

        <div>
            <label for="hire_date" class="form-label">Fecha de contratación <span class="text-red-500">*</span></label>
            <input type="date" id="hire_date" name="hire_date" class="form-input" value="<?= esc($employee['hire_date']) ?>" required>
        </div>

        <div>
            <label for="position" class="form-label">Cargo <span class="text-red-500">*</span></label>
            <input type="text" id="position" name="position" maxlength="80" class="form-input" value="<?= esc($employee['position']) ?>" required placeholder="Ej: Panadero">
        </div>

        <div>
            <label for="base_salary" class="form-label">Salario base ($) <span class="text-red-500">*</span></label>
            <input type="number" step="0.01" min="0" id="base_salary" name="base_salary" class="form-input" value="<?= esc($employee['base_salary']) ?>" required>
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= $employee['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $employee['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2 mt-2 border-t border-gray-100 pt-5">
            <h3 class="font-semibold text-gray-900"><i class="fa-solid fa-key text-orange-500 mr-2"></i>Cuenta de acceso</h3>
            <p class="text-sm text-gray-500 mt-1"><?= !empty($employee['has_login']) ? 'Deja la contraseña en blanco para mantener la actual.' : 'Completa usuario, correo, contraseña y rol para crear una cuenta de acceso.' ?></p>
        </div>

        <div>
            <label for="username" class="form-label">Nombre de usuario</label>
            <input type="text" id="username" name="username" maxlength="50" class="form-input" value="<?= esc($employee['username']) ?>" placeholder="Ej: cajero">
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= esc($employee['email']) ?>" placeholder="correo@ejemplo.com">
        </div>

        <div>
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="<?= !empty($employee['has_login']) ? 'Dejar en blanco para mantener la actual' : 'Mínimo 6 caracteres' ?>">
        </div>

        <div>
            <label for="role_id" class="form-label">Rol</label>
            <select id="role_id" name="role_id" class="form-input">
                <option value="">Sin cuenta de acceso</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>" <?= (string) $employee['role_id'] === (string) $role['id'] ? 'selected' : '' ?>><?= esc(ucfirst($role['name'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Actualizar Empleado
            </button>
            <a href="<?= url('employees') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>