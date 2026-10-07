<?php
$cameraField = $cameraField ?? 'photo';
?>
<button type="button" class="camera-trigger inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 hover:text-orange-600 transition-colors mt-2"
        data-target="<?= esc($cameraField) ?>"
        title="Tomar foto con la cámara">
    <i class="fa-solid fa-camera"></i> Tomar foto
</button>
<img class="camera-preview hidden mt-2 w-16 h-16 rounded-xl object-cover ring-1 ring-orange-100 shadow-sm" id="camera-preview-<?= esc($cameraField) ?>" alt="Foto capturada">

<?php if (empty($GLOBALS['__camera_modal_rendered'])): $GLOBALS['__camera_modal_rendered'] = true; ?>
<!-- Modal cámara para fotos -->
<div class="modal-overlay" id="cameraModal">
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg"><i class="fa-solid fa-camera mr-2"></i>Tomar foto</h3>
            <button type="button" class="camera-close text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-6 bg-white">
            <div class="relative rounded-xl overflow-hidden bg-black aspect-[4/3]">
                <video id="cameraVideo" playsinline autoplay muted class="w-full h-full object-cover"></video>
                <p id="cameraError" class="hidden absolute inset-0 items-center justify-center text-center text-sm text-white bg-gray-900/70 px-6 py-4">
                    <span><i class="fa-solid fa-video-slash block text-2xl text-gray-400 mb-2"></i>No se pudo acceder a la cámara. Verifica los permisos o usa la opción de subir archivo.</span>
                </p>
            </div>
            <div class="mt-4 flex items-center justify-center gap-3">
                <button type="button" id="cameraCaptureBtn" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 hover:-translate-y-px transition-all">
                    <i class="fa-solid fa-camera-retro"></i> Capturar
                </button>
                <button type="button" id="cameraSwitchBtn" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 shadow-sm hover:bg-gray-50 transition-colors">
                    <i class="fa-solid fa-rotate"></i> Cambiar cámara
                </button>
            </div>
            <canvas id="cameraCanvas" class="hidden"></canvas>
        </div>
    </div>
</div>
<?php endif; ?>