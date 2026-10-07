<?php require_once __DIR__ . '/partials/header.php'; ?>

<?php
$cur = $currency ?? setting('currency', '$');
$estado = $delivery['state'] ?? '';
$idxEstado = array_search($estado, Delivery::ESTADOS, true);
$idxEstado = $idxEstado === false ? 0 : $idxEstado;
$clienteNombre = Delivery::nombreCliente($delivery);
$mapsKey = GoogleMaps::key();
$consiguePos = ((float) ($delivery['destination_lat'] ?? 0)) !== 0.0 || ((float) ($delivery['destination_lng'] ?? 0)) !== 0.0;
$driverNombre = trim(($delivery['driver_name'] ?? '') . ' ' . ($delivery['driver_last_name'] ?? ''));
$pagado = (float) ($delivery['paid_amount'] ?? 0) >= (float) ($delivery['total'] ?? 0);
$hitos = [
    ['estado' => 'tomado', 'tiempo' => $delivery['created_at'] ?? null],
    ['estado' => 'preparando', 'tiempo' => $delivery['preparando_at'] ?? null],
    ['estado' => 'en_camino', 'tiempo' => $delivery['en_camino_at'] ?? null],
    ['estado' => 'finalizado', 'tiempo' => $delivery['finalizado_at'] ?? null],
];
$ultimaVista = $ultimaPos ? date('H:i', strtotime($ultimaPos['reported_at'])) : null;
?>

<!-- Breadcrumb -->
<section class="bg-gray-50 border-b border-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-500">
        <a href="<?= url('/') ?>" class="hover:text-orange-600 transition-colors">Inicio</a>
        <span class="mx-2 text-gray-300">/</span>
        <span class="text-gray-700 font-medium">Seguimiento de tu pedido</span>
    </div>
</section>

