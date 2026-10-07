<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>
<?php require_once __DIR__ . '/../partials/audit_acciones.php'; ?>

<?php
$cajaId = (int) $caja['id'];
$cerrada = $caja['state'] === 'cerrada';
$abierto = trim(($caja['opening_name'] ?? '') . ' ' . ($caja['opening_last_name'] ?? ''));
$cerradoPor = $caja['closing_employee_id']
    ? trim(($caja['closing_name'] ?? '') . ' ' . ($caja['closing_last_name'] ?? ''))
    : null;
$reabrio = $caja['reopened_by']
    ? trim(($caja['reopen_name'] ?? '') . ' ' . ($caja['reopen_last_name'] ?? ''))
    : null;

$diasSemana = [
    'Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
    'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado',
];
$diaSemana = $diasSemana[date('l', strtotime($caja['cash_date']))] ?? '';

$esperado = (float) $caja['expected_cash'];
$fisico = $cerrada ? (float) $caja['physical_final_amount'] : null;
$diff = $cerrada ? (float) $caja['difference'] : null;
$cuadra = $cerrada && abs($diff) < 0.005;

// Al cerrar se congela el efectivo esperado (system_final_amount). Si las
// ventas o pagos de la caja cambiaron después, el recálculo de hoy ya no coincide
// con el que se usó en el arqueo: se muestra el congelado y se aclara la deriva.
$esperadoCierre = $cerrada ? (float) $caja['system_final_amount'] : null;
$deriva = $cerrada && abs($esperado - $esperadoCierre) >= 0.01;
$esperadoMostrado = $cerrada ? $esperadoCierre : $esperado;

$etiquetasPago = [
    'efectivo' => ['label' => 'Efectivo', 'icon' => 'fa-money-bill-wave', 'color' => 'text-emerald-600', 'bg' => 'bg-emerald-50', 'bar' => 'bg-emerald-500'],
    'tarjeta'  => ['label' => 'Tarjeta', 'icon' => 'fa-credit-card', 'color' => 'text-sky-600', 'bg' => 'bg-sky-50', 'bar' => 'bg-sky-500'],
    'transferencia' => ['label' => 'Transferencia', 'icon' => 'fa-building-columns', 'color' => 'text-violet-600', 'bg' => 'bg-violet-50', 'bar' => 'bg-violet-500'],
];

$pagoDe = function ($metodo) use ($etiquetasPago) {
    return $etiquetasPago[$metodo] ?? [
        'label' => ucfirst($metodo),
        'icon' => 'fa-money-bill',
        'color' => 'text-gray-600',
        'bg' => 'bg-gray-100',
        'bar' => 'bg-gray-400',
    ];
};
?>

<!-- ===================== ENCABEZADO ===================== -->
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">Caja #<?= $cajaId ?></h1>
            <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= $cerrada ? 'bg-gray-100 text-gray-600' : 'bg-green-50 text-green-700' ?>">
                <i class="fa-solid fa-circle text-[6px]"></i>
                <?= $cerrada ? 'Cerrada' : 'Abierta' ?>
            </span>
            <?php if ((int) $caja['reopen_count'] > 0): ?>
                <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-600">
                    <i class="fa-solid fa-rotate-right"></i> Reabierta <?= (int) $caja['reopen_count'] ?>×
                </span>
            <?php endif; ?>
        </div>
        <p class="mt-1 text-sm text-gray-500">
            <?= esc($diaSemana) ?>,
            <?= esc(date('d/m/Y', strtotime($caja['cash_date']))) ?>
            &middot; abierta por <strong class="text-gray-700"><?= esc($abierto ?: '—') ?></strong>
            a las <?= esc(substr((string) $caja['opening_time'], 0, 5)) ?>
        </p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= url('cash_register') ?>"
           class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <?php if (!$cerrada): ?>
            <button type="button" data-caja-movimiento="<?= $cajaId ?>"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-right-arrow-left text-orange-500"></i> Ingreso / Retiro
            </button>
            <button type="button" data-caja-close="<?= $cajaId ?>"
                    class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-calculator"></i> Hacer arqueo
            </button>
        <?php elseif ($puedeGestionar): ?>
            <button type="button" data-caja-reopen="<?= $cajaId ?>"
                    class="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-violet-500/25 hover:bg-violet-700 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-rotate-right"></i> Reabrir caja
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$cerrada): ?>
    <div class="mb-6 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4 text-sm text-amber-800 flex items-start gap-3">
        <i class="fa-solid fa-circle-info mt-0.5"></i>
        <p>Esta caja sigue abierta. El efectivo esperado se recalcula con cada venta o movimiento hasta que hagas el arqueo.</p>
    </div>
