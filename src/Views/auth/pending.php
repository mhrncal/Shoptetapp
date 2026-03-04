<div class="text-center">
    <div class="mb-4" style="font-size:3rem;">⏳</div>
    <h2 class="fw-bold mb-2">Čeká na schválení</h2>
    <p class="text-muted mb-4" style="font-size:.9rem;">
        Váš účet byl úspěšně vytvořen a čeká na schválení administrátorem.<br>
        Po schválení vám přijde e-mail s informací.
    </p>
    <div class="alert alert-info" style="font-size:.85rem;">
        <i class="bi bi-info-circle me-2"></i>
        Pokud máte dotazy, kontaktujte nás na
        <strong><?= defined('SUPERADMIN_EMAIL') ? htmlspecialchars(SUPERADMIN_EMAIL) : 'admin@shopcode.cz' ?></strong>
    </div>
    <a href="<?= APP_URL ?>/logout" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-box-arrow-right me-1"></i>Odhlásit se
    </a>
</div>
