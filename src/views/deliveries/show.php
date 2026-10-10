<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$estado = $delivery['state'];
$idx = array_search($estado, Delivery::ESTADOS, true);
$idx = $idx === false ? 0 : $idx;
$proximo = Delivery::proximoEstado($estado);
$nombreCliente = Delivery::nombreCliente($delivery);
$driverId = (int) ($delivery['driver_id'] ?? 0);
$mio = $driverId === (int) ($_SESSION['user']['id'] ?? 0);
$puedeEditar = Permiso::puede('domicilios', 'edit');
$mapsKey = GoogleMaps::key();
$consiguePos = ((float) ($delivery['destination_lat'] ?? 0)) !== 0.0 || ((float) ($delivery['destination_lng'] ?? 0)) !== 0.0;
$pagado = (float) ($delivery['paid_amount'] ?? 0) >= (float) ($delivery['total'] ?? 0);
$nombreDriver = trim(($delivery['driver_name'] ?? '') . ' ' . ($delivery['driver_last_name'] ?? ''));
?>

<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Domicilio del pedido #<?= (int) $delivery['order_id'] ?></h1>
        <p class="mt-1 text-sm text-gray-500">
            Cliente: <span class="font-medium text-gray-700"><?= esc($nombreCliente) ?></span>
            <?php if ($nombreDriver): ?> · Domiciliero: <span class="font-medium text-gray-700"><?= esc($nombreDriver) ?></span><?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= url('deliveries') ?>" class="inline-flex items-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-200 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Volver
        </a>
        <?php if ($puedeEditar && $proximo): ?>
            <form method="POST" action="<?= url('deliveries/avanzar/' . $delivery['id']) ?>" class="inline">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition-colors">
                    <i class="fa-solid fa-forward"></i> Avanzar a «<?= esc(Delivery::estadoTexto($proximo)) ?>»
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Stepper -->
<ol id="estadosStepper" class="mb-6 grid grid-cols-2 lg:grid-cols-4 gap-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
    <?php $hitos = [
        ['e' => 'tomado', 'ts' => $delivery['created_at']],
        ['e' => 'preparando', 'ts' => $delivery['preparando_at']],
        ['e' => 'en_camino', 'ts' => $delivery['en_camino_at']],
        ['e' => 'finalizado', 'ts' => $delivery['finalizado_at']],
    ];
    foreach ($hitos as $i => $h):
        $hecho = $i <= $idx; ?>
        <li class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 <?= $hecho ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-400' ?>">
                <i class="fa-solid <?= Delivery::estadoIcon($h['e']) ?>"></i>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold <?= $hecho ? 'text-gray-900' : 'text-gray-400' ?>"><?= esc(Delivery::estadoTexto($h['e'])) ?></p>
                <p class="text-xs text-gray-400"><?= $h['ts'] ? esc(date('d/m H:i', strtotime($h['ts']))) : ($hecho ? '—' : 'Pendiente') ?></p>
            </div>
        </li>
    <?php endforeach; ?>
</ol>