<?php endif; ?>

<!-- ===================== RESUMEN ===================== -->
<div class="mb-5 grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <div class="rounded-2xl bg-white border border-gray-100 shadow-lg shadow-gray-200/50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Fondo inicial</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">$<?= esc(number_format((float) $caja['initial_amount'], 2)) ?></p>
        <p class="mt-2 text-xs text-gray-400">Base configurada: $<?= esc(number_format((float) setting('cash_register_base', 125), 2)) ?></p>
    </div>

    <div class="rounded-2xl bg-white border border-gray-100 shadow-lg shadow-gray-200/50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ventas</p>
        <p class="mt-1 text-2xl font-bold text-gray-900">$<?= esc(number_format($totalVentas, 2)) ?></p>
        <p class="mt-2 text-xs text-gray-500">
            <?= count($ventas) ?> venta<?= count($ventas) === 1 ? '' : 's' ?>
            &middot; <?= (int) $totalArticulos ?> art&iacute;culo<?= (int) $totalArticulos === 1 ? '' : 's' ?>
            <?php if ($anuladas > 0): ?>
                &middot; <span class="text-red-500"><?= (int) $anuladas ?> anulada<?= (int) $anuladas === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </p>
    </div>

    <div class="rounded-2xl bg-white border border-gray-100 shadow-lg shadow-gray-200/50 p-5">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">
            <?= $cerrada ? 'Efectivo esperado al cierre' : 'Efectivo esperado' ?>
        </p>
        <p class="mt-1 text-2xl font-bold text-gray-900">$<?= esc(number_format($esperadoMostrado, 2)) ?></p>
        <p class="mt-2 text-xs text-gray-500">
            Fondo + efectivo recibido + ingresos &minus; retiros
        </p>
        <dl class="mt-3 space-y-1 text-xs text-gray-500">
            <div class="flex justify-between"><dt>Efectivo recibido</dt><dd class="font-semibold text-gray-700">$<?= esc(number_format((float) $caja['cash_sales'], 2)) ?></dd></div>
            <div class="flex justify-between"><dt>Ingresos extras</dt><dd class="font-semibold text-emerald-600">+$<?= esc(number_format((float) $caja['extra_in'], 2)) ?></dd></div>
            <div class="flex justify-between"><dt>Retiros</dt><dd class="font-semibold text-red-500">&minus;$<?= esc(number_format((float) $caja['withdrawals'], 2)) ?></dd></div>
        </dl>
        <?php if ($deriva): ?>
            <p class="mt-3 rounded-lg bg-amber-50 px-2.5 py-2 text-xs text-amber-700 ring-1 ring-amber-100">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Recalculado hoy: $<?= esc(number_format($esperado, 2)) ?>. El arqueo se hizo con el valor congelado al cierre.
            </p>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl p-5 text-white shadow-lg <?= $cerrada ? ($cuadra ? 'bg-gradient-to-br from-emerald-500 to-emerald-400 shadow-emerald-500/25' : 'bg-gradient-to-br from-orange-500 to-orange-400 shadow-orange-500/25') : 'bg-gradient-to-br from-gray-700 to-gray-600 shadow-gray-500/20' ?>">
        <p class="text-xs font-semibold uppercase tracking-wider text-white/80">
            <?= $cerrada ? 'Arqueo' : 'Pendiente de arqueo' ?>
        </p>
        <?php if ($cerrada): ?>
            <p class="mt-1 text-3xl font-bold">$<?= esc(number_format($fisico, 2)) ?></p>
            <p class="mt-1 text-sm text-white/90">Efectivo contado</p>
            <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white/20 px-3 py-1 text-sm font-semibold">
                <?php if ($cuadra): ?>
                    <i class="fa-solid fa-circle-check"></i> Cuadra
                <?php elseif ($diff > 0): ?>
                    <i class="fa-solid fa-arrow-up"></i> Sobrante $<?= esc(number_format($diff, 2)) ?>
                <?php else: ?>
                    <i class="fa-solid fa-arrow-down"></i> Faltante $<?= esc(number_format(abs($diff), 2)) ?>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="mt-1 text-3xl font-bold">$<?= esc(number_format($esperadoMostrado, 2)) ?></p>
            <p class="mt-1 text-sm text-white/90">Falta hacer el conteo físico</p>
            <button type="button" data-caja-close="<?= $cajaId ?>"
                    class="mt-3 w-full rounded-xl bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-100 transition-colors">
                <i class="fa-solid fa-calculator mr-1.5"></i> Hacer arqueo
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($cerrada): ?>
    <div class="mb-5 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-5">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Resumen del cierre</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm">
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Cerró</span>
                <span class="font-semibold text-gray-800"><?= esc($cerradoPor ?: '—') ?></span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Hora de cierre</span>
                <span class="font-semibold text-gray-800"><?= esc($caja['closing_time'] ? substr((string) $caja['closing_time'], 0, 5) : '—') ?></span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Efectivo esperado por el sistema</span>
                <span class="font-semibold text-gray-800">$<?= esc(number_format((float) $caja['system_final_amount'], 2)) ?></span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Efectivo contado</span>
                <span class="font-semibold text-gray-800">$<?= esc(number_format((float) $caja['physical_final_amount'], 2)) ?></span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Diferencia</span>
                <span class="font-semibold <?= $cuadra ? 'text-emerald-600' : ($diff > 0 ? 'text-amber-600' : 'text-red-600') ?>">
                    <?= $cuadra ? 'Cuadra' : ($diff > 0 ? '+' : '-') . '$' . number_format(abs($diff), 2) ?>
                </span>
            </div>
            <div class="flex items-center justify-between border-b border-gray-50 py-1.5">
                <span class="text-gray-500">Duración del turno</span>
                <span class="font-semibold text-gray-800">
                    <?php
                    $apertura = strtotime($caja['cash_date'] . ' ' . $caja['opening_time']);
                    $fin = $caja['closing_time'] ? strtotime($caja['cash_date'] . ' ' . $caja['closing_time']) : time();
                    $minutos = max(0, (int) round(($fin - $apertura) / 60));
                    printf('%dh %02dm', intdiv($minutos, 60), $minutos % 60);
                    ?>
                </span>
            </div>
        </div>

        <?php if ($reabrio): ?>
            <div class="mt-4 rounded-xl bg-violet-50 ring-1 ring-violet-100 px-4 py-3 text-sm text-violet-800 flex items-start gap-3">
                <i class="fa-solid fa-rotate-right mt-0.5"></i>
                <div>
                    <p class="font-semibold">Caja reabierta <?= (int) $caja['reopen_count'] ?> vez<?= (int) $caja['reopen_count'] === 1 ? '' : 'es' ?> por <?= esc($reabrio) ?>.</p>
                    <p class="mt-0.5 text-violet-700">Motivo: <?= esc($caja['reopen_reason'] ?: '—') ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- ===================== PAGOS ===================== -->
