<?php

namespace ShopCode\Controllers\Admin;

use ShopCode\Controllers\BaseController;
use ShopCode\Core\Database;
use ShopCode\Models\AuditLog;

class SystemController extends BaseController
{
    public function index(): void
    {
        $db = Database::getInstance();

        // Počty tabulek
        $tables = [];
        foreach (['users','products','faqs','branches','events','xml_imports','xml_processing_queue','api_tokens','webhooks','audit_logs'] as $t) {
            $tables[$t] = (int)$db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        }

        // Stav XML fronty
        $stmt = $db->query("
            SELECT status, COUNT(*) as cnt
            FROM xml_processing_queue
            GROUP BY status
        ");
        $queueStats = [];
        foreach ($stmt->fetchAll() as $row) {
            $queueStats[$row['status']] = (int)$row['cnt'];
        }

        // Poslední chyby v XML importech
        $stmt = $db->query("
            SELECT xi.*, u.email FROM xml_imports xi
            LEFT JOIN users u ON u.id = xi.user_id
            WHERE xi.status = 'failed'
            ORDER BY xi.created_at DESC LIMIT 10
        ");
        $failedImports = $stmt->fetchAll();

        // PHP info
        $phpInfo = [
            'version'    => PHP_VERSION,
            'memory'     => ini_get('memory_limit'),
            'upload_max' => ini_get('upload_max_filesize'),
            'extensions' => ['pdo_mysql' => extension_loaded('pdo_mysql'), 'json' => extension_loaded('json'), 'mbstring' => extension_loaded('mbstring'), 'xml' => extension_loaded('xml'), 'curl' => extension_loaded('curl')],
        ];

        $this->view('admin/system/index', [
            'tables'        => $tables,
            'queueStats'    => $queueStats,
            'failedImports' => $failedImports,
            'phpInfo'       => $phpInfo,
        ], 'admin');
    }

    public function auditLog(): void
    {
        $page    = max(1, (int)$this->request->get('page', 1));
        $filters = array_filter([
            'user_id' => (int)$this->request->get('user_id'),
            'action'  => $this->request->get('action', ''),
        ]);

        $logs  = AuditLog::all($page, 50, $filters);
        $total = AuditLog::count($filters);

        $this->view('admin/system/audit_log', [
            'logs'    => $logs,
            'total'   => $total,
            'page'    => $page,
            'perPage' => 50,
            'filters' => $filters,
        ], 'admin');
    }
}
