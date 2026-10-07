<?php require_once __DIR__ . '/../layouts/sidebar.php'; ?>

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Resumen del estado del negocio en tiempo real</p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-xl bg-orange-50 px-4 py-2 text-sm font-semibold text-orange-600 ring-1 ring-orange-100">
        <i class="fa-solid fa-calendar-day"></i> <?= esc(date('d/m/Y')) ?>
    </span>
</div>

<!-- KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Ventas de hoy</p>
            <div class="w-10 h-10 rounded-xl bg-orange-100 text-orange-600 flex items-center justify-center">
                <i class="fa-solid fa-cash-register"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">$<?= number_format($salesToday['total'], 2) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= (int) $salesToday['count'] ?> ventas registradas</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Ventas del mes</p>
            <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900">$<?= number_format($salesMonth['total'], 2) ?></p>
        <p class="text-xs text-gray-400 mt-1"><?= (int) $salesMonth['count'] ?> ventas registradas</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Pedidos pendientes</p>
            <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center">
                <i class="fa-solid fa-cake-candles"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900"><?= (int) $pendingOrders['count'] ?></p>
        <p class="text-xs text-gray-400 mt-1">En proceso o por entregar</p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-500">Stock crítico</p>
            <div class="<?= count($lowStockProducts) > 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600' ?> w-10 h-10 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900"><?= count($lowStockProducts) ?></p>
        <p class="text-xs text-gray-400 mt-1">Productos por debajo del mínimo</p>
    </div>
</div>

<!-- Gráficas principales -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <h2 class="font-semibold text-gray-900 mb-1"><i class="fa-solid fa-chart-area text-orange-500 mr-2"></i>Evolución de ventas</h2>
        <p class="text-xs text-gray-400 mb-4">Ingresos por día (últimos 14 días)</p>
        <div id="chartSales"></div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <h2 class="font-semibold text-gray-900 mb-1"><i class="fa-solid fa-chart-pie text-orange-500 mr-2"></i>Ventas por categoría</h2>
        <p class="text-xs text-gray-400 mb-4">Distribución del ingreso</p>
        <div id="chartCategory"></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <h2 class="font-semibold text-gray-900 mb-1"><i class="fa-solid fa-trophy text-orange-500 mr-2"></i>Productos más vendidos</h2>
        <p class="text-xs text-gray-400 mb-4">Top 5 por unidades vendidas</p>
        <div id="chartTopProducts"></div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-lg shadow-gray-200/50">
        <h2 class="font-semibold text-gray-900 mb-1"><i class="fa-solid fa-clipboard-list text-orange-500 mr-2"></i>Pedidos por estado</h2>
        <p class="text-xs text-gray-400 mb-4">Distribución actual</p>
        <div id="chartOrders"></div>
    </div>
</div>

