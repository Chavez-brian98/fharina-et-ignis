<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$cur = $miResumen;
$abierta = $miCaja !== null;
$puedeAbrir = !$abierta;
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Caja</h1>
    <div class="flex items-center gap-2">
        <?php if ($puedeGestionar): ?>
            <button type="button" id="btnOpenAssign"
                    class="inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-user-plus text-orange-500"></i> Abrir y asignar
            </button>
        <?php endif; ?>
        <?php if ($abierta): ?>
            <button type="button" data-caja-movimiento="<?= (int) $miCaja['id'] ?>"
                    class="inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-right-arrow-left text-orange-500"></i> Ingreso / Retiro
            </button>
            <button type="button" data-caja-close="<?= (int) $miCaja['id'] ?>"
                    class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-lock"></i> Cerrar mi caja
            </button>
        <?php else: ?>
            <button type="button" id="btnOpenOwn"
                    class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-lock-open"></i> Abrir mi caja
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$abierta): ?>
    <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-800 flex items-start gap-3">
        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
        <div>
            <p class="font-semibold">No tenés una caja abierta.</p>
            <p class="mt-0.5 text-amber-700">
                Para vender en el punto de venta necesitás abrir una caja con el fondo base de
                <strong>$<?= esc(number_format($fondoBase, 2)) ?></strong>. Te vamos a pedir tu contraseña para confirmar que sos vos.
            </p>
        </div>
    </div>
<?php endif; ?>

<!-- ===================== MI CAJA ===================== -->
<?php if ($abierta && $cur): ?>
    <div class="mb-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-2xl bg-white border border-gray-100 shadow-lg shadow-gray-200/50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Caja abierta</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">#<?= (int) $cur['id'] ?></p>
            <p class="mt-2 text-sm text-gray-500">
                <?= esc(date('d/m/Y', strtotime($cur['cash_date']))) ?> &middot;
                desde las <?= esc(substr((string) $cur['opening_time'], 0, 5)) ?>
            </p>
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                <i class="fa-solid fa-circle text-[6px]"></i> Abierta
            </p>
            <a href="<?= url('cash_register/show/' . (int) $cur['id']) ?>"
               class="mt-4 flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors hover:bg-orange-50 hover:text-orange-600">
                <i class="fa-solid fa-list-check"></i> Ver detalle completo
            </a>
        </div>

        <div class="rounded-2xl bg-white border border-gray-100 shadow-lg shadow-gray-200/50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Fondo inicial</p>
            <p class="mt-1 text-2xl font-bold text-gray-900">$<?= esc(number_format((float) $cur['initial_amount'], 2)) ?></p>
            <p class="mt-2 text-sm text-gray-500">
                Efectivo recebido: <strong>$<?= esc(number_format((float) $cur['cash_sales'], 2)) ?></strong>
            </p>
            <p class="text-sm text-gray-500">
                Ingresos / retiros:
                <strong class="<?= (float) $cur['cash_movements'] < 0 ? 'text-red-600' : 'text-gray-700' ?>">
                    <?= (float) $cur['cash_movements'] >= 0 ? '+' : '-' ?>$<?= esc(number_format(abs((float) $cur['cash_movements']), 2)) ?>
                </strong>
            </p>
        </div>

        <div class="rounded-2xl bg-gradient-to-br from-orange-500 to-orange-400 p-5 text-white shadow-lg shadow-orange-500/25">
            <p class="text-xs font-semibold uppercase tracking-wider text-white/80">Efectivo esperado</p>
            <p class="mt-1 text-3xl font-bold">$<?= esc(number_format((float) $cur['expected_cash'], 2)) ?></p>
            <p class="mt-2 text-sm text-white/90">
                <?= (int) $cur['sales_count'] ?> venta<?= (int) $cur['sales_count'] === 1 ? '' : 's' ?> &middot;
                tarjetas $<?= esc(number_format((float) $cur['card_sales'], 2)) ?>
            </p>
            <button type="button" data-caja-close="<?= (int) $cur['id'] ?>"
                    class="mt-3 w-full rounded-xl bg-white px-4 py-2 text-sm font-semibold text-orange-600 shadow-sm hover:bg-orange-50 transition-colors">
                <i class="fa-solid fa-calculator mr-1.5"></i> Hacer arqueo
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- ===================== PANEL DE SUPERVISIÓN ===================== -->
<?php if ($puedeGestionar): ?>
    <?php if (!empty($cajasAbiertas)): ?>
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-5">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3">Cajas abiertas ahora</p>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($cajasAbiertas as $c): ?>
                    <a href="<?= url('cash_register/show/' . (int) $c['id']) ?>"
                       class="inline-flex items-center gap-2 rounded-xl bg-orange-50 px-3 py-2 text-sm font-medium text-orange-800 ring-1 ring-orange-100 transition-colors hover:bg-orange-100">
                        <i class="fa-solid fa-user-tie text-orange-500"></i>
                        <?= esc(trim($c['employee_name'] . ' ' . $c['employee_last_name'])) ?>
                        <span class="text-xs text-orange-500">#<?= (int) $c['id'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- ===================== HISTORIAL ===================== -->
