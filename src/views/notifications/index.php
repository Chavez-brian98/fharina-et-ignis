<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$iconos = [
    'pedido_nuevo'     => ['fa-bag-shopping', 'text-blue-500'],
    'pedido_estado'    => ['fa-right-left', 'text-indigo-500'],
    'pedido_rechazado' => ['fa-circle-xmark', 'text-red-500'],
    'pedido_cancelado' => ['fa-xmark', 'text-gray-400'],
    'pedido_listo'     => ['fa-circle-check', 'text-green-500'],
    'stock_bajo'       => ['fa-box-open', 'text-amber-500'],
];
?>

<div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Notificaciones</h1>
    <?php if ($noLeidas > 0): ?>
        <form action="<?= url('notifications/leer') ?>" method="POST">
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors">
                <i class="fa-solid fa-check-double"></i> Marcar todas como leídas
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if ($notificaciones): ?>
    <div class="max-w-4xl space-y-3">
        <?php foreach ($notificaciones as $notif):
            $meta = $iconos[$notif['notification_type']] ?? ['fa-bell', 'text-gray-400'];
            $fecha = date('d/m/Y \a \l\a\s H:i', strtotime($notif['creation_date']));
            $enlace = Notificacion::enlace($notif['reference_type'], (int) $notif['reference_id']);
        ?>
            <div class="rounded-2xl border <?= $notif['readed'] ? 'border-gray-200 bg-white' : 'border-blue-100 bg-blue-50/40' ?> shadow-lg shadow-gray-200/40 px-5 py-4 flex items-start gap-4 transition-colors">
                <div class="w-11 h-11 rounded-xl bg-white shadow-sm border border-gray-200 flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= esc($meta[0]) ?> <?= esc($meta[1]) ?> text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-gray-900 text-sm"><?= esc($notif['message'] !== null && $notif['message'] !== '' ? $notif['message'] : $notif['title']) ?></p>
                        <?php if (!$notif['readed']): ?>
                            <span class="shrink-0 w-2 h-2 rounded-full bg-blue-500"></span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5"><?= esc($fecha) ?></p>
                </div>
                <?php if ($enlace): ?>
                    <a href="<?= url($enlace) ?>" class="shrink-0 btn-action text-gray-400 hover:text-blue-600" title="Ver detalle">
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="max-w-4xl rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center">
        <i class="fa-regular fa-bell-slash block text-5xl text-gray-300 mb-3"></i>
        <p class="text-gray-400">No hay notificaciones.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>