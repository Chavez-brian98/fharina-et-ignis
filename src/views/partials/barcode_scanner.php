<?php // Modal de escaneo de código de barras con Html5-QrCode. Se incluye una sola vez por página. ?>

<!-- Modal de escaneo -->
<div class="modal-overlay" id="barcodeModal">
    <div class="modal-panel w-full max-w-lg rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-xl">Escanear código de barras</h3>
            <button type="button" class="btn-close-scan text-white/80 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <div class="px-7 py-6">
            <div id="barcode-reader" class="w-full overflow-hidden rounded-xl ring-1 ring-gray-200 bg-gray-100"></div>
            <p class="mt-4 flex items-start gap-2 text-sm text-gray-500">
                <i class="fa-solid fa-camera text-orange-500 mt-0.5"></i>
                Apunta la cámara al código de barras del producto. El campo se completará automáticamente al detectarlo.
            </p>
            <p class="mt-2 text-xs text-gray-400">Tip: usa el código de barras impreso en el empaque o genera uno desde la app de códigos de tu celular.</p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const barcodeModal = document.getElementById('barcodeModal');
        if (!barcodeModal) return;

        let html5QrCode = null;

        function stopScanner() {
            if (html5QrCode) {
                const scanner = html5QrCode;
                scanner.stop().then(function () {
                    scanner.clear();
                }).catch(function () {}).finally(function () {
                    html5QrCode = null;
                });
            }
        }

        function openScanner() {
            barcodeModal.classList.add('open');
            document.body.style.overflow = 'hidden';

            try {
                html5QrCode = new Html5Qrcode('barcode-reader');
                html5QrCode.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 120 } },
                    function (decodedText) {
                        document.getElementById('barcode').value = decodedText;
                        closeScanner();
                    },
                    function () {}
                ).catch(function () {
                    Swal.fire({
                        title: 'Sin acceso a la cámara',
                        text: 'No se pudo iniciar la cámara. Asegúrate de dar permisos o escríbelo manualmente.',
                        icon: 'warning',
                        confirmButtonColor: '#f97316',
                        confirmButtonText: 'Entendido'
                    });
                    closeScanner();
                });
            } catch (e) {
                Swal.fire({
                    title: 'Escáner no disponible',
                    text: 'La librería de escaneo no cargó. Verifica tu conexión a internet.',
                    icon: 'error',
                    confirmButtonColor: '#f97316',
                    confirmButtonText: 'Entendido'
                });
                closeScanner();
            }
        }

        function closeScanner() {
            stopScanner();
            barcodeModal.classList.remove('open');
            document.body.style.overflow = '';
        }

        document.body.addEventListener('click', function (e) {
            const scanBtn = e.target.closest('.btn-scan-barcode');
            if (scanBtn) {
                e.preventDefault();
                openScanner();
            }
        });

        barcodeModal.addEventListener('click', function (e) {
            if (e.target.closest('.btn-close-scan') || e.target.classList.contains('modal-overlay')) {
                closeScanner();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && barcodeModal.classList.contains('open')) {
                closeScanner();
            }
        });
    });
</script>