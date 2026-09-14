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
<?php $flashSuccess = flash('success'); ?>
<?php $flashError = flash('error'); ?>
<?php $flashMessage = $flashSuccess ?? $flashError; ?>
<?php if ($flashMessage): ?>
    <div id="flashToast" class="hidden"
         data-type="<?= $flashSuccess ? 'success' : 'error' ?>"
         data-message="<?= esc($flashMessage) ?>"></div>
<?php endif; ?>