<div class="mb-4 flex items-center justify-between">
    <h2 class="text-lg font-bold text-gray-900">
        <?= $puedeGestionar ? 'Historial de cajas' : 'Mis cajas' ?>
    </h2>
</div>

<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por empleado, fecha o estado...">
    </div>
    <div>
        <select id="filterStatus" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todas las cajas</option>
            <option value="abierta">Abiertas</option>
            <option value="cerrada">Cerradas</option>
        </select>
    </div>
</div>

<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Caja</th>
                    <th class="px-5 py-3 font-semibold">Empleado</th>
                    <th class="px-5 py-3 font-semibold">Fondo</th>
                    <th class="px-5 py-3 font-semibold">Esperado</th>
                    <th class="px-5 py-3 font-semibold">Físico</th>
                    <th class="px-5 py-3 font-semibold">Diferencia</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($cajas as $item):
                    $emp = trim($item['opening_name'] . ' ' . $item['opening_last_name']);
                    $cerrada = $item['state'] === 'cerrada';
                    $diff = $cerrada ? (float) $item['difference'] : null;
                    ?>
                    <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                        data-status="<?= esc($item['state']) ?>"
                        data-search="<?= esc(strtolower($emp . ' #' . $item['id'] . ' ' . date('d/m/Y', strtotime($item['cash_date'])) . ' ' . ($cerrada ? 'cerrada' : 'abierta'))) ?>">
                        <td class="px-5 py-3.5 font-semibold text-gray-900">#<?= (int) $item['id'] ?></td>
                        <td class="px-5 py-3.5 text-gray-600"><?= esc($emp) ?></td>
                        <td class="px-5 py-3.5 text-gray-600">$<?= esc(number_format((float) $item['initial_amount'], 2)) ?></td>
                        <td class="px-5 py-3.5 text-gray-600">$<?= esc(number_format((float) $item['expected_cash'], 2)) ?></td>
                        <td class="px-5 py-3.5 text-gray-600">
                            <?= $cerrada ? '$' . esc(number_format((float) $item['physical_final_amount'], 2)) : '—' ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <?php if (!$cerrada): ?>
                                <span class="text-gray-300">—</span>
                            <?php elseif (abs((float) $diff) < 0.005): ?>
                                <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-semibold text-green-700">Cuadra</span>
                            <?php elseif ($diff < 0): ?>
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">
                                    Faltan $<?= esc(number_format(abs((float) $diff), 2)) ?>
                                </span>
                            <?php else: ?>
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                                    Sobran $<?= esc(number_format((float) $diff, 2)) ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $cerrada ? 'bg-gray-100 text-gray-600' : 'bg-green-50 text-green-700' ?>">
                                <?= $cerrada ? 'Cerrada' : 'Abierta' ?>
                            </span>
                            <?php if ((int) $item['reopen_count'] > 0): ?>
                                <span class="ml-1 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-600">
                                    reabierta
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-1.5">
                                <a class="btn-action" title="Ver detalle completo" href="<?= url('cash_register/show/' . $item['id']) ?>">
                                    <i class="fa-regular fa-eye"></i>
                                </a>
                                <?php if ($cerrada && $puedeGestionar): ?>
                                    <button type="button" class="btn-action" title="Reabrir"
                                            data-caja-reopen="<?= (int) $item['id'] ?>">
                                        <i class="fa-solid fa-rotate-right text-violet-500"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p id="emptyState" class="hidden text-center text-sm text-gray-400 py-10">
        <i class="fa-solid fa-cash-register block text-3xl mb-2 text-gray-300"></i>
        No hay cajas que coincidan con la búsqueda.
    </p>
</div>