<div class="mb-5 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-5">
        <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Desglose por método de pago</p>
        <?php if (empty($totales)): ?>
            <p class="py-6 text-center text-sm text-gray-400">Esta caja todavía no tiene ventas cobradas.</p>
        <?php else: ?>
            <?php
            $granTotal = 0.0;
            foreach ($totales as $t) {
                $granTotal += (float) $t['amount'];
            }
            ?>
            <div class="space-y-3">
                <?php foreach ($totales as $t):
                    $meta = $pagoDe($t['payment_method']);
                    $pct = $granTotal > 0 ? round(((float) $t['amount'] / $granTotal) * 100, 1) : 0;
                    ?>
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="inline-flex items-center gap-2 font-medium text-gray-700">
                                <i class="fa-solid <?= esc($meta['icon']) ?> <?= esc($meta['color']) ?>"></i>
                                <?= esc($meta['label']) ?>
                                <span class="text-xs text-gray-400">(<?= (int) $t['sales'] ?> venta<?= (int) $t['sales'] === 1 ? '' : 's' ?>)</span>
                            </span>
                            <span class="font-semibold text-gray-900">$<?= esc(number_format((float) $t['amount'], 2)) ?></span>
                        </div>
                        <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full <?= esc($meta['bar']) ?>" style="width: <?= (float) $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3 text-sm">
                <span class="font-semibold text-gray-500">Total cobrado</span>
                <span class="text-lg font-bold text-gray-900">$<?= esc(number_format($granTotal, 2)) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-5">
        <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Datos de la caja</p>
        <dl class="space-y-2.5 text-sm">
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Empleado</dt>
                <dd class="text-right font-semibold text-gray-800"><?= esc($abierto ?: '—') ?></dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Fecha</dt>
                <dd class="text-right font-semibold text-gray-800"><?= esc(date('d/m/Y', strtotime($caja['cash_date']))) ?></dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Apertura</dt>
                <dd class="text-right font-semibold text-gray-800"><?= esc(substr((string) $caja['opening_time'], 0, 5)) ?></dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Cierre</dt>
                <dd class="text-right font-semibold text-gray-800"><?= $cerrada ? esc(substr((string) $caja['closing_time'], 0, 5)) : '—' ?></dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Reaperturas</dt>
                <dd class="text-right font-semibold text-gray-800"><?= (int) $caja['reopen_count'] ?></dd>
            </div>
            <div class="flex items-start justify-between gap-3">
                <dt class="text-gray-500">Estado</dt>
                <dd class="text-right font-semibold <?= $cerrada ? 'text-gray-600' : 'text-green-600' ?>"><?= $cerrada ? 'Cerrada' : 'Abierta' ?></dd>
            </div>
        </dl>
    </div>
