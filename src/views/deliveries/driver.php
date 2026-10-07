<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$misEntregas = $misEntregas ?? [];
$disponibles = $disponibles ?? [];
$muy = (int) ($_SESSION['user']['id'] ?? 0);
$mapsKey = GoogleMaps::key();
?>

<div class="mb-6 flex items-center justify-between">
    <h1 class="text-2xl font-bold text-gray-900">Mis domicilios</h1>
    <div class="flex gap-2">
        <a href="<?= url('deliveries') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 transition-colors">
            <i class="fa-solid fa-list"></i> Cola de envíos
        </a>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <!-- Mis entregas en curso -->
    <div>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-400">
            <i class="fa-solid fa-person-running mr-1"></i> En curso
        </h2>
        <div class="space-y-4">
            <?php foreach ($misEntregas as $d):
                $clase = Delivery::estadoColor($d['state']);
                $proximo = Delivery::proximoEstado($d['state']);
                $nombre = Delivery::nombreCliente($d);
            ?>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-gray-900">Pedido #<?= (int) $d['order_id'] ?></p>
                            <p class="mt-0.5 text-sm text-gray-600 font-medium"><?= esc($nombre) ?></p>
                            <p class="mt-0.5 text-sm text-gray-500">
                                <i class="fa-solid fa-location-dot mr-1 text-orange-400"></i><?= esc($d['destination_address']) ?>
                            </p>
                            <?php if ($d['phone']): ?>
                                <a href="tel:<?= esc($d['phone']) ?>" class="mt-1 inline-flex items-center text-sm text-gray-500 hover:text-orange-600">
                                    <i class="fa-brands fa-whatsapp mr-1"></i><?= esc($d['phone']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full <?= $clase ?>">
                            <i class="fa-solid <?= Delivery::estadoIcon($d['state']) ?>"></i>
                            <?= esc(Delivery::estadoTexto($d['state'])) ?>
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 text-sm">
                        <?php if ($mapsKey): ?>
                            <a href="<?= url('deliveries/show/' . $d['id']) ?>"
                               class="inline-flex items-center gap-2 rounded-lg bg-gray-50 px-3.5 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 transition-colors">
                                <i class="fa-solid fa-map"></i> Ver seguimiento
                            </a>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Seguimiento solo con mapa configurado</span>
                        <?php endif; ?>

                        <form method="POST" action="<?= url('deliveries/avanzar/' . $d['id']) ?>" class="inline">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-3.5 py-2 text-xs font-semibold text-white hover:bg-orange-600 transition-colors">
                                <i class="fa-solid fa-forward"></i>
                                <?= $proximo ? 'Avanzar a «' . esc(Delivery::estadoTexto($proximo)) . '»' : 'Finalizar' ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($misEntregas)): ?>
                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <i class="fa-solid fa-mug-hot text-3xl text-gray-300"></i>
                    <p class="mt-3 text-sm font-medium text-gray-500">No tienes envíos en curso.</p>
                    <p class="text-xs text-gray-400">Toma uno de la cola de la derecha cuando estés listo.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Disponibles para tomar -->
    <div>
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-400">
            <i class="fa-solid fa-inbox mr-1"></i> Disponibles para tomar (<?= count($disponibles) ?>)
        </h2>
        <div class="space-y-4">
            <?php foreach ($disponibles as $d):
                $nombre = Delivery::nombreCliente($d);
            ?>
                <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-gray-900">Pedido #<?= (int) $d['order_id'] ?></p>
                            <p class="mt-0.5 text-sm text-gray-600 font-medium"><?= esc($nombre) ?></p>
                            <p class="mt-0.5 text-sm text-gray-500">
                                <i class="fa-solid fa-location-dot mr-1 text-orange-400"></i><?= esc($d['destination_address']) ?>
                            </p>
                            <?php if ($d['phone']): ?>
                                <a href="tel:<?= esc($d['phone']) ?>" class="mt-1 inline-flex items-center text-sm text-gray-500 hover:text-orange-600">
                                    <i class="fa-brands fa-whatsapp mr-1"></i><?= esc($d['phone']) ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="font-bold text-gray-900">$<?= esc(number_format((float) $d['total'], 2)) ?></span>
                    </div>
                    <div class="mt-4 flex items-center justify-end gap-3 border-t border-amber-100 pt-4">
                        <form method="POST" action="<?= url('deliveries/asignar/' . $d['id']) ?>">
                            <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-700 transition-colors">
                                <i class="fa-solid fa-hand"></i> Tomar envío
                            </button>
                        </form>
                        <a href="<?= url('deliveries/show/' . $d['id']) ?>"
                           class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100 transition-colors">
                            Ver
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($disponibles)): ?>
                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                    <i class="fa-solid fa-bell-slash text-3xl text-gray-300"></i>
                    <p class="mt-3 text-sm font-medium text-gray-500">No hay envíos disponibles ahora mismo.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>