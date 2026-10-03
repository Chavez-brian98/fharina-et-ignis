<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php
$price = number_format((float) $product['sale_price'], 2);
$discount = (float) $product['discount_percent'];
$final = number_format((float) $product['final_price'], 2);
$cur = $currency ?? setting('currency', '$');
$available = (int) $product['stock'];
?>

<!-- Breadcrumb -->
<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-500">
        <a href="<?= url('/') ?>" class="hover:text-orange-600 transition-colors">Inicio</a>
        <span class="mx-2 text-gray-300">/</span>
        <a href="<?= url('catalogo') ?>" class="hover:text-orange-600 transition-colors">Catálogo</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700 font-medium"><?= esc($product['name']) ?></span>
    </div>
</section>

<section class="py-14 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-12 lg:grid-cols-2">
        <!-- Galería -->
        <div>
            <div class="relative aspect-square rounded-3xl overflow-hidden ring-1 ring-gray-200 bg-gray-100">
                <img id="productMainImg" src="<?= esc($gallery[0] ?? $product['image_url'] ?? '') ?>" alt="<?= esc($product['name']) ?>"
                     class="absolute inset-0 w-full h-full object-cover">
                <?php if ($discount > 0): ?>
                    <span class="absolute top-4 left-4 inline-flex items-center gap-1.5 bg-orange-500 text-white text-sm font-bold px-3 py-1.5 rounded-full shadow">
                        <i class="fa-solid fa-tag"></i> Promo -<?= round($discount) ?>%
                    </span>
                <?php endif; ?>
            </div>

            <?php if (count($gallery) > 1): ?>
                <div class="mt-4 grid grid-cols-4 gap-3">
                    <?php foreach ($gallery as $i => $url): ?>
                        <button type="button" data-url="<?= esc($url) ?>"
                                class="product-thumb aspect-square rounded-xl overflow-hidden ring-1 ring-gray-200 hover:ring-orange-300 transition-shadow <?= $i === 0 ? 'ring-2 ring-orange-500' : '' ?>">
                            <img src="<?= esc($url) ?>" alt="Foto <?= ($i + 1) ?> de <?= esc($product['name']) ?>"
                                 class="w-full h-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Información -->
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-500"><?= esc($product['category_name']) ?></p>
            <h1 class="mt-2 font-display text-4xl font-bold text-gray-900"><?= esc($product['name']) ?></h1>

            <div class="mt-6 flex items-baseline flex-wrap gap-3">
                <span class="text-4xl font-bold text-gray-900"><?= esc($cur) ?><?= esc($discount > 0 ? $final : $price) ?></span>
                <?php if ($discount > 0): ?>
                    <span class="text-xl text-gray-400 line-through"><?= esc($cur) ?><?= esc($price) ?></span>
                    <span class="text-sm font-semibold text-green-600">Ahorras <?= esc($cur) ?><?= esc(number_format((float) $product['sale_price'] - (float) $product['final_price'], 2)) ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($product['description'])): ?>
                <p class="mt-6 text-gray-600 leading-relaxed"><?= esc($product['description']) ?></p>
            <?php endif; ?>

            <div class="mt-6 flex items-center gap-2 text-sm">
                <?php if ($available > 10): ?>
                    <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                    <span class="text-gray-600">Disponible</span>
                <?php elseif ($available > 0): ?>
                    <span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span>
                    <span class="text-gray-600">¡Solo quedan <?= $available ?>!</span>
                <?php else: ?>
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                    <span class="text-gray-600">Agotado</span>
                <?php endif; ?>
            </div>

            <div class="mt-9 flex flex-wrap gap-4">
                <a href="#" onclick="return false;" title="Próximamente"
                   class="inline-flex items-center gap-2 bg-orange-500 text-white font-semibold px-8 py-4 rounded-full hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-cart-plus"></i> Agregar al carrito
                </a>
                <a href="<?= url('catalogo') ?>" class="inline-flex items-center gap-2 ring-1 ring-gray-300 text-gray-700 font-semibold px-8 py-4 rounded-full hover:bg-gray-50 transition-colors">
                    Seguir comprando
                </a>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($related)): ?>
    <section class="py-16 lg:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-500">Productos relacionados</p>
                    <h2 class="mt-2 font-display text-3xl font-bold text-gray-900">También te puede gustar</h2>
                </div>
                <div class="flex gap-2">
                    <button type="button" id="relPrev" aria-label="Anterior" class="w-11 h-11 rounded-full ring-1 ring-gray-300 text-gray-600 hover:bg-gray-900 hover:text-white transition-colors flex items-center justify-center">
                        <i class="fa-solid fa-arrow-left"></i>
                    </button>
                    <button type="button" id="relNext" aria-label="Siguiente" class="w-11 h-11 rounded-full ring-1 ring-gray-300 text-gray-600 hover:bg-gray-900 hover:text-white transition-colors flex items-center justify-center">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <div id="relatedTrack" class="related-track mt-10 flex gap-6 overflow-x-auto snap-x snap-mandatory pb-2" style="scrollbar-width:none;">
                <?php foreach ($related as $p): ?>
                    <div class="snap-start shrink-0 w-72 max-w-[80vw]">
                        <?php require __DIR__ . '/partials/product_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <style>
        .related-track::-webkit-scrollbar { display: none; }
    </style>

    <script>
        (function () {
            const track = document.getElementById('relatedTrack');
            if (!track) return;
            function move(dir) {
                const card = track.querySelector('.snap-start');
                const step = card ? card.getBoundingClientRect().width + 24 : 300;
                track.scrollBy({ left: dir * step, behavior: 'smooth' });
            }
            document.getElementById('relPrev')?.addEventListener('click', function () { move(-1); });
            document.getElementById('relNext')?.addEventListener('click', function () { move(1); });
        })();
    </script>
<?php endif; ?>

<script>
    (function () {
        const main = document.getElementById('productMainImg');
        if (!main) return;
        let active = null;
        document.querySelectorAll('.product-thumb').forEach(function (thumb) {
            if (thumb.classList.contains('ring-2')) active = thumb;
            thumb.addEventListener('click', function () {
                main.src = thumb.dataset.url;
                if (active) active.classList.remove('ring-2', 'ring-orange-500');
                thumb.classList.add('ring-2', 'ring-orange-500');
                active = thumb;
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>