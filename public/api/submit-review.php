<?php
/**
 * POST /api/submit-review
 * Přijímá multipart/form-data z Shoptet formuláře.
 * Standalone endpoint (mimo ShopCode routing) — nutný přímý přístup přes webserver.
 */

define('ROOT', dirname(__DIR__, 2));
require ROOT . '/config/config.php';

use ShopCode\Core\Database;
use ShopCode\Models\Review;
use ShopCode\Services\{ImageHandler, AdminNotifier};

header('Content-Type: application/json; charset=UTF-8');

// ── CORS ──────────────────────────────────────────────────
$origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = defined('SHOPTET_DOMAINS') ? SHOPTET_DOMAINS : [];

if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
} elseif (empty($allowed)) {
    // Dev mode — povolíme vše
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Metoda není povolena.', 405);
}

// ── Rate limiting ─────────────────────────────────────────
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = explode(',', $ip)[0]; // první IP z X-Forwarded-For

// Načteme autoloader
spl_autoload_register(function (string $class) {
    $path = ROOT . '/src/' . str_replace(['ShopCode\\', '\\'], ['', '/'], $class) . '.php';
    if (file_exists($path)) require $path;
});

if (!Review::checkRateLimit($ip, 'submit-review', 5, 600)) {
    jsonError('Příliš mnoho požadavků. Zkuste to za chvíli.', 429);
}

// ── Honeypot anti-spam ────────────────────────────────────
if (!empty($_POST['website'])) {
    // Bot vyplnil honeypot — předstíráme úspěch
    jsonSuccess('Recenze byla odeslána ke schválení.');
}

// ── Validace vstupu ───────────────────────────────────────
$errors = [];

$authorName  = trim($_POST['name']  ?? '');
$authorEmail = trim($_POST['email'] ?? '');
$shoptetId   = trim($_POST['product_id'] ?? '');
$sku         = trim($_POST['sku'] ?? '');
$comment     = trim($_POST['comment'] ?? '');
$rating      = isset($_POST['rating']) ? (int)$_POST['rating'] : null;

if (empty($authorName) || mb_strlen($authorName) > 100) {
    $errors[] = 'Zadejte jméno (max 100 znaků).';
}
if (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Zadejte platný e-mail.';
}
if ($rating !== null && ($rating < 1 || $rating > 5)) {
    $errors[] = 'Hodnocení musí být 1–5.';
}
if (mb_strlen($comment) > 500) {
    $errors[] = 'Komentář je příliš dlouhý (max 500 znaků).';
}

// Fotky
$uploadedPhotos = [];
if (!empty($_FILES['photos'])) {
    $files = normalizeFilesArray($_FILES['photos']);

    if (empty($files)) {
        $errors[] = 'Nahrajte alespoň jednu fotku.';
    } elseif (count($files) > 5) {
        $errors[] = 'Maximálně 5 fotek.';
    }
} else {
    $errors[] = 'Nahrajte alespoň jednu fotku.';
}

if ($errors) {
    jsonError(implode(' ', $errors), 422);
}

// ── Najdeme uživatele podle shoptet_id/sku ────────────────
// Předpokládáme, že každý shopcode uživatel má propojení přes shoptet_id produktu
$db        = Database::getInstance();
$userId    = null;
$productId = null;

if ($shoptetId || $sku) {
    $col  = $shoptetId ? 'shoptet_id' : 'sku';
    $val  = $shoptetId ?: $sku;
    $stmt = $db->prepare("SELECT id, user_id FROM products WHERE {$col} = ? LIMIT 1");
    $stmt->execute([$val]);
    $product = $stmt->fetch();
    if ($product) {
        $userId    = $product['user_id'];
        $productId = $product['id'];
    }
}

if (!$userId) {
    // Fallback — pokud je jen jeden uživatel (single-tenant provoz)
    $stmt = $db->prepare("SELECT id FROM users WHERE role = 'user' AND status = 'approved' LIMIT 1");
    $stmt->execute();
    $userId = $stmt->fetchColumn() ?: null;
}

if (!$userId) {
    jsonError('Nelze přiřadit recenzi k e-shopu.', 500);
}

// ── Zpracování fotek ──────────────────────────────────────
$uuid    = bin2hex(random_bytes(8));
$handler = new ImageHandler();
$photos  = [];

foreach (normalizeFilesArray($_FILES['photos']) as $file) {
    try {
        $result   = $handler->process($file, $userId, $uuid);
        $photos[] = ['path' => $result['path'], 'thumb' => $result['thumb']];
    } catch (\RuntimeException $e) {
        // Smažeme již zpracované fotky a vrátíme chybu
        $handler->deleteFolder($userId, $uuid);
        jsonError('Chyba při zpracování fotky: ' . $e->getMessage(), 422);
    }
}

// ── Uložení do DB ─────────────────────────────────────────
$reviewId = Review::create($userId, [
    'product_id'   => $productId,
    'shoptet_id'   => $shoptetId ?: null,
    'sku'          => $sku ?: null,
    'author_name'  => $authorName,
    'author_email' => $authorEmail,
    'rating'       => $rating,
    'comment'      => $comment ?: null,
    'photos'       => $photos,
]);

// ── Email admina ───────────────────────────────────────────
try {
    AdminNotifier::notifySuperadmin(
        subject: '[ShopCode] 📸 Nová fotorecenze čeká na schválení',
        htmlBody: "
            <h2>Nová fotorecenze</h2>
            <table style='border-collapse:collapse;width:100%;'>
                <tr><td style='color:#9ca3af;padding:8px 0;width:120px;'>Autor</td><td><strong>" . htmlspecialchars($authorName) . "</strong> &lt;" . htmlspecialchars($authorEmail) . "&gt;</td></tr>
                <tr><td style='color:#9ca3af;padding:8px 0;'>SKU / ID</td><td>" . htmlspecialchars($sku ?: $shoptetId ?: '—') . "</td></tr>
                <tr><td style='color:#9ca3af;padding:8px 0;'>Fotek</td><td>" . count($photos) . "</td></tr>
                " . ($rating ? "<tr><td style='color:#9ca3af;padding:8px 0;'>Hodnocení</td><td>" . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . "</td></tr>" : '') . "
                " . ($comment ? "<tr><td style='color:#9ca3af;padding:8px 0;vertical-align:top;'>Komentář</td><td>" . htmlspecialchars($comment) . "</td></tr>" : '') . "
            </table>
            <p style='margin-top:20px;'>
                <a href='" . (defined('APP_URL') ? APP_URL : '') . "/reviews/{$reviewId}'
                   style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>
                   Schválit / odmítnout
                </a>
            </p>
        "
    );
} catch (\Throwable $ignored) {}

jsonSuccess('Recenze byla odeslána ke schválení. Děkujeme!');

// ── Helpers ───────────────────────────────────────────────

function jsonSuccess(string $message): never
{
    echo json_encode(['success' => true, 'message' => $message]);
    exit;
}

function jsonError(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function normalizeFilesArray(array $files): array
{
    if (!isset($files['name'])) return [];
    if (!is_array($files['name'])) {
        return $files['error'] === UPLOAD_ERR_OK ? [$files] : [];
    }
    $result = [];
    foreach ($files['name'] as $i => $name) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $result[] = [
                'name'     => $name,
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
    }
    return $result;
}
