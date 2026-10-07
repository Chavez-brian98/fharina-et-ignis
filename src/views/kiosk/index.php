<?php // Quiosco desbloqueado: camara, lector QR y estado del dia. Sin navbar. ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($titulo ?? 'Quiosco de asistencia') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core@4.22.0/dist/tf-core.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter@4.22.0/dist/tf-converter.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl@4.22.0/dist/tf-backend-webgl.min.js"></script>
    <script src="<?= esc(EmpleadoFace::LIB_URI) ?>"></script>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <?php require __DIR__ . '/../partials/theme.php'; ?>
</head>
<body class="h-screen w-screen overflow-hidden bg-gradient-to-br from-orange-50 to-orange-100 flex flex-col p-3 lg:p-4 select-none">

    <header class="shrink-0 flex items-center justify-between gap-3 mb-3">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-white shadow-sm ring-1 ring-black/5 flex items-center justify-center text-orange-500">
                <i class="fa-solid fa-clock text-xl"></i>
            </div>
            <div>
                <h1 class="text-base font-bold text-gray-800 leading-tight">Quiosco de asistencia</h1>
                <p class="text-xs text-gray-500">QR o rostro: las horas se registran al servidor.</p>
            </div>
        </div>
        <form method="post" action="<?= url('kiosco/lock') ?>">
            <input type="hidden" name="clave" value="<?= esc(setting('kiosk_key', '')) ?>">
            <button type="submit"
                    class="px-3 py-2 rounded-xl bg-white/80 backdrop-blur border border-white/60 shadow-sm hover:bg-white text-sm font-semibold text-gray-700 transition">
                <i class="fa-solid fa-lock mr-1"></i>Bloquear
            </button>
        </form>
    </header>

    <main class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-3 gap-3">

        <!-- ============================ Cámara ============================ -->
        <section class="lg:col-span-2 min-h-0 rounded-2xl bg-white shadow-lg shadow-gray-200/50 ring-1 ring-black/5 overflow-hidden flex flex-col">
            <div class="shrink-0 px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-800">
                    <i class="fa-solid fa-video mr-1.5 text-orange-500"></i>Cámara
                </h2>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <span id="estadoRostro" class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-200">Cargando…</span>
                    <button id="btnInvertirCamara" type="button"
                            class="px-2 py-1 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 transition hidden">
                        <i class="fa-solid fa-rotate mr-1"></i>Cambiar cámara
                    </button>
                </div>
            </div>

            <div class="relative flex-1 min-h-0 bg-gray-950">
                <video id="video" autoplay playsinline muted
                       class="absolute inset-0 w-full h-full object-cover"></video>
                <canvas id="overlay" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>
                <!-- El lector QR comparte el area de la camara con el detector de rostro -->
                <div id="qrReader" class="absolute inset-0 hidden overflow-hidden bg-black"></div>
                <div id="overlayInfo"
                     class="absolute bottom-3 left-1/2 -translate-x-1/2 px-3 py-1.5 rounded-xl bg-black/70 text-white text-xs shadow-lg backdrop-blur pointer-events-none">
                    <span id="overlayTexto">Escaneando el código QR.</span>
                </div>
            </div>
        </section>

        <!-- ========================= Panel lateral ========================= -->
        <section class="min-h-0 flex flex-col gap-3">

            <!-- Identificación y marcación -->
            <div class="shrink-0 rounded-2xl bg-white shadow-lg shadow-gray-200/50 ring-1 ring-black/5 p-4">
                <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-2">Identificación</p>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <button data-modo="qr" type="button"
                            class="modoBtn px-3 py-2.5 rounded-xl bg-orange-50 border border-orange-200 text-orange-700 font-semibold hover:bg-orange-100 transition">
                        <i class="fa-solid fa-qrcode mr-1"></i>Escanear QR
                    </button>
                    <button data-modo="rostro" type="button"
                            class="modoBtn px-3 py-2.5 rounded-xl bg-orange-50 border border-orange-200 text-orange-700 font-semibold hover:bg-orange-100 transition">
                        <i class="fa-solid fa-face-smile mr-1"></i>Mi rostro
                    </button>
                </div>

                <p class="mt-4 text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-2">Marcar</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <button data-accion="auto" type="button"
                            class="accionBtn col-span-2 px-2 py-2.5 rounded-xl bg-orange-50 border border-orange-200 text-orange-700 font-semibold hover:bg-orange-100 transition">
                        Automático
                    </button>
                    <button data-accion="entrada" type="button"
                            class="accionBtn px-2 py-2 rounded-xl bg-green-50 border border-green-200 text-green-700 font-semibold hover:bg-green-100 transition">
                        Entrada
                    </button>
                    <button data-accion="descanso" type="button"
                            class="accionBtn px-2 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 font-semibold hover:bg-amber-100 transition">
                        Descanso
                    </button>
                    <button data-accion="salida" type="button"
                            class="accionBtn px-2 py-2 rounded-xl bg-red-50 border border-red-200 text-red-700 font-semibold hover:bg-red-100 transition">
                        Salida
                    </button>
                </div>
                <p class="mt-2 text-[11px] leading-snug text-gray-400">
                    <strong>Automático</strong> marca lo que corresponda según el día;
                    «Descanso» inicia o termina el lonche.
                </p>
            </div>

            <!-- Estado del día: lo que ya se marcó y la salida estimada del turno -->
            <div class="min-h-0 flex-1 rounded-2xl bg-white shadow-lg shadow-gray-200/50 ring-1 ring-black/5 p-4 overflow-hidden flex flex-col">
                <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400 mb-2">Estado del día</p>
                <div id="estadoDia" class="flex-1 flex items-center justify-center text-center text-sm text-gray-400">
                    <div>
                        <i class="fa-solid fa-id-card text-3xl text-gray-200 mb-2"></i>
                        <p>Presentá tu QR o tu rostro<br>para ver tu jornada.</p>
                    </div>
                </div>
            </div>

            <!-- Resultado transitorio de la última marcación -->
            <div id="resultado" class="hidden shrink-0 rounded-2xl p-3 border text-sm"></div>

            <p class="shrink-0 text-[11px] text-gray-400 text-center leading-relaxed">
                El rostro se compara en este servidor y se descarta al terminar.
                <span id="textoEnrolados"><?= (int) ($rostrosEnrolados ?? 0) ?> empleado(s) con rostro enrolado.</span>
            </p>
        </section>
    </main>

    <input type="hidden" id="claveKiosco" value="<?= esc(setting('kiosk_key', '')) ?>">
    <input type="hidden" id="urlVerificar" value="<?= url('kiosco/verificar') ?>">
    <input type="hidden" id="modelosUri" value="<?= esc(EmpleadoFace::MODELOS_URI) ?>">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const video = document.getElementById('video');
            const overlay = document.getElementById('overlay');
            const ctxOverlay = overlay.getContext('2d');
            const estadoRostro = document.getElementById('estadoRostro');
            const overlayTexto = document.getElementById('overlayTexto');
            const btnInvertirCamara = document.getElementById('btnInvertirCamara');
            const qrReaderEl = document.getElementById('qrReader');
            const clave = document.getElementById('claveKiosco').value;
            const urlVerificar = document.getElementById('urlVerificar').value;
            const modelosUri = document.getElementById('modelosUri').value;
            const resultado = document.getElementById('resultado');
            const estadoDia = document.getElementById('estadoDia');
            const accionBtns = document.querySelectorAll('.accionBtn');
            const modoBtns = document.querySelectorAll('.modoBtn');

            let modelosCargados = false;
            let cargandoModelos = null;
            let deteccionActual = null;
            let camaraActual = 'environment';
            let streamActual = null;
            let html5QrCode = null;
            let qrCorriendo = false;
            let ultimoEnviado = null;
            let ultimoEnvio = 0;
            let intervaloRostro = null;
            let procesando = false;
            let accionSeleccionada = 'auto';
            let modo = 'qr';

            const ESPERA_MINIMA_MS = 4000;

            function ajustarCanvas() {
                if (!video.videoWidth) return;
                if (overlay.width !== video.videoWidth || overlay.height !== video.videoHeight) {
                    overlay.width = video.videoWidth;
                    overlay.height = video.videoHeight;
                }
            }

            function setEstado(txt) {
                estadoRostro.textContent = txt;
            }

            // ---------------------------------------------------------------- modelos

            function cargarModelos() {
                if (modelosCargados) return Promise.resolve();
                if (cargandoModelos) return cargandoModelos;

                setEstado('Cargando modelos…');
                cargandoModelos = (async function () {
                    // Sin WebGL setBackend devuelve false (no lanza): si no
                    // rebotamos a CPU, tf queda sin backend y las detecciones
                    // se caen. CPU es mas lenta pero el quiosco sigue marcando.
                    if (!await faceapi.tf.setBackend('webgl')) {
                        await faceapi.tf.setBackend('cpu');
                    }
                    await faceapi.tf.ready();
                    // Solo las tres redes necesarias: FaceExpressionNet no se usa
                    // y son 330 KB de descarga que nadie mira.
                    await Promise.all([
                        faceapi.nets.tinyFaceDetector.loadFromUri(modelosUri),
                        faceapi.nets.faceLandmark68Net.loadFromUri(modelosUri),
                        faceapi.nets.faceRecognitionNet.loadFromUri(modelosUri)
                    ]);
                    modelosCargados = true;
                    setEstado('Modelos listos');
                })().catch(function (err) {
                    cargandoModelos = null;
                    setEstado('Sin modelos');
                    Swal.fire({
                        title: 'No pude cargar los modelos de rostro',
                        text: 'Revisá la conexión a internet del quiosco y recargá la página.',
                        icon: 'warning',
                        confirmButtonColor: '#f97316'
                    });
                    console.error(err);
                });

                return cargandoModelos;
            }

            // ---------------------------------------------------------------- cámara

            function detenerCamara() {
                if (streamActual) {
                    streamActual.getTracks().forEach(function (t) { t.stop(); });
                    streamActual = null;
                }
                video.srcObject = null;
                ctxOverlay.clearRect(0, 0, overlay.width, overlay.height);
            }

            async function arrancarCamara() {
                detenerCamara();
                try {
                    streamActual = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: camaraActual,
                            width: { ideal: 1280 },
                            height: { ideal: 720 }
                        },
                        audio: false
                    });
                    video.srcObject = streamActual;
                    video.onloadedmetadata = function () {
                        video.play();
                        ajustarCanvas();
                    };
                    btnInvertirCamara.classList.remove('hidden');
                } catch (e) {
                    setEstado('Sin cámara');
                    Swal.fire({
                        title: 'Sin acceso a la cámara',
                        text: 'Dale permiso a la cámara para usar el quiosco.',
                        icon: 'warning',
                        confirmButtonColor: '#f97316'
                    });
                }
            }

            // -------------------------------------------------------------- detection

            /**
             * Un frame por vez. Antes el bucle se disparaba con requestAnimationFrame
             * encadenado SIN await: dos detecciones se solapaban sobre el mismo
             * <video> y los Posting_error de tf se acumulaban hasta bloquear la
             * GPU. Cada tick espera a que termine el anterior.
             */
            async function detectarRostro() {
                if (modo !== 'rostro') return;

                if (modelosCargados && video.videoWidth) {
                    ajustarCanvas();
                    try {
                        // Igual que en el enrolamiento: con face-api 1.7.15 la
                        // cadena se hace sobre la PROMESA y el descriptor sale
                        // de computeFaceDescriptor() — el objeto resuelto no
                        // tiene withFaceDescriptors() y encadenar sobre undefined
                        // (frame sin rostro) resuelve undefined sin lanzar.
                        const deteccion = await faceapi
                            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({
                                inputSize: 416,
                                scoreThreshold: 0.35
                            }))
                            .withFaceLandmarks();

                        if (deteccion) {
                            dibujarCaja(deteccion.detection.box);
                            deteccionActual = Array.from(
                                await faceapi.computeFaceDescriptor(video, deteccion.landmarks)
                            );
                        } else {
                            ctxOverlay.clearRect(0, 0, overlay.width, overlay.height);
                            deteccionActual = null;
                            ultimoEnviado = null;
                        }
                    } catch (e) {
                        deteccionActual = null;
                        ultimoEnviado = null;
                    }
                }

                setTimeout(detectarRostro, 250);
            }

            function dibujarCaja(box) {
                ctxOverlay.clearRect(0, 0, overlay.width, overlay.height);
                ctxOverlay.strokeStyle = '#f97316';
                ctxOverlay.lineWidth = 3;
                ctxOverlay.strokeRect(box.x, box.y, box.width, box.height);
                ctxOverlay.fillStyle = 'rgba(0,0,0,0.55)';
                ctxOverlay.fillRect(box.x, box.y + box.height + 4, 150, 22);
                ctxOverlay.fillStyle = '#f97316';
                ctxOverlay.font = '12px system-ui';
                ctxOverlay.fillText('Rostro listo', box.x + 8, box.y + box.height + 19);
            }

            function distancia(a, b) {
                let s = 0;
                for (let i = 0; i < a.length; i++) {
                    const d = a[i] - b[i];
                    s += d * d;
                }
                return Math.sqrt(s);
            }

            // ----------------------------------------------------------------- QR

            async function iniciarQr() {
                // Guard por modo: si el usuario salta a rostro durante los
                // reinicios con setTimeout, arrancar el lector aqui abriria una
                // segunda camara junto a la del reconocimiento.
                if (modo !== 'qr' || qrCorriendo || !qrReaderEl) return;
                qrCorriendo = true;

                try {
                    if (!html5QrCode) {
                        html5QrCode = new Html5Qrcode('qrReader', {
                            formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE]
                        });
                    }
                    await html5QrCode.start(
                        { facingMode: camaraActual },
                        { fps: 10, qrbox: { width: 220, height: 220 }, disableFlip: true },
                        function (decoded) {
                            enviarVerificar({ qr_token: decoded.trim() });
                            reiniciarQr();
                        },
                        function () { }
                    );
                } catch (e) {
                    qrCorriendo = false;
                    setEstado('QR no disponible');
                    Swal.fire({
                        title: 'No pude usar el lector de QR',
                        text: 'Probá con el reconocimiento facial o recargá la página.',
                        icon: 'warning',
                        confirmButtonColor: '#f97316'
                    });
                }
            }

            function detenerQr() {
                if (!html5QrCode || !qrCorriendo) {
                    qrCorriendo = false;
                    return;
                }
                qrCorriendo = false;
                html5QrCode.stop().catch(function () { }).then(function () {
                    // html5-qrcode deja el <video> dentro del contenedor: sin
                    // limpiarlo, el reinicio acumularia una camara por intento.
                    qrReaderEl.innerHTML = '';
                });
            }

            function reiniciarQr() {
                if (modo !== 'qr') return;
                setTimeout(function () {
                    if (modo !== 'qr') return;
                    detenerQr();
                    setTimeout(iniciarQr, 250);
                }, 1200);
            }

            // ---------------------------------------------------------------- envío

            /**
             * Estado del día, persistente: quién es, hora de entrada y salida
             * estimada (la del turno asignado en Horarios). Lo pinta la última
             * respuesta, así que el empleado lo ve sin volver a preguntar.
             */
            function pintarEstadoDia(data) {
                if (!estadoDia) return;
                const reg = data && data.registro;
                const emp = data && data.empleado;

                if (!reg && !emp) {
                    estadoDia.innerHTML = '<div class="flex flex-col items-center text-center text-sm text-gray-400">'
                        + '<i class="fa-solid fa-id-card text-3xl text-gray-200 mb-2"></i>'
                        + '<p>Presentá tu QR o tu rostro<br>para ver tu jornada.</p></div>';
                    return;
                }

                const nombre = emp ? (emp.nombre || '—') : '—';
                const foto = emp && emp.foto ? emp.foto : '';
                const avatar = foto
                    ? '<img src="' + foto + '" alt="" class="w-12 h-12 rounded-full object-cover ring-2 ring-orange-200 mb-1">'
                    : '<div class="w-12 h-12 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-lg mb-1">'
                        + (nombre.charAt(0) || '?').toUpperCase() + '</div>';

                const entrada = (reg && reg.entrada) ? String(reg.entrada).substr(0, 5) : '—';
                const turno = (data && data.turno) ? data.turno : null;
                const salidaEst = turno ? turno.fin : '—';
                let turnoTexto = 'Sin turno asignado';
                if (turno) {
                    turnoTexto = 'Turno de ' + turno.tipo + ' · ' + turno.inicio + ' → ' + turno.fin;
                }

                const fila = function (label, valor, color) {
                    return '<div class="rounded-xl bg-gray-50 ring-1 ring-gray-100 px-3 py-2 text-center">'
                        + '<span class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">' + label + '</span>'
                        + '<span class="block text-lg font-bold ' + color + '">' + valor + '</span></div>';
                };

                estadoDia.innerHTML = '<div class="flex flex-col items-center text-center gap-2">'
                    + avatar
                    + '<p class="font-bold text-gray-900 leading-tight">' + nombre + '</p>'
                    + '<div class="grid grid-cols-2 gap-2 w-full">'
                    + fila('Entrada', entrada, 'text-green-700')
                    + fila('Salida est.', salidaEst, 'text-orange-600')
                    + '</div>'
                    + '<p class="text-xs text-gray-500"><i class="fa-solid fa-calendar-week mr-1 text-gray-400"></i>' + turnoTexto + '</p>'
                    + '</div>';
            }

            function mostrarResultado(ok, mensaje, extra) {
                extra = extra || {};

                // El estado del día se refresca con cada idéntificación, incluso
                // si la acción falló (p. ej. "ya tenías entrada"): ese intento
                // confirma quién está enfrente y qué hay marcado.
                if (extra.registro || extra.empleado) {
                    pintarEstadoDia(extra);
                }

                resultado.className = 'shrink-0 rounded-2xl p-3 border text-sm '
                    + (ok
                        ? 'bg-green-50 border-green-200 text-green-800'
                        : 'bg-red-50 border-red-200 text-red-800');
                resultado.innerHTML = '';

                const fuerte = document.createElement('p');
                fuerte.className = 'font-semibold';
                fuerte.textContent = mensaje;
                resultado.appendChild(fuerte);

                if (extra.empleado && ok) {
                    const nombre = document.createElement('p');
                    nombre.className = 'mt-0.5 font-bold text-gray-900';
                    nombre.textContent = extra.empleado.nombre;
                    resultado.appendChild(nombre);
                }

                const detalle = [];
                if (ok && extra.accion) detalle.push('Acción: ' + extra.accion);
                if (extra.metodo) detalle.push('Método: ' + extra.metodo);
                if (extra.confianza && extra.confianza.texto) detalle.push(extra.confianza.texto);
                if (detalle.length) {
                    const p = document.createElement('p');
                    p.className = 'mt-1 text-xs';
                    p.textContent = detalle.join(' · ');
                    resultado.appendChild(p);
                }

                resultado.classList.remove('hidden');
                clearTimeout(mostrarResultado.timer);
                mostrarResultado.timer = setTimeout(function () {
                    resultado.classList.add('hidden');
                }, 3200);
            }

            function enviarVerificar(payload) {
                const ahora = Date.now();
                if (procesando || ahora - ultimoEnvio < ESPERA_MINIMA_MS) return;
                procesando = true;
                ultimoEnvio = ahora;
                // NO se suelta ultimoEnviado aca: ese lock es lo que impide que
                // la misma cara que sigue en pantalla reintente cada 4 s para
                // siempre (marcaba la asistencia en bucle y llenaba la bitácora
                // de "ya tenías entrada"). Lo libera detectarRostro() cuando la
                // cara desaparece, y se reemplaza solo si entra OTRO rostro.

                const cuerpo = { clave: clave, accion: accionSeleccionada };
                Object.keys(payload).forEach(function (k) { cuerpo[k] = payload[k]; });

                fetch(urlVerificar, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: new URLSearchParams(cuerpo)
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        mostrarResultado(data.ok, data.mensaje, data);
                        if (!data.ok && data.mensaje && data.mensaje.indexOf('bloqueado') !== -1) {
                            setTimeout(function () { location.reload(); }, 1500);
                        }
                    })
                    .catch(function () {
                        mostrarResultado(false, 'No pude comunicarme con el quiosco.');
                    })
                    .finally(function () {
                        procesando = false;
                    });
            }

            /**
             * Intento por rostro. Antes se mandaba el ultimo descriptor cacheado
             * cada 1,5 s para siempre: al no limpiarse, la misma persona repetia
             * la marcacion indefinidamente (y el error "ya tenias entrada" cada
             * 1,5 s). Ahora solo se manda con una cara EN PANTALLA y se bloquea ese
             * rostro por ESPERA_MINIMA_MS.
             */
            function intentoPorRostro() {
                if (modo !== 'rostro' || !deteccionActual || !modelosCargados) return;
                if (ultimoEnviado && distancia(deteccionActual, ultimoEnviado) < 0.4) return;

                ultimoEnviado = deteccionActual.slice();
                enviarVerificar({ descriptor: JSON.stringify(deteccionActual) });
            }

            // ----------------------------------------------------------------- modos

            async function activarModo(nuevo) {
                if (modo === nuevo) return;
                modo = nuevo;

                modoBtns.forEach(function (b) {
                    const activo = b.dataset.modo === nuevo;
                    b.classList.toggle('bg-orange-500', activo);
                    b.classList.toggle('text-white', activo);
                    b.classList.toggle('bg-orange-50', !activo);
                    b.classList.toggle('text-orange-700', !activo);
                });

                if (nuevo === 'qr') {
                    detenerCamara();
                    video.classList.add('hidden');
                    overlay.classList.add('hidden');
                    overlayTexto.textContent = 'Escaneando el código QR.';
                    btnInvertirCamara.classList.add('hidden');
                    qrReaderEl.classList.remove('hidden');
                    iniciarQr();
                } else {
                    detenerQr();
                    qrReaderEl.classList.add('hidden');
                    video.classList.remove('hidden');
                    overlay.classList.remove('hidden');
                    overlayTexto.textContent = 'Mantené tu rostro centrado y con buena luz frontal.';
                    await cargarModelos();
                    await arrancarCamara();
                    detectarRostro();
                    // Un solo interval de por vida: activarModo puede llamarse
                    // muchas veces (qr <-> rostro) y sin guardar el handle cada
                    // visita sumaba otro ticker sobre el mismo codigo.
                    if (!intervaloRostro) {
                        intervaloRostro = setInterval(intentoPorRostro, 1500);
                    }
                }
            }

            modoBtns.forEach(function (b) {
                b.addEventListener('click', function () { activarModo(b.dataset.modo); });
            });

            btnInvertirCamara.addEventListener('click', function () {
                camaraActual = (camaraActual === 'environment') ? 'user' : 'environment';
                if (modo === 'rostro') arrancarCamara();
            });

            accionBtns.forEach(function (b) {
                b.addEventListener('click', function () {
                    accionBtns.forEach(function (x) {
                        x.classList.remove('ring-2', 'ring-orange-400', 'bg-orange-500', 'text-white');
                    });
                    accionSeleccionada = b.dataset.accion;
                    if (b.dataset.accion === 'auto') {
                        b.classList.add('ring-2', 'ring-orange-400');
                    } else {
                        b.classList.add('ring-2', 'ring-orange-400', 'bg-orange-500', 'text-white');
                    }
                });
            });

            (async function init() {
                modo = 'qr';
                overlay.classList.add('hidden');
                video.classList.add('hidden');
                await iniciarQr();
            })();
        });
    </script>
</body>
</html>