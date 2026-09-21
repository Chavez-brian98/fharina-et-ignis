<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php
$businessName = setting('business_name', 'nuestra panadería');
$loginPhoto = setting('login_photo');
$heroImage = $loginPhoto ?: 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=1920&q=80';
?>

<!-- Hero -->
<section class="relative min-h-[70vh] lg:min-h-[760px] flex items-center text-white">
    <img src="<?= esc($heroImage) ?>" alt="Nuestra panadería" class="absolute inset-0 w-full h-full object-cover">
    <div class="absolute inset-0 bg-gradient-to-r from-gray-950/85 via-gray-950/60 to-gray-950/25"></div>

    <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-28 lg:py-36">
        <div class="max-w-2xl">
            <p class="inline-flex items-center gap-3 text-sm font-semibold uppercase tracking-[0.25em] text-orange-300">
                <i class="fa-solid fa-fire-burner"></i> Panadería artesanal
            </p>
            <h1 class="mt-6 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-tight">
                Frescura y tradición en cada horneada
            </h1>
            <p class="mt-6 text-xl text-gray-200 max-w-xl">
                En <?= esc($businessName) ?> elaboramos pan, repostería y bocadillos artesanales con
                ingredientes naturales, todos los días desde temprano.
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="<?= url('catalogo') ?>" class="inline-flex items-center gap-2 bg-orange-500 text-white font-semibold px-8 py-4 rounded-full shadow-lg shadow-orange-950/30 hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-basket-shopping"></i> Ver catálogo
                </a>
                <a href="<?= url('contacto') ?>" class="inline-flex items-center gap-2 ring-1 ring-white/50 text-white font-semibold px-8 py-4 rounded-full hover:bg-white/10 transition-colors">
                    Contáctanos
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Banner: Sobre la panadería -->
<?php
$bannerTitle = 'El sabor de siempre, hecho a mano';
$bannerEyebrow = '¿Quiénes somos?';
$bannerText = 'Somos una panadería familiar comprometida con la calidad: masas fermentadas lentamente, ingredientes frescos y recetas que pasan de generación en generación.';
$bannerImage = 'https://images.unsplash.com/photo-1517433670267-08bb4dbe890f?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<!-- Sobre la panadería (contenido) -->
<section class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mt-0 grid gap-12 sm:grid-cols-2 lg:grid-cols-3">
            <div class="text-center px-6">
                <i class="fa-solid fa-wheat-awn text-4xl text-gray-900"></i>
                <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Ingredientes naturales</h3>
                <p class="mt-2 text-sm text-gray-500 leading-relaxed">Harinas seleccionadas, mantequilla, huevos y frutas frescas al gusto de casa.</p>
            </div>
            <div class="text-center px-6">
                <i class="fa-solid fa-hand text-4xl text-gray-900"></i>
                <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Hecho a mano cada día</h3>
                <p class="mt-2 text-sm text-gray-500 leading-relaxed">Horneamos en lotes pequeños para que todo salga fresco y recién hecho.</p>
            </div>
            <div class="text-center px-6">
                <i class="fa-solid fa-house-chimney text-4xl text-gray-900"></i>
                <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Tradición familiar</h3>
                <p class="mt-2 text-sm text-gray-500 leading-relaxed">Recetas que nos acompañan generación tras generación, con el toque de nuestra familia.</p>
            </div>
        </div>
    </div>
</section>

<!-- Banner: Destacados -->
<?php
$bannerTitle = 'Nuestros productos favoritos';
$bannerEyebrow = 'Destacados';
$bannerText = 'Lo más pedido de la casa, horneado todas las mañanas.';
$bannerImage = 'https://images.unsplash.com/photo-1483695028939-5bb13f8648b0?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<!-- Destacados (contenido) -->
<section class="py-20 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-end mb-10">
            <a href="<?= url('catalogo') ?>" class="inline-flex items-center gap-2 text-gray-700 font-semibold hover:text-orange-600 transition-colors">
                Ver todos los productos <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <?php if (empty($featured)): ?>
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-10 text-center text-gray-500">
                <i class="fa-solid fa-cookie-bite text-3xl text-gray-300 mb-3"></i>
                <p>Próximamente nuestros productos frescos.</p>
            </div>
        <?php else: ?>
            <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($featured as $p): ?>
                    <?php require __DIR__ . '/partials/product_card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Banner CTA -->
<?php
$bannerTitle = '¿Tienes un pedido especial?';
$bannerEyebrow = 'Pedidos personalizados';
$bannerCentered = true;
$bannerImage = 'https://images.unsplash.com/photo-1509365465985-25d11c17e812?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>
<div class="py-16 bg-gray-950 text-center">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-gray-400">Pan de fiesta, tortas personalizadas o pedidos para eventos. ¡Lo horneamos con gusto!</p>
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <a href="<?= url('contacto') ?>" class="inline-flex items-center gap-2 bg-orange-500 text-white font-semibold px-8 py-4 rounded-full hover:bg-orange-600 transition-colors">
                <i class="fa-solid fa-envelope"></i> Escríbenos
            </a>
            <a href="<?= url('nosotros') ?>" class="inline-flex items-center gap-2 ring-1 ring-white/40 text-white font-semibold px-8 py-4 rounded-full hover:bg-white/10 transition-colors">
                Conócenos
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>