<?php

namespace ShopCode\Controllers\Admin;

use ShopCode\Controllers\BaseController;
use ShopCode\Core\Database;

class XmlQueueController extends BaseController
{
    public function index(): void
    {
        $db     = Database::getInstance();
        $page   = max(1, (int)$this->request->get('page', 1));
        $status = $this->request->get('status', '');

        $offset = ($page - 1) * 25;
        $params = [];

        if ($status) {
            $where  = "WHERE q.status = ?";
            $params[] = $status;
        } else {
            $where = '';
        }

        $stmt = $db->prepare("
            SELECT q.*, u.email, u.shop_name
            FROM xml_processing_queue q
            LEFT JOIN users u ON u.id = q.user_id
            {$where}
            ORDER BY q.priority ASC, q.created_at ASC
            LIMIT 25 OFFSET {$offset}
        ");
        $stmt->execute($params);
        $queue = $stmt->fetchAll();

        $countStmt = $db->prepare("SELECT COUNT(*) FROM xml_processing_queue " . ($status ? "WHERE status = ?" : ""));
        $countStmt->execute($status ? [$status] : []);
        $total = (int)$countStmt->fetchColumn();

        // Stats
        $stmt = $db->query("SELECT status, COUNT(*) as cnt FROM xml_processing_queue GROUP BY status");
        $stats = [];
        foreach ($stmt->fetchAll() as $r) {
            $stats[$r['status']] = (int)$r['cnt'];
        }

        $this->view('admin/xml_queue/index', [
            'queue'        => $queue,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => 25,
            'statusFilter' => $status,
            'stats'        => $stats,
        ], 'admin');
    }
}