</div>

<!-- ===================== MOVIMIENTOS ===================== -->
<div class="mb-5 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Movimientos de efectivo</p>
        <div class="flex items-center gap-4 text-xs">
            <span class="text-emerald-600 font-semibold">+$<?= esc(number_format((float) $caja['extra_in'], 2)) ?> ingresos</span>
            <span class="text-red-500 font-semibold">&minus;$<?= esc(number_format((float) $caja['withdrawals'], 2)) ?> retiros</span>
        </div>
    </div>

    <?php if (empty($movimientos)): ?>
        <p class="px-5 py-8 text-center text-sm text-gray-400">
            <i class="fa-solid fa-arrow-right-arrow-left block text-2xl mb-2 text-gray-300"></i>
            No hubo ingresos ni retiros en esta caja.
        </p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                        <th class="px-5 py-3 font-semibold">Tipo</th>
                        <th class="px-5 py-3 font-semibold">Monto</th>
                        <th class="px-5 py-3 font-semibold">Motivo</th>
                        <th class="px-5 py-3 font-semibold">Registró</th>
                        <th class="px-5 py-3 font-semibold text-right">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movimientos as $m): ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors">
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $m['movement_type'] === 'retiro' ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' ?>">
                                    <i class="fa-solid <?= $m['movement_type'] === 'retiro' ? 'fa-arrow-up' : 'fa-arrow-down' ?>"></i>
                                    <?= $m['movement_type'] === 'retiro' ? 'Retiro' : 'Ingreso extra' ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 font-semibold <?= $m['movement_type'] === 'retiro' ? 'text-red-600' : 'text-emerald-600' ?>">
                                <?= $m['movement_type'] === 'retiro' ? '−' : '+' ?>$<?= esc(number_format((float) $m['amount'], 2)) ?>
                            </td>
                            <td class="px-5 py-3 text-gray-600"><?= esc($m['reason']) ?></td>
                            <td class="px-5 py-3 text-gray-600"><?= esc(trim($m['name'] . ' ' . $m['last_name'])) ?></td>
                            <td class="px-5 py-3 text-right text-gray-500 whitespace-nowrap">
                                <?= esc(date('d/m/Y H:i', strtotime($m['movement_date']))) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== VENTAS ===================== -->
