<?php
$moduloLabel = 'este módulo';
$modulo = $GLOBALS['__route_module'] ?? null;
if ($modulo !== null && isset(Permiso::modulos()[$modulo]['label'])) {
    $moduloLabel = Permiso::modulos()[$modulo]['label'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso denegado | <?= esc(setting('business_name', 'Panadería')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php require __DIR__ . '/../partials/theme.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen flex items-center justify-center px-4">
    <div class="max-w-md w-full text-center">
        <div class="rounded-3xl bg-white border border-gray-200 shadow-xl shadow-gray-200/60 px-8 py-10">
            <div class="w-16 h-16 rounded-2xl bg-red-50 text-red-500 flex items-center justify-center mx-auto mb-5 ring-1 ring-red-100">
                <i class="fa-solid fa-lock text-2xl"></i>
            </div>
            <p class="text-xs font-bold tracking-widest text-red-500 uppercase mb-2">Error 403</p>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Acceso denegado</h1>
            <p class="text-sm text-gray-500 leading-relaxed mb-6">
                No tienes permisos para acceder a <span class="font-semibold text-gray-700"><?= esc($moduloLabel) ?></span>.
                Si crees que es un error, pide a un administrador que revise tu rol o tus permisos.
            </p>
            <div class="flex items-center justify-center gap-3">
                <a href="<?= url('dashboard') ?>" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-all">
                    <i class="fa-solid fa-house"></i> Ir al Dashboard
                </a>
                <a href="<?= url('auth/logout') ?>" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
                    Cerrar sesión
                </a>
            </div>
        </div>
    </div>
</body>
</html>