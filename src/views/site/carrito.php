<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php $cur = $currency ?? setting('currency', '$'); ?>

<!-- Breadcrumb -->
<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-500">
        <a href="<?= url('/') ?>" class="hover:text-orange-600 transition-colors">Inicio</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700 font-medium">Carrito de compras</span>
    </div>
</section>

<section class="py-14 lg:py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="font-display text-4xl font-bold text-gray-900">Tu carrito</h1>
        <p class="mt-2 text-gray-500">Estas son las delicias que has elegido para llevar a casa.</p>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <!-- Lista de artículos -->
            <div class="lg:col-span-2">
                <div class="rounded-2xl bg-white ring-1 ring-gray-200 p-12 text-center">
                    <div class="mx-auto w-20 h-20 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-3xl">
                        <i class="fa-solid fa-cookie-bite"></i>
                    </div>
                    <h2 class="mt-6 font-display text-2xl font-bold text-gray-900">Tu carrito está vacío</h2>
                    <p class="mt-2 text-gray-500 max-w-md mx-auto">
                        Aún no has agregado productos. Explora el catálogo y elige tus favoritos.
                    </p>
                    <a href="<?= url('catalogo') ?>" class="mt-8 inline-flex items-center gap-2 bg-orange-500 text-white font-semibold px-8 py-4 rounded-full hover:bg-orange-600 transition-colors">
                        <i class="fa-solid fa-basket-shopping"></i> Explorar catálogo
                    </a>
                </div>
            </div>

            <!-- Resumen -->
            <aside>
                <div class="sticky top-24 rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                    <h2 class="font-display text-xl font-bold text-gray-900">Resumen del pedido</h2>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Subtotal</dt>
                            <dd class="font-medium text-gray-900"><?= esc($cur) ?>0.00</dd>
                        </div>
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Descuentos</dt>
                            <dd class="font-medium text-green-600">-<?= esc($cur) ?>0.00</dd>
                        </div>
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Impuesto (IVA)</dt>
                            <dd class="font-medium text-gray-900"><?= esc($cur) ?>0.00</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                            <dt class="text-base font-semibold text-gray-900">Total</dt>
                            <dd class="font-display text-2xl font-bold text-gray-900"><?= esc($cur) ?>0.00</dd>
                        </div>
                    </dl>

                    <a href="#" onclick="return false;" title="Próximamente"
                       class="mt-8 flex items-center justify-center gap-2 w-full bg-gray-900 text-white font-semibold px-6 py-3.5 rounded-full hover:bg-gray-700 transition-colors">
                        <i class="fa-solid fa-lock"></i> Proceder al pago
                    </a>

                    <p class="mt-4 text-center text-xs text-gray-400">
                        Carrito y pago en línea disponibles próximamente.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>