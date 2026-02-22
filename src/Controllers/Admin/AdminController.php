<?php

namespace ShopCode\Controllers\Admin;

use ShopCode\Controllers\BaseController;
use ShopCode\Core\Database;
use ShopCode\Models\User;

class AdminController extends BaseController
{
    public function dashboard(): void
    {
        $db = Database::getInstance();

        $userStats = User::countByStatus();
        $totalUsers = array_sum($userStats);

        $stmt = $db->query('SELECT COUNT(*) FROM products');
        $totalProducts = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM xml_imports WHERE status = 'completed'");
        $totalImports = (int)$stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM xml_processing_queue WHERE status = 'pending'");
        $queuePending = (int)$stmt->fetchColumn();

        // Poslední registrace
        $recentUsers = User::all([], 1, 5);

        // Poslední audit logy
        $stmt = $db->query('
            SELECT al.*, u.email FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
            ORDER BY al.created_at DESC LIMIT 10
        ');
        $recentLogs = $stmt->fetchAll();

        $this->view('admin/dashboard', [
            'userStats'     => $userStats,
            'totalUsers'    => $totalUsers,
            'totalProducts' => $totalProducts,
            'totalImports'  => $totalImports,
            'queuePending'  => $queuePending,
            'recentUsers'   => $recentUsers,
            'recentLogs'    => $recentLogs,
        ], 'admin');
    }
}
