<?php

require_once __DIR__ . '/../models/Setting.php';
require_once __DIR__ . '/../models/AuditLog.php';

class SettingsController
{
    private $db;
    private $settingModel;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->settingModel = new Setting($db);
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $settings = $this->settingModel->getAll();

        $title = 'Configuración';
        $currentModule = 'settings';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Configuración'],
        ];

        require_once __DIR__ . '/../views/settings/index.php';
    }

    public function update()
    {
        $before = $this->settingModel->getAll();

        $textFields = ['system_name', 'business_name', 'address', 'phone', 'currency', 'tax_rate', 'ticket_footer',
            'tax_id', 'tax_regime', 'commercial_activity', 'company_name', 'cashier_prefix', 'terminal_id',
            'ticket_footer'];

        foreach ($textFields as $key) {
            $this->settingModel->update($key, trim($_POST[$key] ?? ''));
        }

        // Fondo base con el que se abre una caja (debe ser un monto positivo).
        $baseAmount = str_replace(',', '', trim($_POST['cash_register_base'] ?? ''));
        if ($baseAmount !== '' && (!is_numeric($baseAmount) || (float) $baseAmount < 0)) {
            flash('error', 'El fondo base de caja debe ser un monto válido mayor o igual a cero.');
            header('Location: ' . url('settings'));
            exit;
        }
        $this->settingModel->update('cash_register_base', number_format((float) $baseAmount, 2, '.', ''));

        // system_name siempre refleja el nombre del negocio (en producción solo
        // se muestra el nombre del negocio, no el del sistema).
        $this->settingModel->update('system_name', trim($_POST['business_name'] ?? ''));

        // Color principal del tema (hex #RRGGBB)
        $primaryColor = strtolower(trim($_POST['primary_color'] ?? ''));
        if ($primaryColor !== '' && !preg_match('/^#[0-9a-f]{6}$/', $primaryColor)) {
            flash('error', 'El color principal debe ser un código hexadecimal válido (#RRGGBB).');
            header('Location: ' . url('settings'));
            exit;
        }
        $this->settingModel->update('primary_color', $primaryColor !== '' ? $primaryColor : '#f97316');

        foreach (['system_logo', 'login_photo'] as $field) {
            $path = upload_image($field);
            if ($path === false) {
                header('Location: ' . url('settings'));
                exit;
            }
            if ($path !== null) {
                $this->settingModel->update($field, $path);
            }
        }

        $after = $this->settingModel->getAll();
        $this->auditModel->write('update', 'settings', null, $before, $after, 'Configuración del sistema actualizada.');

        flash('success', 'Configuración guardada correctamente.');
        header('Location: ' . url('settings'));
        exit;
    }
}