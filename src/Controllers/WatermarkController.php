<?php

namespace ShopCode\Controllers;

use ShopCode\Models\WatermarkSettings;

class WatermarkController extends BaseController
{
    public function settings(): void
    {
        $userId = $_SESSION['user_id'];
        
        $settings = WatermarkSettings::getForUser($userId);
        if (!$settings) {
            WatermarkSettings::createDefault($userId);
            $settings = WatermarkSettings::getForUser($userId);
        }
        
        $this->view('watermark/settings', [
            'settings' => $settings,
            'positions' => WatermarkSettings::POSITIONS,
            'fonts' => WatermarkSettings::FONTS,
            'sizes' => WatermarkSettings::SIZES,
        ]);
    }
    
    public function update(): void
    {
        $userId = $_SESSION['user_id'];
        
        $data = [
            'text' => $_POST['text'] ?? 'Zákaznická fotka',
            'font' => $_POST['font'] ?? 'Arial',
            'position' => $_POST['position'] ?? 'BR',
            'color' => $_POST['color'] ?? '#FFFFFF',
            'size' => $_POST['size'] ?? 'medium',
            'opacity' => (int)($_POST['opacity'] ?? 80),
            'padding' => (int)($_POST['padding'] ?? 20),
            'shadow_enabled' => isset($_POST['shadow_enabled']),
            'enabled' => isset($_POST['enabled']),
        ];
        
        if (WatermarkSettings::update($userId, $data)) {
            $_SESSION['flash'] = ['success' => 'Nastavení watermarku uloženo'];
        } else {
            $_SESSION['flash'] = ['error' => 'Chyba při ukládání nastavení'];
        }
        
        header('Location: ' . APP_URL . '/watermark/settings');
        exit;
    }
}
