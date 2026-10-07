<?php // Pantalla de bloqueo del quiosco. Pagina suelta: sin sidebar ni breadcrumbs. ?>
<?php $error = $_SESSION['kiosco_error'] ?? null; ?>
<?php unset($_SESSION['kiosco_error']); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Quiosco de asistencia') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php require __DIR__ . '/../partials/theme.php'; ?>
</head>
<body class="min-h-screen bg-gradient-to-br from-orange-50 to-orange-100 flex items-center justify-center p-6">

    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-orange-500 text-white text-2xl shadow-lg shadow-orange-500/30">
                <i class="fa-solid fa-fingerprint"></i>
            </div>
            <h1 class="mt-4 text-2xl font-bold text-gray-800">Quiosco de asistencia</h1>
            <p class="mt-1 text-sm text-gray-500">Marcá tu entrada, descanso o salida</p>
        </div>

        <div class="rounded-2xl bg-white shadow-lg shadow-gray-200/50 p-8 ring-1 ring-black/5">
            <?php if ($enfriamiento > 0): ?>
                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4 text-center">
                    <i class="fa-solid fa-lock text-red-500 text-xl"></i>
                    <p class="mt-2 text-sm font-semibold text-red-700">
                        Quiosco bloqueado por intentos fallidos
                    </p>
                    <p class="mt-1 text-xs text-red-600">
                        Probá de nuevo en <?= (int) $enfriamiento ?> minuto<?= $enfriamiento == 1 ? '' : 's' ?>.
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4">
                    <p class="text-sm font-semibold text-red-700">
                        <i class="fa-solid fa-circle-exclamation mr-1"></i><?= esc($error) ?>
                    </p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= url('kiosco/unlock') ?>" class="space-y-5">
                <div>
                    <label for="clave" class="form-label">Clave del local</label>
                    <input type="password" name="clave" id="clave" required autofocus
                           autocomplete="off"
                           class="form-input text-center text-lg tracking-widest"
                           placeholder="••••••">
                    <p class="mt-2 text-xs text-gray-400">
                        La pedís al encargado. Se cambia en Configuración → Clave del quiosco.
                    </p>
                </div>

                <button type="submit"
                        class="w-full bg-orange-500 hover:bg-orange-600 text-white py-3 text-base font-semibold rounded-xl transition shadow-lg shadow-orange-500/30">
                    <i class="fa-solid fa-unlock mr-2"></i>Abrir quiosco
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-gray-400">
            <?= esc(setting('business_name', 'Panadería')) ?>
        </p>
    </div>

</body>
</html>