<?php
$businessName = setting('business_name', 'Panadería');
$systemLogo = setting('system_logo');
$navItems = [
    ['page' => 'home', 'label' => 'Inicio', 'url' => url('/')],
    ['page' => 'about', 'label' => 'Sobre nosotros', 'url' => url('nosotros')],
    ['page' => 'catalog', 'label' => 'Catálogo', 'url' => url('catalogo')],
    ['page' => 'contact', 'label' => 'Contáctanos', 'url' => url('contacto')],
];
$sitePage = $sitePage ?? 'home';
$flashSuccess = flash('success');
$flashError = flash('error');
$flashMessage = $flashSuccess ?? $flashError;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Inicio') ?> | <?= esc($businessName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=DM+Sans:opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"DM Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        display: ['"Playfair Display"', 'Georgia', 'serif'],
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css">
</head>
<body class="bg-white text-gray-800 antialiased font-sans">

<?php if ($flashMessage): ?>
<div id="flashToast" class="hidden" data-type="<?= $flashSuccess ? 'success' : 'error' ?>" data-message="<?= esc($flashMessage) ?>"></div>
<?php endif; ?>

<header class="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-[1fr_auto_1fr] items-center h-20">
            <!-- Marca -->
            <a href="<?= url('/') ?>" class="justify-self-start flex items-center gap-3 shrink-0">
                <?php if ($systemLogo): ?>
                    <img src="<?= esc($systemLogo) ?>" alt="Logo <?= esc($businessName) ?>" class="w-14 h-14 object-contain">
                <?php else: ?>
                    <div class="w-14 h-14 rounded-2xl border border-gray-200 text-gray-800 flex items-center justify-center text-2xl">
                        <i class="fa-solid fa-bread-slice"></i>
                    </div>
                <?php endif; ?>
                <span class="text-xl font-display font-bold tracking-tight text-gray-900"><?= esc($businessName) ?></span>
            </a>

            <!-- Navegación central -->
            <nav class="hidden md:flex items-center justify-center gap-1 text-sm font-medium">
                <?php foreach ($navItems as $item): ?>
                    <a href="<?= esc($item['url']) ?>"
                       class="relative px-4 py-2 rounded-lg transition-colors <?= $sitePage === $item['page'] ? 'text-orange-600 font-semibold' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-50' ?>">
                        <?= esc($item['label']) ?>
                        <?php if ($sitePage === $item['page']): ?>
                            <span class="absolute left-1/2 -translate-x-1/2 bottom-0.5 h-0.5 w-5 rounded-full bg-orange-500"></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Acciones derecha -->
            <div class="justify-self-end flex items-center gap-2">
                <a href="<?= url('carrito') ?>" title="Carrito de compras"
                   class="relative w-10 h-10 rounded-lg border border-gray-200 text-gray-700 hover:border-orange-300 hover:text-orange-600 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-cart-shopping"></i>
                </a>
                <a href="<?= url('ingresar') ?>"
                   class="hidden md:inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors">
                    Iniciar sesión
                </a>
                <a href="<?= url('registro') ?>"
                   class="hidden md:inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold bg-gray-900 text-white hover:bg-gray-700 transition-colors">
                    Registrarse
                </a>
                <button id="siteMenuToggle" class="md:hidden w-10 h-10 rounded-lg border border-gray-200 text-gray-700 flex items-center justify-center" aria-label="Abrir menú">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </div>
    </div>

    <nav id="siteMenu" class="hidden md:hidden bg-white border-t border-gray-100 px-4 py-3 space-y-1">
        <?php foreach ($navItems as $item): ?>
            <a href="<?= esc($item['url']) ?>"
               class="block px-4 py-2.5 rounded-lg font-medium <?= $sitePage === $item['page'] ? 'bg-gray-50 text-orange-600 font-semibold' : 'text-gray-700 hover:bg-gray-50' ?>">
                <?= esc($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>