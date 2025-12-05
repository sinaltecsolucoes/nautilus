<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\AuditModel;

class AuditController extends BaseController
{
    private AuditModel $auditModel;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->auditModel = new AuditModel();
    }

    public function index(): void
    {
        $logs = $this->auditModel->getAllLogs();

        $data = [
            'title' => 'Auditoria do Sistema',
            'logs'  => $logs
        ];

        $this->view('auditoria/index', $data);
    }
}
