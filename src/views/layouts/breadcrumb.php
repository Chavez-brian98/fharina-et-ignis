<?php if (empty($breadcrumbs) || !is_array($breadcrumbs)) return; ?>

<!-- BREADCRUMB -->
<nav class="flex items-center gap-2 text-sm text-gray-500 mb-6" aria-label="breadcrumb">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <?php $isLast = $i === count($breadcrumbs) - 1; ?>
        <?php if ($i > 0): ?>
            <i class="fa-solid fa-chevron-right text-[10px] text-gray-300"></i>
        <?php endif; ?>
        <?php if (!$isLast && !empty($crumb['url'])): ?>
            <a href="<?= esc($crumb['url']) ?>" class="hover:text-orange-500 transition-colors"><?= esc($crumb['label']) ?></a>
        <?php else: ?>
            <span class="text-gray-800 font-semibold"><?= esc($crumb['label']) ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>

<!-- MENSAJES FLASH (toast con Toastify) -->
<?php
// Se emiten ambos marcadores: antes un success silenciaba al error y ambos
// se borraban de la sesion, ASI QUE el error se perdia para siempre.
$flashToasts = array_filter([
    ['type' => 'success', 'message' => flash('success')],
    ['type' => 'error', 'message' => flash('error')],
], function ($flash) {
    return !empty($flash['message']);
});
?>
<?php foreach ($flashToasts as $flash): ?>
    <div class="flashToast hidden"
         data-type="<?= $flash['type'] ?>"
         data-message="<?= esc($flash['message']) ?>"></div>
<?php endforeach; ?>