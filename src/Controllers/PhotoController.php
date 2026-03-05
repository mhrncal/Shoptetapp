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
            Session::flash('error', 'Fotka nenalezena');
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
        
        Session::flash('success', 'Fotka byla smazána');
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
            Session::flash('error', 'Vyberte fotku k nahrání');
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
            Session::flash('error', 'Fotka nenalezena');
            header('Location: ' . $_SERVER['HTTP_REFERER'] ?? APP_URL . '/reviews');
            exit;
        }
        
        try {
            // Parsuj starou cestu: user_id/uuid/filename.ext
            $pathParts = explode('/', $oldPhoto['path']);
            $userId = (int)$pathParts[0];
            $uuid = $pathParts[1];
            $extension = pathinfo($oldPhoto['path'], PATHINFO_EXTENSION);
            
            // Složka pro fotky
            $photoDir = ROOT . '/public/uploads/' . $userId . '/' . $uuid;
            
            // Smaž všechny staré soubory
            if (is_dir($photoDir)) {
                $files = glob($photoDir . '/*');
                foreach ($files as $file) {
                    unlink($file);
                }
            } else {
                mkdir($photoDir, 0755, true);
            }
            
            // Zpracuj novou fotku
            $uploadDir = ROOT . '/public/uploads';
            $handler = new ImageHandler($uploadDir);
            
            // Načti obrázek
            $tmpFile = $_FILES['photo']['tmp_name'];
            $mimeType = mime_content_type($tmpFile);
            
            $img = match($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($tmpFile),
                'image/png'  => @imagecreatefrompng($tmpFile),
                'image/webp' => @imagecreatefromwebp($tmpFile),
                default => throw new \RuntimeException('Nepodporovaný formát obrázku')
            };
            
            if (!$img) {
                throw new \RuntimeException('Nelze načíst obrázek');
            }
            
            // Ořízni EXIF
            $img = $handler->removeExif($img);
            
            // Smart resize
            $img = $handler->smartResize($img);
            
            // Vytvoř verze
            $original = $handler->cloneImage($img);
            $display = $handler->applyWatermark($img, $userId);
            $thumb = $handler->createThumbnail($display);
            
            // Ulož soubory
            $ext = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => 'jpg'
            };
            
            $basename = $uuid;
            $originalPath = $photoDir . '/' . $basename . '_original.' . $ext;
            $displayPath = $photoDir . '/' . $basename . '.' . $ext;
            $thumbPath = $photoDir . '/thumb_' . $basename . '.' . $ext;
            
            match($ext) {
                'jpg' => [
                    imagejpeg($original, $originalPath, 90),
                    imagejpeg($display, $displayPath, 90),
                    imagejpeg($thumb, $thumbPath, 90)
                ],
                'png' => [
                    imagepng($original, $originalPath, 6),
                    imagepng($display, $displayPath, 6),
                    imagepng($thumb, $thumbPath, 6)
                ],
                'webp' => [
                    imagewebp($original, $originalPath, 90),
                    imagewebp($display, $displayPath, 90),
                    imagewebp($thumb, $thumbPath, 90)
                ]
            };
            
            imagedestroy($img);
            imagedestroy($original);
            imagedestroy($display);
            imagedestroy($thumb);
            
            // Updatuj DB (cesty zůstávají stejné, jen mime_type)
            $stmt = $db->prepare('
                UPDATE review_photos 
                SET mime_type = ?
                WHERE id = ?
            ');
            $stmt->execute([
                $mimeType,
                $id
            ]);
            
            Session::flash('success', 'Fotka byla nahrazena');
            
        } catch (\Exception $e) {
            Session::flash('error', 'Chyba při nahrávání: ' . $e->getMessage());
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
