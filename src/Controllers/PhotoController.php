<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Database, Session};
use ShopCode\Services\ImageHandler;

class PhotoController extends BaseController
{
    /**
     * Smaže fotku z recenze
     */
    public function delete(): void
    {
        $this->validateCsrf();
        $id = (int)$this->request->post('id', 0);
        
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
        $this->validateCsrf();
        $id = (int)$this->request->post('photo_id', 0);
        
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
            $displayPath  = $photoDir . '/' . $basename . '.' . $ext;
            $thumbPath    = $photoDir . '/' . $basename . '_thumb.' . $ext;
            
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
            
            // Updatuj DB – cesty i mime_type, reset shoptet_url
            $newPath  = $userId . '/' . $uuid . '/' . $basename . '.' . $ext;
            $newThumb = $userId . '/' . $uuid . '/' . $basename . '_thumb.' . $ext;
            $stmt = $db->prepare('UPDATE review_photos SET path = ?, thumb = ?, mime_type = ?, shoptet_url = NULL WHERE id = ?');
            $stmt->execute([$newPath, $newThumb, $mimeType, $id]);
            
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
        $id = $this->request->get('id', '');
        
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

    public function rotate(): void
    {
        $this->validateCsrf();
        $id      = (int)$this->request->post('id', 0);
        $degrees = (int)$this->request->post('degrees', 90); // 90 nebo 270
        $userId  = $this->user['id'];

        if (!in_array($degrees, [90, 180, 270])) {
            $this->json(['success' => false, 'error' => 'Neplatný úhel rotace.'], 400);
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT rp.*, r.user_id FROM review_photos rp JOIN reviews r ON r.id = rp.review_id WHERE rp.id = ?');
        $stmt->execute([$id]);
        $photo = $stmt->fetch();

        if (!$photo || (int)$photo['user_id'] !== $userId) {
            $this->json(['success' => false, 'error' => 'Fotka nenalezena.'], 404);
        }

        $ext        = pathinfo($photo['path'], PATHINFO_EXTENSION);
        $displayAbs = ROOT . '/public/uploads/' . ltrim($photo['path'], '/');
        $origAbs    = substr($displayAbs, 0, -strlen('.' . $ext)) . '_original.' . $ext;
        $thumbAbs   = ROOT . '/public/uploads/' . ltrim($photo['thumb'] ?? '', '/');

        $mime = match(strtolower($ext)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => null,
        };
        if (!$mime) {
            $this->json(['success' => false, 'error' => 'Nepodporovaný formát.'], 400);
        }

        try {
            $rotateAndSave = function(string $file, string $mime, int $deg): bool {
                if (!file_exists($file)) return false;
                $img = match($mime) {
                    'image/jpeg' => @imagecreatefromjpeg($file),
                    'image/png'  => @imagecreatefrompng($file),
                    'image/webp' => @imagecreatefromwebp($file),
                };
                if (!$img) return false;
                // imagerotate rotuje proti směru hodinových ručiček
                $rotated = imagerotate($img, 360 - $deg, 0);
                imagedestroy($img);
                $ok = match($mime) {
                    'image/jpeg' => imagejpeg($rotated, $file, 90),
                    'image/png'  => imagepng($rotated, $file, 6),
                    'image/webp' => imagewebp($rotated, $file, 90),
                };
                imagedestroy($rotated);
                return (bool)$ok;
            };

            // Rotuj originál
            $rotateAndSave($origAbs, $mime, $degrees);

            // Rotuj display verzi
            $rotateAndSave($displayAbs, $mime, $degrees);

            // Znovu vygeneruj thumbnail z rotované display verze
            if (file_exists($displayAbs)) {
                $handler = new \ShopCode\Services\ImageHandler(ROOT . '/public/uploads');
                $dispImg = match($mime) {
                    'image/jpeg' => @imagecreatefromjpeg($displayAbs),
                    'image/png'  => @imagecreatefrompng($displayAbs),
                    'image/webp' => @imagecreatefromwebp($displayAbs),
                };
                if ($dispImg) {
                    $thumb = $handler->createThumbnail($dispImg);
                    match($mime) {
                        'image/jpeg' => imagejpeg($thumb, $thumbAbs, 90),
                        'image/png'  => imagepng($thumb, $thumbAbs, 6),
                        'image/webp' => imagewebp($thumb, $thumbAbs, 90),
                    };
                    imagedestroy($dispImg);
                    imagedestroy($thumb);
                }
            }

            $this->json(['success' => true]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
