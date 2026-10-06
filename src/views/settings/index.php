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
                    <?php $cameraField = 'system_logo'; require __DIR__ . '/../partials/camera_capture.php'; ?>
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
                <div>
                    <label for="tax_id" class="form-label">NIT/RUC/Identificación</label>
                    <input type="text" id="tax_id" name="tax_id" class="form-input" value="<?= esc($settings['tax_id'] ?? '') ?>">
                </div>
                <div>
                    <label for="branch_code" class="form-label">Código de sucursal</label>
                    <input type="text" id="branch_code" name="branch_code" class="form-input" value="<?= esc($settings['branch_code'] ?? '') ?>" maxlength="10">
                </div>
                <div>
                    <label for="terminal_id" class="form-label">Terminal</label>
                    <input type="text" id="terminal_id" name="terminal_id" class="form-input" value="<?= esc($settings['terminal_id'] ?? '') ?>" maxlength="10">
                </div>
                <div>
                    <label for="control_number" class="form-label">Número de control</label>
                    <input type="text" id="control_number" name="control_number" class="form-input" value="<?= esc($settings['control_number'] ?? '') ?>" maxlength="20">
                </div>
                <div class="md:col-span-2">
                    <label for="kiosk_key" class="form-label">Clave del quiosco de asistencia</label>
                    <input type="text" id="kiosk_key" name="kiosk_key" class="form-input" value="<?= esc($settings['kiosk_key'] ?? '') ?>" maxlength="60">
                    <p class="text-xs text-gray-400 mt-1.5">Usada para abrir/bloquear <strong>/kiosco</strong>. Solo un dispositivo compartido debería tenerla.</p>
                </div>
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
                <div class="md:col-span-1">
                    <label for="cash_register_base" class="form-label">Fondo base de caja</label>
                    <input type="number" id="cash_register_base" name="cash_register_base" class="form-input"
                           value="<?= esc($settings['cash_register_base'] ?? '125.00') ?>" min="0" step="0.01">
                    <p class="text-xs text-gray-400 mt-1.5">Monto con el que se abre una caja por turno.</p>
                </div>
                <div class="md:col-span-1">
                    <label for="timezone" class="form-label">Zona horaria</label>
                    <select id="timezone" name="timezone" class="form-input">
                        <?php
                        $tzActual = $settings['timezone'] ?? 'America/El_Salvador';
                        if (!array_key_exists($tzActual, timezoneOpciones())) {
                            $tzActual = 'America/El_Salvador';
                        }
                        foreach (timezoneOpciones() as $zona => $etiqueta) : ?>
                            <option value="<?= esc($zona) ?>" <?= $tzActual === $zona ? 'selected' : '' ?>>
                                <?= esc($etiqueta) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1.5">Define la hora de asistencia, caja y tickets.</p>
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

        <!-- Color principal -->
        <div class="rounded-2xl bg-white p-6 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center">
                    <i class="fa-solid fa-palette"></i>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Color principal</h3>
                    <p class="text-xs text-gray-500">El color acento de todo el sistema (botones, sidebar, gráficas).</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <label for="primary_color" class="flex items-center gap-3 cursor-pointer">
                    <input type="color" id="primary_color" name="primary_color"
                           value="<?= esc($settings['primary_color'] ?? '#f97316') ?>"
                           class="relative w-14 h-14 rounded-2xl cursor-pointer border-0 p-1.5 bg-white ring-1 ring-gray-200">
                    <span class="text-sm text-gray-500">Elige el color</span>
                </label>
                <div class="flex items-center gap-1.5" id="primaryPalette">
                    <span style="background: var(--color-primary-50)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                    <span style="background: var(--color-primary-100)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                    <span style="background: var(--color-primary-300)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                    <span style="background: var(--color-primary-500)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                    <span style="background: var(--color-primary-600)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                    <span style="background: var(--color-primary-800)" class="w-7 h-7 rounded-lg ring-1 ring-gray-200"></span>
                </div>
            </div>
            <script>
                document.getElementById('primary_color').addEventListener('input', function () {
                    document.documentElement.style.setProperty('--color-primary', this.value);
                });
            </script>
            <p class="text-xs text-gray-400 mt-3">Cambia el acento naranja por el color que prefieras: los tonos claros (50–300) y oscuros (600–950) se derivan automáticamente.</p>
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
                    <?php $cameraField = 'login_photo'; require __DIR__ . '/../partials/camera_capture.php'; ?>
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