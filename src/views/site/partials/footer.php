<?php
$businessName = setting('business_name', 'Panadería');
$address = setting('address');
$phone = setting('phone');
?>
<footer class="bg-gray-900 text-gray-300">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14 grid gap-10 md:grid-cols-3">
        <div>
            <p class="text-white font-display font-bold text-lg"><?= esc($businessName) ?></p>
            <p class="mt-3 text-sm text-gray-400 leading-relaxed">
                Elaboramos pan, repostería y bocadillos artesanales con ingredientes naturales y el sabor de siempre.
            </p>
        </div>
        <div>
            <p class="text-white font-semibold">Enlaces</p>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a href="<?= url('/') ?>" class="hover:text-orange-400 transition-colors">Inicio</a></li>
                <li><a href="<?= url('nosotros') ?>" class="hover:text-orange-400 transition-colors">Sobre nosotros</a></li>
                <li><a href="<?= url('catalogo') ?>" class="hover:text-orange-400 transition-colors">Catálogo de productos</a></li>
                <li><a href="<?= url('contacto') ?>" class="hover:text-orange-400 transition-colors">Contáctanos</a></li>
            </ul>
        </div>
        <div>
            <p class="text-white font-semibold">Contacto</p>
            <ul class="mt-3 space-y-2 text-sm">
                <?php if ($phone): ?>
                    <li><i class="fa-solid fa-phone mr-2 text-orange-400"></i><?= esc($phone) ?></li>
                <?php endif; ?>
                <?php if ($address): ?>
                    <li><i class="fa-solid fa-location-dot mr-2 text-orange-400"></i><?= esc($address) ?></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="border-t border-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 text-sm text-gray-500 flex flex-wrap items-center justify-between gap-2">
            <p>&copy; <?= date('Y') ?> <?= esc($businessName) ?>. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js"></script>
<script src="/js/main.js"></script>
<script>
    document.getElementById('siteMenuToggle')?.addEventListener('click', function () {
        document.getElementById('siteMenu')?.classList.toggle('hidden');
    });
</script>
</body>
</html>