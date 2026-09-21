<?php require_once __DIR__ . '/partials/header.php'; ?>

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-md mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white ring-1 ring-gray-200 rounded-3xl p-8 sm:p-10 shadow-xl shadow-gray-200/50">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-500">Bienvenido de nuevo</p>
            <h1 class="mt-2 font-display text-3xl font-bold text-gray-900">Inicia sesión</h1>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">
                Accede para agilizar tus compras y consultar tus pedidos. Disponible próximamente.
            </p>

            <form method="POST" action="<?= url('ingresar') ?>" class="mt-8 space-y-5">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="tucorreo@ejemplo.com"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <a href="#" onclick="return false;" title="Próximamente" class="text-sm text-orange-500 hover:text-orange-600">¿Olvidaste tu contraseña?</a>
                    </div>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 bg-orange-500 text-white font-semibold px-6 py-3.5 rounded-full hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                ¿No tienes cuenta?
                <a href="<?= url('registro') ?>" class="font-semibold text-orange-500 hover:text-orange-600">Crea una gratis</a>
            </p>

            <p class="mt-8 pt-6 border-t border-gray-100 text-center text-xs text-gray-400">
                <a href="<?= url('/') ?>" class="hover:text-gray-600"><i class="fa-solid fa-arrow-left mr-1"></i> Volver al inicio</a>
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>