<section class="py-14 lg:py-20 bg-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-orange-500">Pedido #<?= (int) $delivery['order_id'] ?></p>
                <h1 class="mt-2 font-display text-4xl font-bold text-gray-900"><?= esc($estado === 'finalizado' ? '¡Tu pedido fue entregado!' : 'Tu pedido va en camino') ?></h1>
                <p class="mt-2 text-gray-500">
                    Hola <strong><?= esc($clienteNombre) ?></strong> ·
                    <?= esc(date('d/m/Y', strtotime($delivery['created_at']))) ?>
                    <?php if ($pagado): ?>
                        · <span class="text-green-600 font-medium"><i class="fa-solid fa-circle-check mr-1"></i>Pago confirmado</span>
                    <?php else: ?>
                        · <span class="text-amber-600 font-medium"><i class="fa-solid fa-wallet mr-1"></i>Pago pendiente de confirmar</span>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($driverNombre): ?>
                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-motorcycle"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Tu domiciliero</p>
                        <p class="font-semibold text-gray-900"><?= esc($driverNombre) ?></p>
                        <p class="text-xs text-gray-500" id="driverSeen"><?= $ultimaVista ? 'Última señal ' . $ultimaVista : '—' ?></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="rounded-2xl bg-gray-50 ring-1 ring-gray-200 px-5 py-4 flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">Estado actual</p>
                        <p class="font-semibold text-gray-900"><?= esc(Delivery::estadoTexto($estado)) ?></p>
                        <p class="text-xs text-gray-500">El domiciliero está siendo asignado.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stepper de estados -->
        <ol id="estadosStepper" class="mt-12 grid grid-cols-2 lg:grid-cols-4 gap-y-8">
            <?php foreach ($hitos as $i => $hito):
                $hecho = $i <= $idxEstado; ?>
                <li class="flex items-center gap-3 lg:flex-col lg:gap-2 lg:text-center">
                    <div class="relative z-10">
                        <div class="w-12 h-12 rounded-full flex items-center justify-center <?= $hecho ? 'bg-orange-500 text-white shadow-lg shadow-orange-200' : 'bg-gray-100 text-gray-400' ?>">
                            <i class="fa-solid <?= Delivery::estadoIcon($hito['estado']) ?>"></i>
                        </div>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold <?= $hecho ? 'text-gray-900' : 'text-gray-400' ?>"><?= esc(Delivery::estadoTexto($hito['estado'])) ?></p>
                        <p class="text-xs text-gray-400 <?= $hecho ? '' : 'opacity-0' ?>" id="stepTime-<?= $i ?>">
                            <span class="__ts"><?= $hito['tiempo'] ? esc(date('H:i', strtotime($hito['tiempo']))) : '—' ?></span>
                        </p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="mt-12 grid gap-8 lg:grid-cols-5">
            <!-- Mapa -->
            <div class="lg:col-span-3 rounded-3xl overflow-hidden ring-1 ring-gray-200 bg-gray-100">
                <div id="mapCanvas" class="h-80 lg:h-[480px] flex items-center justify-center text-gray-400">
                    <p class="text-sm px-6 text-center">
                        <i class="fa-solid fa-map-location-dot text-2xl mb-2 block opacity-50"></i>
                        <?= $mapsKey ? 'Cargando el mapa…' : 'El mapa no está disponible en este momento. Tus productos van seguros, igualmente.' ?>
                    </p>
                </div>
            </div>

            <!-- Detalle -->
            <aside class="lg:col-span-2 space-y-6">
                <div class="rounded-3xl bg-white ring-1 ring-gray-200 p-7">
                    <h2 class="font-display text-xl font-bold text-gray-900">Resumen</h2>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-500">Dirección</dt>
                            <dd class="font-medium text-gray-900 text-right"><?= esc($delivery['destination_address']) ?></dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-500">Notas</dt>
                            <dd class="font-medium text-gray-900 text-right"><?= $delivery['notes'] ? esc($delivery['notes']) : '—' ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-gray-500">Envío</dt>
                            <dd class="font-medium text-green-600">Gratis</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-4">
                            <dt class="text-base font-semibold text-gray-900">Total</dt>
                            <dd class="font-display text-2xl font-bold text-gray-900"><?= esc($cur) ?><?= esc(number_format((float) $delivery['total'], 2)) ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4" id="distanceRow" style="display:none">
                            <dt class="text-gray-500">Distancia</dt>
                            <dd class="font-medium text-gray-900 text-right" id="distanceBox">—</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-3xl bg-white ring-1 ring-gray-200 p-7">
                    <h2 class="font-display text-xl font-bold text-gray-900">Tus productos</h2>
                    <ul class="mt-4 divide-y divide-gray-100">
                        <?php foreach ($detalles as $d): ?>
                            <li class="py-3 flex items-center gap-3">
                                <?php if ($d['image_url']): ?>
                                    <img src="<?= esc($d['image_url']) ?>" alt="<?= esc($d['product_name']) ?>" class="w-12 h-12 rounded-xl object-cover ring-1 ring-gray-200">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center text-gray-300"><i class="fa-solid fa-cookie-bite"></i></div>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-gray-900 truncate"><?= esc($d['product_name']) ?></p>
                                    <p class="text-xs text-gray-400"><?= (int) $d['quantity'] ?> × <?= esc($cur) ?><?= esc(number_format((float) $d['unit_price'], 2)) ?></p>
                                </div>
                                <span class="font-semibold text-gray-900"><?= esc($cur) ?><?= esc(number_format((float) $d['subtotal'], 2)) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if ($mapsKey): ?>