<div class="mb-5 rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Ventas de la caja</p>
        <?php if (!empty($ventas)): ?>
            <input type="search" id="searchVentas"
                   class="w-56 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100"
                   placeholder="Buscar venta, cliente o cajero...">
        <?php endif; ?>
    </div>

    <?php if (empty($ventas)): ?>
        <p class="px-5 py-8 text-center text-sm text-gray-400">
            <i class="fa-solid fa-cart-shopping block text-2xl mb-2 text-gray-300"></i>
            Todavía no se registraron ventas en esta caja.
        </p>
    <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($ventas as $venta):
                $cajero = trim(($venta['employee_name'] ?? '') . ' ' . ($venta['employee_last_name'] ?? ''));
                $cliente = trim(($venta['client_name'] ?? '') . ' ' . ($venta['client_last_name'] ?? ''));
                $anulada = $venta['state'] !== 'completada';
                ?>
                <details class="group px-5 py-3.5 hover:bg-orange-50/40 transition-colors"
                         data-search="<?= esc(strtolower('#' . $venta['id'] . ' ' . $cajero . ' ' . $cliente . ' ' . date('d/m/Y', strtotime($venta['sale_date'])))) ?>">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <i class="fa-solid fa-chevron-right text-xs text-gray-300 transition-transform group-open:rotate-90"></i>
                            <span class="font-semibold text-gray-900">#<?= (int) $venta['id'] ?></span>
                            <?php if ($anulada): ?>
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">Anulada</span>
                            <?php endif; ?>
                            <span class="text-sm text-gray-500 truncate">
                                <?= esc($cliente !== '' ? $cliente : 'Consumidor final') ?>
                            </span>
                            <span class="text-xs text-gray-400">· <?= (int) $venta['items_count'] ?> art&iacute;culo<?= (int) $venta['items_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="hidden text-gray-400 sm:inline"><?= esc($cajero) ?></span>
                            <span class="font-bold <?= $anulada ? 'text-gray-400 line-through' : 'text-gray-900' ?>">
                                $<?= esc(number_format((float) $venta['total'], 2)) ?>
                            </span>
                        </div>
                    </summary>

                    <div class="mt-3 rounded-xl bg-gray-50/70 p-4">
                        <div class="grid grid-cols-2 gap-3 text-xs text-gray-500 md:grid-cols-4">
                            <div>
                                <p class="font-semibold uppercase tracking-wider text-gray-400">Fecha</p>
                                <p class="mt-0.5 text-gray-700"><?= esc(date('d/m/Y H:i', strtotime($venta['sale_date']))) ?></p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-wider text-gray-400">Cajero</p>
                                <p class="mt-0.5 text-gray-700"><?= esc($cajero ?: '—') ?></p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-wider text-gray-400">Subtotal</p>
                                <p class="mt-0.5 text-gray-700">$<?= esc(number_format((float) $venta['subtotal'], 2)) ?></p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-wider text-gray-400">Impuesto</p>
                                <p class="mt-0.5 text-gray-700">$<?= esc(number_format((float) $venta['tax'], 2)) ?></p>
                            </div>
                        </div>

                        <table class="mt-3 w-full text-xs">
                            <thead>
                                <tr class="text-left text-gray-400">
                                    <th class="py-1.5 font-semibold">Producto</th>
                                    <th class="py-1.5 text-center font-semibold">Cant.</th>
                                    <th class="py-1.5 text-right font-semibold">P. unitario</th>
                                    <th class="py-1.5 text-right font-semibold">Descuento</th>
                                    <th class="py-1.5 text-right font-semibold">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($venta['items'] as $item): ?>
                                    <tr class="border-t border-gray-200/60 text-gray-700">
                                        <td class="py-1.5"><?= esc($item['product_name'] ?: 'Producto eliminado') ?></td>
                                        <td class="py-1.5 text-center"><?= (int) $item['quantity'] ?></td>
                                        <td class="py-1.5 text-right">$<?= esc(number_format((float) $item['unit_price'], 2)) ?></td>
                                        <td class="py-1.5 text-right">$<?= esc(number_format((float) $item['discount'], 2)) ?></td>
                                        <td class="py-1.5 text-right font-semibold">$<?= esc(number_format((float) $item['subtotal'], 2)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200/70 pt-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <?php foreach ($venta['payments'] as $pago):
                                    $meta = $pagoDe($pago['payment_method']);
                                    ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full <?= esc($meta['bg']) ?> <?= esc($meta['color']) ?> px-2.5 py-1 text-xs font-semibold">
                                        <i class="fa-solid <?= esc($meta['icon']) ?>"></i>
                                        <?= esc($meta['label']) ?> $<?= esc(number_format((float) $pago['amount'], 2)) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if ((float) $venta['total_discount'] > 0): ?>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 text-amber-600 px-2.5 py-1 text-xs font-semibold">
                                        <i class="fa-solid fa-tag"></i> Descuento $<?= esc(number_format((float) $venta['total_discount'], 2)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <a href="<?= url('pos/ticket/' . (int) $venta['id']) ?>" target="_blank" rel="noopener"
                               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                                <i class="fa-solid fa-receipt"></i> Ver ticket
                            </a>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        <p id="emptyVentas" class="hidden px-5 py-8 text-center text-sm text-gray-400">
            Ninguna venta coincide con la búsqueda.
        </p>
    <?php endif; ?>
</div>

<!-- ===================== BITÁCORA ===================== -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-5">
    <p class="mb-4 text-xs font-semibold uppercase tracking-wider text-gray-400">Bitácora de la caja</p>

    <?php if (empty($bitacora)): ?>
        <p class="py-4 text-center text-sm text-gray-400">Todavía no hay movimientos registrados en la bitácora.</p>
    <?php else: ?>
        <ol class="relative space-y-4 border-l border-gray-200 pl-6">
            <?php foreach ($bitacora as $entrada):
                $meta = auditActionMeta($entrada['action'], $actionMeta);
                $quien = trim(($entrada['user_name'] ?? '') . ' ' . ($entrada['user_last_name'] ?? ''));
                $extra = [];

                if (!empty($entrada['new_data'])) {
                    $datos = json_decode((string) $entrada['new_data'], true);

                    if (is_array($datos)) {
                        // Claves tal como las escribe CashRegisterController.
                        $etiquetas = [
                            'fondo' => 'fondo',
                            'esperado' => 'esperado',
                            'fisico' => 'contado',
                            'diferencia' => 'diferencia',
                            'monto' => 'monto',
                        ];

                        foreach ($etiquetas as $clave => $visible) {
                            if (isset($datos[$clave]) && is_numeric($datos[$clave])) {
                                $extra[] = $visible . ': $' . number_format((float) $datos[$clave], 2);
                            }
                        }

                        foreach (['motivo', 'empleado', 'abierta_por'] as $clave) {
                            if (!empty($datos[$clave])) {
                                $extra[] = $clave === 'motivo' ? 'motivo: ' . $datos[$clave] : $clave . ': ' . $datos[$clave];
                            }
                        }
                    }
                }
                ?>
                <li class="relative">
                    <span class="absolute -left-[31px] flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white <?= esc($meta['color']) ?>">
                        <i class="fa-solid <?= esc($meta['icon']) ?> text-[10px]"></i>
                    </span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm font-semibold text-gray-900"><?= esc($meta['label']) ?></span>
                        <span class="text-xs text-gray-400">
                            <?= esc(date('d/m/Y H:i', strtotime($entrada['created_at']))) ?>
                        </span>
                    </div>
                    <?php if (!empty($entrada['description'])): ?>
                        <p class="mt-0.5 text-sm text-gray-600"><?= esc($entrada['description']) ?></p>
                    <?php endif; ?>
                    <p class="mt-0.5 text-xs text-gray-400">
                        Por <?= esc($quien !== '' ? $quien : 'sistema') ?>
                        <?php if ($extra): ?>
                            &middot; <?= esc(implode(' · ', $extra)) ?>
                        <?php endif; ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>

<!-- ===================== MODALES (arqueo / movimiento / reapertura) ===================== -->
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

<?php if (!$cerrada): ?>
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

<?php if ($cerrada && $puedeGestionar): ?>
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

<script>
    (function () {
        const money = (value) => '$' + Number(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

        const resumen = <?= json_encode([
            'expected_cash' => $esperado,
            'cash_sales' => (float) $caja['cash_sales'],
            'cash_movements' => round((float) $caja['extra_in'] - (float) $caja['withdrawals'], 2),
        ], JSON_UNESCAPED_UNICODE) ?>;

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

        // Arqueo / cierre
        const closeBox = document.getElementById('closeModal');
        document.querySelectorAll('[data-caja-close]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!closeBox) return;
                const id = btn.dataset.cajaClose;
                document.getElementById('closeModalId').textContent = id;
                document.getElementById('closeModalInput').value = id;
                document.getElementById('closeExpected').textContent = money(resumen.expected_cash);
                document.getElementById('closeCash').textContent = money(resumen.cash_sales);
                document.getElementById('closeMovs').textContent = money(resumen.cash_movements);
                const input = document.getElementById('closePhysical');
                if (input) input.value = '';
                openModal(closeBox);
                if (input) input.focus();
            });
        });

        // Movimiento de efectivo
        const movement = document.getElementById('movementModal');
        document.querySelectorAll('[data-caja-movement]').forEach((btn) => {
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

        // Filtro de ventas por texto
        const searchVentas = document.getElementById('searchVentas');
        if (searchVentas) {
            const ventas = Array.from(document.querySelectorAll('[data-search]'));
            const empty = document.getElementById('emptyVentas');

            const filtrar = () => {
                const q = searchVentas.value.trim().toLowerCase();
                let visibles = 0;

                ventas.forEach((row) => {
                    const show = !q || (row.getAttribute('data-search') || '').includes(q);
                    row.classList.toggle('hidden', !show);
                    if (show) visibles++;
                });

                if (empty) empty.classList.toggle('hidden', visibles > 0);
            };

            searchVentas.addEventListener('input', filtrar);
        }

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