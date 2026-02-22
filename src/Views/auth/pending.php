<?php $pageTitle = 'Čekání na schválení'; ?>

<div class="card border-0 shadow-lg bg-dark-subtle text-center">
    <div class="card-body p-5">
        <div class="mb-4">
            <i class="bi bi-hourglass-split text-warning" style="font-size: 3.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-2">Čekáte na schválení</h4>
        <p class="text-secondary mb-4">
            Váš účet byl úspěšně vytvořen. Administrátor ho musí schválit, než získáte přístup k aplikaci.
            Jakmile dojde ke schválení, budete moci se přihlásit.
        </p>
        <div class="alert alert-secondary d-inline-block py-2 px-4 mb-4">
            <i class="bi bi-envelope me-2"></i>
            <strong><?= \ShopCode\Core\View::e($currentUser['email'] ?? '') ?></strong>
        </div>
        <br>
        <a href="<?= APP_URL ?>/logout" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-right me-2"></i>Odhlásit se
        </a>
    </div>
</div>
