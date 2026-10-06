<?php
/**
 * Modales de la fila de Empleados: QR de asistencia y horario de la semana.
 *
 * Todo el JS va inline en el partial (mismo criterio que matrix_permisos.php):
 * son dos modales propios y meterlos en main.js lo llenaria de casos
 *particularly de esta vista.
 *
 * Variables esperadas del que incluye (employees/index.php):
 *   $esAdmin    el usuario en sesion es administrador (solo el regenera el QR)
 *   $semana     ['desde' => 'Y-m-d', 'hasta' => 'Y-m-d']
 */

$diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$etiquetaSemana = date('d/m', strtotime($semana['desde'])) . ' – ' . date('d/m/Y', strtotime($semana['hasta']));
?>
<!-- Modal: QR del empleado -->
<div class="modal-overlay" id="empQrModal">
    <div class="modal-panel w-full max-w-md rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-5 py-4 text-white">
            <h3 class="font-bold text-base">
                <i class="fa-solid fa-qrcode mr-2"></i><span id="empQrTitulo">QR de asistencia</span>
            </h3>
            <button type="button" class="btn-close-emp text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5 text-center">
            <p class="text-xs text-gray-500 mb-4">
                Mostralo en el quiosco o imprimilo. Es lo que identifica al empleado si
                no se usa el reconocimiento facial.
            </p>
            <div id="empQrCanvasBox" class="inline-flex items-center justify-center rounded-2xl bg-white p-3 ring-1 ring-gray-200 shadow-sm"></div>
            <p id="empQrToken" class="mt-4 font-mono text-[11px] text-gray-400 break-all"></p>

            <div id="empQrVacio" class="hidden py-4">
                <p class="text-sm text-gray-500">Este empleado todavía no tiene QR emitido.</p>
            </div>

            <?php if ($esAdmin): ?>
                <form method="POST" id="empQrForm" action="" class="mt-5">
                    <p class="mb-3 text-xs text-amber-600 bg-amber-50 ring-1 ring-amber-200 rounded-xl px-3 py-2">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        Al generar uno nuevo, el código anterior deja de funcionar.
                    </p>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-orange-500/25 hover:bg-orange-600 transition">
                        <i class="fa-solid fa-rotate"></i>
                        <span id="empQrBotonTexto">Generar un QR nuevo</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: horario de la semana -->
<div class="modal-overlay" id="empCalModal">
    <div class="modal-panel w-full max-w-2xl rounded-2xl bg-white shadow-2xl shadow-gray-800/20 ring-1 ring-black/5 overflow-hidden">
        <div class="flex items-center justify-between bg-gradient-to-r from-orange-500 to-orange-400 px-6 py-4 text-white">
            <h3 class="font-bold text-lg">
                <i class="fa-solid fa-calendar-days mr-2"></i><span id="empCalTitulo">Horario de la semana</span>
            </h3>
            <button type="button" class="btn-close-emp text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="px-6 py-5">
            <p class="text-xs text-gray-500 mb-4">
                Semana del <span class="font-semibold text-gray-700"><?= esc($etiquetaSemana) ?></span>.
            </p>
            <div class="grid grid-cols-7 gap-2" id="empCalGrilla"></div>
            <div class="mt-5 flex items-center justify-between border-t border-gray-100 pt-4">
                <p id="empCalResumen" class="text-sm font-semibold text-gray-700"></p>
                <a id="empCalVerRoster" href="<?= url('schedules') ?>" class="text-sm font-semibold text-orange-600 hover:text-orange-700">
                    Ver roster <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script>
