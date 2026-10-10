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
                        <p class="mt-2 text-sm text-gray-500">Se abrirá una ventana de PayPal para completar el pago de forma segura, sin salir de esta página. Tu pedido se crea al aprobarlo.</p>

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

<!-- Modal de pago PayPal (SDK in-context) -->
<div id="paypalModal" class="hidden fixed inset-0 z-[100] items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="paypalModalTitle">
    <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm" data-modal-close></div>
    <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl p-7">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 id="paypalModalTitle" class="font-display text-2xl font-bold text-gray-900">Pagar con PayPal</h3>
                <p class="mt-1 text-sm text-gray-500">Paga de forma segura sin salir de aquí.</p>
            </div>
            <button type="button" data-modal-close class="w-9 h-9 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 flex items-center justify-center transition-colors" aria-label="Cerrar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="mt-5 flex items-center justify-between rounded-xl bg-orange-50 px-4 py-3">
            <span class="text-sm font-medium text-gray-600">Total a pagar</span>
            <span class="font-display text-xl font-bold text-orange-600"><?= esc($cur) ?><span id="modalAmount">0.00</span></span>
        </div>

        <div id="paypal-button-container" class="mt-5"></div>

        <p id="paypalMessage" class="mt-3 hidden text-sm"></p>

        <button type="button" data-modal-close class="mt-4 w-full text-center text-sm font-medium text-gray-400 hover:text-gray-700 transition-colors">Cancelar</button>
    </div>
</div>

<?php if ($paypalConfigurado): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode($paypalClientId) ?>&currency=USD&intent=capture&components=buttons&disable-funding=card,credit"></script>
<?php endif; ?>

