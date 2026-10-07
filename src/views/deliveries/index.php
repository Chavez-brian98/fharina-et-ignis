<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Domicilios</h1>
    <div class="flex gap-2">
        <a href="<?= url('deliveries/driver') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-gray-900/20 hover:bg-gray-800 hover:-translate-y-px transition-all">
            <i class="fa-solid fa-motorcycle"></i> Mi pantalla
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por pedido, cliente, dirección o domiciliero...">
    </div>
    <div>
        <select data-filter="estado" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <?php foreach (Delivery::estados() as $k => $v): ?>
                <option value="<?= esc($k) ?>" <?= ($estado ?? '') === $k ? 'selected' : '' ?>><?= esc($v) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<div class="grid gap-4 lg:grid-cols-2">
    <?php foreach ($deliveries as $d):
        $nombre = Delivery::nombreCliente($d);
        $nombreDriver = trim(($d['driver_name'] ?? '') . ' ' . ($d['driver_last_name'] ?? ''));
        $pagado = (float) ($d['paid_amount'] ?? 0) >= (float) ($d['total'] ?? 0);
        $clase = Delivery::estadoColor($d['state']);
    ?>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50 transition-shadow hover:shadow-xl"
             data-estado="<?= esc($d['state']) ?>"
             data-search="<?= esc(strtolower((string) $d['order_id'] . ' ' . $nombre . ' ' . $d['destination_address'] . ' ' . $nombreDriver)) ?>">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-bold text-gray-900">
                        Pedido #<?= (int) $d['order_id'] ?>
                        <span class="ml-1 text-xs font-medium text-gray-400"><?= esc(date('d/m H:i', strtotime($d['created_at']))) ?></span>
                    </p>
                    <p class="mt-0.5 text-sm text-gray-600 font-medium"><?= esc($nombre) ?></p>
                    <p class="mt-0.5 text-sm text-gray-500 truncate max-w-md">
                        <i class="fa-solid fa-location-dot mr-1 text-orange-400"></i><?= esc($d['destination_address']) ?>
                    </p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full <?= $clase ?>">
                    <i class="fa-solid <?= Delivery::estadoIcon($d['state']) ?>"></i>
                    <?= esc(Delivery::estadoTexto($d['state'])) ?>
                </span>
            </div>

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4">
                <div class="flex items-center gap-4 text-sm">
                    <span class="font-bold text-gray-900">$<?= esc(number_format((float) $d['total'], 2)) ?></span>
                    <?php if (!$pagado): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-amber-600"><i class="fa-solid fa-wallet"></i> Pago pendiente</span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600"><i class="fa-solid fa-circle-check"></i> Pagado</span>
                    <?php endif; ?>
                    <?php if ($nombreDriver): ?>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500">
                            <i class="fa-solid fa-user text-gray-400"></i> <?= esc($nombreDriver) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= url('deliveries/show/' . $d['id']) ?>"
                       class="inline-flex items-center gap-2 rounded-lg bg-orange-50 px-3.5 py-2 text-xs font-semibold text-orange-600 ring-1 ring-orange-200 hover:bg-orange-500 hover:text-white transition-colors">
                        <i class="fa-solid fa-eye"></i> Ver seguimiento
                    </a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($deliveries)): ?>
        <div class="lg:col-span-2 rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center">
            <div class="mx-auto w-16 h-16 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-2xl">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <h2 class="mt-5 font-semibold text-gray-900">Sin domicilios <?= ($estado !== 'todos' && $estado) ? 'en este estado' : 'por ahora' ?></h2>
            <p class="mt-2 text-sm text-gray-500">Los pedidos en línea aparecerán aquí en cuanto un cliente finalice su compra.</p>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>