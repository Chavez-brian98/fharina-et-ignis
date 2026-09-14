<?php

require_once __DIR__ . '/../models/Dashboard.php';

class DashboardController
{
    private $dashboardModel;

    public function __construct($db)
    {
        $this->dashboardModel = new Dashboard($db);
    }

    public function index()
    {
        $salesToday = $this->dashboardModel->getSalesToday();
        $salesMonth = $this->dashboardModel->getSalesMonth();
        $lowStockProducts = $this->dashboardModel->getLowStockProducts();
        $pendingOrders = $this->dashboardModel->getPendingOrders();
        $totalProducts = $this->dashboardModel->getTotalProducts();

        $lastDays = 14;
        $rows = $this->dashboardModel->getLastDaysSales($lastDays);
        $salesByDate = [];
        foreach ($rows as $row) {
            $salesByDate[$row['sale_date']] = (float) $row['total'];
        }
        $salesSeries = [];
        $salesCategories = [];
        for ($i = $lastDays - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $salesCategories[] = $date;
            $salesSeries[] = $salesByDate[$date] ?? 0;
        }

        $salesByCategory = $this->dashboardModel->getSalesByCategory();
        $topProducts = $this->dashboardModel->getTopProducts(5);
        $ordersByState = $this->dashboardModel->getOrdersByState();

        $stateLabels = [
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'en_produccion' => 'En producción',
            'listo' => 'Listo',
            'entregado' => 'Entregado',
            'rechazado' => 'Rechazado',
            'cancelado' => 'Cancelado',
        ];

        $title = 'Dashboard';
        $currentModule = 'dashboard';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Dashboard', 'url' => null],
        ];

        require_once __DIR__ . '/../views/dashboard/index.php';
    }
}