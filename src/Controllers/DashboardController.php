<?php
namespace ShopCode\Controllers;

use ShopCode\Core\Database;
use ShopCode\Models\{UserModule, XmlImport};

class DashboardController extends BaseController
{
    public function index(): void
    {
        $db     = Database::getInstance();
        $userId = $this->user['id'];

        $counts = [];
        foreach (['products','faqs','branches'] as $tbl) {
            $s = $db->prepare("SELECT COUNT(*) FROM {$tbl} WHERE user_id = ?");
            $s->execute([$userId]);
            $counts[$tbl] = (int)$s->fetchColumn();
        }

        $s = $db->prepare("SELECT COUNT(*) FROM events WHERE user_id = ? AND is_active = 1 AND end_date >= NOW()");
        $s->execute([$userId]);
        $counts['upcoming_events'] = (int)$s->fetchColumn();

        // Posledních 5 importů
        $s = $db->prepare('SELECT * FROM xml_imports WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $s->execute([$userId]);
        $recentImports = $s->fetchAll();

        // Aktivní import ve frontě
        $activeImport = XmlImport::getActiveQueueItem($userId);

        // Aktivní moduly
        $activeModules = UserModule::getActiveNamesForUser($userId);

        $this->view('dashboard/index', [
            'pageTitle'     => 'Dashboard',
            'counts'        => $counts,
            'recentImports' => $recentImports,
            'activeImport'  => $activeImport,
            'activeModules' => $activeModules,
        ]);
    }
}
