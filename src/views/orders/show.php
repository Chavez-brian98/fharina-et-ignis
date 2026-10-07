<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$currency = setting('currency', '$');
$nombreCliente = Order::nombreCliente($order);
$inicial = mb_strtoupper(mb_substr(trim($nombreCliente), 0, 1));
$editable = in_array($order['state'], ['pendiente', 'aprobado'], true);
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Pedido #<?= (int) $order['id'] ?></h1>
        <div class="flex items-center gap-2 mt-1.5">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= Order::estadoColor($order['state']) ?>">
                <?= esc(Order::estadoTexto($order['state'])) ?>
            </span>
            <span class="text-sm text-gray-400">Registrado el <?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></span>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if (!empty($delivery)): ?>
            <a href="<?= url('deliveries/show/' . $delivery['id']) ?>" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">
                <i class="fa-solid fa-motorcycle"></i> Domicilio
            </a>
        <?php endif; ?>
        <?php if ($editable && puede('orders', 'edit')): ?>
            <a href="<?= url('orders/edit/' . $order['id']) ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                <i class="fa-regular fa-pen-to-square"></i> Editar
            </a>
        <?php endif; ?>
        <a href="<?= url('orders') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
    </div>
</div>

<div class="max-w-6xl space-y-5">

    <!-- Resumen -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-user text-orange-500 mr-2"></i>Cliente</h2>
            </div>
            <div class="px-6 py-5 flex items-start gap-4">
                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-orange-100 to-orange-50 text-orange-600 flex items-center justify-center font-bold text-lg shrink-0 ring-1 ring-orange-100">
                    <?= esc($inicial) ?>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold text-gray-900"><?= esc($nombreCliente) ?></p>
                    <p class="text-sm text-gray-500"><?= esc($order['phone'] ?: 'Sin teléfono') ?></p>
                    <?php if (!empty($order['email'])): ?>
                        <p class="text-sm text-gray-500"><?= esc($order['email']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($order['client_address'])): ?>
                        <p class="text-xs text-gray-400 mt-1"><i class="fa-solid fa-location-dot mr-1"></i><?= esc($order['client_address']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
            <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-truck text-orange-500 mr-2"></i>Entrega</h2>
            </div>
            <div class="px-6 py-5 space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Fecha</p>
                    <p class="font-bold text-gray-900 text-lg"><?= date('d/m/Y', strtotime($order['delivery_date'])) ?></p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold">Dirección</p>
                    <?php if (!empty($order['delivery_address'])): ?>
                        <p class="text-gray-700"><i class="fa-solid fa-truck mr-1 text-gray-300 text-sm"></i><?= esc($order['delivery_address']) ?></p>
                    <?php else: ?>
                        <p class="text-gray-400"><i class="fa-solid fa-store mr-1 text-sm"></i>Recoge en tienda</p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($order['rejection_reason'])): ?>
                    <div class="rounded-xl bg-red-50 border border-red-100 px-3 py-2.5">
                        <p class="text-xs font-bold text-red-600 uppercase tracking-wider">Motivo del rechazo / cancelación</p>
                        <p class="text-sm text-red-700"><?= esc($order['rejection_reason']) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Artículos + Seguimiento -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <!-- Artículos -->
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-orange-50/80 to-white px-6 py-5">
                    <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-box text-orange-500 mr-2"></i>Artículos</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                                <th class="px-6 py-3 font-semibold">Producto</th>
                                <th class="px-5 py-3 font-semibold w-32 text-center">Cantidad</th>
                                <th class="px-5 py-3 font-semibold w-36 text-right">Precio</th>
                                <th class="px-5 py-3 font-semibold w-32 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $detalle): ?>
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-6 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <?php if (!empty($detalle['image_url'])): ?>
                                                <img src="<?= esc($detalle['image_url']) ?>" alt="" class="w-11 h-11 rounded-lg object-cover shrink-0 ring-1 ring-gray-100">
                                            <?php else: ?>
                                                <div class="w-11 h-11 rounded-lg bg-orange-50 text-orange-400 flex items-center justify-center shrink-0">
                                                    <i class="fa-solid fa-cake-candles"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900"><?= esc($detalle['product_name']) ?></p>
                                                <?php if (!empty($detalle['personalized_description'])): ?>
                                                    <p class="text-xs text-gray-400 italic"><?= esc($detalle['personalized_description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3.5 text-center font-semibold text-gray-700"><?= (int) $detalle['quantity'] ?></td>
                                    <td class="px-5 py-3.5 text-right text-gray-500"><?= esc($currency) ?><?= number_format((float) $detalle['unit_price'], 2) ?></td>
                                    <td class="px-5 py-3.5 text-right font-bold text-gray-900"><?= esc($currency) ?><?= number_format((float) $detalle['subtotal'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-gray-100 bg-gray-50/50">
                                <td colspan="2" class="px-6 py-4"></td>
                                <td class="px-5 py-4 text-right text-sm font-semibold text-gray-500">Total de la reserva</td>
                                <td class="px-5 py-4 text-right text-xl font-bold text-orange-500"><?= esc($currency) ?><?= number_format((float) $order['total'], 2) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Notas -->
            <?php if (!empty($order['notes'])): ?>
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 px-6 py-5">
                    <p class="text-xs uppercase tracking-wider text-gray-400 font-semibold mb-2">Notas de la reserva</p>
                    <p class="text-sm text-gray-700"><?= nl2br(esc($order['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Seguimiento -->
        <div class="space-y-5">
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
                <div class="border-b border-gray-100 bg-gradient-to-r from-blue-50/80 to-white px-6 py-5">
                    <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-route text-blue-500 mr-2"></i>Seguimiento</h2>
                </div>

                <?php if ($transiciones && puede('orders', 'edit')): ?>
                    <form action="<?= url('orders/estado/' . $order['id']) ?>" method="POST" class="px-6 py-5 border-b border-gray-100">
                        <label for="estado" class="form-label">Avanzar el pedido</label>
                        <select id="estado" name="estado" class="form-input">
                            <?php foreach ($transiciones as $t):
                                $ayuda = null;
                                if ($t === 'aprobado') $ayuda = 'Aprueba la reserva para enviarla a producción';
                                elseif ($t === 'en_produccion') $ayuda = 'El taller ya está elaborando';
                                elseif ($t === 'listo') $ayuda = 'Ya se puede entregar o recoger';
                                elseif ($t === 'entregado') $ayuda = 'Se entregó al cliente';
                                elseif ($t === 'rechazado') $ayuda = 'Requiere motivo';
                                elseif ($t === 'cancelado') $ayuda = 'El cliente o el negocio lo cancela';
                            ?>
                                <option value="<?= esc($t) ?>"><?= esc(Order::estadoTexto($t)) . ($ayuda ? ' — ' . $ayuda : '') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="comment" class="form-label mt-4">Comentario</label>
                        <input type="text" id="comment" name="comment" class="form-input"
                               placeholder="Opcional, salvo al rechazar" maxlength="255">
                        <button type="submit" class="mt-4 w-full inline-flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                            <i class="fa-solid fa-arrow-right"></i> Cambiar estado
                        </button>
                    </form>
                <?php endif; ?>

                <div class="px-6 py-5">
                    <?php if ($historial): ?>
                        <ol class="relative border-l border-gray-200 ml-2 space-y-5">
                            <?php foreach ($historial as $h):
                                $nuevo = $h['new_state'];
                                $msjClase = Order::estadoColor($nuevo);
                            ?>
                                <li class="ml-6">
                                    <span class="absolute -left-[9px] mt-1.5 w-4 h-4 rounded-full border-2 border-white <?= $msjClase ?>"></span>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold <?= $msjClase ?>"><?= esc(Order::estadoTexto($nuevo)) ?></span>
                                        <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($h['change_date'])) ?></span>
                                    </div>
                                    <?php if ($h['previous_state']): ?>
                                        <p class="text-xs text-gray-400 mt-0.5">desde <?= esc(Order::estadoTexto($h['previous_state'])) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($h['comment'])): ?>
                                        <p class="text-sm text-gray-600 mt-1"><?= esc($h['comment']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-400 mt-0.5">Por <?= esc(trim($h['emp_name'] . ' ' . $h['emp_last_name'])) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p class="text-sm text-gray-400 text-center py-4">Sin seguimiento registrado todavía.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>