<?php

require_once __DIR__ . '/../models/AuditLog.php';

class AuditController
{
    private $db;
    private $auditModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->auditModel = new AuditLog($db);
    }

    public function index()
    {
        $auditLogs = $this->auditModel->getAll();
        $actions = $this->auditModel->getActions();
        $tables = $this->auditModel->getTables();

        $title = 'Bitácora';
        $currentModule = 'audit';
        $breadcrumbs = [
            ['label' => 'Sistema', 'url' => url('dashboard')],
            ['label' => 'Bitácora', 'url' => null],
        ];

        require_once __DIR__ . '/../views/audit/index.php';
    }
}