(function () {
    const qrModal = document.getElementById('empQrModal');
    const calModal = document.getElementById('empCalModal');
    if (!qrModal || !calModal) return;

    const qrCanvasBox = document.getElementById('empQrCanvasBox');
    const qrToken = document.getElementById('empQrToken');
    const qrVacio = document.getElementById('empQrVacio');
    const qrForm = document.getElementById('empQrForm');
    const qrBotonTexto = document.getElementById('empQrBotonTexto');
    const calGrilla = document.getElementById('empCalGrilla');
    const calResumen = document.getElementById('empCalResumen');
    const calRoster = document.getElementById('empCalVerRoster');
    const diasSemana = <?= json_encode($diasSemana, JSON_UNESCAPED_UNICODE) ?>;

    function abrir(modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function cerrar(modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.addEventListener('click', function (e) {
        const btnClose = e.target.closest('.btn-close-emp');
        if (btnClose) {
            cerrar(btnClose.closest('.modal-overlay'));
            return;
        }
        if ((e.target === qrModal || e.target === calModal)) {
            cerrar(e.target);
            return;
        }
        const btnQr = e.target.closest('.btn-emp-qr');
        if (btnQr) {
            abrirQr(btnQr);
            return;
        }
        const btnCal = e.target.closest('.btn-emp-cal');
        if (btnCal) {
            abrirHorario(btnCal);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            cerrar(qrModal);
            cerrar(calModal);
        }
    });

    // ---------------------------------------------------------------- QR

    function falloQr(mensaje) {
        qrCanvasBox.classList.add('ring-red-200', 'bg-red-50');
        qrCanvasBox.innerHTML = '<p class="text-sm font-semibold text-red-600 leading-snug">'
            + '<i class="fa-solid fa-triangle-exclamation mr-1"></i>' + mensaje + '</p>';
    }

    function abrirQr(btn) {
        let datos;
        try {
            datos = JSON.parse(btn.getAttribute('data-qr'));
        } catch (err) {
            return;
        }

        document.getElementById('empQrTitulo').textContent = 'QR de ' + datos.nombre;
        qrCanvasBox.innerHTML = '';
        qrCanvasBox.classList.remove('ring-red-200', 'bg-red-50');
        qrVacio.classList.add('hidden');
        qrToken.textContent = '';

        if (qrForm) {
            qrForm.action = datos.regenerar;
            qrBotonTexto.textContent = datos.token
                ? 'Generar un QR nuevo'
                : 'Generar su primer QR';
        }

        if (!datos.token) {
            qrVacio.classList.remove('hidden');
            abrir(qrModal);
            return;
        }

        if (typeof QRCode === 'undefined') {
            falloQr('No se pudo cargar la libreria del QR (se necesita internet).');
            abrir(qrModal);
            return;
        }

        // toCanvas necesita un <canvas> real, no el <div> contenedor: con el div
        // la libreria falla con "getContext is not a function" y no dibuja.
        const lienzo = document.createElement('canvas');
        qrCanvasBox.appendChild(lienzo);

        try {
            QRCode.toCanvas(lienzo, datos.token, {
                width: 220,
                margin: 1,
                color: { dark: '#111827', light: '#ffffff' }
            }, function (err) {
                if (err) falloQr('No se pudo generar el QR.');
            });
        } catch (e) {
            falloQr('No se pudo generar el QR.');
        }

        qrToken.textContent = datos.token;
        abrir(qrModal);
    }

    // ------------------------------------------------------------ Horario

    function abrirHorario(btn) {
        let datos;
        try {
            datos = JSON.parse(btn.getAttribute('data-cal'));
        } catch (err) {
            return;
        }

        document.getElementById('empCalTitulo').textContent = 'Horario de ' + datos.nombre;
        if (calRoster) calRoster.setAttribute('href', datos.roster || '/schedules');

        calGrilla.innerHTML = '';
        let horas = 0;

        (datos.turnos || []).forEach(function (t) {
            const dia = diasSemana[parseInt(t.dia, 10)] || '';
            const celda = document.createElement('div');
            celda.className = 'rounded-xl bg-orange-50/70 ring-1 ring-orange-100 px-2 py-3 text-center';

            const diaEl = document.createElement('p');
            diaEl.className = 'text-[10px] font-bold uppercase tracking-wider text-orange-500';
            diaEl.textContent = dia;

            const fechaEl = document.createElement('p');
            fechaEl.className = 'text-[11px] text-gray-500';
            fechaEl.textContent = t.fecha;

            const horaEl = document.createElement('p');
            horaEl.className = 'mt-1.5 text-xs font-bold text-gray-900 leading-tight';
            horaEl.textContent = t.hora;

            const tipoEl = document.createElement('p');
            tipoEl.className = 'mt-1 text-[10px] font-semibold text-gray-600';
            tipoEl.textContent = t.tipo || '';

            celda.appendChild(diaEl);
            celda.appendChild(fechaEl);
            celda.appendChild(horaEl);
            celda.appendChild(tipoEl);
            calGrilla.appendChild(celda);

            horas += parseFloat(t.horas || 0);
        });

        const libres = 7 - (datos.turnos || []).length;
        if (libres > 0) {
            for (let i = 0; i < libres; i++) {
                const celda = document.createElement('div');
                celda.className = 'rounded-xl bg-gray-50 ring-1 ring-gray-100 px-2 py-3 text-center flex items-center justify-center';
                celda.innerHTML = '<i class="fa-solid fa-minus text-gray-300 text-xs"></i>';
                calGrilla.appendChild(celda);
            }
        }

        if ((datos.turnos || []).length === 0) {
            calResumen.textContent = 'Sin turnos asignados esta semana.';
        } else {
            calResumen.textContent = (datos.turnos.length) + (datos.turnos.length === 1 ? ' turno · ' : ' turnos · ')
                + Math.round(horas * 10) / 10 + ' h';
        }

        abrir(calModal);
    }
})();
</script>