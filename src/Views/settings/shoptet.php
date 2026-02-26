<?php
/**
 * Shoptet integrace — Nastavení přihlašovacích údajů
 */
$e = fn($str) => htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-shop text-primary me-2"></i>
                        Shoptet Integrace
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= $e($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= $e($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Informace -->
                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Automatický import fotorecenzí</h6>
                        <p class="mb-0 small">
                            Po schválení recenze v ShopCode admin UI se automaticky vytvoří CSV soubor a pomocí Selenium robota 
                            se nahraje do vašeho Shoptet admin účtu. Fotky se následně zobrazí na e-shopu.
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="mb-4">
                        <h6 class="mb-3">Status integrace</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <?php if ($hasCredentials): ?>
                                        <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                                        <div>
                                            <div class="fw-medium">Přihlašovací údaje nastaveny</div>
                                            <small class="text-muted"><?= $e($user['shoptet_email']) ?></small>
                                        </div>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger fs-4 me-3"></i>
                                        <div>
                                            <div class="fw-medium">Přihlašovací údaje nenastaveny</div>
                                            <small class="text-muted">Vyplňte níže</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-flex align-items-center">
                                    <?php if ($user['shoptet_auto_import']): ?>
                                        <i class="bi bi-arrow-repeat text-success fs-4 me-3"></i>
                                        <div>
                                            <div class="fw-medium">Automatický import zapnutý</div>
                                            <small class="text-muted">Recenze se nahrají do 30 minut</small>
                                        </div>
                                    <?php else: ?>
                                        <i class="bi bi-pause-circle text-warning fs-4 me-3"></i>
                                        <div>
                                            <div class="fw-medium">Automatický import vypnutý</div>
                                            <small class="text-muted">Zapněte níže</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulář -->
                    <form method="POST" action="/settings/shoptet">
                        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        
                        <div class="mb-3">
                            <label for="shoptet_url" class="form-label">Shoptet Admin URL</label>
                            <input type="url" 
                                   class="form-control" 
                                   id="shoptet_url" 
                                   name="shoptet_url"
                                   value="<?= $e($user['shoptet_url'] ?: 'https://admin.shoptet.cz') ?>"
                                   placeholder="https://admin.shoptet.cz"
                                   required>
                            <small class="text-muted">Obvykle: https://admin.shoptet.cz</small>
                        </div>

                        <div class="mb-3">
                            <label for="shoptet_email" class="form-label">
                                Shoptet Email *
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="shoptet_email" 
                                   name="shoptet_email"
                                   value="<?= $e($user['shoptet_email']) ?>"
                                   placeholder="vas@email.cz"
                                   required>
                            <small class="text-muted">Email pro přihlášení do Shoptet adminu</small>
                        </div>

                        <div class="mb-3">
                            <label for="shoptet_password" class="form-label">
                                Shoptet Heslo *
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="shoptet_password" 
                                   name="shoptet_password"
                                   placeholder="<?= $hasCredentials ? '••••••••' : 'Vaše heslo' ?>"
                                   <?= $hasCredentials ? '' : 'required' ?>>
                            <small class="text-muted">
                                <?php if ($hasCredentials): ?>
                                    Heslo je šifrovaně uloženo. Nechte prázdné pro zachování stávajícího hesla.
                                <?php else: ?>
                                    Heslo bude šifrovaně uloženo pomocí AES-256-CBC
                                <?php endif; ?>
                            </small>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="auto_import" 
                                       name="auto_import"
                                       value="1"
                                       <?= $user['shoptet_auto_import'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_import">
                                    <strong>Povolit automatický import</strong>
                                    <div class="small text-muted">
                                        Schválené recenze se automaticky nahrají do Shoptetu každých 30 minut
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="bi bi-shield-lock me-2"></i>
                            <strong>Bezpečnost:</strong> Heslo je šifrováno pomocí AES-256-CBC a uloženo bezpečně v databázi. 
                            Nikdy není přenášeno v čitelné podobě.
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Uložit nastavení
                            </button>
                            
                            <?php if ($hasCredentials): ?>
                                <button type="button" 
                                        class="btn btn-outline-danger"
                                        onclick="if(confirm('Opravdu smazat Shoptet credentials? Automatický import se vypne.')) { 
                                            document.getElementById('delete-form').submit(); 
                                        }">
                                    <i class="bi bi-trash me-2"></i>Smazat credentials
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ($hasCredentials): ?>
                        <form id="delete-form" method="POST" action="/settings/shoptet/delete" class="d-none">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nápověda -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="bi bi-question-circle text-info me-2"></i>
                        Jak to funguje?
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="small mb-0">
                        <li class="mb-2">Zákazník odešle fotorecenzi přes formulář na vašem webu</li>
                        <li class="mb-2">Recenze se uloží do ShopCode s statusem "Čeká na schválení"</li>
                        <li class="mb-2">Vy recenzi schválíte v admin UI (kliknete "Schválit")</li>
                        <li class="mb-2">CRON worker (běží každých 30 minut) automaticky:
                            <ul class="mt-1">
                                <li>Najde schválené recenze</li>
                                <li>Vygeneruje CSV soubor ve formátu Shoptet</li>
                                <li>Spustí Selenium robota</li>
                                <li>Robot se přihlásí do vašeho Shoptet adminu</li>
                                <li>Nahraje CSV s fotkami</li>
                                <li>Shoptet stáhne fotky a přidá k produktům</li>
                            </ul>
                        </li>
                        <li>Fotky se zobrazí na e-shopu ✅</li>
                    </ol>
                </div>
            </div>

            <!-- Požadavky serveru -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-white border-bottom">
                    <h6 class="mb-0">
                        <i class="bi bi-server text-warning me-2"></i>
                        Požadavky na server
                    </h6>
                </div>
                <div class="card-body">
                    <div class="small">
                        <p class="mb-2"><strong>Pro funkčnost automatického importu je potřeba:</strong></p>
                        <ul class="mb-0">
                            <li>Chromium browser a ChromeDriver nainstalované</li>
                            <li>ChromeDriver běžící na portu 9515</li>
                            <li>Composer balíček: <code>facebook/webdriver</code></li>
                            <li>CRON worker nastavený (každých 30 minut)</li>
                            <li>ENCRYPTION_KEY definovaný v config.php</li>
                        </ul>
                        <p class="mt-2 mb-0 text-muted">
                            <i class="bi bi-book me-1"></i>
                            Detailní návod: <a href="https://github.com/mhrncal/Shoptetapp/blob/main/docs/PHOTO-REVIEWS-WORKFLOW.md" target="_blank">PHOTO-REVIEWS-WORKFLOW.md</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
