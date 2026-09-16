<?php $businessName = setting('business_name', 'Panadería'); ?>
<?php $systemLogo = setting('system_logo'); ?>
<?php $loginPhoto = setting('login_photo', 'https://images.unsplash.com/photo-1509440159596-0249088772ff?auto=format&fit=crop&w=1200&q=80'); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($businessName) ?> · Iniciar sesión</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<?php $flashSuccess = flash('success'); ?>
<?php $flashError = flash('error'); ?>
<?php $flashMessage = $flashSuccess ?? $flashError; ?>
<?php if ($flashMessage): ?>
    <div id="flashToast" class="hidden"
         data-type="<?= $flashSuccess ? 'success' : 'error' ?>"
         data-message="<?= esc($flashMessage) ?>"></div>
<?php endif; ?>

<div class="min-h-screen flex flex-col lg:flex-row">

    <!-- Imagen (mitad izquierda) -->
    <div class="relative lg:w-1/2 lg:min-h-screen h-56 lg:h-auto overflow-hidden">
        <img src="<?= esc($loginPhoto) ?>"
             alt="Panadería artesanal"
             class="absolute inset-0 w-full h-full object-cover">
    </div>

    <!-- Login (mitad derecha) -->
    <div class="w-full lg:w-1/2 flex items-center justify-center flex-1 p-6 lg:p-10">
        <div class="w-full max-w-sm">
            <div class="rounded-2xl bg-white p-8 shadow-lg shadow-gray-200/50 ring-1 ring-gray-100">
                <div class="flex flex-col items-center text-center mb-7">
                    <?php if ($systemLogo): ?>
                        <img src="<?= esc($systemLogo) ?>" alt="Logo" class="w-28 h-28 object-contain mb-3">
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-2xl bg-orange-500 flex items-center justify-center text-white text-2xl shadow-lg shadow-orange-500/25 mb-3">
                            <i class="fa-solid fa-bread-slice"></i>
                        </div>
                    <?php endif; ?>
                    <h1 class="text-xl font-bold text-gray-900"><?= esc($businessName) ?></h1>
                    <p class="text-sm text-gray-500 mt-1">Inicia sesión en tu cuenta</p>
                </div>

                <form action="<?= url('auth/login') ?>" method="POST" class="space-y-5">
                    <div>
                        <label for="email" class="form-label">Correo electrónico</label>
                        <div class="relative">
                            <i class="fa-solid fa-envelope absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="email" id="email" name="email" class="form-input pl-10" placeholder="tucorreo@ejemplo.com" required autofocus>
                        </div>
                    </div>

                    <div>
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="relative">
                            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="password" id="password" name="password" class="form-input pl-10" placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="text-right">
                        <a href="#" class="text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:shadow-orange-500/40 hover:-translate-y-px transition-all">
                        <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 mt-6">
                © <?= date('Y') ?> <?= esc($businessName) ?> · Sistema de gestión para panaderías
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.js"></script>
<script src="/js/main.js"></script>
</body>
</html>