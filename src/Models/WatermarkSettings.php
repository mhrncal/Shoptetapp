<?php

namespace ShopCode\Models;

use ShopCode\Core\Database;

class WatermarkSettings
{
    public const POSITIONS = [
        'TL' => ['label' => 'Vlevo nahoře',   'coords' => ['left', 'top']],
        'TC' => ['label' => 'Nahoře uprostřed', 'coords' => ['center', 'top']],
        'TR' => ['label' => 'Vpravo nahoře',  'coords' => ['right', 'top']],
        'ML' => ['label' => 'Vlevo uprostřed', 'coords' => ['left', 'middle']],
        'MC' => ['label' => 'Uprostřed',      'coords' => ['center', 'middle']],
        'MR' => ['label' => 'Vpravo uprostřed', 'coords' => ['right', 'middle']],
        'BL' => ['label' => 'Vlevo dole',     'coords' => ['left', 'bottom']],
        'BC' => ['label' => 'Dole uprostřed',  'coords' => ['center', 'bottom']],
        'BR' => ['label' => 'Vpravo dole',    'coords' => ['right', 'bottom']],
    ];

    public const FONTS = [
        'Arial' => 'Arial',
        'Helvetica' => 'Helvetica',
        'Times New Roman' => 'Times',
        'Courier New' => 'Courier',
        'Verdana' => 'Verdana',
        'Georgia' => 'Georgia',
    ];

    public const SIZES = [
        'small'  => ['label' => 'Malá',    'px' => 16],
        'medium' => ['label' => 'Střední', 'px' => 24],
        'large'  => ['label' => 'Velká',   'px' => 36],
    ];

    public static function getForUser(int $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM watermark_settings WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    public static function createDefault(int $userId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare('
            INSERT INTO watermark_settings (user_id, text, position)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE user_id = user_id
        ');
        return $stmt->execute([$userId, 'Zákaznická fotka', 'BR']);
    }

    public static function update(int $userId, array $data): bool
    {
        $db = Database::getInstance();
        
        // Zkontroluj jestli shadow_enabled sloupec existuje
        $hasColumn = false;
        try {
            $stmt = $db->query("SHOW COLUMNS FROM watermark_settings LIKE 'shadow_enabled'");
            $hasColumn = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            // Ignoruj chybu
        }
        
        if ($hasColumn) {
            // S shadow_enabled
            $stmt = $db->prepare('
                UPDATE watermark_settings
                SET text = ?, font = ?, position = ?, color = ?, size = ?, opacity = ?, padding = ?, shadow_enabled = ?, enabled = ?
                WHERE user_id = ?
            ');
            return $stmt->execute([
                $data['text'] ?? 'Zákaznická fotka',
                $data['font'] ?? 'Arial',
                $data['position'] ?? 'BR',
                $data['color'] ?? '#FFFFFF',
                $data['size'] ?? 'medium',
                (int)($data['opacity'] ?? 80),
                (int)($data['padding'] ?? 20),
                isset($data['shadow_enabled']) ? 1 : 0,
                isset($data['enabled']) ? 1 : 0,
                $userId
            ]);
        } else {
            // Bez shadow_enabled (fallback)
            $stmt = $db->prepare('
                UPDATE watermark_settings
                SET text = ?, font = ?, position = ?, color = ?, size = ?, opacity = ?, padding = ?, enabled = ?
                WHERE user_id = ?
            ');
            return $stmt->execute([
                $data['text'] ?? 'Zákaznická fotka',
                $data['font'] ?? 'Arial',
                $data['position'] ?? 'BR',
                $data['color'] ?? '#FFFFFF',
                $data['size'] ?? 'medium',
                (int)($data['opacity'] ?? 80),
                (int)($data['padding'] ?? 20),
                isset($data['enabled']) ? 1 : 0,
                $userId
            ]);
        }
    }
}
