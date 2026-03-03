<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Database;
use ShopCode\Services\ImageHandler;

class PhotoController extends BaseController
{
    /**
     * Smaže fotku z recenze
     */
    public function delete(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        
        $db = Database::getInstance();
        
        // Načti fotku
        $stmt = $db->prepare('SELECT * FROM review_photos WHERE id = ?');
        $stmt->execute([$id]);
        $photo = $stmt->fetch();
        
        if (!$photo) {
            $_SESSION['flash'] = ['error' => 'Fotka nenalezena'];
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
            exit;
        }
        
        // Smaž soubory
        $basePath = ROOT . '/public/uploads/' . dirname($photo['path']);
        if (is_dir($basePath)) {
            array_map('unlink', glob("{$basePath}/*"));
            rmdir($basePath);
        }
        
        // Smaž z DB
        $stmt = $db->prepare('DELETE FROM review_photos WHERE id = ?');
        $stmt->execute([$id]);
        
        $_SESSION['flash'] = ['success' => 'Fotka byla smazána'];
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
        exit;
    }
    
    /**
     * Re-upload fotky (nahradit existující)
     */
    public function reupload(): void
    {
        $id = (int)($_POST['photo_id'] ?? 0);
        
        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash'] = ['error' => 'Vyberte fotku k nahrání'];
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
            exit;
        }
        
        $db = Database::getInstance();
        
        // Načti původní fotku
        $stmt = $db->prepare('
            SELECT rp.*, r.user_id 
            FROM review_photos rp
            JOIN reviews r ON r.id = rp.review_id
            WHERE rp.id = ?
        ');
        $stmt->execute([$id]);
        $oldPhoto = $stmt->fetch();
        
        if (!$oldPhoto) {
            $_SESSION['flash'] = ['error' => 'Fotka nenalezena'];
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
            exit;
        }
        
        try {
            // Smaž staré soubory (ale NE složku!)
            $oldDir = ROOT . '/public/uploads/' . dirname($oldPhoto['path']);
            
            // Smaž jednotlivé soubory
            $oldFiles = [
                ROOT . '/public/uploads/' . $oldPhoto['path'],
                ROOT . '/public/uploads/' . $oldPhoto['thumb'],
                ROOT . '/public/uploads/' . str_replace(['.jpg', '.png', '.webp'], ['_original.jpg', '_original.png', '_original.webp'], $oldPhoto['path'])
            ];
            
            foreach ($oldFiles as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            
            // Nahraj novou fotku DO STEJNÉ složky
            $uploadDir = ROOT . '/public/uploads';
            $handler = new ImageHandler($uploadDir);
            
            // Použij STEJNÝ basename jako měla stará fotka
            $oldBasename = basename($oldPhoto['path'], '.jpg');
            $oldBasename = basename($oldBasename, '.png');
            $oldBasename = basename($oldBasename, '.webp');
            
            // Zpracuj novou fotku
            $result = $handler->process($_FILES['photo'], $oldPhoto['user_id']);
            
            // Přesuň nové soubory na místo starých
            $newDir = ROOT . '/public/uploads/' . dirname($result['path']);
            $targetDir = $oldDir;
            
            // Pokud jsou v jiné složce, přesuň
            if ($newDir !== $targetDir) {
                $files = glob("{$newDir}/*");
                foreach ($files as $file) {
                    $target = $targetDir . '/' . basename($file);
                    rename($file, $target);
                }
                rmdir($newDir);
                
                // Update paths v result
                $result['path'] = str_replace(dirname($result['path']), dirname($oldPhoto['path']), $result['path']);
                $result['thumb'] = str_replace(dirname($result['thumb']), dirname($oldPhoto['thumb']), $result['thumb']);
            }
            
            // Updatuj DB s původními cestami (jen refresh)
            $stmt = $db->prepare('
                UPDATE review_photos 
                SET mime_type = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $result['mime'],
                $id
            ]);
            
            $_SESSION['flash'] = ['success' => 'Fotka byla nahrazena'];
            
        } catch (\Exception $e) {
            $_SESSION['flash'] = ['error' => 'Chyba při nahrávání: ' . $e->getMessage()];
        }
        
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
        exit;
    }
    
    /**
     * Stažení jednotlivé fotky
     */
    public function download(): void
    {
        $id = $_GET['id'] ?? '';
        
        // Legacy ID check (pro staré JSON fotky)
        if (str_starts_with($id, 'legacy_')) {
            http_response_code(404);
            echo "Legacy fotky nelze stahovat samostatně. Použijte CSV/XML export.";
            exit;
        }
        
        $id = (int)$id;
        
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM review_photos WHERE id = ?');
        $stmt->execute([$id]);
        $photo = $stmt->fetch();
        
        if (!$photo) {
            http_response_code(404);
            echo "Fotka nenalezena";
            exit;
        }
        
        // Originál (bez watermarku)
        $originalPath = str_replace('.jpg', '_original.jpg', $photo['path']);
        $originalPath = str_replace('.png', '_original.png', $originalPath);
        $originalPath = str_replace('.webp', '_original.webp', $originalPath);
        
        $filepath = ROOT . '/public/uploads/' . $originalPath;
        
        // Pokud originál neexistuje, použij display verzi
        if (!file_exists($filepath)) {
            $filepath = ROOT . '/public/uploads/' . $photo['path'];
        }
        
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo "Soubor nenalezen";
            exit;
        }
        
        header('Content-Type: ' . $photo['mime_type']);
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }
}
