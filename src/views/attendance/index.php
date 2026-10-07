<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
// Variables que llegan del AttendanceController::index.
$hoy = $hoy ?? Attendance::fechaDeMySQL();
$registro = $registro ?? null;
$turno = $turno ?? null;
$historial = $historial ?? [];
$empleado = $empleado ?? null;
$qrToken = $qrToken ?? '';
$nombreEmpleado = $empleado ? trim($empleado['name'] . ' ' . $empleado['last_name']) : '';

// Botones disponibles segun el estado real de la fila de hoy.
$entro = $registro && $registro['check_in'] !== null;
$cerrado = $registro && $registro['check_out'] !== null;
$enDescanso = $registro && $registro['en_descanso'];
$descansoCerrado = $registro && $registro['break_start'] !== null && $registro['break_end'] !== null;

$estadoTexto = $registro
    ? ($enDescanso ? 'En descanso' : ($cerrado ? 'Jornada cerrada' : 'Trabajando'))
    : 'Sin marcar';
$estadoColor = $enDescanso
    ? 'bg-amber-100 text-amber-700'
    : ($cerrado ? 'bg-gray-100 text-gray-600' : ($entro ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'));
?>

<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Mi Asistencia</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?= esc($nombreEmpleado) ?> &middot;
            <?= esc(date('d/m/Y', strtotime($hoy))) ?>
        </p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold <?= $estadoColor ?>">
        <i class="fa-solid fa-circle-dot"></i> <?= esc($estadoTexto) ?>
    </span>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    <!-- ===================== Marcaciones de hoy ===================== -->
    <div class="xl:col-span-2 space-y-6">

        <!-- Reloj / turno -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Turno de hoy</p>
                    <?php if ($turno): ?>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            <?= esc(substr($turno['start_time'], 0, 5)) ?>
                            <span class="text-gray-300 mx-1">&rarr;</span>
                            <?= esc(substr($turno['end_time'], 0, 5)) ?>
                        </p>
                        <p class="mt-1 text-sm text-gray-500">
                            <?= esc(Shift::tipoTexto($turno['shift_type'])) ?>
                        </p>
                    <?php else: ?>
                        <p class="mt-1 text-2xl font-bold text-gray-300">Sin turno</p>
                        <p class="mt-1 text-sm text-gray-500">No tenés un turno asignado para hoy.</p>
                    <?php endif; ?>
                </div>

                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Salida estimada</p>
                    <p class="mt-1 text-2xl font-bold <?= $turno ? 'text-orange-500' : 'text-gray-300' ?>">
                        <?= $turno ? esc(substr($turno['end_time'], 0, 5)) : '—' ?>
                    </p>
                    <?php if ($registro && $registro['tarde']): ?>
                        <p class="mt-1 text-xs font-semibold text-red-500">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Entraste <?= esc(Attendance::horasMinutos($registro['min_tarde'])) ?> tarde
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($registro && $registro['avance'] !== null): ?>
                <div class="mt-5">
                    <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                        <span>Avance del turno</span>
                        <span class="font-semibold"><?= (int) $registro['avance'] ?>%</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-orange-500 transition-all" style="width: <?= (int) $registro['avance'] ?>%"></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Marcacion: solo lectura. Las marcaciones se hacen en el quiosco. -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-6">
            <h2 class="font-semibold text-gray-900 mb-1">
                <i class="fa-solid fa-fingerprint text-orange-500 mr-2"></i>Mi jornada de hoy
            </h2>
            <p class="text-sm text-gray-500 mb-5">
                Acá sólo se consulta. La entrada, la salida y los descansos se registran
                en el <strong>quiosco</strong> (<span class="font-mono">/kiosco</span>),
                con tu QR o con tu rostro.
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="flex items-center justify-between gap-3 rounded-xl border <?= $entro ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' ?> px-4 py-3.5">
                    <span class="text-sm font-bold <?= $entro ? 'text-green-700' : 'text-gray-500' ?>">
                        <i class="fa-solid fa-right-to-bracket mr-2"></i>Entrada
                    </span>
                    <span class="text-sm font-bold <?= $entro ? 'text-green-700' : 'text-gray-400' ?>">
                        <?= $entro ? esc(substr($registro['check_in'], 0, 5)) : '—' ?>
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-xl border <?= $cerrado ? 'border-gray-200 bg-gray-100' : 'border-gray-200 bg-gray-50' ?> px-4 py-3.5">
                    <span class="text-sm font-bold <?= $cerrado ? 'text-gray-700' : 'text-gray-500' ?>">
                        <i class="fa-solid fa-right-from-bracket mr-2"></i>Salida
                    </span>
                    <span class="text-sm font-bold <?= $cerrado ? 'text-gray-700' : 'text-gray-400' ?>">
                        <?= $cerrado ? esc(substr($registro['check_out'], 0, 5)) : '—' ?>
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3 rounded-xl border <?= $registro && $registro['break_start'] !== null ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50' ?> px-4 py-3.5">
                    <span class="text-sm font-bold <?= $registro && $registro['break_start'] !== null ? 'text-amber-700' : 'text-gray-500' ?>">
                        <i class="fa-solid fa-mug-hot mr-2"></i>Descanso
                    </span>
                    <span class="text-sm font-bold <?= $registro && $registro['break_start'] !== null ? 'text-amber-700' : 'text-gray-400' ?>">
                        <?php if ($registro && $registro['break_start'] !== null): ?>
                            <?= esc(substr($registro['break_start'], 0, 5)) ?>
                            <?= $registro['break_end'] !== null ? '&rarr; ' . esc(substr($registro['break_end'], 0, 5)) : '(en curso)' ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>

                <a href="<?= url('kiosco') ?>"
                   class="flex items-center justify-center gap-2 rounded-xl bg-orange-500 px-5 py-3.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-orange-600">
                    <i class="fa-solid fa-desktop"></i> Marcar en el quiosco
                </a>
            </div>

            <?php if ($registro && $descansoCerrado): ?>
                <p class="mt-4 text-sm text-gray-500">
                    <i class="fa-solid fa-mug-hot mr-1 text-amber-500"></i>
                    Descanso registrado: <?= esc(Attendance::horasMinutos($registro['min_descanso'])) ?>
                </p>
            <?php endif; ?>

            <?php if ($registro && $registro['min_efectivos'] !== null && !$cerrado): ?>
                <div class="mt-5 grid grid-cols-3 gap-3 border-t border-gray-100 pt-5 text-center">
                    <div>
                        <p class="text-xs text-gray-400">Horas</p>
                        <p class="text-lg font-bold text-gray-900"><?= esc($registro['horas_texto']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Descanso</p>
                        <p class="text-lg font-bold text-amber-600"><?= esc($registro['descanso_texto']) ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Método</p>
                        <p class="text-lg font-bold text-gray-900">
                            <i class="fa-solid <?= esc(Attendance::metodoIcon($registro['check_in_method'])) ?>"></i>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Historial -->
        <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="font-semibold text-gray-900">
                    <i class="fa-solid fa-clock-rotate-left text-orange-500 mr-2"></i>Mis últimos días
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Fecha</th>
                            <th class="px-4 py-3 font-semibold">Entrada</th>
                            <th class="px-4 py-3 font-semibold">Salida</th>
                            <th class="px-4 py-3 font-semibold">Horas</th>
                            <th class="px-4 py-3 font-semibold">Descanso</th>
                            <th class="px-6 py-3 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (!$historial): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                                    Todavía no tenés registros de asistencia.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($historial as $h): ?>
                            <tr>
                                <td class="px-6 py-3 font-medium text-gray-700">
                                    <?= esc(date('d/m/Y', strtotime($h['attendance_date']))) ?>
                                    <?php if ($h['attendance_date'] === $hoy): ?>
                                        <span class="ml-1 rounded bg-orange-100 px-1.5 py-0.5 text-[10px] font-bold text-orange-700">HOY</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?= $h['check_in'] ? esc(substr($h['check_in'], 0, 5)) : '—' ?></td>
                                <td class="px-4 py-3 text-gray-600"><?= $h['check_out'] ? esc(substr($h['check_out'], 0, 5)) : '—' ?></td>
                                <td class="px-4 py-3 font-semibold text-gray-700"><?= esc($h['horas_texto']) ?></td>
                                <td class="px-4 py-3 text-gray-500"><?= esc($h['descanso_texto']) ?></td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?= Attendance::estadoColor($h['state']) ?>">
                                        <?= esc(Attendance::estadoTexto($h['state'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===================== Codigo QR ===================== -->
    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 p-6">
            <h2 class="font-semibold text-gray-900 mb-1">
                <i class="fa-solid fa-qrcode text-orange-500 mr-2"></i>Mi código QR
            </h2>
            <p class="text-sm text-gray-500 mb-5">
                Es tu forma de firmar la entrada y la salida en el quiosco sin usar la cámara.
                Podés imprimirlo y pegarlo donde quieras.
            </p>

            <div class="flex flex-col items-center">
                <div id="qrCanvas" class="rounded-xl border border-gray-200 bg-white p-3"></div>
                <p class="mt-3 font-mono text-[10px] tracking-wider text-gray-400"><?= esc($qrToken) ?></p>
            </div>

            <div class="mt-5 space-y-2">
                <button type="button" onclick="window.print()" class="flex w-full items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 transition-colors hover:bg-gray-50">
                    <i class="fa-solid fa-print"></i> Imprimir mi QR
                </button>
                <form action="<?= url('attendance/regenerateQr') ?>" method="POST"
                      onsubmit="return confirm('Se generará un código nuevo y el anterior dejará de funcionar. ¿Continuar?')">
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-red-600 transition-colors hover:bg-red-50">
                        <i class="fa-solid fa-rotate"></i> Regenerar código
                    </button>
                </form>
            </div>

            <p class="mt-4 border-t border-gray-100 pt-4 text-xs leading-relaxed text-gray-400">
                Este código identifica tu asistencia, no es una contraseña: no sirve para
                iniciar sesión.
            </p>
        </div>

        <!-- El quiosco acepta QR o rostro: este modulo ya no marca -->
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-5">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fa-solid fa-face-smile mr-1 text-gray-400"></i> ¿Por qué no puedo marcar acá?
            </h3>
            <p class="mt-2 text-xs leading-relaxed text-gray-500">
                Las marcaciones pasaron al <strong>quiosco</strong> (<span class="font-mono">/kiosco</span>),
                que acepta <strong>QR</strong> o <strong>reconocimiento facial</strong>. Así las horas las
                toma el reloj del servidor en un solo lugar. El rostro es <strong>optativo</strong>: lo
                enrola un administrador desde la ficha del empleado, usando su foto de perfil.
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>

<!-- 1.4.4 es la ultima version que publica build/ con toCanvas: en 1.5.x el
     paquete npm ya no trae el bundle de navegador y la URL da 404. -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script>
(function () {
    var token = <?= json_encode($qrToken, JSON_UNESCAPED_UNICODE) ?>;
    var nodo = document.getElementById('qrCanvas');

    function fallo(motivo) {
        if (!nodo) return;
        nodo.classList.add('border-red-200', 'bg-red-50');
        nodo.innerHTML = '<p class="text-sm font-semibold text-red-600 text-center leading-snug">'
            + '<i class="fa-solid fa-triangle-exclamation mr-1"></i>' + motivo + '</p>';
    }

    if (!nodo) return;

    if (!token) { fallo('Todavia no tenes un QR emitido. Usá "Generar un código".'); return; }

    if (typeof QRCode === 'undefined') {
        fallo('No se pudo cargar la librería del QR (se necesita internet).');
        return;
    }

    // toCanvas exige un <canvas> de verdad: si se le pasa el <div> contenedor
    // revienta con "a.getContext is not a function" y no dibuja nada.
    var lienzo = document.createElement('canvas');
    nodo.innerHTML = '';
    nodo.appendChild(lienzo);

    try {
        QRCode.toCanvas(lienzo, token, {
            width: 190,
            margin: 1,
            color: { dark: '#111827', light: '#ffffff' }
        }, function (err) {
            if (err) fallo('No se pudo generar el QR.');
        });
    } catch (e) {
        fallo('No se pudo generar el QR.');
    }
})();
</script>