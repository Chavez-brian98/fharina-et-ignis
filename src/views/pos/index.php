<?php require __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex flex-col xl:flex-row gap-6 items-start">

    <!-- ========================= CATÁLOGO ========================= -->
    <section class="xl:flex-1 min-w-0 w-full">
        <div class="flex flex-col sm:flex-row gap-3 mb-4">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                <input id="posSearch" type="text" placeholder="Buscar producto por nombre o categoría..."
                       class="form-input !pl-10" autocomplete="off">
            </div>
            <button type="button" class="btn-scan-barcode
                    rounded-xl px-4 py-2.5 text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-orange-50 hover:text-orange-500 transition-colors shadow-sm shrink-0"
                    title="Escanear código de barras">
                <i class="fa-solid fa-barcode" aria-hidden="true"></i>
            </button>
            <button type="button" id="posClearCart"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-red-500 transition-colors shadow-sm shrink-0">
                <i class="fa-solid fa-trash-can mr-1.5"></i>Limpiar
            </button>
        </div>

        <div id="posCategories" class="flex flex-wrap gap-2 mb-4">
            <button type="button" data-cat=""
                    class="pos-chip active rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors">Todos</button>
            <?php foreach ($categories as $c): ?>
                <button type="button" data-cat="<?= (int) $c['id'] ?>"
                        class="pos-chip rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"><?= esc($c['name']) ?></button>
            <?php endforeach; ?>
        </div>

        <div id="posGrid" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($catalog as $p): ?>
                <?php
                    $out = (int) $p['stock'] <= 0;
                    $hasDisc = (float) $p['discount_percent'] > 0;
                ?>
                <div class="pos-card bg-white rounded-2xl overflow-hidden shadow-lg shadow-gray-200/50 border border-gray-100 transition select-none <?= $out ? 'opacity-50' : 'hover:border-orange-300 hover:shadow-orange-100/60 cursor-pointer' ?>"
                     data-id="<?= (int) $p['id'] ?>"
                     data-name="<?= esc($p['name']) ?>"
                     data-original="<?= (float) $p['sale_price'] ?>"
                     data-price="<?= (float) $p['final_price'] ?>"
                     data-stock="<?= (int) $p['stock'] ?>"
                     data-discount="<?= (float) $p['discount_percent'] ?>"
                     data-category="<?= (int) $p['category_id'] ?>"
                     data-barcode="<?= esc($p['barcode'] ?? '') ?>"
                     data-search="<?= esc(strtolower($p['name'] . ' ' . $p['category_name'])) ?>">
                    <div class="relative h-28 bg-gradient-to-br from-orange-50 to-gray-50 flex items-center justify-center">
                        <?php if (!empty($p['image_url'])): ?>
                            <img src="<?= esc($p['image_url']) ?>" alt="<?= esc($p['name']) ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <i class="fa-solid fa-bread-slice text-4xl text-orange-200 <?= $out ? '' : 'pos-product-icon' ?> transition-transform"></i>
                        <?php endif; ?>
                        <span class="absolute top-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-500 text-white shadow-sm"><?= esc($p['category_name']) ?></span>
                        <?php if ($hasDisc): ?>
                            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-500 text-white shadow-sm">-<?= (int) $p['discount_percent'] ?>%</span>
                        <?php endif; ?>
                        <?php if ($out): ?>
                            <span class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-600 text-white">Sin stock</span>
                        <?php endif; ?>
                    </div>
                    <div class="p-3">
                        <p class="text-sm font-semibold text-gray-900 leading-snug truncate" title="<?= esc($p['name']) ?>"><?= esc($p['name']) ?></p>
                        <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                            <span class="text-orange-500 font-bold text-sm"><?= esc(setting('currency', '$')) ?><?= number_format((float) $p['final_price'], 2) ?></span>
                            <?php if ($hasDisc): ?>
                                <span class="text-xs text-gray-400 line-through"><?= esc(setting('currency', '$')) ?><?= number_format((float) $p['sale_price'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div id="posEmpty" class="hidden col-span-full text-center py-14 text-gray-400">
                <i class="fa-solid fa-magnifying-glass text-3xl mb-3 opacity-40"></i>
                <p class="text-sm font-medium">No se encontraron productos</p>
            </div>
        </div>
    </section>

    <!-- ========================= CARRITO ========================= -->
    <aside class="xl:w-[380px] shrink-0 w-full">
        <div class="bg-white rounded-2xl shadow-lg shadow-gray-200/50 p-5 xl:sticky xl:top-6 flex flex-col gap-4">

            <div class="flex items-center justify-between">
                <h2 class="font-bold text-gray-900">Venta actual</h2>
                <span id="posCount" class="text-xs font-bold px-2.5 py-1 rounded-full bg-orange-50 text-orange-500">0</span>
            </div>

            <ul id="posCartItems" class="space-y-3 max-h-80 overflow-y-auto -mx-1 px-1"></ul>
            <div id="posCartEmpty" class="text-center py-8 text-gray-300">
                <i class="fa-solid fa-cart-arrow-down text-3xl mb-2 opacity-50"></i>
                <p class="text-sm font-medium">Toca un producto para agregarlo</p>
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-2">
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Subtotal</span><span id="posSubtotal" class="font-semibold text-gray-700">$0.00</span>
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Descuento</span><span id="posDiscount" class="font-semibold text-green-500">-$0.00</span>
                </div>
                <div class="flex justify-between text-sm text-gray-500">
                    <span>Impuesto</span><span id="posTax" class="font-semibold text-gray-700">$0.00</span>
                </div>
                <div class="flex justify-between text-lg font-bold text-gray-900 border-t border-gray-100 pt-2">
                    <span>TOTAL</span><span id="posTotal">$0.00</span>
                </div>
            </div>

            <div class="space-y-2.5">
                <label class="form-label mb-1">Forma de pago</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><i class="fa-solid fa-money-bill-wave"></i></span>
                    <input type="number" id="payEfectivo" min="0" step="0.01" value=""
                           class="form-input !pl-10" placeholder="Efectivo">
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><i class="fa-solid fa-credit-card"></i></span>
                    <input type="number" id="payTarjeta" min="0" step="0.01" value=""
                           class="form-input !pl-10" placeholder="Tarjeta">
                </div>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"><i class="fa-solid fa-building-columns"></i></span>
                    <input type="number" id="payTransferencia" min="0" step="0.01" value=""
                           class="form-input !pl-10" placeholder="Transferencia">
                </div>

                <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-2.5 text-sm">
                    <span class="text-gray-500 font-medium">Pagado</span>
                    <span id="posPaid" class="font-bold text-gray-800">$0.00</span>
                </div>
                <div id="posBalanceWrap" class="flex items-center justify-between rounded-xl px-4 py-2.5 text-sm hidden">
                    <span id="posBalanceLabel" class="font-medium"></span>
                    <span id="posBalance" class="font-bold"></span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1">
                <button type="button" id="posCancel"
                        class="rounded-xl px-4 py-3 text-sm font-semibold bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
                <button type="button" id="posCheckout"
                        class="rounded-xl px-4 py-3 text-sm font-bold bg-orange-500 text-white shadow-lg shadow-orange-200 hover:bg-orange-600 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                        disabled>
                    <i class="fa-solid fa-circle-check mr-1.5"></i>Cobrar
                </button>
            </div>

            <form id="posCheckoutForm" method="post" action="<?= url('pos/checkout') ?>">
                <input type="hidden" name="payload" id="posPayload" value="">
            </form>
        </div>
    </aside>
</div>

<?php require_once __DIR__ . '/../partials/barcode_scanner.php'; ?>

<?php
    $taxRate = (float) setting('tax_rate', 0);
    $currency = setting('currency', '$');
?>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const TAX_RATE = <?= $taxRate ?>;
    const CURRENCY = '<?= esc($currency) ?>';
    const checkoutForm = document.getElementById('posCheckoutForm');

    // --- Estado -------------------------------------------------------------
    let cart = {};            // product_id -> {id,name,original,price,stock,percent,qty}
    let activeCategory = '';

    // --- Helpers ------------------------------------------------------------
    function money(n) {
        return CURRENCY + Number(n || 0).toFixed(2);
    }

    function cardEl(id) {
        return document.querySelector('.pos-card[data-id="' + id + '"]');
    }

    function parseCard(card) {
        return {
            id: card.getAttribute('data-id'),
            name: card.getAttribute('data-name'),
            original: parseFloat(card.getAttribute('data-original')),
            price: parseFloat(card.getAttribute('data-price')),
            stock: parseInt(card.getAttribute('data-stock'), 10),
            percent: parseFloat(card.getAttribute('data-discount'))
        };
    }

    // --- Carrito ------------------------------------------------------------
    function addToCart(id) {
        const card = cardEl(id);
        if (!card || card.classList.contains('opacity-50')) return;

        const data = parseCard(card);
        if (cart[id]) {
            if (cart[id].qty >= data.stock) {
                Swal.fire({ title: 'Stock insuficiente', text: 'Solo hay ' + data.stock + ' disponibles de "' + data.name + '".', icon: 'warning', confirmButtonColor: '#f97316' });
                return;
            }
            cart[id].qty++;
        } else {
            cart[id] = { ...data, qty: 1 };
        }
        renderCart();
    }

    function changeQty(id, delta) {
        if (!cart[id]) return;
        const next = cart[id].qty + delta;
        if (next < 1) { removeItem(id); return; }
        if (next > cart[id].stock) return;
        cart[id].qty = next;
        renderCart();
    }

    function removeItem(id) {
        delete cart[id];
        renderCart();
    }

    function clearCart() {
        cart = {};
        renderCart();
    }

    function cartTotals() {
        let subtotal = 0, discount = 0, qty = 0;
        Object.values(cart).forEach(function (it) {
            subtotal += it.original * it.qty;
            discount += (it.original - it.price) * it.qty;
            qty += it.qty;
        });
        const tax = (subtotal - discount) * TAX_RATE / 100;
        return { subtotal: subtotal, discount: discount, tax: tax, total: subtotal - discount + tax, qty: qty };
    }

    // --- Render -------------------------------------------------------------
    function renderCart() {
        const list = document.getElementById('posCartItems');
        list.innerHTML = '';
        const empty = document.getElementById('posCartEmpty');
        const ids = Object.keys(cart);

        empty.classList.toggle('hidden', ids.length > 0);

        ids.forEach(function (id) {
            const it = cart[id];
            const lineTotal = (it.price * it.qty);
            const lineDisc = (it.original - it.price) * it.qty;
            const li = document.createElement('li');
            li.className = 'flex items-center gap-3 py-2 border-b border-gray-50 last:border-0';
            li.innerHTML = ''
                + '<div class="flex-1 min-w-0">'
                + '<p class="text-sm font-semibold text-gray-800 truncate">' + it.name + '</p>'
                + '<p class="text-xs text-gray-400">' + money(it.price)
                + (lineDisc > 0 ? ' <span class="text-green-500 font-semibold">(-' + money(lineDisc) + ')</span>' : '')
                + '</p>'
                + '</div>'
                + '<div class="flex items-center gap-1">'
                + '<button type="button" class="pos-qty w-7 h-7 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-bold" data-act="dec" data-id="' + id + '">−</button>'
                + '<span class="w-7 text-center text-sm font-bold text-gray-800">' + it.qty + '</span>'
                + '<button type="button" class="pos-qty w-7 h-7 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 text-xs font-bold" data-act="inc" data-id="' + id + '">+</button>'
                + '</div>'
                + '<div class="text-right w-20">'
                + '<p class="text-sm font-bold text-gray-900">' + money(lineTotal) + '</p>'
                + '<button type="button" class="pos-remove text-xs text-gray-300 hover:text-red-500 transition-colors" data-id="' + id + '"><i class="fa-solid fa-xmark"></i></button>'
                + '</div>';
            list.appendChild(li);
        });

        const t = cartTotals();
        document.getElementById('posCount').textContent = t.qty;
        document.getElementById('posSubtotal').textContent = money(t.subtotal);
        document.getElementById('posDiscount').textContent = '-' + money(t.discount);
        document.getElementById('posTax').textContent = money(t.tax);
        document.getElementById('posTotal').textContent = money(t.total);
        renderPayments();
    }

    function renderPayments() {
        const total = cartTotals().total;
        const paid = getPaid();
        const label = document.getElementById('posBalanceLabel');
        const balance = document.getElementById('posBalance');
        const wrap = document.getElementById('posBalanceWrap');
        const btn = document.getElementById('posCheckout');

        document.getElementById('posPaid').textContent = money(paid);

        if (paid >= total - 0.005) {
            wrap.classList.remove('hidden');
            label.textContent = 'Vuelto';
            label.classList.remove('text-red-500');
            balance.classList.remove('text-red-500');
            balance.textContent = money(paid - total);
        } else if (Object.keys(cart).length > 0) {
            wrap.classList.remove('hidden');
            label.textContent = 'Faltante';
            label.classList.add('text-red-500');
            balance.classList.add('text-red-500');
            balance.textContent = money(total - paid);
        } else {
            wrap.classList.add('hidden');
        }

        btn.disabled = !(Object.keys(cart).length > 0 && paid >= total - 0.005);
    }

    function getPaid() {
        let sum = 0;
        ['payEfectivo', 'payTarjeta', 'payTransferencia'].forEach(function (id) {
            const el = document.getElementById(id);
            sum += parseFloat(el.value) || 0;
        });
        return sum;
    }

    // --- Eventos catálogo ----------------------------------------------------
    document.getElementById('posGrid').addEventListener('click', function (e) {
        const card = e.target.closest('.pos-card');
        if (card) addToCart(card.getAttribute('data-id'));
    });

    document.getElementById('posSearch').addEventListener('input', applyPosFilters);
    document.getElementById('posCategories').addEventListener('click', function (e) {
        const chip = e.target.closest('.pos-chip');
        if (!chip) return;
        document.querySelectorAll('.pos-chip').forEach(function (c) { c.classList.remove('active'); });
        chip.classList.add('active');
        activeCategory = chip.getAttribute('data-cat');
        applyPosFilters();
    });

    function applyPosFilters() {
        const q = (document.getElementById('posSearch').value || '').trim().toLowerCase();
        let visible = 0;

        document.querySelectorAll('.pos-card').forEach(function (card) {
            const matchCat = !activeCategory || card.getAttribute('data-category') === activeCategory;
            const matchQ = !q || card.getAttribute('data-search').indexOf(q) !== -1;
            const show = matchCat && matchQ;
            card.classList.toggle('hidden', !show);
            if (show) visible++;
        });

        document.getElementById('posEmpty').classList.toggle('hidden', visible > 0);
    }

    /// --- Código de barras ----------------------------------------------------
    window.onBarcodeDetected = function (code) {
        code = String(code || '').trim();
        if (!code) return;

        let found = null;
        document.querySelectorAll('.pos-card').forEach(function (card) {
            if (card.getAttribute('data-barcode') === code) found = card;
        });

        if (!found) {
            Swal.fire({ title: 'No se encontró', text: 'No hay un producto activo con el código "' + code + '".', icon: 'warning', confirmButtonColor: '#f97316', confirmButtonText: 'Entendido' });
            return;
        }
        if (found.classList.contains('opacity-50')) return;

        addToCart(found.getAttribute('data-id'));
        if (cart[found.getAttribute('data-id')]) {
            const name = found.getAttribute('data-name');
            Swal.fire({ title: 'Agregado', text: name + ' añadido al carrito.', icon: 'success', timer: 900, position: 'top-end', showConfirmButton: false, toast: true });
        }
    };

    // --- Eventos carrito -----------------------------------------------------
    document.getElementById('posCartItems').addEventListener('click', function (e) {
        const qtyBtn = e.target.closest('.pos-qty');
        const rm = e.target.closest('.pos-remove');
        if (qtyBtn) {
            const id = qtyBtn.getAttribute('data-id');
            changeQty(id, qtyBtn.getAttribute('data-act') === 'inc' ? 1 : -1);
        } else if (rm) {
            removeItem(rm.getAttribute('data-id'));
        }
    });

    ['payEfectivo', 'payTarjeta', 'payTransferencia'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', renderPayments);
    });

    document.getElementById('posClearCart').addEventListener('click', function () {
        if (Object.keys(cart).length === 0) return;
        Swal.fire({
            title: '¿Limpiar el carrito?',
            text: 'Se quitarán todos los productos agregados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) clearCart();
        });
    });

    document.getElementById('posCancel').addEventListener('click', clearCart);

    // --- Checkout ------------------------------------------------------------
    document.getElementById('posCheckout').addEventListener('click', function () {
        const items = [], payments = [];

        Object.values(cart).forEach(function (it) {
            items.push({ product_id: parseInt(it.id, 10), quantity: it.qty });
        });

        [['payEfectivo', 'efectivo'], ['payTarjeta', 'tarjeta'], ['payTransferencia', 'transferencia']].forEach(function (pair) {
            const amount = parseFloat(document.getElementById(pair[0]).value) || 0;
            if (amount > 0) payments.push({ method: pair[1], amount: amount });
        });

        if (items.length === 0 || payments.length === 0) return;

        document.getElementById('posPayload').value = JSON.stringify({ items: items, payments: payments });
        checkoutForm.submit();
    });
});
</script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>