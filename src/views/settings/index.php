<?php require __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="space-y-8">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Configuración del sistema</h2>
            <p class="text-sm text-gray-500 mt-1">Administra la identidad, el ticket y la apariencia del sistema.</p>
        </div>
    </div>

    <form action="<?= url('settings/update') ?>" method="POST" enctype="multipart/form-data" class="space-y-8">

        <!-- Identidad -->
        <div class="rounded-2xl bg-white p-6 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                    <i class="fa-solid fa-store"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Identidad del negocio</h3>
                    <p class="text-xs text-gray-500">Nombre y logo que se muestran en toda la app.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="system_name" class="form-label">Nombre del sistema</label>
                    <input type="text" id="system_name" name="system_name" class="form-input" value="<?= esc($settings['system_name'] ?? '') ?>">
                </div>
                <div>
                    <label for="business_name" class="form-label">Nombre del negocio</label>
                    <input type="text" id="business_name" name="business_name" class="form-input" value="<?= esc($settings['business_name'] ?? '') ?>">
                </div>
            </div>

            <div class="mt-5">
                <label for="system_logo" class="form-label">Logo del sistema</label>
                <div class="flex items-center gap-4">
                    <?php if (!empty($settings['system_logo'])): ?>
                        <img src="<?= esc($settings['system_logo']) ?>" alt="Logo actual" class="w-14 h-14 rounded-xl object-contain ring-1 ring-gray-200 bg-white p-1">
                    <?php else: ?>
                        <div class="w-14 h-14 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center text-lg">
                            <i class="fa-regular fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="system_logo" name="system_logo" accept="image/*" class="form-input">
                </div>
                <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, WEBP o GIF · máximo 2 MB · se sube al sistema y se muestra en el sidebar.</p>
            </div>
        </div>

        <!-- Contacto -->
        <div class="rounded-2xl bg-white p-6 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                    <i class="fa-solid fa-address-book"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Contacto</h3>
                    <p class="text-xs text-gray-500">Datos de la sucursal, se imprimen en el ticket.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div class="md:col-span-1">
                    <label for="phone" class="form-label">Teléfono</label>
                    <input type="text" id="phone" name="phone" class="form-input" value="<?= esc($settings['phone'] ?? '') ?>">
                </div>
                <div class="md:col-span-1">
                    <label for="currency" class="form-label">Símbolo de moneda</label>
                    <input type="text" id="currency" name="currency" class="form-input" value="<?= esc($settings['currency'] ?? '') ?>" maxlength="5">
                </div>
                <div class="md:col-span-1">
                    <label for="tax_rate" class="form-label">Impuesto (%)</label>
                    <input type="number" id="tax_rate" name="tax_rate" class="form-input" value="<?= esc($settings['tax_rate'] ?? '0') ?>" min="0" max="100" step="0.01">
                </div>
                <div class="md:col-span-3">
                    <label for="address" class="form-label">Dirección</label>
                    <input type="text" id="address" name="address" class="form-input" value="<?= esc($settings['address'] ?? '') ?>">
                </div>
            </div>
        </div>

        <!-- Ticket -->
        <div class="rounded-2xl bg-white p-6 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Ticket</h3>
                    <p class="text-xs text-gray-500">Mensaje que aparece al final del ticket de venta (POS).</p>
                </div>
            </div>

            <div>
                <label for="ticket_footer" class="form-label">Descripción del ticket (parte inferior)</label>
                <textarea id="ticket_footer" name="ticket_footer" rows="4" class="form-input resize-y"><?= esc($settings['ticket_footer'] ?? '') ?></textarea>
                <p class="text-xs text-gray-400 mt-1.5">Se imprime debajo del total, por ejemplo: "¡Gracias por su compra!"</p>
            </div>
        </div>

        <!-- Apariencia -->
        <div class="rounded-2xl bg-white p-6 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                    <i class="fa-solid fa-palette"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Apariencia del login</h3>
                    <p class="text-xs text-gray-500">Imagen de fondo de la pantalla de inicio de sesión.</p>
                </div>
            </div>

            <div>
                <label for="login_photo" class="form-label">Foto del login (mitad izquierda)</label>
                <div class="flex items-center gap-4">
                    <img src="<?= esc($settings['login_photo'] ?? '') ?>" alt="Foto de login actual"
                         class="w-28 h-20 rounded-xl object-cover ring-1 ring-gray-200 bg-gray-100">
                    <input type="file" id="login_photo" name="login_photo" accept="image/*" class="form-input">
                </div>
                <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, WEBP o GIF · máximo 2 MB · recomendado 1200 × 800 px.</p>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-colors">
                <i class="fa-solid fa-floppy-disk"></i> Guardar cambios
            </button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../layouts/footer.php'; ?>