<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nuevo Cliente</h1>
    <a href="<?= url('clients') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-user text-orange-500 mr-2"></i>Información del cliente</h2>
    </div>
    <form action="<?= url('clients/store') ?>" method="POST" enctype="multipart/form-data" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= old('name') ?>" required>
        </div>

        <div>
            <label for="last_name" class="form-label">Apellido <span class="text-red-500">*</span></label>
            <input type="text" id="last_name" name="last_name" class="form-input" value="<?= old('last_name') ?>" required>
        </div>

        <div>
            <label for="id_document" class="form-label">DUI / NIT</label>
            <input type="text" id="id_document" name="id_document" maxlength="30" class="form-input" value="<?= old('id_document') ?>" placeholder="Ej: 01234567-8">
        </div>

        <div>
            <label for="client_type" class="form-label">Tipo de cliente <span class="text-red-500">*</span></label>
            <select id="client_type" name="client_type" class="form-input">
                <option value="persona" <?= old('client_type', 'persona') === 'empresa' ? '' : 'selected' ?>>Persona natural</option>
                <option value="empresa" <?= old('client_type') === 'empresa' ? 'selected' : '' ?>>Empresa / otro</option>
            </select>
        </div>

        <div id="companyField" class="md:col-span-2 <?= old('client_type') === 'empresa' ? '' : 'hidden' ?>">
            <label for="company_name" class="form-label">Nombre de la empresa <span class="text-red-500">*</span></label>
            <input type="text" id="company_name" name="company_name" maxlength="150" class="form-input" value="<?= old('company_name') ?>" placeholder="Ej: Panadería del Valle, S.A. de C.V.">
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" maxlength="20" class="form-input" value="<?= old('phone') ?>" placeholder="Ej: 5555-0001">
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= old('email') ?>" placeholder="cliente@mail.com">
        </div>

        <div class="md:col-span-2">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" class="form-input" value="<?= old('address') ?>">
        </div>

        <div>
            <label for="birth_date" class="form-label">Fecha de nacimiento</label>
            <input type="date" id="birth_date" name="birth_date" class="form-input" value="<?= old('birth_date') ?>">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="profile_photo" class="form-label">Foto de perfil</label>
            <input type="file" id="profile_photo" name="profile_photo" accept="image/*" class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-orange-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-orange-600 hover:file:bg-orange-100">
            <p class="text-xs text-gray-400 mt-1">Opcional. JPG, PNG, WEBP o GIF, máximo 2 MB.</p>
            <?php $cameraField = 'profile_photo'; require __DIR__ . '/../partials/camera_capture.php'; ?>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Cliente
            </button>
            <a href="<?= url('clients') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('client_type');
        const companyField = document.getElementById('companyField');
        const companyInput = document.getElementById('company_name');

        function syncCompany() {
            const isCompany = typeSelect.value === 'empresa';
            companyField.classList.toggle('hidden', !isCompany);
            companyInput.required = isCompany;
            companyInput.disabled = !isCompany;
        }

        typeSelect.addEventListener('change', syncCompany);
        syncCompany();
    })();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>