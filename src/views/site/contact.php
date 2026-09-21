<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php
$businessName = setting('business_name', 'nuestra panadería');
$address = setting('address');
$phone = setting('phone');
$mapQuery = rawurlencode($address ?: $businessName);
?>

<!-- Banner: portada -->
<?php
$bannerTitle = 'Estamos para servirte';
$bannerEyebrow = 'Contáctanos';
$bannerText = 'Escríbenos tus pedidos, dudas o sugerencias. Te respondemos lo antes posible.';
$bannerImage = 'https://images.unsplash.com/photo-1517433670267-08bb4dbe890f?auto=format&fit=crop&w=1920&q=80';
require __DIR__ . '/partials/banner.php';
?>

<section class="py-20 lg:py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-10 lg:grid-cols-5">
        <!-- Formulario -->
        <form method="POST" action="<?= url('contacto') ?>" class="lg:col-span-3 rounded-2xl bg-white ring-1 ring-gray-200 p-8 lg:p-10">
            <h2 class="text-2xl font-extrabold text-gray-900">Envíanos un mensaje</h2>
            <p class="mt-2 text-sm text-gray-500">Completa el formulario y te contactaremos pronto.</p>

            <div class="mt-8 grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nombre completo *</label>
                    <input type="text" id="name" name="name" required maxlength="150"
                           class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico *</label>
                    <input type="email" id="email" name="email" required maxlength="150"
                           class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500">
                </div>
            </div>

            <div class="mt-6">
                <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                <input type="tel" id="phone" name="phone" maxlength="30"
                       class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500">
            </div>

            <div class="mt-6">
                <label for="message" class="block text-sm font-medium text-gray-700">Mensaje *</label>
                <textarea id="message" name="message" required rows="5"
                          class="mt-2 w-full rounded-xl border border-gray-200 px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500"></textarea>
            </div>

            <button type="submit"
                    class="mt-8 inline-flex items-center gap-2 bg-orange-500 text-white font-semibold px-7 py-3.5 rounded-full hover:bg-orange-600 transition-colors">
                <i class="fa-solid fa-paper-plane"></i> Enviar mensaje
            </button>
        </form>

        <!-- Info de contacto -->
        <aside class="lg:col-span-2 space-y-6">
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-phone text-2xl text-gray-900 mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-gray-900">Teléfono</h3>
                        <p class="mt-1 text-sm text-gray-500"><?= esc($phone ?: 'Disponible por mensaje') ?></p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-location-dot text-2xl text-gray-900 mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-gray-900">Dirección</h3>
                        <p class="mt-1 text-sm text-gray-500"><?= esc($address ?: 'Consulta nuestra ubicación en el mapa.') ?></p>
                    </div>
                </div>
            </div>
            <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-clock text-2xl text-gray-900 mt-1"></i>
                    <div>
                        <h3 class="font-semibold text-gray-900">Horario</h3>
                        <p class="mt-1 text-sm text-gray-500">Lunes a sábado<br>de 6:00 a. m. a 7:00 p. m.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <!-- Mapa -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-16">
        <h2 class="font-display text-3xl font-bold text-gray-900">Nuestra ubicación</h2>
        <div class="mt-6 rounded-2xl overflow-hidden ring-1 ring-gray-200">
            <iframe
                src="https://maps.google.com/maps?q=<?= esc($mapQuery) ?>&t=&z=15&ie=UTF8&iwloc=&output=embed"
                class="w-full h-[400px] border-0"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen></iframe>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>