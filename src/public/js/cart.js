/**
 * Carrito del portal web (localStorage). El servidor NUNCA confía en él:
 * el checkout recomputa precios y totales desde la base de datos.
 *
 * API global: window.SiteCart.{get,items,add,setQty,remove,count,total,clear}
 * Botones:  [data-add-to-cart][data-id][data-name][data-price]
 * Badge:     [data-cart-badge]   (se actualiza solo)
 */
(function () {
    'use strict';

    var KEY = 'fharina_cart';

    function read() {
        try { return JSON.parse(localStorage.getItem(KEY)) || {}; }
        catch (e) { return {}; }
    }
    function write(cart) {
        localStorage.setItem(KEY, JSON.stringify(cart));
        updateBadge();
    }

    function updateBadge() {
        var n = count();
        document.querySelectorAll('[data-cart-badge]').forEach(function (el) {
            el.textContent = n;
            el.classList.toggle('hidden', n === 0);
        });
    }

    function count() {
        return Object.values(read()).reduce(function (a, o) { return a + (o.qty || 0); }, 0);
    }

    function total() {
        return Object.values(read()).reduce(function (a, o) { return a + (o.qty || 0) * Number(o.price || 0); }, 0);
    }

    var SiteCart = {
        get: function () { return read(); },
        items: function () {
            return Object.entries(read()).map(function (e) {
                return { id: Number(e[0]), name: e[1].name, price: Number(e[1].price), qty: e[1].qty };
            });
        },
        add: function (id, name, price, qty) {
            var cart = read();
            var k = String(id);
            cart[k] = cart[k] || { name: name, price: price, qty: 0 };
            cart[k].qty = Math.min(99, cart[k].qty + (qty || 1));
            write(cart);
            if (window.Toastify) {
                Toastify({ text: '\u2714 ' + cart[k].name + ' agregado al carrito', duration: 1600, gravity: 'bottom', position: 'right', className: 'cart-toast', style: { background: '#111827' } }).showToast();
            }
        },
        setQty: function (id, qty) {
            var cart = read();
            var k = String(id);
            if (!cart[k]) return;
            cart[k].qty = Math.max(1, Math.min(99, qty));
            write(cart);
        },
        remove: function (id) {
            var cart = read();
            delete cart[String(id)];
            write(cart);
        },
        count: count,
        total: total,
        clear: function () {
            localStorage.removeItem(KEY);
            updateBadge();
        },
    };

    window.SiteCart = SiteCart;

    document.addEventListener('DOMContentLoaded', function () {
        updateBadge();

        document.querySelectorAll('[data-add-to-cart]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var block = this.closest('[data-product-block]');
                var input = block ? block.querySelector('input[name=qty]') : null;
                var qty = input ? (parseInt(input.value, 10) || 1) : 1;
                SiteCart.add(this.dataset.id, this.dataset.name, this.dataset.price, qty);
            });
        });

        // Stepper de cantidad de la página de producto (.btn-qty).
        document.querySelectorAll('[data-product-block] .btn-qty').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = this.closest('[data-product-block]').querySelector('input[name=qty]');
                if (!input) return;
                var qty = Math.max(1, Math.min(99, (parseInt(input.value, 10) || 1) + Number(this.dataset.qty || 0)));
                input.value = qty;
            });
        });
    });
})();