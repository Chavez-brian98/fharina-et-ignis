<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title . ' | ' . setting('business_name', 'Panadería') : setting('business_name', 'Panadería') ?></title>

    <script src="https://cdn.tailwindcss.com"></script>
    <?php require __DIR__ . '/../partials/theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js@1.12.0/src/toastify.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 antialiased">

<div class="flex min-h-screen">

    <!-- ======================= SIDEBAR (colapsable) ======================= -->
    <aside id="sidebar" class="sidebar bg-white border-r border-gray-200 flex flex-col">
        <div class="relative flex items-center justify-center px-4 py-5 border-b border-gray-100">
            <div class="brand-row flex flex-col items-center gap-2.5 max-w-full">
                <div class="brand-box relative w-14 h-14 rounded-2xl flex items-center justify-center overflow-hidden shrink-0 <?= setting('system_logo') ? '' : 'bg-orange-500 text-white shadow-md shadow-orange-200' ?>">
                    <?php if (setting('system_logo')): ?>
                        <img src="<?= esc(setting('system_logo')) ?>" alt="Logo" class="brand-icon w-full h-full object-contain">
                    <?php else: ?>
                        <i class="fa-solid fa-bread-slice text-xl brand-icon"></i>
                    <?php endif; ?>
                    <i class="fa-solid fa-bars-staggered brand-expand text-orange-500 text-lg" aria-hidden="true"></i>
                </div>
            </div>
            <button type="button" id="sidebarToggle" class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg flex items-center justify-center text-gray-400 hover:bg-orange-50 hover:text-orange-500 transition-colors" title="Colapsar menú">
                <i class="fa-solid fa-bars-staggered"></i>
            </button>
        </div>

        <?php
        // El menú se arma desde el catálogo de módulos (src/config/permisos.php):
        // solo se muestra lo que el usuario en sesión puede ver.
        $navGroups = Permiso::modulosPorGrupo();
        $navOrder = ['Punto de Venta', 'Sistema', 'Catálogo', 'Operaciones', 'Personal', 'Reportes', 'Configuración'];
        ?>
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-5">

            <?php foreach ($navOrder as $groupName):
                $groupItems = [];
                foreach ($navGroups[$groupName] ?? [] as $key => $modulo) {
                    // 'nav' => false = existe como módulo (gate y matriz) pero
                    // no se muestra en el menú (Mi Perfil vive en el user-row,
                    // el quiosco tiene su propia pantalla).
                    if (isset($modulo['nav']) && $modulo['nav'] === false) {
                        continue;
                    }
                    if (puede($key)) {
                        $groupItems[$key] = $modulo;
                    }
                }
                if (empty($groupItems)) {
                    continue;
                }
            ?>
            <div>
                <p class="px-2 mb-1 text-[11px] font-bold tracking-widest text-gray-400 uppercase sidebar-section-title"><?= esc($groupName) ?></p>
                <?php foreach ($groupItems as $key => $modulo):
                    $isActive = ($currentModule ?? '') === $key;
                    $destino = $modulo['url'] ?? ($modulo['placeholder'] ?? '#');
                    $classes = 'flex items-center gap-3 px-2 py-2 rounded-lg text-sm font-medium sidebar-link'
                        . ($isActive ? ' nav-active' : '')
                        . (empty($modulo['url']) ? ' sidebar-anchor opacity-60' : '');
                ?>
                <a href="<?= $modulo['url'] ? url($modulo['url']) : esc($destino) ?>" title="<?= esc($modulo['label']) ?>" class="<?= $classes ?>">
                    <i class="fa-solid <?= esc($modulo['icon']) ?> w-4 text-center shrink-0"></i><span class="sidebar-text"><?= esc($modulo['nav'] ?? $modulo['label']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

        </nav>

        <?php $currentUser = $_SESSION['user'] ?? null; ?>
        <div class="px-4 py-4 border-t border-gray-100 flex items-center gap-2 user-row">
            <a href="<?= url('profile') ?>" title="Mi perfil" class="flex items-center gap-3 min-w-0 flex-1 rounded-xl px-1.5 py-1 -mx-1 hover:bg-orange-50 transition-colors">
                <?php if (!empty($currentUser['profile_photo'])): ?>
                    <img src="<?= esc($currentUser['profile_photo']) ?>" alt="Foto de perfil" class="w-9 h-9 rounded-full object-cover shrink-0">
                <?php else: ?>
                    <div class="w-9 h-9 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold shrink-0"><?= esc(strtoupper(substr($currentUser['name'] ?? 'A', 0, 1))) ?></div>
                <?php endif; ?>
                <div class="sidebar-text flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate"><?= esc($currentUser['name'] ?? 'Administrador') ?></p>
                    <p class="text-xs text-gray-400 truncate"><?= esc($currentUser['email'] ?? 'admin@bakery.com') ?></p>
                </div>
            </a>
            <a href="<?= url('auth/logout') ?>" title="Cerrar sesión" class="btn-action sidebar-text">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </aside>

    <!-- Fondo oscuro para el drawer en móvil/tablet -->
    <div id="sidebarBackdrop" class="sidebar-backdrop" aria-hidden="true"></div>

    <!-- ======================= CONTENIDO ======================= -->
    <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 min-w-0 w-full">
        <!-- Botón flotante (móvil/tablet): abre el sidebar -->
        <button type="button" id="sidebarOpenBtn"
                class="lg:hidden fixed top-3 right-3 z-50 w-10 h-10 rounded-xl bg-white border border-gray-200 shadow-lg shadow-gray-200/60 flex items-center justify-center text-orange-500 hover:bg-orange-50 transition-colors"
                title="Abrir menú" aria-label="Abrir menú">
            <i class="fa-solid fa-bars"></i>
        </button>

        <?php
        // Campana de notificaciones (visible para cualquier empleado logueado).
        $notifNoLeidas = 0;
        $notifUltimas = [];
        $notifUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($notifUserId > 0 && isset($GLOBALS['__db'])) {
            try {
                $notifModel = new Notificacion($GLOBALS['__db']);
                $notifNoLeidas = (int) $notifModel->noLeidas($notifUserId);
                $notifUltimas = $notifModel->para($notifUserId, 5);
            } catch (\Throwable $notifEx) {
                $notifNoLeidas = 0;
                $notifUltimas = [];
            }
        }

        $notifIcons = [
            'pedido_nuevo'     => ['fa-bag-shopping', 'text-blue-500'],
            'pedido_estado'    => ['fa-right-left', 'text-indigo-500'],
            'pedido_rechazado' => ['fa-circle-xmark', 'text-red-500'],
            'pedido_cancelado' => ['fa-xmark', 'text-gray-400'],
            'pedido_listo'     => ['fa-circle-check', 'text-green-500'],
            'stock_bajo'       => ['fa-box-open', 'text-amber-500'],
        ];
        ?>
        <script>window.__notifUserId = <?= $notifUserId ?>;</script>

        <div id="notificationsBell" class="fixed top-3 right-16 lg:right-4 z-50">
            <button type="button" id="notifToggle"
                    class="relative w-10 h-10 rounded-xl bg-white border border-gray-200 shadow-lg shadow-gray-200/60 flex items-center justify-center text-gray-500 hover:text-blue-600 transition-colors"
                    title="Notificaciones" aria-label="Notificaciones">
                <i class="fa-regular fa-bell"></i>
                <span id="notifBadge" class="absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold items-center justify-center <?= $notifNoLeidas > 0 ? 'flex' : 'hidden' ?>">
                    <?= min($notifNoLeidas, 99) ?>
                </span>
            </button>

            <div id="notifPanel" class="hidden absolute right-0 mt-2 w-80 sm:w-96 bg-white border border-gray-200 rounded-2xl shadow-2xl shadow-gray-300/40 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-900">Notificaciones</p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="notifMarkAll"
                                class="text-xs font-medium text-blue-600 hover:text-blue-700 <?= $notifNoLeidas > 0 ? '' : 'opacity-40 pointer-events-none' ?>">
                            Marcar leídas
                        </button>
                        <a href="<?= url('notifications') ?>" class="text-xs font-medium text-gray-400 hover:text-gray-600">
                            Ver todas
                        </a>
                    </div>
                </div>
                <div id="notifList" data-global="<?= esc(url('notifications/leer')) ?>">
                    <?php if ($notifUltimas): ?>
                        <?php foreach ($notifUltimas as $notif): ?>
                            <?php $notifMeta = $notifIcons[$notif['notification_type']] ?? ['fa-bell', 'text-gray-400']; ?>
                            <?php $notifEnlace = Notificacion::enlace($notif['reference_type'], (int) $notif['reference_id']); ?>
                            <a href="<?= $notifEnlace ? url($notifEnlace) : 'javascript:void(0)' ?>"
                               data-id="<?= (int) $notif['id'] ?>"
                               class="notif-item flex items-start gap-3 px-4 py-3 border-b border-gray-50 hover:bg-blue-50/50 transition-colors <?= $notif['readed'] ? '' : 'bg-blue-50/40' ?>">
                                <i class="fa-solid <?= esc($notifMeta[0]) ?> <?= esc($notifMeta[1]) ?> text-base mt-0.5"></i>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-gray-800 leading-snug">
                                        <?= esc($notif['message'] !== null && $notif['message'] !== '' ? $notif['message'] : $notif['title']) ?>
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5"><?= esc(date('d/m H:i', strtotime($notif['creation_date']))) ?></p>
                                </div>
                                <?php if (!$notif['readed']): ?>
                                    <span class="shrink-0 w-2 h-2 rounded-full bg-blue-500 mt-1.5"></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p id="notifEmpty" class="text-center text-xs text-gray-400 px-4 py-8">No hay notificaciones.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function () {
            const bell = document.getElementById('notificationsBell');

            if (!bell || !window.__notifUserId) {
                return;
            }

            const toggle = document.getElementById('notifToggle');
            const panel = document.getElementById('notifPanel');
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifList');

            window.notifRefrescar = function (datos) {
                const noLeidas = (datos && datos.no_leidas) ? parseInt(datos.no_leidas, 10) : 0;

                if (noLeidas > 0) {
                    badge.textContent = Math.min(noLeidas, 99);
                    badge.classList.remove('hidden');
                    badge.classList.add('flex');
                } else {
                    badge.classList.add('hidden');
                    badge.classList.remove('flex');
                }

                const marcarTodo = document.getElementById('notifMarkAll');
                if (marcarTodo) {
                    marcarTodo.classList.toggle('opacity-40', noLeidas === 0);
                    marcarTodo.classList.toggle('pointer-events-none', noLeidas === 0);
                }
            };

            window.notifEnviar = function (url, datos) {
                const body = new URLSearchParams(datos || {});

                return fetch(url, { method: 'POST', body: body, headers: { 'X-Requested-With': 'fetch' } })
                    .then((r) => r.json())
                    .then((json) => window.notifRefrescar(json))
                    .catch(() => null);
            };

            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                panel.classList.toggle('hidden');
            });

            document.addEventListener('click', function (e) {
                if (!bell.contains(e.target)) {
                    panel.classList.add('hidden');
                }
            });

            list.addEventListener('click', function (e) {
                const item = e.target.closest('.notif-item');

                if (!item) {
                    return;
                }

                const id = item.getAttribute('data-id');
                window.notifEnviar(list.getAttribute('data-global') + '/' + id, {});

                const href = item.getAttribute('href');
                if (!href || href === 'javascript:void(0)') {
                    e.preventDefault();
                }
            });

            document.getElementById('notifMarkAll').addEventListener('click', function () {
                window.notifEnviar(list.getAttribute('data-global'), {});
            });

            setInterval(function () {
                fetch('<?= esc(url('notifications/unread')) ?>', { headers: { 'X-Requested-With': 'fetch' } })
                    .then((r) => r.json())
                    .then((json) => window.notifRefrescar(json))
                    .catch(() => null);
            }, 45000);
        })();
        </script>

        <?php if (file_exists(__DIR__ . '/breadcrumb.php')) require __DIR__ . '/breadcrumb.php'; ?>