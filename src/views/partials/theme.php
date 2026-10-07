<?php
/**
 * Tema dinámico: color primario configurable desde Configuración.
 *
 * Expone una variable --color-primary (hex desde la tabla settings) y deriva la
 * paleta completa (50..950) con color-mix(), luego remapea la paleta "orange"
 * de Tailwind (Play CDN) a esas variables. Así todas las clases orange-* ya
 * existentes se recoloran sin tocar las vistas.
 *
 * Incluir DESPUÉS de <script src="cdn.tailwindcss.com"> y de cualquier
 * asignación previa a tailwind.config (se mergea, no se pisa).
 */
$primaryColor = setting('primary_color', '#f97316');
?>
<style>
    :root {
        --color-primary: <?= esc($primaryColor) ?>;
        --color-primary-50: color-mix(in srgb, var(--color-primary) 5%, white);
        --color-primary-100: color-mix(in srgb, var(--color-primary) 13%, white);
        --color-primary-200: color-mix(in srgb, var(--color-primary) 26%, white);
        --color-primary-300: color-mix(in srgb, var(--color-primary) 44%, white);
        --color-primary-400: color-mix(in srgb, var(--color-primary) 64%, white);
        --color-primary-500: var(--color-primary);
        --color-primary-600: color-mix(in srgb, var(--color-primary) 88%, black);
        --color-primary-700: color-mix(in srgb, var(--color-primary) 72%, black);
        --color-primary-800: color-mix(in srgb, var(--color-primary) 56%, black);
        --color-primary-900: color-mix(in srgb, var(--color-primary) 40%, black);
        --color-primary-950: color-mix(in srgb, var(--color-primary) 22%, black);
    }
</style>
<script>
    (function () {
        var config = (window.tailwind && window.tailwind.config) || { theme: {} };
        if (!config.theme) config = { theme: {} };
        config.theme = config.theme || {};
        config.theme.extend = config.theme.extend || {};
        var colors = config.theme.extend.colors || (config.theme.extend.colors = {});
        var orange = colors.orange || (colors.orange = {});
        var shades = {
            50: 'var(--color-primary-50)', 100: 'var(--color-primary-100)',
            200: 'var(--color-primary-200)', 300: 'var(--color-primary-300)',
            400: 'var(--color-primary-400)', 500: 'var(--color-primary-500)',
            600: 'var(--color-primary-600)', 700: 'var(--color-primary-700)',
            800: 'var(--color-primary-800)', 900: 'var(--color-primary-900)',
            950: 'var(--color-primary-950)'
        };
        for (var k in shades) { orange[k] = shades[k]; }
        config.theme.extend.colors = colors;
        window.tailwind.config = config;
    })();
</script>