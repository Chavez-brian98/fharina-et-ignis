<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php $businessName = setting('business_name', 'nuestra panadería'); ?>

<!-- Banner: header -->
<?php
$bannerTitle = 'Una historia horneada con amor';
$bannerEyebrow = 'Sobre nosotros';
$bannerText = 'En ' . $businessName . ' llevamos años endulzando y llenando de aroma los hogares con pan artesanal, repostería y delicias hechas a mano.';
$bannerImage = 'https://images.unsplash.com/photo-1587248720327-8eb72564be1e?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<!-- Misión / Visión (contenido) -->
<section class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-10 lg:grid-cols-2">
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8 lg:p-10">
            <i class="fa-solid fa-bullseye text-3xl text-gray-900"></i>
            <h2 class="mt-5 font-display text-3xl font-bold text-gray-900">Misión</h2>
            <p class="mt-3 text-gray-600 leading-relaxed">
                Brindar a nuestros clientes productos de panadería y repostería frescos, elaborados con
                ingredientes naturales y atención cálida, cuidando cada detalle desde el horno hasta su mesa.
            </p>
        </div>
        <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8 lg:p-10">
            <i class="fa-solid fa-eye text-3xl text-gray-900"></i>
            <h2 class="mt-5 font-display text-3xl font-bold text-gray-900">Visión</h2>
            <p class="mt-3 text-gray-600 leading-relaxed">
                Ser la panadería de preferencia de la comunidad, reconocida por la calidad de sus productos,
                su sabor artesanal y el cariño con el que atendemos a cada familia.
            </p>
        </div>
    </div>
</section>

<!-- Banner: Valores -->
<?php
$bannerTitle = 'Lo que nos guía cada día';
$bannerEyebrow = 'Nuestros valores';
$bannerImage = 'https://images.unsplash.com/photo-1555507036-ab1f4038808a?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<!-- Valores (contenido) -->
<section class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
        <div class="text-center px-6">
            <i class="fa-solid fa-heart text-3xl text-gray-900"></i>
            <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Pasión</h3>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">Horneamos con amor y dedicación en cada receta.</p>
        </div>
        <div class="text-center px-6">
            <i class="fa-solid fa-seedling text-3xl text-gray-900"></i>
            <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Frescura</h3>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">Ingredientes naturales y producto recién horneado.</p>
        </div>
        <div class="text-center px-6">
            <i class="fa-solid fa-handshake text-3xl text-gray-900"></i>
            <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Calidad</h3>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">Cuidamos cada detalle para ofrecerte lo mejor.</p>
        </div>
        <div class="text-center px-6">
            <i class="fa-solid fa-people-group text-3xl text-gray-900"></i>
            <h3 class="mt-5 font-display text-2xl font-semibold text-gray-900">Familia</h3>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">Atendemos cada cliente como parte de la nuestra.</p>
        </div>
    </div>
</section>

<!-- Banner: Equipo -->
<?php
$bannerTitle = 'Las manos detrás del horno';
$bannerEyebrow = 'Nuestro equipo';
$bannerText = 'Un equipo comprometido con el sabor y la calidad de principio a fin.';
$bannerImage = 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<!-- Equipo (contenido) -->
<section class="py-20 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <?php
        $team = [
            ['name' => 'María Orellana', 'role' => 'Fundadora y panadera principal'],
            ['name' => 'Carlos Orellana', 'role' => 'Repostero'],
            ['name' => 'Ana Martínez', 'role' => 'Administradora y atención al cliente'],
            ['name' => 'Jorge Rodríguez', 'role' => 'Pastelero'],
            ['name' => 'Lucía Castillo', 'role' => 'Maestra panadera'],
            ['name' => 'Pedro Gómez', 'role' => 'Despacho y entregas'],
        ];
        ?>
        <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($team as $member): ?>
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8 text-center hover:ring-orange-300 transition-all hover:shadow-xl hover:shadow-gray-200/50">
                    <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 text-gray-700 flex items-center justify-center text-2xl font-bold font-display">
                        <?= esc(substr($member['name'], 0, 1)) ?>
                    </div>
                    <h3 class="mt-5 font-display text-xl font-semibold text-gray-900"><?= esc($member['name']) ?></h3>
                    <p class="mt-1 text-sm font-medium text-orange-600"><?= esc($member['role']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>