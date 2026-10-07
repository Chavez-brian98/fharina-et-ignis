<?php
/**
 * Bloque de biometría del empleado (solo administradores).
 *
 * Lo que se guarda NO es la foto: es el vector de 128 flotantes que
 * FaceRecognitionNet calcula sobre la foto de perfil. El cálculo ocurre en el
 * navegador (los pesos no caben en PHP) y viaja en un input oculto dentro del
 * MISMO formulario del empleado, que el servidor vuelve a validar con
 * EmpleadoFace::normalizar().
 *
 * Va dentro del <form> de alta/edición: por eso quitar la biometría es un
 * checkbox (remove_face) y no otro <form> — los forms anidados no existen en
 * HTML y el navegador descarta el interno.
 *
 * Variables esperadas del que incluye:
 *   $esAdmin   solo los administradores ven/envían esto
 *   $rostro    fila de empleado_faces o null (null = sin enrolar)
 *   $employee  solo en edit, para leer la foto ya guardada
 */

if (empty($esAdmin)) {
    return;
}

$faceId = 'profile_photo';
$faceEnrolado = !empty($rostro);
$faceCalidad = isset($rostro['quality']) ? (float) $rostro['quality'] : null;
$faceGuardado = !empty($rostro['created_at']) ? strtotime((string) $rostro['created_at']) : null;
$fotoActual = isset($employee['profile_photo']) ? (string) $employee['profile_photo'] : '';
?>
<div class="md:col-span-2 border-t border-gray-100 pt-5">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h3 class="font-semibold text-gray-900">
                <i class="fa-solid fa-face-smile text-orange-500 mr-2"></i>Datos biométricos
            </h3>
            <p class="text-sm text-gray-500 mt-1">
                Se calculan a partir de la foto de perfil y permiten marcar en el quiosco
                sin pasar el QR. Solo un administrador puede crearlos o borrarlos.
            </p>
        </div>
        <span id="faceEstado"
              class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold <?= $faceEnrolado ? 'bg-green-50 text-green-700 ring-1 ring-green-200' : 'bg-gray-50 text-gray-500 ring-1 ring-gray-200' ?>">
            <i class="fa-solid <?= $faceEnrolado ? 'fa-circle-check' : 'fa-circle-minus' ?>"></i>
            <span class="faceEstadoTexto"><?= $faceEnrolado ? 'Enrolado' : 'Sin enrolar' ?></span>
        </span>
    </div>

    <div class="mt-4 rounded-2xl bg-gray-50/70 ring-1 ring-gray-200 p-4">
        <input type="hidden" name="descriptor" id="faceDescriptor" value="">
        <input type="hidden" name="quality" id="faceQuality" value="">

        <div class="flex flex-wrap items-center gap-3">
            <button type="button" id="faceGenerar"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 ring-1 ring-gray-200 shadow-sm hover:bg-gray-50 transition disabled:opacity-60">
                <i class="fa-solid fa-wand-magic-sparkles text-orange-500"></i>
                <span id="faceGenerarTexto">Generar desde la foto</span>
            </button>

            <label id="faceQuitarWrap" class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer <?= $faceEnrolado ? '' : 'hidden' ?>">
                <input type="checkbox" name="remove_face" id="faceQuitar" value="1" class="rounded border-gray-300 text-orange-500 focus:ring-orange-200">
                Quitar la biometría
            </label>
        </div>

        <p id="faceMensaje" class="mt-3 text-xs text-gray-500">
            <?php if ($faceEnrolado): ?>
                Guardada <?= $faceGuardado ? date('d/m/Y H:i', $faceGuardado) : '—' ?><?= $faceCalidad !== null ? ' · calidad ' . number_format($faceCalidad * 100, 0) . '%' : '' ?>.
                Podés regenerarla si cambiás la foto.
            <?php else: ?>
                Elegí la foto y tocá “Generar desde la foto”. El modelo (~7 MB) se
                descarga una vez y el navegador lo cachea para los demás formularios.
            <?php endif; ?>
        </p>
    </div>
</div>

