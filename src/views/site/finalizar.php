<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php $cur = $currency ?? setting('currency', '$');
$cliente = $_SESSION['cliente']; ?>

<!-- Breadcrumb -->
<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-500">
        <a href="<?= url('/') ?>" class="hover:text-orange-600 transition-colors">Inicio</a>
        <span class="mx-2 text-gray-300">/</span>
        <a href="<?= url('carrito') ?>" class="hover:text-orange-600 transition-colors">Carrito</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700 font-medium">Finalizar pedido</span>
    </div>
</section>

<section class="py-14 lg:py-20 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="font-display text-4xl font-bold text-gray-900">Finalizar tu pedido</h1>
        <p class="mt-2 text-gray-500">Cuida tu dirección para que el domiciliero llegue sin rodeos.</p>

        <div class="mt-10 grid gap-10 lg:grid-cols-3">
            <!-- Formulario -->
            <div class="lg:col-span-2">
                <form id="checkoutForm" class="rounded-2xl bg-white ring-1 ring-gray-200 p-8 space-y-6">
                    <div>
                        <h2 class="font-display text-xl font-bold text-gray-900">1. ¿A dónde lo llevamos?</h2>
                        <div class="mt-5 space-y-5">
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700">Dirección de entrega</label>
                                <textarea id="address" name="address" required rows="2"
                                          placeholder="Ej.: C. El Progreso #12, Col. Centro, San Salvador"
                                          class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition"><?= esc($cliente['address'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-gray-700">Notas (<span class="text-gray-400">opcional</span>)</label>
                                <input type="text" id="notes" name="notes"
                                       placeholder="Ej.: Llamar al llegar, portón azul"
                                       class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                            </div>
                            <p class="text-sm text-gray-400">
                                <i class="fa-solid fa-location-dot mr-1.5"></i>
                                Cliente: <span class="text-gray-600"><?= esc($cliente['name']) ?></span> ·
                                <?= esc($cliente['email']) ?>
                            </p>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <div>
                        <h2 class="font-display text-xl font-bold text-gray-900">2. Pago <span class="text-sm font-normal text-gray-400">(PayPal · sandbox)</span></h2>
                        <p class="mt-2 text-sm text-gray-500">Serás redirigido a PayPal para completar el pago de forma segura. Tu pedido se crea al aprobarlo.</p>

                        <?php if (!$paypalConfigurado): ?>
                            <div class="mt-5 rounded-xl bg-amber-50 ring-1 ring-amber-200 p-5 flex gap-3">
                                <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-1"></i>
                                <p class="text-sm text-amber-800">
                                    El pago con PayPal aún no está configurado. El pedido que generes quedará
                                    <strong>pendiente de pago</strong> y el personal lo confirmará al entregar.
                                </p>
                            </div>
                        <?php endif; ?>

                        <button type="submit" id="payBtn" <?= $paypalConfigurado ? '' : 'data-no-paypal="1"' ?>
                                class="mt-6 w-full inline-flex items-center justify-center gap-3 bg-orange-500 text-white font-semibold px-6 py-4 rounded-2xl hover:bg-orange-600 transition-colors">
                            <i class="fa-brands fa-paypal text-xl"></i> Pagar <?= esc($cur) ?><span id="payAmount">0.00</span> con PayPal
                        </button>
                    </div>
                </form>
            </div>

            <!-- Resumen -->
            <aside>
                <div class="sticky top-24 rounded-2xl bg-white ring-1 ring-gray-200 p-8">
                    <h2 class="font-display text-xl font-bold text-gray-900">Tu pedido</h2>
                    <div id="checkoutItems" class="mt-5 space-y-3 max-h-72 overflow-y-auto pr-1"></div>
                    <dl class="mt-6 pt-6 border-t border-gray-100 space-y-3 text-sm">
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Subtotal</dt>
                            <dd class="font-medium text-gray-900" id="checkoutSubtotal"><?= esc($cur) ?>0.00</dd>
                        </div>
                        <div class="flex items-center justify-between text-gray-600">
                            <dt>Envío</dt>
                            <dd class="font-medium text-green-600">Gratis</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-base font-semibold text-gray-900">Total</dt>
                            <dd class="font-display text-2xl font-bold text-gray-900" id="checkoutTotal"><?= esc($cur) ?>0.00</dd>
                        </div>
                    </dl>

                    <p id="checkoutEmpty" class="mt-6 text-sm text-gray-400">
                        Tu carrito está vacío. <a href="<?= url('catalogo') ?>" class="text-orange-500 hover:text-orange-600 font-medium">Ver catálogo</a>.
                    </p>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