<script>
(function () {
    var cur = <?= json_encode($cur) ?>;
    var paypalConfigured = <?= $paypalConfigurado ? 'true' : 'false' ?>;
    var ENDPOINT = <?= json_encode(url('finalizar')) ?>;
    var form = document.getElementById('checkoutForm');
    var payBtn = document.getElementById('payBtn');
    var modal = document.getElementById('paypalModal');
    var msgEl = document.getElementById('paypalMessage');
    var busy = false;
    var buttonsRendered = false;
    var pendingOrderId = null;
    var pendingPaypalId = null;
    var pendingTotal = null;

    function money(n) { return cur + n.toFixed(2); }

    function setMessage(text, type) {
        if (!msgEl) return;
        var colors = { info: 'text-gray-500', warn: 'text-amber-600', error: 'text-red-600' };
        msgEl.className = 'mt-3 text-sm ' + (colors[type] || 'text-gray-500');
        msgEl.textContent = text || '';
        msgEl.classList.toggle('hidden', !text);
    }

    function errorMessage(data) {
        return data.error === 'address' ? 'Escribe la dirección de entrega.'
            : data.error === 'items' ? 'Tu carrito está vacío.'
            : data.error === 'product' ? 'Uno de los productos ya no está disponible.'
            : data.error === 'paypal' ? 'PayPal no pudo procesar el pago. ' + (data.message || '')
            : 'No se pudo crear el pedido. Inténtalo de nuevo.';
    }

    function resetPayBtn() {
        busy = false;
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fa-brands fa-paypal text-xl"></i> Pagar ' + money(window.SiteCart.total()) + ' con PayPal';
    }

    function toast(text) {
        if (window.Toastify) {
            Toastify({ text: '\u26a0 ' + text, duration: 4000, gravity: 'bottom', position: 'right', className: 'cart-toast', style: { background: '#b91c1c' } }).showToast();
        }
    }

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
        document.getElementById('modalAmount').textContent = total.toFixed(2);

        var vacio = items.length === 0;
        document.getElementById('checkoutEmpty').classList.toggle('hidden', !vacio);
        form.querySelectorAll('input, textarea, button').forEach(function (el) {
            if (el !== document.getElementById('checkoutEmpty')) el.disabled = vacio;
        });
        if (vacio) payBtn.classList.add('opacity-50');
    }

    function openModal() { modal.classList.remove('hidden'); modal.classList.add('flex'); }
    function closeModal() { modal.classList.add('hidden'); modal.classList.remove('flex'); }

    function validarDireccion() {
        var address = document.getElementById('address').value.trim();
        if (!address) { document.getElementById('address').focus(); return null; }
        return address;
    }

    // Crea el pedido + la orden PayPal (una sola vez por intento) y devuelve el id de PayPal.
    function crearOrden() {
        var address = validarDireccion();
        if (!address) return Promise.reject(new Error('Escribe la dirección de entrega.'));
        // Reutiliza la orden ya creada si el total no cambió (evita pedidos duplicados al reintentar).
        if (pendingPaypalId && pendingTotal === window.SiteCart.total()) {
            return Promise.resolve(pendingPaypalId);
        }
        setMessage('Creando tu pedido\u2026', 'info');
        return fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                address: address,
                notes: document.getElementById('notes').value,
                items: window.SiteCart.items().map(function (it) { return { id: it.id, qty: it.qty }; }),
                flow: 'js'
            })
        })
        .then(function (res) { return res.json().catch(function () { return { ok: false, error: 'db' }; }); })
        .then(function (data) {
            if (!data.ok) {
                if (data.error === 'login') {
                    window.location.href = <?= json_encode(url('ingresar')) ?> + '?next=finalizar';
                    throw new Error('__silent__');
                }
                throw new Error(errorMessage(data));
            }
            pendingOrderId = data.order_id;
            pendingPaypalId = data.paypal_order_id;
            pendingTotal = window.SiteCart.total();
            setMessage('');
            return data.paypal_order_id;
        });
    }

    // Captura el pago aprobado dentro del popup y lleva al seguimiento.
    function confirmarPago(paypalOrderId) {
        setMessage('Confirmando el pago\u2026', 'info');
        return fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'capture', order_id: pendingOrderId, paypal_order_id: paypalOrderId })
        })
        .then(function (res) { return res.json().catch(function () { return { ok: false, error: 'db' }; }); })
        .then(function (data) {
            if (!data.ok) throw new Error(data.message || 'No se pudo confirmar el pago.');
            window.SiteCart.clear();
            window.location.href = data.redirect || <?= json_encode(url('cuenta')) ?>;
        });
    }

    function renderPaypal() {
        if (buttonsRendered || !window.paypal) return;
        buttonsRendered = true;
        window.paypal.Buttons({
            style: { layout: 'vertical', color: 'gold', shape: 'pill', label: 'paypal', height: 48 },
            createOrder: function () {
                setMessage('');
                return crearOrden().catch(function (err) {
                    if (err && err.message !== '__silent__') { setMessage(err.message, 'error'); }
                    throw err;
                });
            },
            onApprove: function (data) {
                return confirmarPago(data.orderID).catch(function (err) {
                    setMessage(err.message || 'No se pudo confirmar el pago.', 'error');
                });
            },
            onCancel: function () {
                setMessage('Cancelaste el pago. Puedes intentarlo de nuevo.', 'warn');
            },
            onError: function (err) {
                setMessage('Ocurrió un error con PayPal. Inténtalo de nuevo.', 'error');
                if (window.console) console.error(err);
            }
        }).render('#paypal-button-container');
    }

    // Flujo clásico de página completa (sin SDK / sin PayPal configurado).
    function pagoRedirect() {
        var address = validarDireccion();
        if (!address) return;
        busy = true;
        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Creando tu pedido\u2026';

        fetch(ENDPOINT, {
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
                throw new Error(errorMessage(data));
            }
            window.SiteCart.clear();
            if (data.approval_url) {
                window.location.href = data.approval_url;
            } else {
                window.location.href = data.redirect || <?= json_encode(url('cuenta')) ?>;
            }
        })
        .catch(function (err) {
            toast(err.message);
            resetPayBtn();
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy || window.SiteCart.count() === 0) return;
        if (!validarDireccion()) return;

        if (paypalConfigured && window.paypal) {
            openModal();
            renderPaypal();
        } else {
            pagoRedirect();
        }
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });

    // PayPal SDK se carga async: si el usuario ya abrió el modal, píntalo al estar listo.
    if (paypalConfigured && window.paypal === undefined) {
        var espera = 0;
        var timer = setInterval(function () {
            espera += 250;
            if (window.paypal) {
                clearInterval(timer);
                if (!modal.classList.contains('hidden')) renderPaypal();
            } else if (espera >= 8000) {
                clearInterval(timer);
            }
        }, 250);
    }

    renderSummary();
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>