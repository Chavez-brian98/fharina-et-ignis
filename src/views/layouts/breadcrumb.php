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

<!-- MENSAJES FLASH -->
<?php $success = flash('success'); ?>
<?php if ($success): ?>
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3">
        <i class="fa-solid fa-circle-check text-green-600"></i>
        <span class="text-sm text-green-800"><?= esc($success) ?></span>
    </div>
<?php endif; ?>

<?php $error = flash('error'); ?>
<?php if ($error): ?>
    <div class="mb-5 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
        <i class="fa-solid fa-circle-exclamation text-red-600"></i>
        <span class="text-sm text-red-800"><?= esc($error) ?></span>
    </div>
<?php endif; ?>