<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Editar Cliente</h1>
    <a href="<?= url('clients') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
    <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-user text-orange-500 mr-2"></i>Información del cliente</h2>
    </div>
    <form action="<?= url('clients/update/' . $client['id']) ?>" method="POST" class="px-6 py-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="name" class="form-label">Nombre <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" class="form-input" value="<?= esc($client['name']) ?>" required>
        </div>

        <div>
            <label for="last_name" class="form-label">Apellido <span class="text-red-500">*</span></label>
            <input type="text" id="last_name" name="last_name" class="form-input" value="<?= esc($client['last_name']) ?>" required>
        </div>

        <div>
            <label for="phone" class="form-label">Teléfono</label>
            <input type="text" id="phone" name="phone" maxlength="20" class="form-input" value="<?= esc($client['phone']) ?>" placeholder="Ej: 5555-0001">
        </div>

        <div>
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" id="email" name="email" maxlength="150" class="form-input" value="<?= esc($client['email']) ?>" placeholder="cliente@mail.com">
        </div>

        <div class="md:col-span-2">
            <label for="address" class="form-label">Dirección</label>
            <input type="text" id="address" name="address" maxlength="255" class="form-input" value="<?= esc($client['address']) ?>">
        </div>

        <div>
            <label for="birth_date" class="form-label">Fecha de nacimiento</label>
            <input type="date" id="birth_date" name="birth_date" class="form-input" value="<?= esc($client['birth_date']) ?>">
        </div>

        <div>
            <label for="status" class="form-label">Estado</label>
            <select id="status" name="status" class="form-input">
                <option value="active" <?= $client['status'] === 'active' ? 'selected' : '' ?>>Activo</option>
                <option value="inactive" <?= $client['status'] === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>

        <div class="md:col-span-2 flex items-center gap-3 mt-2">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-floppy-disk"></i> Actualizar Cliente
            </button>
            <a href="<?= url('clients') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">Cancelar</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>