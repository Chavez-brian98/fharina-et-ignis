<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nuevo Rol</h1>
    <a href="<?= url('roles') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-shield-halved text-orange-500 mr-2"></i>Datos del rol</h2>
    </div>
    <form action="<?= url('roles/store') ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Nombre del rol <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" maxlength="60" class="form-input" value="<?= old('name') ?>" required placeholder="Ej: cajero">
            <p class="text-xs text-gray-400 mt-1">Solo letras, números y guion bajo. Ej: sub_jefe.</p>
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" selected>Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="description" class="form-label">Descripción</label>
            <input type="text" id="description" name="description" maxlength="255" class="form-input" value="<?= old('description') ?>" placeholder="Ej: Acceso al punto de venta y clientes">
        </div>

        <div class="md:col-span-2">
            <label for="is_admin" class="flex items-start gap-3 rounded-xl bg-gray-50 ring-1 ring-gray-200 px-4 py-3 cursor-pointer">
                <input type="checkbox" id="is_admin" name="is_admin" value="1" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-orange-500 focus:ring-orange-200"
                       <?= isset($_POST['is_admin']) ? 'checked' : '' ?>>
                <span>
                    <span class="block text-sm font-semibold text-gray-900">Acceso total (administrador)</span>
                    <span class="block text-xs text-gray-500">El rol podrá ver, crear, editar y eliminar en todos los módulos, sin importar la tabla de permisos.</span>
                </span>
            </label>
        </div>

        <?php require __DIR__ . '/../partials/permisos_rol.php'; ?>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Rol
            </button>
            <a href="<?= url('roles') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>