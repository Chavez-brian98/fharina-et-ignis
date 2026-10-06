<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$hoy = new DateTime('today');
$currency = setting('currency', '$');
$puedeCrear = puede('orders', 'create');
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Pedidos</h1>
        <p class="text-sm text-gray-400 mt-1">Reservas de clientes con fecha de entrega. El seguimiento se hace desde cada pedido.</p>
    </div>
    <?php if ($puedeCrear): ?>
        <a href="<?= url('orders/create') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
            <i class="fa-solid fa-plus"></i> Nuevo Pedido
        </a>
    <?php endif; ?>
</div>

<!-- Filtros de fecha (se re-carga desde el servidor) -->
<form method="GET" action="<?= url('orders') ?>" class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div>
        <label for="desde" class="form-label">Entregas desde</label>
        <input type="date" id="desde" name="desde" value="<?= esc($desde) ?>"
               class="form-input w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
    </div>
    <div>
        <label for="hasta" class="form-label">Hasta</label>
        <input type="date" id="hasta" name="hasta" value="<?= esc($hasta) ?>"
               class="form-input w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
        <p class="text-xs text-gray-400 mt-1">Vacío = sin tope.</p>
    </div>
    <div class="flex items-end gap-2">
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">
            <i class="fa-solid fa-filter"></i> Filtrar
        </button>
        <a href="<?= url('orders') ?>" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Hoy en adelante</a>
    </div>
</form>

<!-- Búsqueda y filtro por estado (en vivo, como el resto de módulos) -->
<div class="mb-5 grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="md:col-span-2 relative">
        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
        <input type="text" id="searchInput"
               class="w-full rounded-xl border border-gray-200 bg-white pl-10 pr-4 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
               placeholder="Buscar por número, cliente o dirección...">
    </div>
    <div>
        <select data-filter="state" class="search-filter w-full rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm shadow-gray-200/60 outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
            <option value="">Todos los estados</option>
            <?php foreach (Order::estados() as $clave => $label): ?>
                <option value="<?= esc($clave) ?>"><?= esc($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- Tabla -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">N°</th>
                    <th class="px-5 py-3 font-semibold">Cliente</th>
                    <th class="px-5 py-3 font-semibold">Entrega</th>
                    <th class="px-5 py-3 font-semibold">Dirección</th>
                    <th class="px-5 py-3 font-semibold">Total</th>
                    <th class="px-5 py-3 font-semibold">Estado</th>
                    <th class="px-5 py-3 font-semibold text-right">Acciones</th>
                </tr>
            </thead>
            <tbody id="crudTableBody">
                <?php foreach ($orders as $item):
                    $nombre = Order::nombreCliente($item);
                    $inicial = mb_strtoupper(mb_substr(trim($nombre), 0, 1));
                    $diff = (int) $hoy->diff(new DateTime($item['delivery_date']))->format('%r%a');
                    if ($diff < 0) {
                        $entregaLabel = 'Vencido';
                        $entregaClase = 'text-red-500 bg-red-50';
                    } elseif ($diff === 0) {
                        $entregaLabel = 'Hoy';
                        $entregaClase = 'text-orange-600 bg-orange-50';
                    } elseif ($diff === 1) {
                        $entregaLabel = 'Mañana';
                        $entregaClase = 'text-blue-600 bg-blue-50';
                    } else {
                        $entregaLabel = 'En ' . $diff . ' días';
                        $entregaClase = 'text-gray-600 bg-gray-100';
                    }
                ?>
                <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors"
                    data-status="<?= esc($item['state']) ?>"
                    data-search="<?= esc(strtolower(trim($item['id'] . ' ' . $nombre . ' ' . ($item['phone'] ?? '') . ' ' . ($item['delivery_address'] ?? '') . ' ' . Order::estadoTexto($item['state'])))) ?>">
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center justify-center min-w-[34px] h-8 rounded-lg bg-gray-900 text-white text-xs font-bold px-2">#<?= (int) $item['id'] ?></span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-orange-100 to-orange-50 text-orange-600 flex items-center justify-center font-bold text-sm shrink-0 ring-1 ring-orange-100">
                                <?= esc($inicial) ?>
                            </div>
                            <div class="min-w-0">
                                <span class="font-semibold text-gray-900 block truncate"><?= esc($nombre) ?></span>
                                <span class="text-xs text-gray-400"><?= esc($item['phone'] ?: 'Sin teléfono') ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="font-semibold text-gray-700 block"><?= date('d/m/Y', strtotime($item['delivery_date'])) ?></span>
                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold <?= $entregaClase ?>"><?= $entregaLabel ?></span>
                    </td>
                    <td class="px-5 py-3.5 text-gray-500 max-w-[220px]">
                        <?php if (!empty($item['delivery_address'])): ?>
                            <span class="truncate block" title="<?= esc($item['delivery_address']) ?>">
                                <i class="fa-solid fa-truck mr-1 text-gray-300 text-xs"></i><?= esc($item['delivery_address']) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-gray-300"><i class="fa-solid fa-store mr-1 text-xs"></i>Recoge en tienda</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 font-bold text-gray-900 whitespace-nowrap"><?= esc($currency) ?><?= number_format((float) $item['total'], 2) ?></td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= Order::estadoColor($item['state']) ?>">
                            <?= esc(Order::estadoTexto($item['state'])) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1.5">
                            <a class="btn-action btn-detail" title="Ver seguimiento" href="<?= url('orders/show/' . $item['id']) ?>">
                                <i class="fa-solid fa-route"></i>
                            </a>
                            <?php if (puede('orders', 'edit') && in_array($item['state'], ['pendiente', 'aprobado'], true)): ?>
                                <a class="btn-action" title="Editar" href="<?= url('orders/edit/' . $item['id']) ?>">
                                    <i class="fa-regular fa-pen-to-square"></i>
                                </a>
                            <?php endif; ?>
                            <?php if ($item['state'] === 'pendiente'): ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar pedido"
                                        data-url="<?= url('orders/delete/' . $item['id']) ?>"
                                        data-name="Pedido #<?= (int) $item['id'] ?>">
                                    <i class="fa-regular fa-trash-can"></i>
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
        <i class="fa-solid fa-cake-candles block text-3xl mb-2 text-gray-300"></i>
        No hay pedidos que coincidan con la búsqueda.
    </p>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>