<!-- Stock bajo -->
<div class="rounded-2xl border border-gray-200 bg-white shadow-lg shadow-gray-200/50 overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <h2 class="font-semibold text-gray-900"><i class="fa-solid fa-boxes-stacked text-orange-500 mr-2"></i>Productos con stock bajo</h2>
        <a href="<?= url('products') ?>" class="text-sm font-semibold text-orange-500 hover:text-orange-600 transition-colors">Ver productos <i class="fa-solid fa-arrow-right ml-1"></i></a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs uppercase tracking-wider text-gray-400">
                    <th class="px-5 py-3 font-semibold">Producto</th>
                    <th class="px-5 py-3 font-semibold">Categoría</th>
                    <th class="px-5 py-3 font-semibold">Stock</th>
                    <th class="px-5 py-3 font-semibold">Mínimo</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($lowStockProducts) > 0): ?>
                    <?php $stockColors = ['bg-rose-100 text-rose-600', 'bg-sky-100 text-sky-600', 'bg-amber-100 text-amber-600', 'bg-emerald-100 text-emerald-600', 'bg-violet-100 text-violet-600', 'bg-teal-100 text-teal-600']; ?>
                    <?php foreach ($lowStockProducts as $item): ?>
                        <tr class="border-b border-gray-50 last:border-0 hover:bg-orange-50/40 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg <?= $stockColors[$item['id'] % count($stockColors)] ?> flex items-center justify-center shrink-0">
                                        <i class="fa-solid fa-cube text-xs"></i>
                                    </div>
                                    <span class="font-semibold text-gray-900"><?= esc($item['name']) ?></span>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500"><?= esc($item['category_name']) ?></td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600">
                                    <i class="fa-solid fa-triangle-exclamation"></i> <?= (int) $item['stock'] ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500"><?= (int) $item['min_stock'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">
                            <i class="fa-solid fa-circle-check text-3xl mb-2 block text-green-300"></i>
                            Todo el inventario está por encima del stock mínimo.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Colores aleatorios y vibrantes (no dependen del tema; nunca quedan grises)
    function hslToHex(h, s, l) {
        s /= 100; l /= 100;
        var c = (1 - Math.abs(2 * l - 1)) * s;
        var x = c * (1 - Math.abs(((h / 60) % 2) - 1));
        var m = l - c / 2;
        var r = 0, g = 0, b = 0;
        if (h < 60) { r = c; g = x; }
        else if (h < 120) { r = x; g = c; }
        else if (h < 180) { g = c; b = x; }
        else if (h < 240) { g = x; b = c; }
        else if (h < 300) { r = x; b = c; }
        else { r = c; b = x; }
        function cv(v) { return Math.round((v + m) * 255).toString(16).padStart(2, '0'); }
        return '#' + cv(r) + cv(g) + cv(b);
    }
    function randomPalette(count) {
        var colors = [];
        var hue = Math.floor(Math.random() * 360);
        for (var i = 0; i < count; i++) {
            hue = (hue + 137.508) % 360;
            colors.push(hslToHex(hue, 72, 54));
        }
        return colors;
    }

    // Evolución de ventas (área)
    var salesEl = document.getElementById('chartSales');
    if (salesEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(salesEl, {
            chart: {
                type: 'area',
                height: 300,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            series: [{ name: 'Ventas ($)', data: <?= json_encode($salesSeries) ?> }],
            xaxis: {
                categories: <?= json_encode($salesCategories) ?>,
                labels: { style: { colors: '#9ca3af' } },
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            yaxis: {
                labels: {
                    style: { colors: '#9ca3af' },
                    formatter: function (v) { return '$' + v.toFixed(0); }
                }
            },
            colors: [randomPalette(1)[0]],
            stroke: { curve: 'smooth', width: 3 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
            dataLabels: { enabled: false },
            grid: { borderColor: '#f3f4f6' },
            tooltip: { y: { formatter: function (v) { return '$' + v.toFixed(2); } } }
        }).render();
    }

    // Ventas por categoría (donut)
    var catEl = document.getElementById('chartCategory');
    if (catEl && typeof ApexCharts !== 'undefined') {
        var catSeries = <?= json_encode(array_map(fn($r) => (float) $r['total'], $salesByCategory)) ?>;
        var catLabels = <?= json_encode(array_map(fn($r) => $r['name'], $salesByCategory)) ?>;
        new ApexCharts(catEl, {
            chart: { type: 'donut', height: 300, fontFamily: 'inherit' },
            series: catSeries,
            labels: catLabels,
            colors: randomPalette(catLabels.length),
            legend: { position: 'bottom', labels: { colors: '#6b7280' }, fontSize: '13px' },
            stroke: { colors: ['#ffffff'], width: 2 },
            plotOptions: { pie: { donut: { size: '70%' } } },
            dataLabels: { enabled: false },
            tooltip: { y: { formatter: function (v) { return '$' + v.toFixed(2); } } }
        }).render();
    }

    // Productos más vendidos (barras horizontales)
    var topEl = document.getElementById('chartTopProducts');
    if (topEl && typeof ApexCharts !== 'undefined') {
        new ApexCharts(topEl, {
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Unidades vendidas', data: <?= json_encode(array_map(fn($r) => (int) $r['quantity'], $topProducts)) ?> }],
            plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%' } },
            xaxis: { labels: { style: { colors: '#9ca3af' } } },
            yaxis: { labels: { style: { colors: '#9ca3af' } } },
            colors: [randomPalette(1)[0]],
            grid: { borderColor: '#f3f4f6' },
            dataLabels: { enabled: false }
        }).render();
    }

    // Pedidos por estado (pie)
    var ordersEl = document.getElementById('chartOrders');
    if (ordersEl && typeof ApexCharts !== 'undefined') {
        var ordersLabels = <?= json_encode(array_map(fn($r) => $stateLabels[$r['state']] ?? $r['state'], $ordersByState)) ?>;
        var ordersSeries = <?= json_encode(array_map(fn($r) => (int) $r['count'], $ordersByState)) ?>;
        new ApexCharts(ordersEl, {
            chart: { type: 'pie', height: 300, fontFamily: 'inherit' },
            series: ordersSeries,
            labels: ordersLabels,
            colors: randomPalette(ordersLabels.length),
            legend: { position: 'bottom', labels: { colors: '#6b7280' }, fontSize: '13px' },
            stroke: { colors: ['#ffffff'], width: 2 },
            dataLabels: { enabled: false }
        }).render();
    }
});
</script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>