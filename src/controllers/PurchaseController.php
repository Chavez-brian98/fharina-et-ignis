<?php

require_once __DIR__ . '/../models/Purchase.php';
require_once __DIR__ . '/../models/Ingredient.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/AuditLog.php';

/**
 * Compras (órdenes de compra a proveedores).
 *
 * Estados: pendiente → parcial → recibida, más cancelada.
 * - store/update/delete exigen los permisos create/edit/delete del catálogo.
 * - receive exige 'edit' (ver Permiso::accionDeRuta) porque mueve stock.
 * - cancel exige 'delete' por la misma razón que en el resto de módulos:
 *   descarta la orden.
 */
class PurchaseController
{
    private $db;
    private $purchaseModel;
    private $ingredientModel;
    private $supplierModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->purchaseModel = new Purchase($db);
        $this->ingredientModel = new Ingredient($db);
        $this->supplierModel = new Supplier($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $purchases = $this->purchaseModel->getAll();
        $suppliers = $this->supplierModel->getAllForSelect();

        $title = 'Compras';
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/index.php';
    }

    public function create()
    {
        $suppliers = $this->supplierModel->getAllForSelect();
        $ingredients = $this->ingredientModel->getAll();
        $offerPrices = $this->supplierModel->getOffersPriceMap();

        $title = 'Nueva Compra';
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => url('purchases')],
            ['label' => 'Nueva', 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/create.php';
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('purchases/create'));
            exit;
        }

        $orderId = $this->purchaseModel->create($data, $items);

        if ($orderId) {
            $new = $this->purchaseModel->getById($orderId);
            $this->auditModel->write('create', 'purchase_orders', $orderId, null, $new ?: null, 'Orden de compra #' . $orderId . ' creada.');
            flash('success', 'Orden de compra #' . $orderId . ' creada correctamente.');
            header('Location: ' . url('purchases/edit/' . $orderId));
            exit;
        }

        flash('error', 'No se pudo crear la orden de compra.');
        header('Location: ' . url('purchases/create'));
        exit;
    }

    public function edit($id)
    {
        $purchase = $this->purchaseModel->getById($id);

        if (!$purchase) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        $details = $this->purchaseModel->getDetails($id);
        $receipts = $this->purchaseModel->getReceipts($id);
        $receivedTotals = $this->purchaseModel->getReceivedTotals($id);

        $suppliers = $this->supplierModel->getAllForSelect();
        $ingredients = $this->ingredientModel->getAll();
        $offerPrices = $this->supplierModel->getOffersPriceMap();

        $editable = $purchase['state'] === 'pendiente' && !$receipts;

        $title = 'Editar Compra #' . $id;
        $currentModule = 'purchases';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Compras', 'url' => url('purchases')],
            ['label' => 'Compra #' . $id, 'url' => null],
        ];

        require_once __DIR__ . '/../views/purchases/edit.php';
    }

    /**
     * Hoja imprimible A4 con la orden de compra completa (proveedor, dirección
     * de envío, líneas y totales). PDF en línea: el navegador lo muestra y es un
     * Ctrl+P directo para dárselo al proveedor o archivarlo.
     */
    public function pdf($id)
    {
        $purchase = $this->purchaseModel->getById($id);

        if (!$purchase) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        $details = $this->purchaseModel->getDetails($id);
        $supplier = $this->supplierModel->getById((int) $purchase['supplier_id']);

        require_once __DIR__ . '/../vendor/autoload.php';

        $html = $this->renderPdfOrden($purchase, $details, $supplier);

        $mpdf = new \Mpdf\Mpdf([
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 14,
            'default_font' => 'dejavusans',
            'default_font_size' => 9,
            'tempDir' => sys_get_temp_dir() . '/mpdf-compras',
        ]);

        $negocio = setting('business_name', 'Fharina et Ignis');
        $mpdf->SetTitle('Orden de compra #' . $id, true);
        $mpdf->SetAuthor($negocio, true);
        $mpdf->SetSubject('Orden de compra #' . $id . ' — ' . $purchase['supplier_name'], true);

        $ePdf = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $mpdf->SetFooter('<table style="width:100%;font-size:6.5pt;color:#9ca3af;border-collapse:collapse">'
            . '<tr><td style="text-align:left">' . $ePdf($negocio) . '</td>'
            . '<td style="text-align:center">Orden de compra #' . (int) $id . '</td>'
            . '<td style="text-align:right">P&aacute;gina {PAGENO} de {nbpg}</td>'
            . '</tr></table>');

        $mpdf->WriteHTML($html);
        $mpdf->Output('Orden-Compra-' . (int) $id . '.pdf', \Mpdf\Output\Destination::INLINE);
        exit;
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($before['state'] !== 'pendiente' || $this->purchaseModel->countReceipts($id) > 0) {
            flash('error', 'Solo se puede editar una orden pendiente y sin recepciones registradas.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $data = $this->datosDeCabecera();
        $items = $this->itemsDelPost();

        if ($data === null || $items === null) {
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        if ($this->purchaseModel->update($id, $data, $items)) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('update', 'purchase_orders', $id, $before, $after ?: null, 'Orden de compra #' . $id . ' actualizada.');
            flash('success', 'Orden de compra #' . $id . ' actualizada correctamente.');
        } else {
            flash('error', 'No se pudo actualizar la orden de compra.');
        }

        header('Location: ' . url('purchases/edit/' . $id));
        exit;
    }

    public function receive($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('purchases'));
            exit;
        }

        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if (in_array($before['state'], ['recibida', 'cancelada'], true)) {
            flash('error', 'No se puede recibir mercancía en una orden ' . Purchase::estadoTexto($before['state']) . '.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        // El POST trae received[<detail_id>] con la cantidad real de cada línea.
        $posted = $_POST['received'] ?? [];
        $recibido = [];

        if (is_array($posted)) {
            foreach ($posted as $detailId => $cantidad) {
                $cantidad = (float) str_replace(',', '.', (string) $cantidad);
                if ($cantidad > 0) {
                    $recibido[(int) $detailId] = $cantidad;
                }
            }
        }

        if (!$recibido) {
            flash('error', 'Indica al menos una cantidad recibida mayor que cero.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $observations = trim($_POST['observations'] ?? '');

        if (mb_strlen($observations) > 500) {
            flash('error', 'Las observaciones no pueden superar los 500 caracteres.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $employeeId = $this->employeeIdActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión para registrar la recepción.');
            header('Location: ' . url('purchases/edit/' . $id));
            exit;
        }

        $resultado = $this->purchaseModel->receive($id, $recibido, $employeeId, $observations);

        if ($resultado) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('receive', 'purchase_orders', $id, $before, $after ?: null,
                'Mercancía recibida en la orden #' . $id . ' (' . $resultado['lineas'] . ' línea(s), estado ' . Purchase::estadoTexto($resultado['estado']) . ').');
            flash('success', 'Recepción registrada: la orden #' . $id . ' quedó ' . Purchase::estadoTexto($resultado['estado']) . '.');
        } else {
            flash('error', 'No se pudo registrar la recepción de mercancía.');
        }

        header('Location: ' . url('purchases/edit/' . $id));
        exit;
    }

    public function cancel($id)
    {
        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if (in_array($before['state'], ['recibida', 'cancelada'], true)) {
            flash('error', 'La orden #' . $id . ' ya está ' . Purchase::estadoTexto($before['state']) . '.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($this->purchaseModel->cancel($id)) {
            $after = $this->purchaseModel->getById($id);
            $this->auditModel->write('cancel', 'purchase_orders', $id, $before, $after ?: null, 'Orden de compra #' . $id . ' cancelada.');
            flash('success', 'Orden de compra #' . $id . ' cancelada.');
        } else {
            flash('error', 'No se pudo cancelar la orden de compra.');
        }

        header('Location: ' . url('purchases'));
        exit;
    }

    public function delete($id)
    {
        $before = $this->purchaseModel->getById($id);

        if (!$before) {
            flash('error', 'Orden de compra no encontrada.');
            header('Location: ' . url('purchases'));
            exit;
        }

        $receipts = $this->purchaseModel->countReceipts($id);

        if ($receipts > 0) {
            flash('error', 'No se puede eliminar: la orden #' . $id . ' tiene ' . $receipts . ' recepción(es) de mercancía registrada(s). Cancélala en su lugar.');
            header('Location: ' . url('purchases'));
            exit;
        }

        if ($this->purchaseModel->delete($id)) {
            $this->auditModel->write('delete', 'purchase_orders', $id, $before, null, 'Orden de compra #' . $id . ' eliminada.');
            flash('success', 'Orden de compra #' . $id . ' eliminada.');
        } else {
            flash('error', 'No se pudo eliminar la orden de compra.');
        }

        header('Location: ' . url('purchases'));
        exit;
    }

    /**
     * Valida la cabecera del POST. Devuelve null (con el flash puesto) si algo
     * no cuadra, para que la vista redibuje el formulario.
     */
    private function datosDeCabecera()
    {
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $orderDate = trim($_POST['order_date'] ?? '');
        $estimated = trim($_POST['estimated_delivery_date'] ?? '');

        if ($supplierId <= 0 || !$this->purchaseModel->supplierExists($supplierId)) {
            flash('error', 'Selecciona un proveedor válido.');
            return null;
        }

        if ($orderDate === '') {
            flash('error', 'La fecha de la orden es obligatoria.');
            return null;
        }

        if (!$this->fechaValida($orderDate)) {
            flash('error', 'La fecha de la orden no tiene un formato válido.');
            return null;
        }

        if ($estimated !== '' && !$this->fechaValida($estimated)) {
            flash('error', 'La fecha estimada de entrega no tiene un formato válido.');
            return null;
        }

        if ($estimated !== '' && $estimated < $orderDate) {
            flash('error', 'La entrega estimada no puede ser anterior a la fecha de la orden.');
            return null;
        }

        $employeeId = $this->employeeIdActual();

        if (!$employeeId) {
            flash('error', 'No se pudo identificar al usuario en sesión.');
            return null;
        }

        return [
            'supplier_id' => $supplierId,
            'employee_id' => $employeeId,
            'order_date' => $orderDate,
            'estimated_delivery_date' => $estimated,
        ];
    }

    /**
     * Normaliza las líneas del POST: Descarta las filas vacías y descarta
     * ingredientes repetidos (se suman las cantidades). Devuelve null con flash
     * si no queda ninguna línea válida.
     */
    private function itemsDelPost()
    {
        $ingredientIds = $_POST['ingredient_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['unit_price'] ?? [];

        if (!is_array($ingredientIds) || !is_array($quantities) || !is_array($prices)) {
            flash('error', 'Las líneas de la orden no se recibieron correctamente.');
            return null;
        }

        $items = [];
        $vistos = [];

        foreach ($ingredientIds as $i => $rawId) {
            $ingredientId = (int) $rawId;

            // Fila vacía: el usuario añadió una línea y la borró.
            if ($ingredientId <= 0) {
                continue;
            }

            if (!$this->ingredientModel->isActive($ingredientId)) {
                flash('error', 'Uno de los ingredientes seleccionados no está activo.');
                return null;
            }

            $quantity = (float) str_replace(',', '.', (string) ($quantities[$i] ?? 0));
            $price = (float) str_replace(',', '.', (string) ($prices[$i] ?? 0));

            if ($quantity <= 0) {
                flash('error', 'Indica una cantidad mayor que cero para cada ingrediente.');
                return null;
            }

            if ($price < 0) {
                flash('error', 'El precio unitario no puede ser negativo.');
                return null;
            }

            if (isset($vistos[$ingredientId])) {
                // Mismo ingrediente en dos filas: se acumula para no duplicar
                // purchase_order_details (el detalle es único por orden).
                $items[$vistos[$ingredientId]]['quantity'] += $quantity;
                continue;
            }

            $vistos[$ingredientId] = count($items);
            $items[] = [
                'ingredient_id' => $ingredientId,
                'quantity' => $quantity,
                'unit_price' => $price,
            ];
        }

        if (!$items) {
            flash('error', 'Agrega al menos un ingrediente a la orden.');
            return null;
        }

        return $items;
    }

    private function fechaValida($date)
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        return $d && $d->format('Y-m-d') === $date;
    }

    private function employeeIdActual()
    {
        $id = (int) ($_SESSION['user']['id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * HTML del PDF de la orden de compra. Tablas con border-collapse:collapse y
     * celdas de 1px para los separadores (mismo criterio que el ticket del POS
     * y los horarios): mPDF descuadra <div>/<hr> y deja huecos entre celdas.
     */
    private function renderPdfOrden($purchase, $details, $supplier)
    {
        $negocio = setting('business_name', 'Fharina et Ignis');
        $direccionEnvio = setting('address', '');
        $telefono = setting('phone', '');
        $moneda = setting('currency', '$');

        $primario = $this->colorDeTema();
        $primarioSuave = $this->aclara($primario, 0.93);
        $primarioClaro = $this->aclara($primario, 0.86);
        $logo = $this->logoParaPdf();

        $e = function ($v) {
            return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        };
        $fecha = function ($v) {
            return $v ? date('d/m/Y', strtotime($v)) : '—';
        };

        $estados = [
            'pendiente' => ['Pendiente', '#d97706'],
            'parcial' => ['Parcial', '#0284c7'],
            'recibida' => ['Recibida', '#16a34a'],
            'cancelada' => ['Cancelada', '#6b7280'],
        ];
        [$estadoTexto, $estadoColor] = $estados[$purchase['state']] ?? ['Desconocido', '#6b7280'];

        $filas = '';
        $total = 0.0;
        $n = 0;
        foreach ($details as $d) {
            $n++;
            $cantidad = (float) $d['quantity'];
            $precio = (float) $d['unit_price'];
            $subtotal = $cantidad * $precio;
            $total += $subtotal;
            $fondo = $n % 2 === 0 ? '#ffffff' : $primarioSuave;
            $filas .= '<tr style="background-color:' . $fondo . '">'
                . '<td style="padding:4pt 5pt;text-align:center;color:#6b7280">' . $n . '</td>'
                . '<td style="padding:4pt 5pt;font-weight:bold">' . $e($d['ingredient_name']) . '</td>'
                . '<td style="padding:4pt 5pt;text-align:right;white-space:nowrap">' . number_format($cantidad, 2) . '</td>'
                . '<td style="padding:4pt 5pt;text-align:center">' . $e($d['unit_of_measure']) . '</td>'
                . '<td style="padding:4pt 5pt;text-align:right;white-space:nowrap">' . $e($moneda) . ' ' . number_format($precio, 2) . '</td>'
                . '<td style="padding:4pt 5pt;text-align:right;white-space:nowrap">' . $e($moneda) . ' ' . number_format($subtotal, 2) . '</td>'
                . '</tr>';
        }

        $logoHtml = ($logo !== null)
            ? '<td style="width:45mm;padding:0"><img src="' . $e($logo) . '" style="width:42mm;height:auto" /></td>'
            : '<td style="width:45mm;padding:0"><span style="font-size:13pt;font-weight:bold;color:' . $primario . '">' . $e($negocio) . '</span></td>';

        $html = '<table style="width:100%;border-collapse:collapse">'
            . '<tr>'
            . $logoHtml
            . '<td style="vertical-align:top;text-align:right">'
            .   '<span style="font-size:6pt;color:#9ca3af;letter-spacing:1pt">DOCUMENTO INTERNO</span><br/>'
            .   '<span style="font-size:16pt;font-weight:bold;color:' . $primario . '">ORDEN DE COMPRA</span><br/>'
            .   '<span style="font-size:11pt;font-weight:bold">#' . str_pad((string) (int) $purchase['id'], 4, '0', STR_PAD_LEFT) . '</span>'
            .   ' &nbsp; <span style="font-size:8pt;color:#ffffff;background-color:' . $estadoColor . ';padding:1.5pt 5pt;border-radius:6pt">' . $e($estadoTexto) . '</span>'
            . '</td>'
            . '</tr>'
            . '<tr><td colspan="2" style="padding:6pt 0 0 0">'
            . '<table style="width:100%;border-collapse:collapse">'
            .   '<tr><td style="background-color:' . $primarioClaro . ';border:0.5pt solid ' . $primario . ';padding:6pt 7pt">'
            .     '<span style="font-size:8pt;font-weight:bold;color:' . $primario . '">ENTREGAR EN &mdash; DIRECCIÓN DE ENVÍO</span><br/>'
            .     '<span style="font-size:10.5pt;font-weight:bold">' . $e($negocio) . '</span><br/>'
            .     ($direccionEnvio !== '' ? $e($direccionEnvio) . '<br/>' : '')
            .     ($telefono !== '' ? 'Tel: ' . $e($telefono) : '')
            .   '</td></tr>'
            . '</table>'
            . '</td></tr>'
            . '<tr>'
            . '<td style="width:50%;padding:7pt 0 0 0;vertical-align:top">'
            .   '<table style="width:100%;border-collapse:collapse">'
            .     '<tr><td colspan="2" style="padding:0 0 2pt 0;font-size:8pt;font-weight:bold;color:' . $primario . '">PROVEEDOR</td></tr>'
            .     '<tr><td style="padding:0;font-size:10.5pt;font-weight:bold">' . $e($purchase['supplier_name']) . '</td></tr>'
            .     ($purchase['supplier_tax_id']
                ? '<tr><td style="padding:0;color:#374151">NIT/RUC: ' . $e($purchase['supplier_tax_id']) . '</td></tr>' : '')
            .     ($purchase['supplier_contact']
                ? '<tr><td style="padding:0;color:#374151">Contacto: ' . $e($purchase['supplier_contact']) . '</td></tr>' : '')
            .     ($supplier && $supplier['phone']
                ? '<tr><td style="padding:0;color:#374151">Tel: ' . $e($supplier['phone']) . '</td></tr>' : '')
            .     ($supplier && $supplier['email']
                ? '<tr><td style="padding:0;color:#374151">Email: ' . $e($supplier['email']) . '</td></tr>' : '')
            .     ($supplier && $supplier['address']
                ? '<tr><td style="padding:0;color:#374151">' . $e($supplier['address']) . '</td></tr>' : '')
            .     ($supplier && $supplier['payment_terms']
                ? '<tr><td style="padding:0;color:#374151">Condiciones: ' . $e($supplier['payment_terms']) . '</td></tr>' : '')
            .   '</table>'
            . '</td>'
            . '<td style="width:50%;padding:7pt 0 0 10pt;vertical-align:top">'
            .   '<table style="width:100%;border-collapse:collapse">'
            .     '<tr><td style="padding:0 0 2pt 0;font-size:8pt;font-weight:bold;color:' . $primario . '">DETALLE DE LA ORDEN</td></tr>'
            .     '<tr><td style="padding:0;color:#374151">Fecha de la orden: <b>' . $fecha($purchase['order_date']) . '</b></td></tr>'
            .     '<tr><td style="padding:0;color:#374151">Entrega estimada: <b>' . $fecha($purchase['estimated_delivery_date']) . '</b></td></tr>'
            .     '<tr><td style="padding:0;color:#374151">Registró: ' . $e(trim($purchase['employee_name'] . ' ' . $purchase['employee_last_name'])) . '</td></tr>'
            .     '<tr><td style="padding:0;color:#374151">Líneas: ' . count($details) . '</td></tr>'
            .   '</table>'
            . '</td>'
            . '</tr>'
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse">'
            . '<colgroup><col style="width:7%"/><col style="width:38%"/><col style="width:14%"/><col style="width:12%"/><col style="width:15%"/><col style="width:14%"/></colgroup>'
            . '<thead><tr style="background-color:' . $primario . ';color:#ffffff">'
            .   '<th style="padding:4pt 5pt;text-align:center">#</th>'
            .   '<th style="padding:4pt 5pt;text-align:left">Ingrediente</th>'
            .   '<th style="padding:4pt 5pt;text-align:right">Cantidad</th>'
            .   '<th style="padding:4pt 5pt;text-align:center">Unidad</th>'
            .   '<th style="padding:4pt 5pt;text-align:right">P. Unitario</th>'
            .   '<th style="padding:4pt 5pt;text-align:right">Subtotal</th>'
            . '</tr></thead>'
            . '<tbody>'
            .   $filas
            .   '<tr style="background-color:' . $primarioSuave . '">'
            .     '<td colspan="5" style="padding:5pt 5pt;text-align:right;font-weight:bold;border-top:1.5pt solid ' . $primario . '">TOTAL DE LA ORDEN</td>'
            .     '<td style="padding:5pt 5pt;text-align:right;font-weight:bold;border-top:1.5pt solid ' . $primario . ';white-space:nowrap">' . $e($moneda) . ' ' . number_format($total, 2) . '</td>'
            .   '</tr>'
            . '</tbody>'
            . '</table>'
            . '<table style="width:100%;border-collapse:collapse">'
            .   '<tr><td style="padding:10pt 0 0 0;font-style:italic;color:#6b7280;font-size:8.5pt">Coordinar la entrega con el proveedor en la dirección de envío indicada. Gracias por su servicio.</td></tr>'
            . '</table>'
            . '<table style="width:100%;margin-top:22pt;border-collapse:collapse">'
            .   '<tr>'
            .     '<td style="width:50%;border-top:0.75pt solid #9ca3af;text-align:center;color:#6b7280;font-size:8pt;padding-top:4pt">Firma y sello del proveedor</td>'
            .     '<td style="width:6%"></td>'
            .     '<td style="width:44%;border-top:0.75pt solid #9ca3af;text-align:center;color:#6b7280;font-size:8pt;padding-top:4pt">Recibido por</td>'
            .   '</tr>'
            . '</table>';

        return $html;
    }

    /** Color de acento del sistema (primary_color). Cae al naranja de la marca. */
    private function colorDeTema()
    {
        $color = strtolower(trim((string) setting('primary_color', '')));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '#f97316';
    }

    /** Mezcla un color con blanco: $blanco 0.86 deja un tono muy claro. */
    private function aclara($hex, $blanco)
    {
        if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string) $hex, $m)) {
            return '#f3f4f6';
        }

        $out = '#';
        for ($i = 1; $i <= 3; $i++) {
            $v = (int) hexdec($m[$i]);
            $out .= str_pad(dechex((int) round($v + ((255 - $v) * $blanco))), 2, '0', STR_PAD_LEFT);
        }

        return $out;
    }

    /**
     * Ruta del logo para mPDF. La web guarda "/uploads/x.png" (lo resuelve el
     * navegador); mPDF necesita la ruta real en disco o una URL absoluta.
     */
    private function logoParaPdf()
    {
        $logo = trim((string) setting('system_logo', ''));
        if ($logo === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $logo)) {
            return $logo;
        }

        $rel = ltrim((string) parse_url($logo, PHP_URL_PATH), '/');
        if ($rel === '' || strpos($rel, '..') !== false) {
            return null;
        }

        $abs = dirname(__DIR__) . '/public/' . $rel;

        return is_file($abs) ? $abs : null;
    }
}