<!--
    TensorFlow.js va antes que face-api y en el mismo orden que en el quiosco:
    la libreria toma el backend de `tf`, no lo trae embebido. Si una version
    de face-api lo empaquetara, cargar esto de mas es inocuo; si falta, el
    enrollamiento muere con "Cannot read properties of undefined (reading 'tf')".
-->
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-core@4.22.0/dist/tf-core.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-converter@4.22.0/dist/tf-converter.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs-backend-webgl@4.22.0/dist/tf-backend-webgl.min.js"></script>
<script src="<?= esc(EmpleadoFace::LIB_URI) ?>"></script>
<script>
/**
 * Calcula el descriptor facial sobre la foto de perfil y lo deja en el input
 * oculto que ya viaja con el formulario del empleado.
 *
 * La librería y los pesos SON los mismos que usa el quiosco
 * (EmpleadoFace::MODELO / MODELOS_URI). Si se mezclaran los pesos de
 * face-api.js 0.22.x con los de @vladmandic, el vector guardado nunca
 * coincidiría con el del quiosco.
 */
(function () {
    const inputFoto = document.getElementById('<?= $faceId ?>');
    const inputDesc = document.getElementById('faceDescriptor');
    const inputCalidad = document.getElementById('faceQuality');
    const btnGenerar = document.getElementById('faceGenerar');
    const btnTexto = document.getElementById('faceGenerarTexto');
    const chkQuitar = document.getElementById('faceQuitar');
    const wrapQuitar = document.getElementById('faceQuitarWrap');
    const mensaje = document.getElementById('faceMensaje');
    const estado = document.getElementById('faceEstado');
    const modelosUri = '<?= esc(EmpleadoFace::MODELOS_URI) ?>';
    const fotoActual = <?= json_encode($fotoActual, JSON_UNESCAPED_UNICODE) ?>;

    let cargando = null;
    let ocupado = false;

    if (!inputFoto || !btnGenerar) return;

    function decir(txt, tono) {
        if (!mensaje) return;
        mensaje.className = 'mt-3 text-xs ' + (tono || 'text-gray-500');
        mensaje.textContent = txt;
    }

    function pintarEstado(enrolado) {
        if (!estado) return;
        estado.className = 'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold '
            + (enrolado
                ? 'bg-green-50 text-green-700 ring-1 ring-green-200'
                : 'bg-gray-50 text-gray-500 ring-1 ring-gray-200');
        estado.innerHTML = '<i class="fa-solid ' + (enrolado ? 'fa-circle-check' : 'fa-circle-minus') + '"></i>'
            + '<span class="faceEstadoTexto">' + (enrolado ? 'Enrolado' : 'Sin enrolar') + '</span>';
    }

    function marcarPendiente() {
        inputDesc.value = '';
        inputCalidad.value = '';
        if (chkQuitar) chkQuitar.checked = false;
        pintarEstado(false);
        if (wrapQuitar) wrapQuitar.classList.add('hidden');
        if (btnTexto) btnTexto.textContent = 'Generar desde la foto';
    }

    function cargarModelos() {
        if (cargando) return cargando;
        btnGenerar.disabled = true;
        btnTexto.textContent = 'Cargando modelo…';
        decir('Descargando el modelo de rostro la primera vez (unos 7 MB)…', 'text-gray-500');

        cargando = (async function () {
            // Sin WebGL (VM, GPU deshabilitada, navegador sin soporte) setBackend
            // devuelve false en vez de lanzar. Ahi caemos a CPU: es mas lenta pero
            // funciona, en vez de dejar tf sin backend inicializar.
            if (!await faceapi.tf.setBackend('webgl')) {
                await faceapi.tf.setBackend('cpu');
            }
            await faceapi.tf.ready();
            // Solo las tres redes necesarias; FaceExpressionNet no se usa.
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelosUri),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelosUri),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelosUri)
            ]);
        })().catch(function (err) {
            cargando = null;
            btnGenerar.disabled = false;
            btnTexto.textContent = 'Generar desde la foto';
            decir('No pude cargar el modelo. Revisá la conexión a internet e intentá de nuevo.', 'text-red-600');
            console.error(err);
            throw err;
        });

        return cargando;
    }

    function comoImagen(src) {
        return new Promise(function (res, rej) {
            const img = new Image();
            img.onload = function () { res(img); };
            img.onerror = function () { rej(new Error('no se pudo leer la foto')); };
            img.src = src;
        });
    }

    async function generar() {
        if (ocupado) return;
        ocupado = true;
        btnGenerar.disabled = true;
        btnTexto.textContent = 'Analizando…';

        try {
            await cargarModelos();

            // La foto puede ser la recién elegida en el input o la ya guardada.
            let src = null;
            if (inputFoto.files && inputFoto.files.length > 0) {
                src = URL.createObjectURL(inputFoto.files[0]);
            } else if (fotoActual) {
                src = fotoActual;
            }

            if (!src) {
                decir('Primero elegí una foto de perfil: el vector se calcula sobre esa imagen.', 'text-amber-600');
                btnTexto.textContent = 'Generar desde la foto';
                btnGenerar.disabled = false;
                ocupado = false;
                return;
            }

            const img = await comoImagen(src);

            // La cadena vive en la PROMESA de detectSingleFace, no en el objeto
            // resuelto: con @vladmandic/face-api 1.7.15 lo que devuelve await es
            // una FaceDetection con box/score nomas (sin withFaceLandmarks) y
            // withFaceDescriptors() no existe en esta version — por eso el boton
            // fallaba con "base.withFaceLandmarks is not a function". El vector
            // se calcula aparte con computeFaceDescriptor(img, landmarks).
            // Sin rostro la promesa resuelve undefined, asi que el guard de abajo
            // sigue cortando igual, sin TypeError.
            const deteccion = await faceapi
                .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({
                    inputSize: 416,
                    scoreThreshold: 0.35
                }))
                .withFaceLandmarks();

            if (!deteccion) {
                decir('No detecté un rostro en esa foto. Usá una imagen de frente, bien iluminada y sin sombrero.', 'text-amber-600');
                btnTexto.textContent = 'Generar desde la foto';
                btnGenerar.disabled = false;
                ocupado = false;
                return;
            }

            const vector = Array.from(await faceapi.computeFaceDescriptor(img, deteccion.landmarks));
            if (vector.length !== 128) {
                decir('El vector salió incompleto (' + vector.length + ' de 128). No se guardó nada.', 'text-red-600');
                btnTexto.textContent = 'Generar desde la foto';
                btnGenerar.disabled = false;
                ocupado = false;
                return;
            }

            inputDesc.value = JSON.stringify(vector);
            inputCalidad.value = String(deteccion.detection.score);
            pintarEstado(true);
            if (chkQuitar) chkQuitar.checked = false;
            if (wrapQuitar) wrapQuitar.classList.remove('hidden');
            btnTexto.textContent = 'Regenerar desde la foto';
            decir('Listo: se guarda al presionar “' + (window.__faceSubmitLabel || 'Guardar') + '”. Rostro al '
                + Math.round(deteccion.detection.score * 100) + '%.', 'text-green-700');
        } catch (e) {
            // Se muestra la causa real: un catch generico dejaba "revisá la
            // conexión" para errores de código que no tienen nada que ver.
            const motivo = (e && e.message) ? e.message : 'error desconocido';
            decir('No pude generar la biometría (' + motivo + '). Revisá la foto y la conexión.', 'text-red-600');
            btnTexto.textContent = 'Generar desde la foto';
            console.error(e);
        }

        ocupado = false;
        btnGenerar.disabled = false;
    }

    btnGenerar.addEventListener('click', function () { generar(); });

    // Elegir otra foto invalida el vector calculado con la anterior.
    inputFoto.addEventListener('change', function () {
        marcarPendiente();
        decir('Foto nueva: generá la biometría de nuevo antes de guardar.', 'text-amber-600');
    });

    // En edit.php el botón de guardar dice "Actualizar Empleado".
    const btnGuardar = document.querySelector('button[type=submit]');
    if (btnGuardar) {
        window.__faceSubmitLabel = (btnGuardar.textContent || '').trim().replace(/\s+/g, ' ');
    }
})();
</script>