<?php

require_once __DIR__ . '/../models/Setting.php';

class SettingsController
{
    private $settingModel;

    public function __construct($db)
    {
        $this->settingModel = new Setting($db);
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
        $textFields = ['system_name', 'business_name', 'address', 'phone', 'currency', 'tax_rate', 'ticket_footer'];

        foreach ($textFields as $key) {
            $this->settingModel->update($key, trim($_POST[$key] ?? ''));
        }

        // system_name siempre refleja el nombre del negocio (en producción solo
        // se muestra el nombre del negocio, no el del sistema).
        $this->settingModel->update('system_name', trim($_POST['business_name'] ?? ''));

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

        flash('success', 'Configuración guardada correctamente.');
        header('Location: ' . url('settings'));
        exit;
    }
}