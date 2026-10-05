<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php if (!function_exists('old')) {
    function old($key, $default = '') { return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default; }
} ?>
<?php
$dias = [];
if (isset($_POST['availability_days']) && is_array($_POST['availability_days'])) {
    $dias = $_POST['availability_days'];
}
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Nuevo Proveedor</h1>
    <a href="<?= url('suppliers') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-4xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-truck text-orange-500 mr-2"></i>Datos del proveedor</h2>
    </div>
    <form action="<?= url('suppliers/store') ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Empresa <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= old('name') ?>"
                   placeholder="Ej: Harinera Central" required maxlength="120">
        </div>

        <div>
            <label for="tax_id" class="form-label">NIT / RUC</label>
            <input type="text" id="tax_id" name="tax_id" class="form-input" value="<?= old('tax_id') ?>"
                   placeholder="Ej: 0614-2233345-6" maxlength="50">
        </div>

        <div>
            <label for="supplier_type" class="form-label">Tipo de proveedor</label>
            <select id="supplier_type" name="supplier_type" class="form-input">
                <option value="">Selecciona un tipo...</option>
                <?php foreach (Supplier::tipos() as $tipo): ?>
                    <option value="<?= esc($tipo) ?>" <?= old('supplier_type') === $tipo ? 'selected' : '' ?>><?= esc($tipo) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="contact" class="form-label">Encargado</label>
            <input type="text" id="contact" name="contact" class="form-input" value="<?= old('contact') ?>"
                   placeholder="Ej: María López" maxlength="100">
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" class="form-input" value="<?= old('phone') ?>"
                   placeholder="Ej: 2222-1111" maxlength="20">
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" class="form-input" value="<?= old('email') ?>"
                   placeholder="Ej: ventas@proveedor.com" maxlength="150">
        </div>

        <div class="md:col-span-2">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" class="form-input" value="<?= old('address') ?>"
                   placeholder="Ej: Av. Principal km 4, ruta al puerto" maxlength="255">
        </div>

        <div class="md:col-span-2">
            <label for="supplies" class="form-label">Producto a proveer</label>
            <input type="text" id="supplies" name="supplies" class="form-input" value="<?= old('supplies') ?>"
                   placeholder="Ej: Harina de trigo, levadura, kraft" maxlength="255">
            <p class="text-xs text-gray-400 mt-1.5">Separa varios productos con comas.</p>
        </div>

        <div class="md:col-span-2">
            <label class="form-label">Días de disponibilidad</label>
            <div class="flex flex-wrap gap-2">
                <?php foreach (Supplier::dias() as $clave => $etiqueta): ?>
                    <label class="cursor-pointer">
                        <input type="checkbox" name="availability_days[]" value="<?= esc($clave) ?>"
                               class="peer sr-only" <?= in_array($clave, $dias, true) ? 'checked' : '' ?>>
                        <span class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 transition peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-200">
                            <?= esc($etiqueta) ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1.5">Días en los que el proveedor entrega o atiende.</p>
        </div>

        <div>
            <label for="payment_terms" class="form-label">Condiciones de pago</label>
            <input type="text" id="payment_terms" name="payment_terms" class="form-input"
                   value="<?= old('payment_terms') ?>" placeholder="Ej: Contado, 30 días" maxlength="150">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= old('status', 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= old('status') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="notes" class="form-label">Notas</label>
            <textarea id="notes" name="notes" rows="3" class="form-input resize-y"
                      placeholder="Horario de entrega, requisitos de almacenamiento, etc."><?= old('notes') ?></textarea>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Proveedor
            </button>
            <a href="<?= url('suppliers') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>