<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Database;
use ShopCode\Models\UserModule;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $db     = Database::getInstance();
        $userId = $this->user['id'];

        // Statistiky pro dashboard
        $productCount = (int)$db->prepare('SELECT COUNT(*) FROM products WHERE user_id = ?')
            ->execute([$userId]) ? $db->query("SELECT COUNT(*) FROM products WHERE user_id = {$userId}")->fetchColumn() : 0;

        $stmt = $db->prepare('SELECT COUNT(*) FROM products WHERE user_id = ?');
        $stmt->execute([$userId]);
        $productCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM faqs WHERE user_id = ?');
        $stmt->execute([$userId]);
        $faqCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare('SELECT COUNT(*) FROM branches WHERE user_id = ?');
        $stmt->execute([$userId]);
        $branchCount = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM events WHERE user_id = ? AND is_active = 1 AND end_date >= NOW()");
        $stmt->execute([$userId]);
        $upcomingEvents = (int)$stmt->fetchColumn();

        // Posledních 5 importů
        $stmt = $db->prepare('SELECT * FROM xml_imports WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $stmt->execute([$userId]);
        $recentImports = $stmt->fetchAll();

        // Aktivní moduly
        $activeModules = UserModule::getActiveNamesForUser($userId);

        $this->view('dashboard/index', [
            'productCount'   => $productCount,
            'faqCount'       => $faqCount,
            'branchCount'    => $branchCount,
            'upcomingEvents' => $upcomingEvents,
            'recentImports'  => $recentImports,
            'activeModules'  => $activeModules,
        ]);
    }
}
