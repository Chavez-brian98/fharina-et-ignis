<?php
$id = (int) ($p['id'] ?? 0);
$name = $p['name'] ?? '';
$desc = $p['description'] ?? '';
$category = $p['category_name'] ?? 'Producto';
$img = $p['image_url'] ?? '';
$price = isset($p['sale_price']) ? number_format((float) $p['sale_price'], 2) : '0.00';
$discount = isset($p['discount_percent']) ? (float) $p['discount_percent'] : 0;
$final = isset($p['final_price']) ? number_format((float) $p['final_price'], 2) : $price;
$cur = $currency ?? setting('currency', '$');
?>
<div class="group relative flex flex-col rounded-2xl bg-white ring-1 ring-gray-200 overflow-hidden transition-all duration-300 hover:ring-orange-300 hover:shadow-xl hover:shadow-gray-200/50">
    <a href="<?= url('producto/' . $id) ?>" class="block">
        <div class="aspect-[4/3] relative bg-gray-100 overflow-hidden">
            <?php if ($img): ?>
                <img src="<?= esc($img) ?>" alt="<?= esc($name) ?>" loading="lazy"
                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
            <?php else: ?>
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fa-solid fa-bread-slice text-5xl text-gray-300"></i>
                </div>
            <?php endif; ?>

            <?php if ($discount > 0): ?>
                <span class="absolute top-3 left-3 inline-flex items-center gap-1 bg-orange-500 text-white text-xs font-bold px-2.5 py-1 rounded-full shadow">
                    <i class="fa-solid fa-tag"></i> -<?= round($discount) ?>%
                </span>
            <?php endif; ?>
        </div>

        <div class="p-5 pb-3">
            <p class="text-xs font-semibold uppercase tracking-wider text-orange-500"><?= esc($category) ?></p>
            <h3 class="mt-1.5 font-display text-base font-semibold text-gray-900 group-hover:text-orange-600 transition-colors"><?= esc($name) ?></h3>
            <?php if ($desc): ?>
                <p class="mt-1.5 text-sm text-gray-500 leading-relaxed line-clamp-2"><?= esc($desc) ?></p>
            <?php endif; ?>
            <div class="mt-3 flex items-baseline gap-2">
                <span class="text-xl font-bold text-gray-900"><?= esc($cur) ?><?= esc($discount > 0 ? $final : $price) ?></span>
                <?php if ($discount > 0): ?>
                    <span class="text-sm text-gray-400 line-through"><?= esc($cur) ?><?= esc($price) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>

    <div class="px-5 pb-5 mt-auto">
        <button type="button" title="Próximamente"
                class="w-full inline-flex items-center justify-center gap-2 bg-gray-900 text-white text-sm font-semibold px-4 py-2.5 rounded-full hover:bg-orange-500 transition-colors">
            <i class="fa-solid fa-cart-plus"></i> Agregar al carrito
        </button>
    </div>
</div>