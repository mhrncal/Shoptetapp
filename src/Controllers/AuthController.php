<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response, View};
use ShopCode\Models\{User, UserModule, AuditLog};

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        if (Session::has('user')) {
            Response::redirect('/dashboard');
        }
        $this->view('auth/login', [], 'auth');
    }

    public function login(): void
    {
        $this->validateCsrf();

        $email    = trim($this->request->post('email', ''));
        $password = $this->request->post('password', '');
        $remember = (bool)$this->request->post('remember');

        if (empty($email) || empty($password)) {
            Session::flash('error', 'Vyplňte e-mail a heslo.');
            Response::redirect('/login');
        }

        $user = User::findByEmail($email);

        if (!$user) {
            Session::flash('error', 'Nesprávný e-mail nebo heslo.');
            Response::redirect('/login');
        }

        // Lockout
        if (User::isLocked($user)) {
            Session::flash('error', "Účet je dočasně zablokován kvůli příliš mnoha neúspěšným pokusům. Zkuste to za " . LOGIN_LOCKOUT_MINUTES . " minut.");
            Response::redirect('/login');
        }

        if (!password_verify($password, $user['password_hash'])) {
            $justLocked = User::incrementLoginAttempts($email);
            Session::flash('error', 'Nesprávný e-mail nebo heslo.');
            AuditLog::log('login_failed', 'user', (string)$user['id'], null, ['email' => $email]);

            // Notifikuj superadmina pokud byl účet právě zamknut
            if ($justLocked) {
                try {
                    \ShopCode\Services\AdminNotifier::userLocked(
                        userId:      $user['id'],
                        email:       $email,
                        ipAddress:   $_SERVER['REMOTE_ADDR'] ?? 'neznámá',
                        attempts:    LOGIN_MAX_ATTEMPTS,
                        lockMinutes: LOGIN_LOCKOUT_MINUTES
                    );
                } catch (\Throwable $e) {
                    // Tichá chyba — nepřerušujeme flow
                }
            }

            Response::redirect('/login');
        }

        // Úspěšné přihlášení
        User::updateLastLogin($user['id']);
        Session::regenerate();
        Session::set('user', [
            'id'         => $user['id'],
            'email'      => $user['email'],
            'first_name' => $user['first_name'],
            'last_name'  => $user['last_name'],
            'role'       => $user['role'],
            'status'     => $user['status'],
            'shop_name'  => $user['shop_name'],
        ]);

        // Remember me
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            User::saveRememberToken($user['id'], $token);
            setcookie(
                'remember_token',
                $token,
                time() + REMEMBER_LIFETIME,
                '/',
                '',
                APP_ENV === 'production',
                true
            );
        }

        AuditLog::log('login', 'user', (string)$user['id']);

        if ($user['status'] === 'pending') {
            Response::redirect('/pending');
        }

        Response::redirect('/dashboard');
    }

    public function logout(): void
    {
        $userId = $this->user['id'] ?? null;

        // Pokud impersonuje — vrátíme ho zpět jako superadmin
        if (Session::has('impersonating_as')) {
            $adminData = Session::get('impersonating_as');
            Session::delete('impersonating_as');
            Session::set('user', $adminData);
            Session::flash('success', 'Impersonace ukončena.');
            Response::redirect('/admin/users');
        }

        if ($userId) {
            User::deleteRememberToken($userId);
            AuditLog::log('logout', 'user', (string)$userId);
        }

        setcookie('remember_token', '', time() - 3600, '/', '', APP_ENV === 'production', true);
        Session::destroy();
        Response::redirect('/login');
    }

    public function registerForm(): void
    {
        if (Session::has('user')) {
            Response::redirect('/dashboard');
        }
        $this->view('auth/register', [], 'auth');
    }

    public function register(): void
    {
        $this->validateCsrf();

        $email     = trim($this->request->post('email', ''));
        $password  = $this->request->post('password', '');
        $password2 = $this->request->post('password2', '');
        $firstName = trim($this->request->post('first_name', ''));
        $lastName  = trim($this->request->post('last_name', ''));
        $shopName  = trim($this->request->post('shop_name', ''));

        // Validace
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Zadejte platný e-mail.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Heslo musí mít alespoň 8 znaků.';
        }
        if ($password !== $password2) {
            $errors[] = 'Hesla se neshodují.';
        }
        if (empty($firstName) || empty($lastName)) {
            $errors[] = 'Zadejte jméno a příjmení.';
        }
        if (User::findByEmail($email)) {
            $errors[] = 'Tento e-mail je již registrován.';
        }

        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            Response::redirect('/register');
        }

        $userId = User::create([
            'email'      => $email,
            'password'   => $password,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'shop_name'  => $shopName,
            'role'       => 'user',
            'status'     => 'pending',
        ]);

        // Přiřadit moduly (jako inactive — admin aktivuje)
        UserModule::assignAllToUser($userId);

        AuditLog::log('register', 'user', (string)$userId, null, ['email' => $email]);

        // Email uživateli — uvítání
        try {
            $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
            $appUrl  = defined('APP_URL')  ? APP_URL  : '';
            $welcomeHtml = "
                <!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'></head>
                <body style='background:#0f1117;font-family:sans-serif;color:#e5e7eb;margin:0;padding:40px 20px;'>
                <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>
                <table width='560' style='background:#1a1d27;border-radius:12px;padding:40px;'>
                <tr><td>
                  <p style='font-size:20px;font-weight:700;color:#fff;margin-top:0;'>{$appName}</p>
                  <h2 style='color:#fff;'>Vítejte, {$firstName}!</h2>
                  <p>Váš účet byl úspěšně vytvořen. Před tím než budete moci pokračovat, musí váš účet schválit administrátor.</p>
                  <p>O schválení vás budeme informovat e-mailem.</p>
                  <hr style='border-color:#374151;margin:24px 0;'>
                  <p style='color:#6b7280;font-size:13px;'>© " . date('Y') . " {$appName}</p>
                </td></tr></table></td></tr></table>
                </body></html>
            ";
            \ShopCode\Services\Mailer::send($email, "Vítejte v {$appName}", $welcomeHtml);
        } catch (\Throwable $ignored) {}

        // Notifikace superadmina o nové registraci
        try {
            $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
            $appUrl  = defined('APP_URL')  ? APP_URL  : '';
            $adminHtml = "
                <!DOCTYPE html><html><head><meta charset='UTF-8'></head>
                <body style='background:#0f1117;font-family:sans-serif;color:#e5e7eb;margin:0;padding:40px 20px;'>
                <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center'>
                <table width='560' style='background:#1a1d27;border-radius:12px;padding:40px;'>
                <tr><td>
                  <p style='font-size:20px;font-weight:700;color:#fff;margin-top:0;'>{$appName}</p>
                  <h2 style='color:#fff;'>🆕 Nová registrace</h2>
                  <p>Nový uživatel se zaregistroval a čeká na schválení.</p>
                  <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                    <tr style='border-bottom:1px solid #374151;'>
                      <td style='padding:8px 0;color:#9ca3af;width:120px;'>Jméno</td>
                      <td style='padding:8px 0;'><strong>{$firstName} {$lastName}</strong></td>
                    </tr>
                    <tr style='border-bottom:1px solid #374151;'>
                      <td style='padding:8px 0;color:#9ca3af;'>E-mail</td>
                      <td style='padding:8px 0;'>{$email}</td>
                    </tr>
                    <tr>
                      <td style='padding:8px 0;color:#9ca3af;'>E-shop</td>
                      <td style='padding:8px 0;'>" . htmlspecialchars($shopName ?: '—') . "</td>
                    </tr>
                  </table>
                  <a href='{$appUrl}/admin/users/{$userId}'
                     style='display:inline-block;background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;font-weight:600;'>
                     Schválit / zamítnout
                  </a>
                </td></tr></table></td></tr></table>
                </body></html>
            ";
            \ShopCode\Services\Mailer::notifySuperadmin("[$appName] Nová registrace — {$email}", $adminHtml);
        } catch (\Throwable $ignored) {}

        Session::regenerate();
        Session::set('user', [
            'id'         => $userId,
            'email'      => $email,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'role'       => 'user',
            'status'     => 'pending',
            'shop_name'  => $shopName,
        ]);

        Response::redirect('/pending');
    }

    public function pending(): void
    {
        if ($this->user && $this->user['status'] === 'approved') {
            Response::redirect('/dashboard');
        }
        $this->view('auth/pending', [], 'auth');
    }
}
