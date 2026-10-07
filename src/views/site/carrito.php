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
                <div id="cartEmpty" class="rounded-2xl bg-white ring-1 ring-gray-200 p-12 text-center hidden">
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

                <div id="cartContent" class="hidden">
                    <div id="cartItems" class="rounded-2xl bg-white ring-1 ring-gray-200 divide-y divide-gray-100 overflow-hidden"></div>
                    <button type="button" id="cartClear"
                            class="mt-6 inline-flex items-center gap-2 text-sm font-medium text-gray-400 hover:text-red-500 transition-colors">
                        <i class="fa-solid fa-trash-can"></i> Vaciar carrito
                    </button>
                </div>
            </div>

            <!-- Resumen -->
            <aside>
                <div class="sticky top-24 rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                    <h2 class="font-display text-xl font-bold text-gray-900">Resumen del pedido</h2>

                    <dl class="mt-6 space-y-3 text-sm">
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Subtotal</dt>
                            <dd class="font-medium text-gray-900" id="sumSubtotal"><?= esc($cur) ?>0.00</dd>
                        </div>
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Envío a domicilio</dt>
                            <dd class="font-medium text-green-600">Gratis</dd>
                        </div>
                        <div class="border-t border-gray-100 pt-4 flex items-center justify-between">
                            <dt class="text-base font-semibold text-gray-900">Total</dt>
                            <dd class="font-display text-2xl font-bold text-gray-900" id="sumTotal"><?= esc($cur) ?>0.00</dd>
                        </div>
                    </dl>

                    <a href="<?= url('finalizar') ?>"
                       class="mt-8 flex items-center justify-center gap-2 w-full bg-gray-900 text-white font-semibold px-6 py-3.5 rounded-full hover:bg-gray-700 transition-colors">
                        <i class="fa-solid fa-lock"></i> Proceder al pago
                    </a>

                    <p class="mt-4 text-center text-xs text-gray-400">
                        Debes iniciar sesión como cliente para finalizar tu pedido.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
(function () {
    var cur = <?= json_encode($cur) ?>;
    var itemsRoot = document.getElementById('cartItems');
    var emptyBox = document.getElementById('cartEmpty');
    var contentBox = document.getElementById('cartContent');

    function money(n) { return cur + n.toFixed(2); }

    function render() {
        var items = window.SiteCart.items();
        var total = window.SiteCart.total();

        itemsRoot.innerHTML = '';
        items.forEach(function (it) {
            var row = document.createElement('div');
            row.className = 'px-6 py-5 flex flex-wrap items-center gap-4';
            row.innerHTML =
                '<div class="min-w-0 flex-1">' +
                '   <h3 class="font-semibold text-gray-900 truncate"></h3>' +
                '   <p class="mt-0.5 text-sm text-gray-500">Unidad ' + money(it.price) + '</p>' +
                '</div>' +
                '<div class="flex items-center rounded-full ring-1 ring-gray-200">' +
                '   <button type="button" data-cart-action="dec" data-id="' + it.id + '" class="w-9 h-9 text-gray-500 hover:text-gray-900" aria-label="Quitar">' +
                '       <i class="fa-solid fa-minus text-xs"></i></button>' +
                '   <span class="w-10 text-center font-semibold text-gray-900">' + it.qty + '</span>' +
                '   <button type="button" data-cart-action="inc" data-id="' + it.id + '" class="w-9 h-9 text-gray-500 hover:text-gray-900" aria-label="Agregar">' +
                '       <i class="fa-solid fa-plus text-xs"></i></button>' +
                '</div>' +
                '<div class="w-24 text-right font-semibold text-gray-900">' + money(it.price * it.qty) + '</div>' +
                '<button type="button" data-cart-action="del" data-id="' + it.id + '" class="w-9 h-9 text-gray-300 hover:text-red-500 transition-colors" aria-label="Eliminar">' +
                '   <i class="fa-solid fa-xmark"></i></button>';
            row.querySelector('h3').textContent = it.name;
            itemsRoot.appendChild(row);
        });

        document.getElementById('sumSubtotal').textContent = money(total);
        document.getElementById('sumTotal').textContent = money(total);

        emptyBox.classList.toggle('hidden', items.length > 0);
        contentBox.classList.toggle('hidden', items.length === 0);
    }

    document.getElementById('cartClear').addEventListener('click', function () {
        window.SiteCart.clear();
        render();
    });

    itemsRoot.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-cart-action]');
        if (!btn) return;
        var id = btn.dataset.id;
        if (btn.dataset.cartAction === 'inc') window.SiteCart.setQty(id, window.SiteCart.get()[id].qty + 1);
        if (btn.dataset.cartAction === 'dec') {
            var q = window.SiteCart.get()[id].qty - 1;
            q <= 0 ? window.SiteCart.remove(id) : window.SiteCart.setQty(id, q);
        }
        if (btn.dataset.cartAction === 'del') window.SiteCart.remove(id);
        render();
    });

    render();
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>