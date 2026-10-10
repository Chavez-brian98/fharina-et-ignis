<?php
// Buscador de empleado en tiempo real (input de texto + 3 sugerencias).
// Reemplaza los <select> de empleado en los formularios. Configuración vía $empSearch:
//   label       => texto del label (default 'Empleado')
//   name        => nombre del input oculto (default 'employee_id')
//   inputId     => id del input visible (default: el mismo name)
//   value       => empleado preseleccionado (id), default 0
//   required    => valida que se elija una sugerencia antes de enviar
//   placeholder => texto del input
//   empleados   => filas con id, name, last_name, role (opcional), disabled (bool),
//                  note (texto extra, p.ej. 'ya tiene caja abierta')
$empSearch = $empSearch ?? [];
$esLabel = $empSearch['label'] ?? 'Empleado';
$esName = $empSearch['name'] ?? 'employee_id';
$esId = $empSearch['inputId'] ?? $esName;
$esValue = (int) ($empSearch['value'] ?? 0);
$esRequired = !empty($empSearch['required']);
$esPlaceholder = $empSearch['placeholder'] ?? 'Busca por nombre, apellido o rol…';
$esEmpleados = $empSearch['empleados'] ?? [];

// Normalización servidor: minúsculas y sin acentos/ñ (el JS replica con NFD).
$esNormalizar = function ($texto) {
    $t = mb_strtolower(trim((string) $texto), 'UTF-8');

    return strtr($t, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
};

$esDatos = [];
$esSeleccionado = null;

foreach ($esEmpleados as $e) {
    $nombre = trim(($e['name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
    $rol = isset($e['role']) && trim((string) $e['role']) !== '' ? trim((string) $e['role']) : '';
    $texto = $nombre . ($rol !== '' ? ' · ' . $rol : '');

    $esDatos[] = [
        'id' => (int) $e['id'],
        'texto' => $texto,
        'buscar' => $esNormalizar($nombre . ' ' . $rol),
        'disabled' => !empty($e['disabled']),
        'note' => isset($e['note']) ? trim((string) $e['note']) : '',
    ];

    if ($esValue > 0 && (int) $e['id'] === $esValue) {
        $esSeleccionado = $texto;
    }
}
?>

<div id="<?= esc($esId) ?>_wrap" class="emp-search">
    <?php if ($esLabel !== ''): ?>
        <label for="<?= esc($esId) ?>" class="form-label"><?= esc($esLabel) ?><?= $esRequired ? ' <span class="text-red-500">*</span>' : '' ?></label>
    <?php endif; ?>
    <div class="relative">
        <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
        <input type="text" id="<?= esc($esId) ?>" class="form-input pl-9 emp-search-input" autocomplete="off"
               placeholder="<?= esc($esPlaceholder) ?>" aria-label="<?= esc($esLabel) ?>"
               value="<?= esc($esSeleccionado ?? '') ?>" role="combobox" aria-expanded="false">
        <input type="hidden" name="<?= esc($esName) ?>" class="emp-search-value" value="<?= $esValue > 0 ? (int) $esValue : '' ?>">
        <ul class="emp-search-list hidden" role="listbox"></ul>
    </div>
    <p class="emp-search-hint mt-1.5 text-xs text-gray-400 hidden"></p>
</div>

<script>
(function () {
    const wrap = document.getElementById(<?= json_encode($esId . '_wrap') ?>);
    if (!wrap) return;

    const input = wrap.querySelector('.emp-search-input');
    const hidden = wrap.querySelector('.emp-search-value');
    const list = wrap.querySelector('.emp-search-list');
    const hint = wrap.querySelector('.emp-search-hint');
    const DATOS = <?= json_encode($esDatos, JSON_UNESCAPED_UNICODE) ?>;
    const MAX_SUGERENCIAS = 3;
    const REQUERIDO = <?= $esRequired ? 'true' : 'false' ?>;
    const ETIQUETA = <?= json_encode($esLabel) ?>;

    let activo = -1;
    let ultimoTexto = '';

    function normalizar(t) {
        return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function vaciarLista() {
        list.innerHTML = '';
        list.classList.add('hidden');
        input.setAttribute('aria-expanded', 'false');
        activo = -1;
    }

    function pintar() {
        const texto = normalizar(input.value);
        if (texto === '') {
            vaciarLista();
            return;
        }

        const matches = DATOS.filter(function (d) {
            return d.buscar.indexOf(texto) !== -1;
        }).slice(0, MAX_SUGERENCIAS);

        list.innerHTML = '';

        if (matches.length === 0) {
            vaciarLista();
            return;
        }

        matches.forEach(function (d) {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.dataset.id = d.id;
            li.dataset.texto = d.texto;

            const span = document.createElement('span');
            span.textContent = d.texto;
            li.appendChild(span);

            if (d.disabled) {
                li.classList.add('disabled');
                const nota = document.createElement('em');
                nota.className = 'not-italic text-xs text-gray-400';
                nota.textContent = d.note || 'No disponible';
                li.appendChild(nota);
            }

            list.appendChild(li);
        });

        list.classList.remove('hidden');
        input.setAttribute('aria-expanded', 'true');
        activo = -1;
    }

    function seleccionar(li) {
        if (!li || li.classList.contains('disabled')) return;
        hidden.value = li.dataset.id;
        input.value = li.dataset.texto;
        ultimoTexto = input.value;
        hint.classList.add('hidden');
        vaciarLista();
    }

    input.addEventListener('input', function () {
        if (hidden.value !== '' && input.value !== ultimoTexto) {
            hidden.value = '';
        }
        hint.classList.add('hidden');
        pintar();
    });

    input.addEventListener('keydown', function (e) {
        const opciones = list.querySelectorAll('li:not(.disabled)');
        if (opciones.length === 0) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            opciones.forEach(function (o) { o.classList.remove('active'); });
            activo = e.key === 'ArrowDown'
                ? (activo + 1) % opciones.length
                : (activo <= 0 ? opciones.length - 1 : activo - 1);
            opciones[activo].classList.add('active');
        } else if (e.key === 'Enter') {
            if (activo >= 0 && opciones[activo]) {
                e.preventDefault();
                seleccionar(opciones[activo]);
            }
        } else if (e.key === 'Escape') {
            vaciarLista();
        }
    });

    list.addEventListener('mousedown', function (e) {
        const li = e.target.closest('li');
        if (li) {
            e.preventDefault();
            seleccionar(li);
        }
    });

    input.addEventListener('blur', function () {
        setTimeout(vaciarLista, 150);
    });

    const form = wrap.closest('form');
    if (form && REQUERIDO) {
        form.addEventListener('submit', function (e) {
            if (hidden.value !== '') return;
            const texto = input.value.trim();
            hint.textContent = texto === ''
                ? 'Elige un ' + ETIQUETA.toLowerCase() + ' de la lista.'
                : 'Elige una de las sugerencias.';
            hint.classList.remove('hidden');
            input.focus();
            e.preventDefault();
        });
    }
})();
</script>