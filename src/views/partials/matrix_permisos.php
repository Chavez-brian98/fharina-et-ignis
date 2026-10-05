<?php
/**
 * Modal reutilizable para editar permisos por registro (checkboxes).
 *
 * $matrixConfig['modo']        'rol' | 'empleado'
 * $matrixConfig['conBloqueo']  adds the «Bloquear acceso» column (employees)
 *
 * The trigger button must carry data-perms with the JSON payload:
 *   roles:     { url, titulo, admin, perms:{ modulo:{ view,create,edit,delete } } }
 *   employees: { url, titulo, admin, rolId, overrides:{ modulo:{...} },
 *                permsPorRol:{ roleId:{ modulo:{...} } }, rolesAdmin:{ roleId:0|1 } }
 *
 * In employee mode the matrix is painted with the permissions the employee
 * effectively has (role + overrides) and is repainted when the role changes.
 * Only the modules the user actually changes are submitted, so untouched
 * permissions keep inheriting from the role.
 */
$accionesModal = Permiso::acciones();
$gruposModal = Permiso::modulosPorGrupo();
$conBloqueo = !empty($matrixConfig['conBloqueo']);
$columnas = $conBloqueo ? count($accionesModal) + 2 : count($accionesModal) + 1;

$catalog = [
    'acciones' => [],
    'columnas' => $columnas,
    'conBloqueo' => $conBloqueo,
    'grupos' => [],
];
foreach ($accionesModal as $accion => $label) {
    $catalog['acciones'][$accion] = $label;
}
foreach ($gruposModal as $grupo => $modulos) {
    $catalog['grupos'][$grupo] = [];
    foreach ($modulos as $clave => $modulo) {
        $catalog['grupos'][$grupo][] = [
            'clave' => $clave,
            'label' => $modulo['label'],
            'icon' => $modulo['icon'],
            'url' => $modulo['url'] ?? null,
        ];
    }
}
?>
<div class="modal-overlay" id="permModal">
    <div class="modal-panel w-full max-w-4xl rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 overflow-hidden flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white shrink-0">
            <h3 class="font-bold text-lg" id="permModalTitle">Permisos</h3>
            <button type="button" class="perm-close text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>

        <form id="permForm" method="post" class="flex flex-col min-h-0 flex-1">
            <?php if ($conBloqueo): ?>
                <input type="hidden" name="perm_matrix" id="permMatrixFlag" value="1">
            <?php endif; ?>
            <div class="px-6 pt-5 <?= empty($matrixConfig['roles']) ? 'hidden' : '' ?>" id="permRoleWrap">
                <label for="permRoleSelect" class="form-label">Rol del empleado</label>
                <select id="permRoleSelect" name="role_id" class="form-input">
                    <?php foreach (($matrixConfig['roles'] ?? []) as $rol): ?>
                        <option value="<?= (int) $rol['id'] ?>">
                            <?= esc(Role::label($rol['name'])) ?><?= (int) $rol['is_admin'] === 1 ? ' (acceso total)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-1.5">
                    Las casillas ya vienen marcadas con lo que el empleado puede hacer
                    <strong>según su rol</strong>. Al modificar un módulo queda como
                    <strong>permiso específico</strong> (tiene prioridad sobre el rol);
                    el resto sigue heredándose del rol.
                </p>
            </div>

            <div class="px-6 py-5 bg-white overflow-y-auto flex-1" id="permModalBody"></div>

            <div class="flex items-center justify-between gap-3 border-t border-gray-100 bg-gray-50 px-6 py-4 shrink-0">
                <p class="text-xs text-gray-500" id="permModalHint"></p>
                <div class="flex items-center gap-2">
                    <button type="button" id="permSelectAll"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-check-double"></i> Marcar todo
                    </button>
                    <button type="button" id="permClearAll"
                            class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-eraser"></i> Limpiar
                    </button>
                    <button type="button" id="permCancel"
                            class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" id="permSave"
                            class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                        <i class="fa-solid fa-floppy-disk"></i> Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    (function () {
        const modal = document.getElementById('permModal');
        if (!modal) return;

        const catalog = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE) ?>;
        const form = document.getElementById('permForm');
        const body = document.getElementById('permModalBody');
        const titleEl = document.getElementById('permModalTitle');
        const saveBtn = document.getElementById('permSave');
        const hintEl = document.getElementById('permModalHint');

        const esc = (value) => String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const TODAS = Object.keys(catalog.acciones);
        let fila = null;          // payload abierto
        let snapshot = {};        // estado inicial de cada módulo (para detectar cambios)

        const open = () => {
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
        };
        const close = () => {
            modal.classList.remove('open');
            document.body.style.overflow = '';
            fila = null;
            snapshot = {};
        };

        const clavesModulos = () => {
            const claves = [];
            for (const grupo in catalog.grupos) {
                catalog.grupos[grupo].forEach((m) => claves.push(m.clave));
            }
            return claves;
        };

        /**
         * Permisos que tiene el empleado ahora mismo = lo que otorga el rol
         * seleccionado + las excepciones específicas ya guardadas.
         */
        const permisosActuales = (payload, rolId) => {
            const porRol = (payload.permsPorRol && payload.permsPorRol[String(rolId)]) || {};
            const overrides = payload.overrides || {};
            const salida = {};

            clavesModulos().forEach((clave) => {
                const base = porRol[clave] || {};
                const actual = {};

                TODAS.forEach((accion) => { actual[accion] = base[accion] === true; });

                const propio = overrides[clave];
                if (propio) {
                    TODAS.forEach((accion) => { actual[accion] = propio[accion] === true; });
                    // Fila toda en ceros = módulo bloqueado explícitamente.
                    actual.bloqueo = !TODAS.some((accion) => actual[accion] === true);
                } else {
                    actual.bloqueo = false;
                }

                salida[clave] = actual;
            });

            return salida;
        };

        const rolEsAdmin = (rolId) => !!(fila && fila.rolesAdmin && fila.rolesAdmin[String(rolId)] === 1);

        const checkCell = (modulo, accion, checked, blocked) => {
            return '<td class="px-2 py-2 text-center">'
                + '<input type="checkbox" name="perm_' + modulo + '_' + accion + '" value="1"'
                + ' data-mod="' + esc(modulo) + '" data-acc="' + esc(accion) + '"'
                + ' class="perm-box w-4 h-4 rounded border-gray-300 text-orange-500 focus:ring-orange-200 cursor-pointer"'
                + (checked ? ' checked' : '')
                + (blocked ? ' disabled' : '')
                + '></td>';
        };

        const adminNotice = (texto) => {
            return '<div class="rounded-2xl bg-green-50 ring-1 ring-green-200 px-5 py-6 text-sm text-green-800">'
                + '<i class="fa-solid fa-circle-check text-lg mr-1"></i>'
                + ' Este registro tiene <strong>acceso total</strong>: los permisos se aplican automáticamente y no pueden limitarse.'
                + '<p class="mt-2 text-green-700">' + esc(texto) + '</p>'
                + '</div>';
        };

        const buildMatrix = (payload) => {
            const perms = payload.perms || {};
            const propios = payload.especificos || [];
            let head = '<tr class="bg-gray-50 text-[11px] font-bold uppercase tracking-wider text-gray-400">'
                + '<th class="px-4 py-3 text-left">Módulo</th>';
            for (const accion in catalog.acciones) {
                head += '<th class="px-2 py-3 text-center w-24">' + esc(catalog.acciones[accion]) + '</th>';
            }
            if (catalog.conBloqueo) {
                head += '<th class="px-2 py-3 text-center w-28 text-red-400">Bloquear</th>';
            }
            head += '</tr>';

            let rows = '';
            let total = 0;

            for (const grupo in catalog.grupos) {
                const items = catalog.grupos[grupo];
                if (!items.length) continue;

                const filas = items.map((modulo) => {
                    const actual = perms[modulo.clave] || {};
                    const bloqueado = catalog.conBloqueo && actual.bloqueo === true;
                    total++;

                    let celdas = '';
                    for (const accion in catalog.acciones) {
                        celdas += checkCell(modulo.clave, accion, actual[accion] === true, bloqueado);
                    }
                    if (catalog.conBloqueo) {
                        celdas += '<td class="px-2 py-2 text-center">'
                            + '<input type="checkbox" name="bloqueo_' + modulo.clave + '" value="1"'
                            + ' data-bloqueo="' + esc(modulo.clave) + '"'
                            + ' class="perm-block w-4 h-4 rounded border-gray-300 text-red-500 focus:ring-red-200 cursor-pointer"'
                            + (bloqueado ? ' checked' : '')
                            + '></td>';
                    }

                    const sinModulo = modulo.url === null
                        ? '<span class="ml-1 rounded-md bg-gray-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-gray-400">Próximamente</span>'
                        : '';

                    const esProprio = propios.indexOf(modulo.clave) !== -1;
                    const marcaPropio = esProprio
                        ? '<span class="ml-1 rounded-md bg-sky-50 px-1.5 py-0.5 text-[9px] font-bold uppercase text-sky-600" title="Permiso específico de este empleado (tiene prioridad sobre su rol)">Específico</span>'
                        : '';

                    return '<tr data-row="' + esc(modulo.clave) + '" class="hover:bg-orange-50/30 transition-colors">'
                        + '<td class="px-4 py-2">'
                        + '<span class="inline-flex items-center gap-2 font-medium text-gray-800">'
                        + '<i class="fa-solid ' + esc(modulo.icon) + ' text-orange-400 w-4 text-center"></i>'
                        + esc(modulo.label) + '</span>' + marcaPropio + sinModulo
                        + '</td>' + celdas + '</tr>';
                }).join('');

                rows += '<tr class="bg-orange-50/50"><td colspan="' + catalog.columnas + '"'
                    + ' class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-orange-600">'
                    + esc(grupo) + '</td></tr>' + filas;
            }

            return '<div class="rounded-2xl border border-gray-200 overflow-hidden">'
                + '<table class="w-full text-sm"><thead>' + head + '</thead>'
                + '<tbody class="divide-y divide-gray-100">' + rows + '</tbody></table></div>';
        };

        // Estado inicial de la matriz, para saber qué módulos tocó el usuario.
        const tomarSnapshot = () => {
            const estado = {};

            modal.querySelectorAll('tr[data-row]').forEach((tr) => {
                const bloquea = tr.querySelector('input[data-bloqueo]');
                const filaActual = { bloqueo: bloquea ? bloquea.checked : false };

                tr.querySelectorAll('input[data-acc]').forEach((c) => {
                    filaActual[c.dataset.acc] = c.checked;
                });

                estado[tr.dataset.row] = filaActual;
            });

            return estado;
        };

        // «Bloquear acceso» manda sobre las casillas de ese módulo.
        const syncBloqueo = (modulo) => {
            const box = modal.querySelector('input[data-bloqueo="' + modulo + '"]');
            if (!box) return;
            modal.querySelectorAll('input[data-mod="' + modulo + '"]').forEach((c) => {
                c.disabled = box.checked;
                c.parentElement.classList.toggle('opacity-40', box.checked);
            });
        };

        const setAll = (checked) => {
            modal.querySelectorAll('input[data-mod]').forEach((c) => {
                if (!c.disabled) c.checked = checked;
            });
            modal.querySelectorAll('input[data-bloqueo]').forEach((b) => {
                b.checked = false;
                syncBloqueo(b.dataset.bloqueo);
            });
        };

modal.addEventListener('change', (e) => {
            if (e.target.classList.contains('perm-block')) {
                syncBloqueo(e.target.dataset.bloqueo);
                return;
            }

            if (e.target.id === 'permRoleSelect') {
                render();
                return;
            }

            // Crear/Editar/Eliminar dependen de Ver: se marcan juntos.
            if (!e.target.classList.contains('perm-box')) return;
            const modulos = modal.querySelectorAll('input[data-mod="' + e.target.dataset.mod + '"]');
            const ver = modal.querySelector('input[data-mod="' + e.target.dataset.mod + '"][data-acc="view"]');

            if (e.target.dataset.acc !== 'view') {
                if (e.target.checked && ver && !ver.checked) ver.checked = true;
                return;
            }

            if (!e.target.checked) {
                modulos.forEach((c) => { c.checked = false; });
            }
        });

        document.getElementById('permSelectAll').addEventListener('click', () => setAll(true));
        document.getElementById('permClearAll').addEventListener('click', () => setAll(false));
        document.getElementById('permCancel').addEventListener('click', close);
        modal.querySelectorAll('.perm-close').forEach((b) => b.addEventListener('click', close));
        modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('open')) close();
        });

        // En empleados solo se envían los módulos tocados: los que quedan intactos
        // y sin excepción propia siguen heredando del rol. Los que ya tenían una
        // excepción se envían igual, para no borrarla sin querer.
        form.addEventListener('submit', () => {
            if (!catalog.conBloqueo) return;

            const propios = Object.keys(fila.overrides || {});
            const filasMatriz = modal.querySelectorAll('tr[data-row]');
            const flag = document.getElementById('permMatrixFlag');

            // Sin matriz visible (rol de acceso total) sólo se aplica el rol:
            // se avisa al servidor para que no toque las excepciones guardadas.
            if (flag) flag.value = filasMatriz.length ? '1' : '0';

            filasMatriz.forEach((tr) => {
                const modulo = tr.dataset.row;
                const inicial = snapshot[modulo];

                if (!inicial) return;

                const bloquea = tr.querySelector('input[data-bloqueo]');
                let cambio = bloquea ? inicial.bloqueo !== bloquea.checked : false;

                if (!cambio) {
                    tr.querySelectorAll('input[data-acc]').forEach((c) => {
                        if (!!inicial[c.dataset.acc] !== c.checked) cambio = true;
                    });
                }

                if (!cambio && propios.indexOf(modulo) === -1) {
                    tr.querySelectorAll('input').forEach((i) => { i.disabled = true; });
                }
            });
        });

        document.body.addEventListener('click', (e) => {
            const trigger = e.target.closest('.btn-perms');
            if (!trigger) return;

            const raw = trigger.getAttribute('data-perms');
            if (!raw) return;

            try { fila = JSON.parse(raw); } catch (err) { return; }

            form.action = fila.url;
            if (titleEl) titleEl.textContent = fila.titulo || 'Permisos';

            const roleSelect = document.getElementById('permRoleSelect');
            const roleWrap = document.getElementById('permRoleWrap');
            if (roleSelect && fila.rolId) roleSelect.value = String(fila.rolId);
            if (roleWrap) roleWrap.classList.toggle('hidden', fila.admin === true);

            render();
            open();
        });

        /**
         * Pinta la matriz del registro abierto. En empleados se combinan los
         * permisos del rol seleccionado con las excepciones ya guardadas.
         */
        function render() {
            const roleSelect = document.getElementById('permRoleSelect');
            const rolId = roleSelect && roleSelect.value ? roleSelect.value : fila.rolId;
            const adminActual = fila.admin === true;
            const adminPorRol = catalog.conBloqueo && rolEsAdmin(rolId);

            saveBtn.disabled = adminActual === true;

            if (adminActual === true || adminPorRol === true) {
                body.innerHTML = adminNotice(adminActual === true
                    ? 'Para usarlo necesitás quitar la marca de administrador desde la edición del registro.'
                    : 'El rol seleccionado tiene acceso total: al guardar se aplicará y sus permisos no se podrán limitar.');
                snapshot = {};
                hintEl.textContent = adminActual === true
                    ? 'Los permisos no se guardan mientras el acceso total esté activo.'
                    : 'Vas a cambiarle el rol a uno de acceso total; sus permisos quedarán sin restricción.';
                return;
            }

            const perms = catalog.conBloqueo
                ? permisosActuales(fila, rolId)
                : (fila.perms || {});

            body.innerHTML = buildMatrix({ perms, especificos: Object.keys(fila.overrides || {}) });

            Object.keys(perms).forEach((modulo) => {
                if (catalog.conBloqueo && perms[modulo] && perms[modulo].bloqueo) syncBloqueo(modulo);
            });

            snapshot = tomarSnapshot();

            hintEl.textContent = catalog.conBloqueo
                ? 'Sólo se guardan los módulos que modifiques; el resto sigue heredándose del rol.'
                : 'Los cambios se aplican apenas guardes.';
        }
    })();
</script>