<!-- ===================== MODAL: ABRIR MI CAJA (reautenticación) ===================== -->
<div class="modal-overlay" id="openOwnModal" data-plain>
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg">Abrir mi caja</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="post" action="<?= url('cash_register/open') ?>" class="px-6 py-5">
            <p class="text-sm text-gray-600 mb-4">
                Confirmá tu contraseña para abrir la caja con el fondo base de
                <strong>$<?= esc(number_format($fondoBase, 2)) ?></strong>.
            </p>
            <label for="openOwnPassword" class="form-label">Tu contraseña</label>
            <input type="password" name="password" id="openOwnPassword" required autocomplete="current-password"
                   class="form-input" placeholder="••••••••">
            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="btn-close-modal rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-lock-open"></i> Abrir caja
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL: ABRIR Y ASIGNAR ===================== -->
<?php if ($puedeGestionar): ?>
    <div class="modal-overlay" id="openAssignModal" data-plain>
        <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
            <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
                <h3 class="font-bold text-lg">Abrir y asignar caja</h3>
                <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
            </div>
            <form method="post" action="<?= url('cash_register/open') ?>" class="px-6 py-5">
                <?php
                $empSearch = [
                    'label' => 'Cajero',
                    'name' => 'employee_id',
                    'inputId' => 'assignEmployee',
                    'value' => 0,
                    'required' => true,
                    'placeholder' => 'Busca al cajero…',
                    'empleados' => array_map(function ($c) {
                        return [
                            'id' => (int) $c['id'],
                            'name' => $c['name'] ?? '',
                            'last_name' => $c['last_name'] ?? '',
                            'role' => '',
                            'disabled' => (int) ($c['has_open_register'] ?? 0) === 1,
                            'note' => 'Ya tiene caja abierta',
                        ];
                    }, $cajeros),
                ];
                require __DIR__ . '/../partials/employee_search.php';
                unset($empSearch);
                ?>

                <label for="assignAmount" class="form-label mt-4">Fondo inicial</label>
                <input type="number" name="initial_amount" id="assignAmount" step="0.01" min="0"
                       value="<?= esc(number_format($fondoBase, 2, '.', '')) ?>" class="form-input">
                <p class="text-xs text-gray-500 mt-1.5">Fondo base configurado en $<?= esc(number_format($fondoBase, 2)) ?>.</p>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" class="btn-close-modal rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                        <i class="fa-solid fa-user-plus"></i> Abrir caja
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- ===================== MODAL: CERRAR CAJA ===================== -->
<div class="modal-overlay" id="closeModal" data-plain>
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg">Cerrar caja #<span id="closeModalId">0</span></h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="post" action="<?= url('cash_register/close') ?>" class="px-6 py-5">
            <input type="hidden" name="id" id="closeModalInput">

            <div class="rounded-xl bg-gray-50 px-4 py-3 mb-4 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-gray-500">Efectivo esperado</span>
                    <span class="font-bold text-gray-900" id="closeExpected">$0.00</span>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-gray-500">Ventas en efectivo</span>
                    <span class="font-semibold text-gray-700" id="closeCash">$0.00</span>
                </div>
                <div class="flex items-center justify-between mt-1">
                    <span class="text-gray-500">Ingresos / retiros</span>
                    <span class="font-semibold text-gray-700" id="closeMovs">$0.00</span>
                </div>
            </div>

            <label for="closePhysical" class="form-label">Efectivo contado en la caja</label>
            <input type="number" name="physical_final_amount" id="closePhysical" step="0.01" min="0" required
                   class="form-input" placeholder="0.00">
            <p class="text-xs text-gray-500 mt-1.5">Contá el efectivo físico y escribí el total.</p>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="btn-close-modal rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-lock"></i> Cerrar caja
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== MODAL: MOVIMIENTO ===================== -->
<?php if ($abierta): ?>
    <div class="modal-overlay" id="movementModal" data-plain>
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg">Ingreso o retiro</h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="post" action="<?= url('cash_register/movement') ?>" class="px-6 py-5">
            <input type="hidden" name="cash_register_id" id="movementInput">

            <label for="movementType" class="form-label">Tipo de movimiento</label>
            <select name="movement_type" id="movementType" required class="form-input">
                <option value="ingreso_extra">Ingreso extra</option>
                <option value="retiro">Retiro</option>
            </select>

            <label for="movementAmount" class="form-label mt-4">Monto</label>
            <input type="number" name="amount" id="movementAmount" step="0.01" min="0.01" required
                   class="form-input" placeholder="0.00">

            <label for="movementReason" class="form-label mt-4">Motivo</label>
            <input type="text" name="reason" id="movementReason" maxlength="255" required
                   class="form-input" placeholder="Ej: cambio para la caja, retiro a bóveda...">

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="btn-close-modal rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-floppy-disk"></i> Registrar
                </button>
            </div>
        </form>
    </div>
    </div>
<?php endif; ?>