<!-- Asignación -->
<?php if (!$driverId && $estado !== 'finalizado' && $puedeEditar): ?>
    <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="font-semibold text-amber-900">Este envío todavía no tiene domiciliero</p>
                <p class="mt-0.5 text-sm text-amber-700">Tómalo tú o asígnalo a un compañero.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="<?= url('deliveries/asignar/' . $delivery['id']) ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600 transition-colors">
                        <i class="fa-solid fa-hand"></i> Tomar yo este envío
                    </button>
                </form>
                <form method="POST" action="<?= url('deliveries/asignar/' . $delivery['id']) ?>" class="flex items-center gap-2">
                    <?php
                    $empSearch = [
                        'label' => '',
                        'name' => 'driver_id',
                        'inputId' => 'driverSel',
                        'value' => 0,
                        'required' => false,
                        'placeholder' => 'Asignar a…',
                        'empleados' => $empleados,
                    ];
                    require __DIR__ . '/../partials/employee_search.php';
                    unset($empSearch);
                    ?>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">
                        Asignar
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <!-- Mapa en vivo -->
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white overflow-hidden shadow-lg shadow-gray-200/50">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-map-location-dot mr-2 text-orange-500"></i>Seguimiento en vivo</h2>
            <?php if ($mio && $estado !== 'finalizado'): ?>
                <button id="btnShare"
                        class="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-3.5 py-2 text-xs font-semibold text-white hover:bg-gray-700 transition-colors"
                        data-url="<?= url('deliveries/location/' . $delivery['id']) ?>">
                    <i class="fa-solid fa-location-crosshairs"></i> <span id="btnShareTxt">Compartir mi ubicación</span>
                </button>
            <?php endif; ?>
        </div>
        <div id="mapCanvas" class="h-80 lg:h-[420px] flex items-center justify-center text-gray-400">
            <p class="text-sm px-6 text-center">
                <i class="fa-solid fa-map-location-dot text-2xl mb-2 block opacity-50"></i>
                <?= $mapsKey ? 'Cargando el mapa…' : 'El mapa no está configurado (falta GOOGLE_MAPS_API_KEY).' ?>
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-sm text-gray-500 border-t border-gray-100">
            <span>
                <i class="fa-brands fa-whatsapp mr-1"></i>
                <?= $delivery['phone'] ? '<a href="tel:' . esc($delivery['phone']) . '" class="hover:text-orange-600">' . esc($delivery['phone']) . '</a>' : 'Sin teléfono registrado' ?>
            </span>
            <span id="distanceBox" class="hidden"></span>
        </div>
    </div>

    <!-- Resumen -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
            <h2 class="font-semibold text-gray-900">Resumen</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-gray-500">Dirección</dt>
                    <dd class="font-medium text-gray-900 text-right"><?= esc($delivery['destination_address']) ?></dd>
                </div>
                <div class="flex items-start justify-between gap-3">
                    <dt class="text-gray-500">Notas</dt>
                    <dd class="font-medium text-gray-900 text-right"><?= $delivery['notes'] ? esc($delivery['notes']) : '—' ?></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">Pedido</dt>
                    <dd class="font-medium text-gray-900">#<?= (int) $delivery['order_id'] ?> (<?= esc(Delivery::estadoTexto($estado)) ?>)</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">Total</dt>
                    <dd class="font-bold text-gray-900">$<?= esc(number_format((float) $delivery['total'], 2)) ?></dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-gray-500">Pago</dt>
                    <dd class="<?= $pagado ? 'text-green-600 font-medium' : 'text-amber-600 font-medium' ?>">
                        <?= $pagado ? '<i class="fa-solid fa-circle-check mr-1"></i>Confirmado' : '<i class="fa-solid fa-wallet mr-1"></i>Pendiente' ?>
                    </dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
            <h2 class="font-semibold text-gray-900">Artículos</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <?php foreach ($detalles as $d): ?>
                    <li class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2 last:border-0">
                        <span class="text-gray-700 min-w-0"><span class="font-semibold text-gray-900"><?= (int) $d['quantity'] ?>×</span> <?= esc($d['product_name']) ?></span>
                        <span class="font-medium text-gray-900">$<?= esc(number_format((float) $d['subtotal'], 2)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <?php if (!empty($pagos)): ?>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
                <h2 class="font-semibold text-gray-900">Pagos</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <?php foreach ($pagos as $p): ?>
                        <li class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2 last:border-0">
                            <span class="text-gray-600">
                                <?= esc(ucfirst($p['payment_method'] ?? '')) ?>
                                <?= $p['paypal_order_id'] ? '<span class="text-gray-400 text-xs">(' . esc(substr($p['paypal_order_id'], 0, 12)) . '…)</span>' : '' ?>
                            </span>
                            <span class="font-semibold text-gray-900">$<?= esc(number_format((float) $p['amount'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($mapsKey): ?>
<script>
(function () {
    'use strict';
    var deliveryId = <?= (int) $delivery['id'] ?>;
    var estados = <?= json_encode(Delivery::ESTADOS) ?>;
    var ultima = <?= $ultimaPos ? json_encode(['lat' => (float) $ultimaPos['lat'], 'lng' => (float) $ultimaPos['lng']]) : 'null' ?>;
    var destinoLat = <?= $consiguePos ? json_encode((float) $delivery['destination_lat']) : 'null' ?>;
    var destinoLng = <?= $consiguePos ? json_encode((float) $delivery['destination_lng']) : 'null' ?>;
    var fallback = { lat: 13.794185, lng: -88.896530 };
    var mapa, marcador, directions, ws, watchId = null, enviado = false;

    function posInicial() {
        if (ultima && typeof ultima.lat === 'number') return { lat: ultima.lat, lng: ultima.lng };
        if (destinoLat !== null) return { lat: destinoLat, lng: destinoLng };
        return fallback;
    }

    function marcarEstado(nuevo) {
        var idx = estados.indexOf(nuevo);
        if (idx < 0) return;
        document.querySelectorAll('#estadosStepper li').forEach(function (li, i) {
            var hecho = i <= idx;
            var dot = li.querySelector('.w-10.h-10');
            if (dot) dot.className = 'w-10 h-10 rounded-full flex items-center justify-center shrink-0 ' + (hecho ? 'bg-orange-500 text-white' : 'bg-gray-100 text-gray-400');
            var label = li.querySelector('.text-sm');
            if (label) label.className = 'text-sm font-semibold ' + (hecho ? 'text-gray-900' : 'text-gray-400');
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
            if (directions && destinoLat !== null) {
                directions.route({ origin: pos, destination: { lat: destinoLat, lng: destinoLng }, travelMode: 'DRIVING' }, function (r, s) {
                    var db = document.getElementById('distanceBox');
                    if (s === 'OK' && r.routes[0] && r.routes[0].legs[0]) {
                        db.innerHTML = '<i class="fa-solid fa-route mr-1"></i>Faltan <strong>' + r.routes[0].legs[0].distance.text + '</strong>';
                        db.classList.remove('hidden');
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

    function enviarPosicion(pos) {
        var url = document.getElementById('btnShare') ? document.getElementById('btnShare').dataset.url : null;
        if (!url) return;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lat: pos.coords.latitude, lng: pos.coords.longitude })
        }).then(function (r) { return r.json(); }).then(function (d) {
            enviado = true;
            if (d.ok && d.reported_at) {
                var txt = document.getElementById('btnShareTxt');
                if (txt) txt.textContent = 'Compartiendo… últimos ' + new Date(d.reported_at).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' });
            }
        }).catch(function () {});
    }

    window.initMap = function () {
        mapa = new google.maps.Map(document.getElementById('mapCanvas'), { center: posInicial(), zoom: 14, mapTypeId: 'roadmap' });
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

        var btn = document.getElementById('btnShare');
        if (btn) {
            var activo = false;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!navigator.geolocation) { btn.textContent = 'Geolocalización no disponible'; return; }
                if (!activo) {
                    watchId = navigator.geolocation.watchPosition(
                        function (pos) {
                            enviarPosicion(pos);
                            if (marcador) marcador.setPosition({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                            if (mapa) mapa.panTo({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                        },
                        function (err) {
                            document.getElementById('btnShareTxt').textContent = 'No se pudo obtener ubicación';
                        },
                        { enableHighAccuracy: true, maximumAge: 5000 }
                    );
                    activo = true;
                    document.getElementById('btnShare').classList.remove('bg-gray-900');
                    document.getElementById('btnShare').classList.add('bg-green-600');
                    document.getElementById('btnShareTxt').textContent = 'Compartiendo…';
                } else {
                    navigator.geolocation.clearWatch(watchId);
                    activo = false;
                    document.getElementById('btnShare').classList.add('bg-gray-900');
                    document.getElementById('btnShare').classList.remove('bg-green-600');
                    document.getElementById('btnShareTxt').textContent = 'Compartir mi ubicación';
                }
            });
        }
    };
})();
</script>
<script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?= urlencode($mapsKey) ?>&callback=initMap"
        onerror="document.getElementById('mapCanvas').innerHTML='<p class=&quot;text-sm text-gray-400&quot;>No se pudo cargar el mapa.</p>';"></script>
<?php endif; ?>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>