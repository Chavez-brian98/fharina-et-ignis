<?php
// Banner de sección: foto de fondo con el título dentro.
// Espera: $bannerTitle (obligatorio), $bannerImage, $bannerEyebrow, $bannerText, $bannerCentered.
if (empty($bannerTitle)) {
    return;
}
$bannerImage = $bannerImage ?? '';
$bannerText = $bannerText ?? '';
$bannerEyebrow = $bannerEyebrow ?? '';
$bannerCentered = $bannerCentered ?? false;
?>
<section class="relative py-20 lg:py-28 bg-gray-900 text-white overflow-hidden">
    <?php if ($bannerImage): ?>
        <img src="<?= esc($bannerImage) ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-45">
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-r from-gray-950/90 via-gray-950/70 to-gray-950/30"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 <?= $bannerCentered ? 'text-center' : '' ?>">
        <?php if ($bannerEyebrow): ?>
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-400"><?= esc($bannerEyebrow) ?></p>
        <?php endif; ?>
        <h2 class="mt-4 font-display text-4xl sm:text-5xl font-bold"><?= esc($bannerTitle) ?></h2>
        <?php if ($bannerText): ?>
            <p class="mt-4 text-lg text-gray-300 max-w-2xl <?= $bannerCentered ? 'mx-auto' : '' ?>"><?= esc($bannerText) ?></p>
        <?php endif; ?>
    </div>
</section>