(function () {
    var cur = <?= json_encode($cur) ?>;
    var form = document.getElementById('checkoutForm');
    var payBtn = document.getElementById('payBtn');
    var busy = false;

    function money(n) { return cur + n.toFixed(2); }

    function renderSummary() {
        var items = window.SiteCart.items();
        var total = window.SiteCart.total();
        var root = document.getElementById('checkoutItems');

        root.innerHTML = '';
        items.forEach(function (it) {
            var row = document.createElement('div');
            row.className = 'flex items-baseline justify-between gap-3 text-sm';
            row.innerHTML =
                '<span class="text-gray-600 min-w-0"><span class="font-semibold text-gray-900">' + it.qty + '\u00d7</span> ' + it.name + '</span>' +
                '<span class="font-medium text-gray-900 whitespace-nowrap">' + money(it.price * it.qty) + '</span>';
            root.appendChild(row);
        });

        document.getElementById('checkoutSubtotal').textContent = money(total);
        document.getElementById('checkoutTotal').textContent = money(total);
        document.getElementById('payAmount').textContent = total.toFixed(2);

        var vacio = items.length === 0;
        document.getElementById('checkoutEmpty').classList.toggle('hidden', !vacio);
        form.querySelectorAll('input, textarea, button').forEach(function (el) {
            if (el !== document.getElementById('checkoutEmpty')) el.disabled = vacio;
        });
        if (vacio) payBtn.classList.add('opacity-50');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy || window.SiteCart.count() === 0) return;

        var address = document.getElementById('address').value.trim();
        if (!address) {
            document.getElementById('address').focus();
            return;
        }

        busy = true;
        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Creando tu pedido…';

        fetch(<?= json_encode(url('finalizar')) ?>, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                address: address,
                notes: document.getElementById('notes').value,
                items: window.SiteCart.items().map(function (it) { return { id: it.id, qty: it.qty }; })
            })
        })
        .then(function (res) { return res.json().catch(function () { return { ok: false, error: 'db' }; }); })
        .then(function (data) {
            if (!data.ok) {
                if (data.error === 'login') {
                    window.location.href = <?= json_encode(url('ingresar')) ?> + '?next=finalizar';
                    return;
                }
                var msg = data.error === 'address' ? 'Escribe la dirección de entrega.'
                    : data.error === 'items' ? 'Tu carrito está vacío.'
                    : data.error === 'product' ? 'Uno de los productos ya no está disponible.'
                    : data.error === 'paypal' ? 'PayPal no pudo procesar el pago. ' + (data.message || '')
                    : 'No se pudo crear el pedido. Inténtalo de nuevo.';
                throw new Error(msg);
            }

            // Pedido creado: se limpia el carrito y se abre PayPal.
            window.SiteCart.clear();
            if (data.approval_url) {
                window.location.href = data.approval_url;
            } else if (data.redirect) {
                // Sin PayPal configurado: seguimiento del pedido pendiente.
                window.location.href = data.redirect;
            } else {
                window.location.href = <?= json_encode(url('cuenta')) ?>;
            }
        })
        .catch(function (err) {
            if (window.Toastify) {
                Toastify({ text: '\u26a0 ' + err.message, duration: 4000, gravity: 'bottom', position: 'right', className: 'cart-toast', style: { background: '#b91c1c' } }).showToast();
            }
            busy = false;
            payBtn.disabled = false;
            payBtn.innerHTML = '<i class="fa-brands fa-paypal text-xl"></i> Pagar ' + money(window.SiteCart.total()) + ' con PayPal';
        });
    });

    renderSummary();
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>