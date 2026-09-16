<?php

require_once __DIR__ . '/../models/Sale.php';

class PosController
{
    private $db;
    private $saleModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->saleModel = new Sale($db);
    }

    public function index()
    {
        $catalog = $this->saleModel->getCatalog();

        // Categorías únicas presentes en el catálogo (para los filtros del POS).
        $categories = [];
        $seen = [];
        foreach ($catalog as $p) {
            if (!isset($seen[$p['category_id']])) {
                $seen[$p['category_id']] = true;
                $categories[] = ['id' => $p['category_id'], 'name' => $p['category_name']];
            }
        }

        $title = 'Punto de Venta';
        $currentModule = 'pos';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => '/'],
            ['label' => 'Punto de Venta', 'url' => null],
        ];

        require_once __DIR__ . '/../views/pos/index.php';
    }

    public function checkout()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('pos'));
            exit;
        }

        $payload = json_decode($_POST['payload'] ?? '', true);

        if (!is_array($payload) || empty($payload['items']) || empty($payload['payments'])) {
            flash('error', 'Debe agregar productos y un método de pago.');
            header('Location: ' . url('pos'));
            exit;
        }

        $items = [];
        foreach ($payload['items'] as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId > 0 && $quantity > 0) {
                $items[] = ['product_id' => $productId, 'quantity' => $quantity];
            }
        }

        $payments = [];
        $methodNames = [
            'efectivo' => 'Efectivo',
            'tarjeta' => 'Tarjeta',
            'transferencia' => 'Transferencia',
        ];
        foreach ($payload['payments'] as $pay) {
            $method = $pay['method'] ?? '';
            $amount = round((float) ($pay['amount'] ?? 0), 2);
            if (isset($methodNames[$method]) && $amount > 0) {
                $payments[] = ['method' => $method, 'amount' => $amount];
            }
        }

        if (empty($items) || empty($payments)) {
            flash('error', 'Debe registrar productos y al menos un monto de pago válido.');
            header('Location: ' . url('pos'));
            exit;
        }

        try {
            $taxRate = (float) setting('tax_rate', 0);
            $saleId = $this->saleModel->createSale($items, $payments, $taxRate);
        } catch (Exception $e) {
            flash('error', $e->getMessage());
            header('Location: ' . url('pos'));
            exit;
        }

        header('Location: ' . url('pos/ticket/' . $saleId));
        exit;
    }

    public function ticket($id)
    {
        $sale = $this->saleModel->getTicketData($id);

        if (!$sale) {
            flash('error', 'Venta no encontrada.');
            header('Location: ' . url('pos'));
            exit;
        }

        $business = [
            'name' => setting('business_name', 'Panadería'),
            'address' => setting('address', ''),
            'phone' => setting('phone', ''),
            'currency' => setting('currency', '$'),
            'tax_rate' => (float) setting('tax_rate', 0),
            'ticket_footer' => setting('ticket_footer', '¡Gracias por su compra!'),
        ];

        require_once __DIR__ . '/../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'format' => [72, 297],
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 6,
            'margin_bottom' => 4,
            'default_font' => 'dejavusans',
            'tempDir' => sys_get_temp_dir() . '/mpdf-tickets',
        ]);

        $html = $this->renderTicketHtml($sale, $business);

        $mpdf->WriteHTML($html);

        $mpdf->Output('Ticket-' . str_pad($id, 6, '0', STR_PAD_LEFT) . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }

    private function renderTicketHtml($sale, $business)
    {
        $cur = $business['currency'];
        $payLabels = ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia', 'mixto' => 'Mixto'];

        $lines = '';
        $items = $sale['details'];
        foreach ($items as $it) {
            $name = $this->ticketShorten($it['name'], 22);
            $lines .= '<tr>'
                . '<td style="font-size:9px;line-height:1.5;">' . $this->e($name) . '</td>'
                . '<td style="font-size:9px;text-align:right;white-space:nowrap;line-height:1.5;">' . (int) $it['quantity'] . ' x ' . number_format((float) $it['unit_price'], 2) . '</td>'
                . '</tr>'
                . '<tr>'
                . '<td></td>'
                . '<td style="font-size:9px;text-align:right;white-space:nowrap;line-height:1.5;color:#333;">= ' . $cur . number_format((float) $it['subtotal'], 2) . '</td>'
                . '</tr>';
        }

        $payRows = '';
        $paid = 0.0;
        foreach ($sale['payments'] as $p) {
            $paid += (float) $p['amount'];
            $label = $payLabels[$p['payment_method']] ?? ucfirst($p['payment_method']);
            $payRows .= '<tr>'
                . '<td style="font-size:9px;line-height:1.5;">' . $this->e($label) . '</td>'
                . '<td style="font-size:9px;text-align:right;white-space:nowrap;line-height:1.5;">' . $cur . number_format((float) $p['amount'], 2) . '</td>'
                . '</tr>';
        }

        $discountRows = '';
        if ((float) $sale['total_discount'] > 0) {
            $discountRows .= '<tr>'
                . '<td style="font-size:9px;">Descuento</td>'
                . '<td style="font-size:9px;text-align:right;">-' . $cur . number_format((float) $sale['total_discount'], 2) . '</td>'
                . '</tr>';
        }
        $taxRows = '';
        if ((float) $sale['tax'] > 0) {
            $taxRows .= '<tr>'
                . '<td style="font-size:9px;">Impuesto</td>'
                . '<td style="font-size:9px;text-align:right;">' . $cur . number_format((float) $sale['tax'], 2) . '</td>'
                . '</tr>';
        }

        $header = '';
        if (setting('system_logo')) {
            $header = '<div style="text-align:center;margin-bottom:4px;"><img src="' . $this->e(setting('system_logo')) . '" style="width:32mm;max-height:18mm;object-fit:contain;" /></div>';
        }

        $dateTime = date('d/m/Y H:i', strtotime($sale['sale_date']));

        return '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:dejavusans, sans-serif;}'
            . 'table{width:100%;border-collapse:collapse;}'
            . '</style></head><body>'
            . $header
            . '<div style="text-align:center;font-size:12px;font-weight:bold;">' . $this->e($business['name']) . '</div>'
            . ($business['address'] ? '<div style="text-align:center;font-size:8px;">' . $this->e($business['address']) . '</div>' : '')
            . ($business['phone'] ? '<div style="text-align:center;font-size:8px;">Tel: ' . $this->e($business['phone']) . '</div>' : '')
            . '<div style="border-top:1px dashed #000;margin:5px 0;"></div>'
            . '<table><tr><td style="font-size:9px;">Ticket N°</td><td style="font-size:9px;text-align:right;font-weight:bold;">' . str_pad((int) $sale['id'], 6, '0', STR_PAD_LEFT) . '</td></tr>'
            . '<tr><td style="font-size:9px;">Fecha</td><td style="font-size:9px;text-align:right;">' . $dateTime . '</td></tr></table>'
            . '<div style="border-top:1px dashed #000;margin:5px 0;"></div>'
            . '<table>' . $lines . '</table>'
            . '<div style="border-top:1px dashed #000;margin:5px 0;"></div>'
            . '<table>'
            . '<tr><td style="font-size:9px;">Subtotal</td><td style="font-size:9px;text-align:right;">' . $cur . number_format((float) $sale['subtotal'], 2) . '</td></tr>'
            . $discountRows
            . $taxRows
            . '<tr><td style="font-size:11px;font-weight:bold;">TOTAL</td><td style="font-size:11px;font-weight:bold;text-align:right;">' . $cur . number_format((float) $sale['total'], 2) . '</td></tr>'
            . '</table>'
            . '<div style="border-top:1px dashed #000;margin:5px 0;"></div>'
            . '<table>' . $payRows
            . '<tr><td style="font-size:9px;font-weight:bold;">Pagado</td><td style="font-size:9px;font-weight:bold;text-align:right;">' . $cur . number_format($paid, 2) . '</td></tr>'
            . '<tr><td style="font-size:9px;font-weight:bold;">Vuelto</td><td style="font-size:9px;font-weight:bold;text-align:right;">' . $cur . number_format(max($paid - (float) $sale['total'], 0), 2) . '</td></tr>'
            . '</table>'
            . '<div style="border-top:1px dashed #000;margin:5px 0;"></div>'
            . '<div style="text-align:center;font-size:9px;font-weight:bold;">' . $this->e($business['ticket_footer']) . '</div>'
            . '<div style="text-align:center;font-size:7px;margin-top:2px;">' . $this->e('Sistema de Ventas') . '</div>'
            . '</body></html>';
    }

    private function ticketShorten($text, $max)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8');
        if (mb_strlen($text, 'UTF-8') > $max) {
            return mb_substr($text, 0, $max, 'UTF-8') . '…';
        }
        return $text;
    }

    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}