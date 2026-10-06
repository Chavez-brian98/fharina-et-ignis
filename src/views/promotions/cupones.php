<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<?php
$tipo = $promotion['promotion_type'];
$esTipoCupon = $tipo === 'cupon';
?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Cupones — <?= esc($promotion['name']) ?></h1>
        <p class="text-sm text-gray-500 mt-1">
            <span class="rounded-full bg-blue-50 text-blue-700 px-2 py-0.5 text-xs font-semibold"><?= esc(Promocion::tipoTexto($tipo)) ?></span>
            <?php if ($promotion['discount_percentage'] !== null): ?>
                <span class="text-gray-400">· Descuento automático de <strong><?= number_format((float) $promotion['discount_percentage'], 0) ?>%</strong> en el punto de venta</span>
            <?php else: ?>
                <span class="text-gray-400">· Se administran aquí; el punto de venta no los aplica automáticamente.</span>
            <?php endif; ?>
        </p>
    </div>
    <a href="<?= url('promotions') ?>" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Volver
    </a>
</div>

<div class="max-w-4xl space-y-5">

    <!-- Generar cupón -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
        <div class="border-b border-gray-100 bg-gradient-to-r from-blue-50/80 to-white px-6 py-5">
            <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-ticket text-blue-500 mr-2"></i>Generar cupón</h2>
            <p class="text-xs text-gray-500 mt-1">Cada cupón tiene un código único. Puedes asignarlo a un cliente y limitar sus usos.</p>
        </div>
        <form action="<?= url('promotions/generarCupon/' . $promotion['id']) ?>" method="POST" class="px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-1">
                    <label for="code" class="form-label">Código <span class="text-red-500">*</span></label>
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-blue-400 text-xs"><i class="fa-solid fa-ticket"></i></span>
                            <input type="text" id="code" name="code" class="form-input pl-8 font-mono font-bold uppercase"
                                   placeholder="PAN10" maxlength="30" required>
                        </div>
                        <button type="button" id="generateCode"
                                class="shrink-0 inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3.5 py-2.5 text-xs font-semibold text-gray-600 hover:bg-blue-50 hover:text-blue-600 transition-colors"
                                title="Generar código aleatorio">
                            <i class="fa-solid fa-shuffle"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Letras y números, sin espacios (3 a 30 caracteres).</p>
                </div>
                <div>
                    <label for="client_id" class="form-label">Cliente</label>
                    <select id="client_id" name="client_id" class="form-input">
                        <option value="0">Sin asignar</option>
                        <?php foreach ($clients as $client):
                            $nombre = trim($client['name'] . ' ' . $client['last_name']);
                            if ($client['client_type'] === 'empresa' && trim($client['company_name']) !== '') {
                                $nombre = $client['company_name'] . ' — ' . $nombre;
                            }
                        ?>
                            <option value="<?= (int) $client['id'] ?>"><?= esc($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="max_uses" class="form-label">Usos máximos</label>
                    <input type="number" id="max_uses" name="max_uses" min="1" max="9999" value="1" class="form-input">
                </div>
            </div>
            <button type="submit" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-blue-500 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 hover:bg-blue-600 hover:shadow-blue-500/40 hover:-translate-y-px transition-all">
                <i class="fa-solid fa-plus"></i> Generar cupón
            </button>
        </form>
    </div>

    <!-- Lista de cupones -->
    <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-gray-200/60 overflow-hidden">
        <div class="border-b border-gray-100 bg-gradient-to-r from-blue-50/80 to-white px-6 py-5">
            <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-list text-blue-500 mr-2"></i>Cupones generados</h2>
        </div>

        <?php if ($coupons): ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($coupons as $cupon):
                    $sqlNombre = $cupon['es_empresa']
                        ? $cupon['company_name']
                        : trim(($cupon['client_name'] ?? '') . ' ' . ($cupon['client_last_name'] ?? ''));
                    $nombre = $cupon['tiene_cliente'] ? $sqlNombre : 'Sin asignar';
                    $agotado = (int) $cupon['current_uses'] >= (int) $cupon['max_uses'];
                ?>
                    <div class="px-6 py-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 border border-blue-100 px-3 py-1.5 font-mono text-sm font-bold text-blue-700">
                                <i class="fa-solid fa-ticket text-xs"></i><?= esc($cupon['code']) ?>
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-500">
                            <i class="fa-solid fa-user text-gray-300"></i>
                            <span class="<?= $cupon['tiene_cliente'] ? '' : 'italic text-gray-400' ?>"><?= esc($nombre) ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm <?= $agotado ? 'text-red-500 font-semibold' : 'text-gray-500' ?>">
                                <?= (int) $cupon['current_uses'] ?> / <?= (int) $cupon['max_uses'] ?> usos
                            </span>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $agotado ? 'bg-red-50 text-red-600' : (empty($cupon['status']) || $cupon['status'] === 'inactive' ? 'bg-gray-100 text-gray-500' : 'bg-green-50 text-green-600') ?>">
                                <?= $agotado ? 'Agotado' : (empty($cupon['status']) || $cupon['status'] === 'inactive' ? 'Inactivo' : 'Activo') ?>
                            </span>
                        </div>
                        <?php $btnEliminar = (int) $cupon['current_uses'] === 0; ?>
                        <div class="shrink-0">
                            <?php if ($btnEliminar): ?>
                                <button type="button" class="btn-action btn-delete text-red-400 hover:bg-red-50"
                                        title="Eliminar cupón"
                                        data-url="<?= url('promotions/eliminarCupon/' . $promotion['id'] . '?cupon_id=' . (int) $cupon['id']) ?>"
                                        data-name="<?= esc($cupon['code']) ?>">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            <?php else: ?>
                                <span class="text-xs text-gray-300" title="Un cupón usado no se puede eliminar">&nbsp;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="text-center text-sm text-gray-400 py-10">
                <i class="fa-solid fa-ticket block text-3xl mb-2 text-gray-300"></i>
                Todavía no hay cupones para esta promoción.
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const input = document.getElementById('code');
    const generate = document.getElementById('generateCode');
    const ALFABETO = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    generate.addEventListener('click', function () {
        let codigo = '';

        for (let i = 0; i < 8; i++) {
            codigo += ALFABETO[Math.floor(Math.random() * ALFABETO.length)];
        }

        input.value = codigo;
        input.focus();
    });
})();
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>