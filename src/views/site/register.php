<?php require_once __DIR__ . '/partials/header.php'; ?>

<section class="py-16 lg:py-24 bg-gray-50">
    <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white ring-1 ring-gray-200 rounded-3xl p-8 sm:p-10 shadow-xl shadow-gray-200/50">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-500">Únete a la familia</p>
            <h1 class="mt-2 font-display text-3xl font-bold text-gray-900">Crea tu cuenta</h1>
            <p class="mt-2 text-sm text-gray-500 leading-relaxed">
                Guarda tus datos de contacto y recibe nuestras promociones. Disponible próximamente.
            </p>

            <form method="POST" action="<?= url('registro') ?>" class="mt-8 space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nombre completo</label>
                    <input type="text" id="name" name="name" required autocomplete="name"
                           placeholder="Tu nombre completo"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input type="email" id="email" name="email" required autocomplete="email"
                           placeholder="tucorreo@ejemplo.com"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                    <input type="tel" id="phone" name="phone" autocomplete="tel"
                           placeholder="0000-0000"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Dirección (<span class="text-gray-400">opcional</span>)</label>
                    <input type="text" id="address" name="address" autocomplete="street-address"
                           placeholder="Ciudad, dirección"
                           class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password"
                               placeholder="••••••••"
                               class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                    </div>
                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                        <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password"
                               placeholder="••••••••"
                               class="mt-1.5 w-full rounded-xl border border-gray-300 px-4 py-3 text-gray-900 placeholder-gray-400 focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 outline-none transition">
                    </div>
                </div>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 bg-orange-500 text-white font-semibold px-6 py-3.5 rounded-full hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-user-plus"></i> Crear cuenta
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500">
                ¿Ya tienes cuenta?
                <a href="<?= url('ingresar') ?>" class="font-semibold text-orange-500 hover:text-orange-600">Inicia sesión</a>
            </p>

            <p class="mt-8 pt-6 border-t border-gray-100 text-center text-xs text-gray-400">
                <a href="<?= url('/') ?>" class="hover:text-gray-600"><i class="fa-solid fa-arrow-left mr-1"></i> Volver al inicio</a>
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/partials/footer.php'; ?>