<script>
(function () {
    'use strict';
    var deliveryId = <?= (int) $delivery['id'] ?>;
    var estados = <?= json_encode(Delivery::ESTADOS) ?>;
    var ultima = <?= $ultimaPos ? json_encode(['lat' => (float) $ultimaPos['lat'], 'lng' => (float) $ultimaPos['lng']]) : 'null' ?>;
    var destinoLat = <?= $consiguePos ? json_encode((float) $delivery['destination_lat']) : 'null' ?>;
    var destinoLng = <?= $consiguePos ? json_encode((float) $delivery['destination_lng']) : 'null' ?>;
    var fallback = { lat: 13.794185, lng: -88.896530 }; // San Salvador

    var mapa, marcador, directions, ws;

    function setTerceraHora() {
        document.getElementById('driverSeen') && (document.getElementById('driverSeen').textContent = 'Última señal ' + new Date().toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' }));
    }

    function marcarEstado(nuevo) {
        var idx = estados.indexOf(nuevo);
        if (idx < 0) return;
        document.querySelectorAll('#estadosStepper li').forEach(function (li, i) {
            var hecho = i <= idx;
            li.classList.toggle('text-current', hecho);
            var dot = li.querySelector('.w-12.h-12');
            if (dot) {
                dot.className = 'w-12 h-12 rounded-full flex items-center justify-center ' + (hecho ? 'bg-orange-500 text-white shadow-lg shadow-orange-200' : 'bg-gray-100 text-gray-400');
            }
            var label = li.querySelector('.text-sm');
            if (label) {
                var b = hecho ? 'text-gray-900' : 'text-gray-400';
                label.className = 'text-sm font-semibold ' + b;
            }
            var time = li.querySelector('.text-xs');
            if (time) time.classList.toggle('opacity-0', !hecho);
        });
    }

    function onWs(e) {
        var msg;
        try { msg = JSON.parse(e.data); } catch (err) { return; }
        if (!msg) return;
        if (msg.type === 'location' && typeof msg.lat === 'number' && typeof msg.lng === 'number') {
            var pos = { lat: msg.lat, lng: msg.lng };
            if (marcador) marcador.setPosition(pos);
            if (mapa) mapa.panTo(pos);
            if (msg.at) setTerceraHora();
            // Recalcular distancia con DirectionsService.
            if (directions && destinoLat !== null) {
                directions.route({ origin: pos, destination: { lat: destinoLat, lng: destinoLng }, travelMode: 'DRIVING' },
                    function (r, s) {
                        if (s === 'OK' && r.routes[0] && r.routes[0].legs[0]) {
                            document.getElementById('distanceBox').textContent = r.routes[0].legs[0].distance.text;
                        }
                    });
            }
        }
        if (msg.type === 'event' && msg.estado) marcarEstado(msg.estado);
    }

    function conectar() {
        try {
            var proto = location.protocol === 'https:' ? 'wss:' : 'ws:';
            ws = new WebSocket(proto + '//' + location.host + '/ws?delivery=' + deliveryId);
            ws.onmessage = onWs;
            ws.onclose = function () { setTimeout(conectar, 5000); };
            ws.onerror = function () { try { ws.close(); } catch (e) {} };
        } catch (e) { setTimeout(conectar, 5000); }
    }

    function posInicial() {
        if (ultima && typeof ultima.lat === 'number') return { lat: ultima.lat, lng: ultima.lng };
        if (destinoLat !== null) return { lat: destinoLat, lng: destinoLng };
        return fallback;
    }

    window.initMap = function () {
        mapa = new google.maps.Map(document.getElementById('mapCanvas'), {
            center: posInicial(), zoom: 14,
            mapTypeId: 'roadmap'
        });
        directions = new google.maps.DirectionsService();

        if (destinoLat !== null) {
            new google.maps.Marker({
                position: { lat: destinoLat, lng: destinoLng }, map: mapa,
                icon: { path: google.maps.SymbolPath.CIRCLE, scale: 9, fillColor: '#f97316', fillOpacity: 1, strokeColor: '#ffffff', strokeWeight: 3 },
                title: 'Destino'
            });
        }

        marcador = new google.maps.Marker({
            position: posInicial(), map: mapa,
            icon: { url: 'https://maps.google.com/mapfiles/ms/icons/motorcycle.png', scaledSize: new google.maps.Size(36, 42) },
            title: 'Domiciliero'
        });

        conectar();
    };
})();
</script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($mapsKey) ?>&callback=initMap"
        onerror="document.getElementById('mapCanvas').innerHTML='<p class=&quot;text-sm text-gray-400&quot;>No se pudo cargar el mapa.</p>';"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>