<?php

require_once __DIR__ . '/../models/Sale.php';
require_once __DIR__ . '/../models/CashRegister.php';
require_once __DIR__ . '/../models/AuditLog.php';

class PosController
{
    private $db;
    private $saleModel;
    private $auditModel;
    private $cashModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->saleModel = new Sale($db);
        $this->auditModel = new AuditLog($db);
        $this->cashModel = new CashRegister($db);
    }

    /**
     * Caja abierta del usuario en sesion. Solo quien tiene acceso al modulo
     * Caja opera bajo una caja: los demas (meseros, por ejemplo) venden sin ella.
     */
    private function cajaAbierta()
    {
        if (!puede('cash_register', 'view')) {
            return null;
        }

        $employeeId = (int) ($_SESSION['user']['id'] ?? 0);

        return $employeeId > 0 ? $this->cashModel->getOpenByEmployee($employeeId) : null;
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

        $requiereCaja = puede('cash_register', 'view');
        $caja = $requiereCaja ? $this->cajaAbierta() : null;

        $title = 'Punto de Venta';
        $currentModule = 'pos';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
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

        // Quien opera bajo una caja no puede vender sin caja abierta.
        $caja = $this->cajaAbierta();
        if (puede('cash_register', 'view') && !$caja) {
            flash('error', 'Tenés que abrir tu caja antes de registrar una venta.');
            header('Location: ' . url('cash_register'));
            exit;
        }

        try {
            $taxRate = (float) setting('tax_rate', 0);
            $employeeId = $_SESSION['user']['id'] ?? null;
            $cashRegisterId = $caja ? (int) $caja['id'] : null;
            $saleId = $this->saleModel->createSale($items, $payments, $taxRate, $employeeId, $cashRegisterId);

            $saleData = $this->saleModel->getTicketData($saleId);
            $this->auditModel->write('sale', 'ventas', $saleId, null, $saleData ?: null, 'Venta realizada.');
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

        $business = $this->getTicketBusiness();

        require_once __DIR__ . '/../vendor/autoload.php';

        $html = $this->renderTicketHtml($sale, $business);

        // Volcado opcional del HTML para inspeccionar el ticket sin abrir el PDF.
        if (getenv('TICKET_DEBUG_HTML')) {
            file_put_contents('/tmp/ticket_debug.html', $html);
        }

        // Papel térmico: 80 mm de ancho, largo variable según la cantidad de
        // líneas. Courier da la tipografía monoespaciada de una registradora.
        $mpdf = new \Mpdf\Mpdf([
            'format' => [80, 297],
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 3,
            'margin_bottom' => 3,
            'default_font' => 'courier',
            'default_font_size' => 7,
            'tempDir' => sys_get_temp_dir() . '/mpdf-tickets',
        ]);

        $mpdf->WriteHTML($html);

        $mpdf->Output('Ticket-' . str_pad($id, 6, '0', STR_PAD_LEFT) . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }

    /**
     * Datos fiscales y de cabecera que imprime el ticket. Todos son opcionales:
     * si no están configurados en /settings la línea simplemente se omite.
     */
    private function getTicketBusiness()
    {
        $taxRate = (float) setting('tax_rate', 0);

        return [
            'name' => setting('business_name', 'Panadería'),
            'company_name' => setting('company_name', ''),
            'tax_id' => setting('tax_id', ''),
            'tax_regime' => setting('tax_regime', ''),
            'activity' => setting('commercial_activity', ''),
            'address' => setting('address', ''),
            'phone' => setting('phone', ''),
            'currency' => setting('currency', '$'),
            'tax_rate' => $taxRate,
            'tax_label' => $taxRate > 0 ? number_format($taxRate, 2) . '%' : 'EXENTO',
            'terminal_id' => setting('terminal_id', '01'),
            'cashier_prefix' => setting('cashier_prefix', 'CAJ'),
            'ticket_footer' => setting('ticket_footer', '¡Gracias por su compra!'),
        ];
    }

    private function renderTicketHtml($sale, $business)
    {
        $cur = $business['currency'];
        $payLabels = [
            'efectivo' => 'EFECTIVO',
            'tarjeta' => 'TARJETA',
            'transferencia' => 'TRANSFERENCIA',
            'mixto' => 'MIXTO',
        ];

        $receipt = str_pad((int) $sale['id'], 6, '0', STR_PAD_LEFT);
        $dt = strtotime($sale['sale_date']);
        $cashier = $this->ticketCashier($sale, $business);

        // --- Encabezado ------------------------------------------------------
        $head = '<div class="c name">' . $this->e(mb_strtoupper($business['name'], 'UTF-8')) . '</div>'
            . '<div class="c s7 b t">TICKET DE VENTA</div>';

        $fiscal = [];
        if ($business['company_name']) {
            $fiscal[] = $business['company_name'];
        }
        if ($business['tax_id']) {
            $fiscal[] = 'RUC/NIT: ' . $business['tax_id'];
        }
        if ($business['tax_regime']) {
            $fiscal[] = $business['tax_regime'];
        }
        if ($business['activity']) {
            $fiscal[] = $business['activity'];
        }
        $fiscal[] = 'IVA: ' . $business['tax_label'];
        if ($business['address']) {
            $fiscal[] = $business['address'];
        }
        if ($business['phone']) {
            $fiscal[] = 'TEL: ' . $business['phone'];
        }
        foreach ($fiscal as $line) {
            $head .= '<div class="c s7">' . $this->e($line) . '</div>';
        }

        // --- Datos de la transaccion ---------------------------------------
        $info = '<table class="kv">'
            . $this->row('RECIBO N.', $receipt)
            . $this->row('CAJERO', $cashier)
            . $this->row('SUCURSAL', $this->settingOr('Principal', 'ticket_branch', 'Principal'))
            . $this->row('TERMINAL', $this->settingOr($business['terminal_id'], 'terminal_id', '01'))
            . $this->row('FECHA', date('d/m/Y', $dt))
            . $this->row('HORA', date('H:i:s', $dt))
            . $this->row('N. TRANSACCION', str_pad((int) $sale['id'] . date('Ymd', $dt), 15, '0', STR_PAD_LEFT))
            . $this->row('DOCUMENTO', 'TICKET VENTA')
            . $this->row('ESTADO', 'COMPLETADA')
            . '</table>';

        // --- Detalle de productos -------------------------------------------
        $rows = '';
        foreach ($sale['details'] as $it) {
            $qty = (int) $it['quantity'];
            $name = mb_strtoupper((string) $it['name'], 'UTF-8');

            $rows .= '<tr>'
                . '<td class="c">' . $qty . '</td>'
                . '<td>' . $this->e($this->padName($name, 22)) . '</td>'
                . '<td class="r">' . $this->e($this->money($cur, (float) $it['unit_price'])) . '</td>'
                . '<td class="r b">' . $this->e($this->money($cur, (float) $it['subtotal'])) . '</td>'
                . '</tr>';

            // El descuento vive en la fila de detalle (discount es negativo).
            if ((float) $it['discount'] < 0) {
                $rows .= '<tr>'
                    . '<td></td><td class="s7">DESCUENTO</td>'
                    . '<td></td>'
                    . '<td class="r s7">-' . $this->e($this->money($cur, abs((float) $it['discount']))) . '</td>'
                    . '</tr>';
            }
        }

        $items = '<table class="it">'
            . '<colgroup><col style="width:10%"><col style="width:40%">'
            . '<col style="width:25%"><col style="width:25%"></colgroup>'
            . '<tr class="hd">'
            . '<td class="c">CANT</td><td>DESCRIPCION</td>'
            . '<td class="r">P.UNIT</td><td class="r">IMPORTE</td>'
            . '</tr>'
            . '<tr class="hrule"><td colspan="4">&nbsp;</td></tr>'
            . $rows . '</table>';

        // --- Totales y pago --------------------------------------------------
        $paid = 0.0;
        $cash = 0.0;
        $payRows = '';
        foreach ($sale['payments'] as $p) {
            $amount = (float) $p['amount'];
            $paid += $amount;
            if ($p['payment_method'] === 'efectivo') {
                $cash += $amount;
            }
            $payRows .= '<tr><td>' . $this->e($payLabels[$p['payment_method']] ?? mb_strtoupper((string) $p['payment_method'], 'UTF-8'))
                . '</td><td class="r">' . $this->e($this->money($cur, $amount)) . '</td></tr>';
        }

        // Las ventas sembradas en init.sql no traen filas en sale_payments, solo
        // el metodo en ventas. Sin esto el pago y el cambio saldrian en $0.00.
        if (empty($sale['payments'])) {
            $paid = (float) $sale['total'];
            if ($sale['payment_method'] === 'efectivo') {
                $cash = (float) $sale['total'];
            }
            $payRows = '<tr><td>' . $this->e($payLabels[$sale['payment_method']] ?? 'MIXTO')
                . '</td><td class="r">' . $this->e($this->money($cur, $paid)) . '</td></tr>';
        }

        $totals = '<table class="kv">';
        if ((float) $sale['total_discount'] != 0.0) {
            $totals .= $this->row('DESCUENTO', '-' . $this->money($cur, (float) $sale['total_discount']));
        }
        $totals .= $this->row('SUBTOTAL', $this->money($cur, (float) $sale['subtotal']));
        $totals .= $this->row('IVA (' . $business['tax_label'] . ')', $this->money($cur, (float) $sale['tax']));
        $totals .= '</table>'
            . $this->rule(true)
            . '<table class="kv"><tr><td><b>TOTAL</b></td><td class="r"><b>'
            . $this->e($this->money($cur, (float) $sale['total'])) . '</b></td></tr>'
            . $payRows
            . $this->row('FORMA DE PAGO', $payLabels[$sale['payment_method']] ?? 'MIXTO')
            . $this->row('EFECTIVO RECIBIDO', $this->money($cur, $cash))
            . $this->row('CAMBIO', $this->money($cur, max($paid - (float) $sale['total'], 0)))
            . '</table>';

        // --- Pie -------------------------------------------------------------
        $control = $this->controlNumber($sale, $dt);
        $docId = 'DOC-' . $receipt . '-' . date('Ymd', $dt);

        $foot = '<table class="kv">'
            . $this->row('AUT. CONTROL', $control)
            . $this->row('DOC. ID', $docId)
            . '</table>'
            . '<div class="c s7 t">GRACIAS POR SU COMPRA</div>'
            . '<div class="c s7">Conserve este comprobante para su reclamo.</div>'
            . '<div class="c bcode"><barcode code="' . $this->e($docId) . '" type="C128B" size="0.5" height="2" color="0,0,0"></barcode></div>'
            . '<div class="c s7">' . $this->e($docId) . '</div>';

        $css = '<style>
            @page { background: #fbf9f0; }
            body { font-family: courier, monospace; font-size: 7pt; line-height: 1.3; color: #16150f; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 0; vertical-align: top; }
            .name { font-size: 8.5pt; font-weight: bold; }
            .t { margin-top: 1.5px; }
            .c { text-align: center; }
            .r { text-align: right; }
            .b { font-weight: bold; }
            .s7 { font-size: 6.2pt; }
            .kv td { padding: 0.6px 0; }
            .it td { padding: 0.6px 0; }
            /* mPDF no dibuja border en div, y en td deja huecos entre celdas:
               las lineas van en una fila propia que abarca todo el ancho. */
            .hrule td { padding: 0; font-size: 1pt; line-height: 1pt; border-bottom: 0.4pt dashed #55524a; }
            .rule td { padding: 0; font-size: 1pt; line-height: 1pt; border-bottom: 0.4pt dashed #55524a; }
            .rule.solid td { border-bottom: 0.5pt solid #16150f; }
            .bcode { margin: 4px 0 1px; }
        </style>';

        return '<html><head><meta charset="utf-8">' . $css . '</head><body>'
            . $head
            . $this->rule()
            . $info
            . $this->rule()
            . $items
            . $this->rule()
            . $totals
            . $this->rule()
            . $foot
            . '</body></html>';
    }

    /**
     * Separador de ancho completo. mPDF ignora el borde de un div vacio, asi
     * que se hace con una fila de una sola celda.
     */
    private function rule($solid = false)
    {
        return '<table class="rule' . ($solid ? ' solid' : '') . '">'
            . '<tr><td>&nbsp;</td></tr></table>';
    }

    /**
     * Fila "clave ... valor" para las tablas de dos columnas.
     */
    private function row($key, $value)
    {
        return '<tr><td>' . $this->e((string) $key) . '</td>'
            . '<td class="r">' . $this->e((string) $value) . '</td></tr>';
    }

    private function money($cur, $amount)
    {
        return $cur . number_format((float) $amount, 2);
    }

    /**
     * El POS no tiene selector de empleado: el cajero es quien inicia sesion.
     * Si la venta si tiene employee_id (carga manual o importacion) se prioriza.
     */
    private function ticketCashier($sale, $business)
    {
        $name = trim((string) ($sale['employee_name'] ?? '') . ' ' . (string) ($sale['employee_last_name'] ?? ''));

        if ($name === '') {
            $name = trim((string) ($_SESSION['user']['name'] ?? '') . ' ' . (string) ($_SESSION['user']['last_name'] ?? ''));
        }

        $prefix = trim((string) ($business['cashier_prefix'] ?? ''));

        // Sin nombre conocido solo se imprime la serie ("CAJ"), no "CAJ CAJERO".
        if ($name === '') {
            return $prefix;
        }

        $label = mb_strtoupper($name, 'UTF-8');

        return $prefix !== '' ? $prefix . ' ' . $label : $label;
    }

    private function settingOr($fallback, $key, $default)
    {
        $value = setting($key, '');

        return $value !== '' ? $value : ($fallback !== null ? $fallback : $default);
    }

    /**
     * Numero de autorizacion/control. Sin una serie configurada se deriva del
     * id de venta para que el ticket siempre muestre uno.
     */
    private function controlNumber($sale, $timestamp)
    {
        $series = setting('control_series', '');
        $number = str_pad((int) $sale['id'], 6, '0', STR_PAD_LEFT);

        if ($series !== '') {
            return $series . '-' . $number;
        }

        return 'A' . date('y', $timestamp) . $number;
    }

    private function padName($name, $max)
    {
        if (mb_strlen($name, 'UTF-8') <= $max) {
            return $name;
        }

        return mb_substr($name, 0, $max - 1, 'UTF-8') . '.';
    }

    private function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}