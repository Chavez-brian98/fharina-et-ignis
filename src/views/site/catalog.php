<?php require_once __DIR__ . '/partials/header.php'; ?>

<!-- Banner: portada -->
<?php
$bannerTitle = 'Nuestros productos';
$bannerEyebrow = 'Catálogo';
$bannerText = 'Pan, repostería y especialidades de la casa. Todos los días frescos y listos para llevar.';
$bannerImage = 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<section class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Filtros -->
        <div class="flex flex-wrap items-center gap-4 mb-10">
            <div class="relative flex-1 min-w-[240px] max-w-md">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" id="siteSearch" placeholder="Buscar productos..."
                       class="w-full rounded-full border border-gray-200 bg-white pl-11 pr-4 py-3 text-gray-900 placeholder-gray-400 shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>
            <select id="siteCategory" class="rounded-full border border-gray-200 bg-white px-5 py-3 text-gray-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">Todas las categorías</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>"><?= esc($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (empty($products)): ?>
            <div class="rounded-2xl bg-white ring-1 ring-gray-100 p-14 text-center text-gray-500">
                <i class="fa-solid fa-cookie-bite text-4xl text-gray-300 mb-4"></i>
                <p class="font-semibold text-gray-700">No hay productos disponibles por el momento.</p>
                <p class="mt-1 text-sm">Vuelve pronto, estaremos horneando novedades.</p>
            </div>
        <?php else: ?>
            <div id="siteCatalogGrid" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($products as $p): ?>
                    <div class="site-card" data-search="<?= esc(strtolower(($p['name'] ?? '') . ' ' . ($p['category_name'] ?? ''))) ?>" data-category="<?= (int) $p['category_id'] ?>">
                        <?php require __DIR__ . '/partials/product_card.php'; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="siteEmptyState" class="hidden rounded-2xl bg-white ring-1 ring-gray-100 p-14 text-center text-gray-500">
                <i class="fa-solid fa-magnifying-glass text-4xl text-gray-300 mb-4"></i>
                <p class="font-semibold text-gray-700">Sin resultados</p>
                <p class="mt-1 text-sm">Prueba con otro término o cambia de categoría.</p>
            </div>
        <?php endif; ?>
    </div>
</section>

<script>
    (function () {
        const grid = document.getElementById('siteCatalogGrid');
        if (!grid) return;
        const empty = document.getElementById('siteEmptyState');
        const search = document.getElementById('siteSearch');
        const category = document.getElementById('siteCategory');
        const cards = Array.from(grid.querySelectorAll('.site-card'));

        function apply() {
            const q = (search.value || '').trim().toLowerCase();
            const cat = category.value;
            let visible = 0;
            cards.forEach(function (card) {
                const ok = (!q || card.dataset.search.includes(q)) && (!cat || card.dataset.category === cat);
                card.classList.toggle('hidden', !ok);
                if (ok) visible++;
            });
            if (empty) empty.classList.toggle('hidden', visible > 0);
        }
        search.addEventListener('input', apply);
        category.addEventListener('change', apply);
    })();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>