<!-- ===================== MODAL: REABRIR ===================== -->
<?php if ($puedeGestionar): ?>
    <div class="modal-overlay" id="reopenModal" data-plain>
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-violet-600 to-violet-500 px-6 py-4 text-white">
            <h3 class="font-bold text-lg">Reabrir caja #<span id="reopenModalId">0</span></h3>
            <button type="button" class="btn-close-modal text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="post" action="<?= url('cash_register/reopen') ?>" class="px-6 py-5">
            <input type="hidden" name="id" id="reopenInput">
            <p class="text-sm text-gray-600 mb-4">
                Al reabrir se borran el conteo físico y la diferencia del cierre anterior,
                y la caja vuelve a quedar abierta a nombre del cajero.
            </p>
            <label for="reopenReason" class="form-label">Motivo de la reapertura</label>
            <input type="text" name="reason" id="reopenReason" maxlength="255" required
                   class="form-input" placeholder="Ej: conteo mal hecho, faltante justificado...">
            <p class="text-xs text-gray-500 mt-1.5">Queda registrado en la bitácora.</p>

            <div class="mt-5 flex items-center justify-end gap-2">
                <button type="button" class="btn-close-modal rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 hover:bg-violet-700 transition-all">
                    <i class="fa-solid fa-rotate-right"></i> Reabrir
                </button>
            </div>
        </form>
    </div>
    </div>
<?php endif; ?>

<!-- ===================== MODAL: DETALLE ===================== -->
<!-- El detalle completo vive en su propia vista: /cash_register/show/{id} -->

<script>
    (function () {
        const money = (value) => '$' + Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        const resumen = <?= json_encode($cur ? [
            'id' => (int) $cur['id'],
            'expected_cash' => (float) $cur['expected_cash'],
            'cash_sales' => (float) $cur['cash_sales'],
            'cash_movements' => (float) $cur['cash_movements'],
        ] : null, JSON_UNESCAPED_UNICODE) ?>;

        const openModal = (el) => {
            if (!el) return;
            el.classList.add('open');
            document.body.style.overflow = 'hidden';
        };
        const closeModal = (el) => {
            if (!el) return;
            el.classList.remove('open');
            document.body.style.overflow = '';
        };

        // Abrir mi caja (pide contraseña)
        const openOwn = document.getElementById('openOwnModal');
        const btnOpenOwn = document.getElementById('btnOpenOwn');
        if (btnOpenOwn && openOwn) {
            btnOpenOwn.addEventListener('click', () => {
                openModal(openOwn);
                const pass = document.getElementById('openOwnPassword');
                if (pass) pass.focus();
            });
        }

        // Abrir y asignar
        const openAssign = document.getElementById('openAssignModal');
        const btnOpenAssign = document.getElementById('btnOpenAssign');
        if (btnOpenAssign && openAssign) {
            btnOpenAssign.addEventListener('click', () => openModal(openAssign));
        }

        // Arqueo / cierre
        const closeBox = document.getElementById('closeModal');
        document.querySelectorAll('[data-caja-close]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!closeBox) return;
                const id = btn.dataset.cajaClose;
                document.getElementById('closeModalId').textContent = id;
                document.getElementById('closeModalInput').value = id;
                document.getElementById('closeExpected').textContent = money(resumen ? resumen.expected_cash : 0);
                document.getElementById('closeCash').textContent = money(resumen ? resumen.cash_sales : 0);
                document.getElementById('closeMovs').textContent = money(resumen ? resumen.cash_movements : 0);
                const input = document.getElementById('closePhysical');
                if (input) input.value = '';
                openModal(closeBox);
                if (input) input.focus();
            });
        });

        // Movimiento de efectivo
        const movement = document.getElementById('movementModal');
        document.querySelectorAll('[data-caja-movimiento]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!movement) return;
                document.getElementById('movementInput').value = btn.dataset.cajaMovement;
                openModal(movement);
            });
        });

        // Reabrir
        const reopen = document.getElementById('reopenModal');
        document.querySelectorAll('[data-caja-reopen]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!reopen) return;
                const id = btn.dataset.cajaReopen;
                document.getElementById('reopenModalId').textContent = id;
                document.getElementById('reopenInput').value = id;
                const reason = document.getElementById('reopenReason');
                if (reason) reason.value = '';
                openModal(reopen);
                if (reason) reason.focus();
            });
        });

        // Cerrar con backdrop, Escape o el botón .btn-close-modal
        document.querySelectorAll('.modal-overlay').forEach((overlay) => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay || e.target.closest('.btn-close-modal')) closeModal(overlay);
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape') return;
            document.querySelectorAll('.modal-overlay.open').forEach(closeModal);
        });
    })();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
