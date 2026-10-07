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
    <form action="<?= url('employees/update/' . $employee['id']) ?>" method="POST" enctype="multipart/form-data" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
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
            <input type="text" id="id_document" name="id_document" data-dui inputmode="numeric" maxlength="10" pattern="\d{8}-\d" class="form-input" value="<?= esc($employee['id_document']) ?>" required placeholder="Ej: 01234567-8">
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

        <div>
            <label for="role_id" class="form-label">Rol <span class="text-red-500">*</span></label>
            <select id="role_id" name="role_id" class="form-input" required>
                <option value="">Selecciona un rol...</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= (int) $rol['id'] ?>" <?= (int) $employee['role_id'] === (int) $rol['id'] ? 'selected' : '' ?>>
                        <?= esc(Role::label($rol['name'])) ?><?= (int) $rol['is_admin'] === 1 ? ' (acceso total)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="md:col-span-2 mt-2 border-t border-gray-100 pt-5">
            <h3 class="font-semibold text-gray-900"><i class="fa-solid fa-key text-orange-500 mr-2"></i>Cuenta de acceso</h3>
            <p class="text-sm text-gray-500 mt-1"><?= !empty($employee['has_login']) ? 'Deja la contraseña en blanco para mantener la actual.' : 'Opcional. Completa correo y contraseña para crear una cuenta de acceso.' ?></p>
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= esc($employee['email']) ?>" placeholder="correo@ejemplo.com">
        </div>

        <div>
            <label for="password" class="form-label">Contraseña</label>
            <input type="password" id="password" name="password" class="form-input" placeholder="<?= !empty($employee['has_login']) ? 'Dejar en blanco para mantener la actual' : 'Mínimo 6 caracteres' ?>">
        </div>

        <div class="md:col-span-2">
            <label for="profile_photo" class="form-label">Foto de perfil</label>
            <div class="flex items-center gap-4">
                <?php if (!empty($employee['profile_photo'])): ?>
                    <img src="<?= esc($employee['profile_photo']) ?>" alt="Foto de perfil actual"
                         class="w-14 h-14 rounded-xl object-cover ring-1 ring-orange-100 shadow-sm">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-orange-100 to-orange-50 text-orange-500 flex items-center justify-center ring-1 ring-orange-100 shadow-sm">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                <?php endif; ?>
                <div class="flex-1">
                    <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-orange-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-orange-600 hover:file:bg-orange-100">
                    <p class="text-xs text-gray-400 mt-1">Opcional. Si no eliges archivo se mantendrá la foto actual.</p>
                    <?php $cameraField = 'profile_photo'; require __DIR__ . '/../partials/camera_capture.php'; ?>
                </div>
            </div>
            <?php if (!empty($employee['profile_photo'])): ?>
                <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="remove_profile_photo" value="1" class="rounded border-gray-300 text-orange-500 focus:ring-orange-200">
                    Quitar foto actual
                </label>
            <?php endif; ?>
        </div>

        <!-- Biometría: solo administradores. -->
        <?php require __DIR__ . '/../partials/face_enroll.php'; ?>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Actualizar Empleado
            </button>
            <a href="<?= url('employees') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>