<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php $cur = $currency ?? setting('currency', '$'); ?>

<!-- Breadcrumb -->
<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-500">
        <a href="<?= url('/') ?>" class="hover:text-orange-600 transition-colors">Inicio</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700 font-medium">Mi cuenta</span>
    </div>
</section>

<section class="py-14 lg:py-20 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center font-display text-2xl font-bold">
                    <?= esc(mb_substr($cliente['name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="font-display text-3xl font-bold text-gray-900"><?= esc($cliente['name']) ?></h1>
                    <p class="mt-1 text-sm text-gray-500"><?= esc($cliente['email']) ?></p>
                </div>
            </div>
            <a href="<?= url('salir') ?>"
               class="inline-flex items-center gap-2 text-sm font-medium text-gray-400 hover:text-gray-900 transition-colors">
                <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
            </a>
        </div>

        <div class="mt-10">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="font-display text-2xl font-bold text-gray-900">Mis pedidos</h2>
                    <p class="mt-1 text-sm text-gray-500">Sigue tus entregas y revisa tus compras anteriores.</p>
                </div>
                <a href="<?= url('catalogo') ?>"
                   class="inline-flex items-center gap-2 bg-gray-900 text-white text-sm font-semibold px-5 py-3 rounded-full hover:bg-orange-500 transition-colors">
                    <i class="fa-solid fa-plus"></i> Nuevo pedido
                </a>
            </div>

            <?php if (empty($historial)): ?>
                <div class="mt-8 rounded-2xl bg-white ring-1 ring-gray-200 p-12 text-center">
                    <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-3xl">
                        <i class="fa-solid fa-truck"></i>
                    </div>
                    <h3 class="mt-6 font-display text-xl font-bold text-gray-900">Aún no tienes pedidos</h3>
                    <p class="mt-2 text-sm text-gray-500 max-w-md mx-auto">
                        Cuando hagas tu primer pedido a domicilio podrás seguirlo aquí.
                    </p>
                </div>
            <?php else: ?>
                <div class="mt-8 space-y-4">
                    <?php foreach ($historial as $h):
                        $estado = Delivery::estadoTexto($h['delivery_state'] ?? '');
                        $color = Delivery::estadoColor($h['delivery_state'] ?? '');
                    ?>
                        <a href="<?= url('rastrear/' . $h['tracking_token']) ?>"
                           class="block rounded-2xl bg-white ring-1 ring-gray-200 hover:ring-orange-300 transition-shadow p-6">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">
                                        Pedido #<?= (int) $h['order_id'] ?>
                                        <span class="ml-2 text-xs font-medium text-gray-400"><?= esc(date('d/m/Y H:i', strtotime($h['created_at']))) ?></span>
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 truncate max-w-md"><?= esc($h['destination_address']) ?></p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="text-right">
                                        <p class="text-sm text-gray-400">Total</p>
                                        <p class="font-display text-lg font-bold text-gray-900"><?= esc($cur) ?><?= esc(number_format((float) $h['total'], 2)) ?></p>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full <?= $color ?>">
                                        <i class="fa-solid <?= Delivery::estadoIcon($h['delivery_state'] ?? '') ?>"></i>
                                        <?= esc($estado) ?>
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>