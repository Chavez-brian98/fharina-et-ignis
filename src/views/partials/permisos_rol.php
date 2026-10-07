<?php
$accionesPermiso = Permiso::acciones();
$gruposPermiso = Permiso::modulosPorGrupo();
$esAdminRol = (int) ($role['is_admin'] ?? 0) === 1;
$permisosActual = $rolePermissions ?? [];

$permisoDe = function ($modulo, $accion) use ($permisosActual, $esAdminRol) {
    if ($esAdminRol) {
        return true;
    }
    return !empty($permisosActual[$modulo][$accion]);
};
?>
<div class="md:col-span-2 mt-2 border-t border-gray-100 pt-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h3 class="font-semibold text-gray-900"><i class="fa-solid fa-key text-orange-500 mr-2"></i>Permisos del rol</h3>
            <p class="text-sm text-gray-500 mt-1">Marca qué puede ver, crear, editar y eliminar este rol en cada módulo.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="permSelectAll" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-check-double"></i> Marcar todo
            </button>
            <button type="button" id="permClearAll" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-eraser"></i> Limpiar
            </button>
        </div>
    </div>
</div>

<?php if ($esAdminRol): ?>
    <div class="md:col-span-2 rounded-xl bg-green-50 ring-1 ring-green-200 px-4 py-3 text-sm text-green-800">
        <i class="fa-solid fa-circle-check mr-1"></i>
        Este rol tiene <strong>acceso total</strong>: los permisos se aplican automáticamente y no pueden limitarse.
    </div>
<?php endif; ?>

<div id="rolePermissionsGrid" class="md:col-span-2 rounded-2xl border border-gray-200 overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <tr>
                <th class="px-4 py-3 text-left">Módulo</th>
                <?php foreach ($accionesPermiso as $accion => $label): ?>
                    <th class="px-3 py-3 text-center w-24"><?= esc($label) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
            <?php foreach ($gruposPermiso as $grupo => $modulos): ?>
                <tr class="bg-orange-50/50">
                    <td colspan="<?= count($accionesPermiso) + 1 ?>" class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-orange-600"><?= esc($grupo) ?></td>
                </tr>
                <?php foreach ($modulos as $clave => $modulo): ?>
                    <tr class="hover:bg-orange-50/30 transition-colors">
                        <td class="px-4 py-2.5">
                            <span class="inline-flex items-center gap-2 font-medium text-gray-800">
                                <i class="fa-solid <?= esc($modulo['icon']) ?> text-orange-400 w-4 text-center"></i>
                                <?= esc($modulo['label']) ?>
                            </span>
                            <?php if (empty($modulo['url'])): ?>
                                <span class="ml-2 rounded-md bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-gray-400">Próximamente</span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ($accionesPermiso as $accion => $label): ?>
                            <?php $inputId = 'perm_' . $clave . '_' . $accion; ?>
                            <td class="px-3 py-2.5 text-center">
                                <input type="checkbox" id="<?= esc($inputId) ?>" name="<?= esc($inputId) ?>" value="1"
                                       data-perm="<?= esc($clave) ?>"
                                       class="perm-check w-4 h-4 rounded border-gray-300 text-orange-500 focus:ring-orange-200 cursor-pointer"
                                       <?= $permisoDe($clave, $accion) ? 'checked' : '' ?>
                                       <?= $esAdminRol ? 'disabled' : '' ?>>
                                <input type="hidden" name="<?= esc($inputId) ?>" value="1" class="perm-mirror"
                                       <?= $esAdminRol ? '' : 'disabled' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    (function () {
        const grid = document.getElementById('rolePermissionsGrid');
        const adminToggle = document.getElementById('is_admin');
        if (!grid) return;
        const checks = () => grid.querySelectorAll('.perm-check');
        const mirrors = () => grid.querySelectorAll('.perm-mirror');

        // Mientras el rol es administrador los checks van deshabilitados, así que
        // un input oculto equivalente mantiene el valor al enviar el formulario.
        // Si luego se quita la marca de administrador el rol conserva los permisos.
        const syncAdmin = () => {
            const isAdmin = adminToggle && adminToggle.checked;
            checks().forEach((c) => { c.disabled = !!isAdmin; });
            mirrors().forEach((m) => { m.disabled = !isAdmin; });
        };

        if (adminToggle) {
            adminToggle.addEventListener('change', syncAdmin);
            syncAdmin();
        }

        const selectAll = document.getElementById('permSelectAll');
        const clearAll = document.getElementById('permClearAll');
        if (selectAll) {
            selectAll.addEventListener('click', () => {
                checks().forEach((c) => { if (!c.disabled) c.checked = true; });
            });
        }
        if (clearAll) {
            clearAll.addEventListener('click', () => {
                checks().forEach((c) => { if (!c.disabled) c.checked = false; });
            });
        }
    })();
</script>