<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mi Perfil</h1>
        <p class="text-sm text-gray-500 mt-1">Actualiza tu información personal, credenciales y foto de perfil.</p>
    </div>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-user text-orange-500 mr-2"></i>Información personal</h2>
    </div>

    <form action="<?= url('profile/update') ?>" method="POST" enctype="multipart/form-data" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Foto de perfil -->
        <div class="md:col-span-2 flex flex-col sm:flex-row items-start sm:items-center gap-5">
            <div class="relative shrink-0">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img src="<?= esc($user['profile_photo']) ?>" alt="Foto de perfil"
                         class="w-20 h-20 rounded-full object-cover ring-4 ring-orange-100 shadow-lg shadow-orange-100/60">
                <?php else: ?>
                    <div class="w-20 h-20 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-2xl font-bold ring-4 ring-orange-100 shadow-lg shadow-orange-100/60">
                        <?= esc(strtoupper(substr($user['name'], 0, 1))) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="flex-1 w-full">
                <label for="profile_photo" class="form-label">Foto de perfil</label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="form-input">
                <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, WEBP o GIF · máximo 2 MB. Déjalo vacío para conservar la actual.</p>
                <?php $cameraField = 'profile_photo'; require __DIR__ . '/../partials/camera_capture.php'; ?>
            </div>
        </div>

        <div>
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" maxlength="100" class="form-input" value="<?= esc($user['name']) ?>" required>
        </div>

        <div>
            <label for="last_name" class="form-label">Apellido <span class="text-red-500">*</span></label>
            <input type="text" id="last_name" name="last_name" maxlength="100" class="form-input" value="<?= esc($user['last_name']) ?>" required>
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico <span class="text-red-500">*</span></label>
            <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= esc($user['email']) ?>" required>
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" maxlength="20" class="form-input" value="<?= esc($user['phone']) ?>">
        </div>

        <div>
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" class="form-input" value="<?= esc($user['address']) ?>">
        </div>

        <div class="md:col-span-2 mt-2 border-t border-gray-100 pt-5">
            <h3 class="font-semibold text-gray-900"><i class="fa-solid fa-key text-orange-500 mr-2"></i>Cambiar contraseña</h3>
            <p class="text-sm text-gray-500 mt-1">Déjala en blanco para mantener la contraseña actual.</p>
        </div>

        <div class="md:col-span-2">
            <label for="password" class="form-label">Nueva contraseña</label>
            <input type="password" id="password" name="password" autocomplete="new-password" class="form-input" placeholder="Mínimo 6 caracteres">
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>