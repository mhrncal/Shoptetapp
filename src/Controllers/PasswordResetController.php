<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Response};
use ShopCode\Models\{User, PasswordReset};
use ShopCode\Services\Mailer;

class PasswordResetController extends BaseController
{
    // ---- Krok 1: Formulář pro zadání emailu ----

    public function requestForm(): void
    {
        $this->view('password-reset/request', ['pageTitle' => 'Zapomenuté heslo'], 'auth');
    }

    public function requestSubmit(): void
    {
        $this->validateCsrf();
        $email = trim($this->request->post('email', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Zadejte platný e-mail.');
            $this->redirect('/password/reset');
        }

        $user = User::findByEmail($email);

        // Vždy zobrazíme stejnou zprávu — nechceme odhalit existence účtu
        if ($user && $user['status'] !== 'rejected') {
            $token     = PasswordReset::create($user['id']);
            $resetLink = (defined('APP_URL') ? APP_URL : '') . '/password/reset/' . $token;

            $html = $this->buildEmail($user['first_name'] ?? 'uživateli', $resetLink);
            Mailer::send($user['email'], 'Resetování hesla — ' . (defined('APP_NAME') ? APP_NAME : 'ShopCode'), $html);
        }

        Session::flash('success', 'Pokud e-mail existuje v systému, obdržíte odkaz pro reset hesla.');
        $this->redirect('/password/reset');
    }

    // ---- Krok 2: Formulář pro nové heslo ----

    public function resetForm(): void
    {
        $token = $this->request->param('token');
        $row   = PasswordReset::verify($token);

        if (!$row) {
            Session::flash('error', 'Odkaz pro reset hesla je neplatný nebo vypršel.');
            $this->redirect('/password/reset');
        }

        $this->view('password-reset/form', [
            'pageTitle' => 'Nové heslo',
            'token'     => $token,
            'email'     => $row['email'],
        ], 'auth');
    }

    public function resetSubmit(): void
    {
        $this->validateCsrf();
        $token   = $this->request->param('token');
        $row     = PasswordReset::verify($token);

        if (!$row) {
            Session::flash('error', 'Odkaz pro reset hesla je neplatný nebo vypršel.');
            $this->redirect('/password/reset');
        }

        $password = $this->request->post('password', '');
        $confirm  = $this->request->post('password_confirm', '');

        if (strlen($password) < 8) {
            Session::flash('error', 'Heslo musí mít alespoň 8 znaků.');
            $this->redirect('/password/reset/' . $token);
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Hesla se neshodují.');
            $this->redirect('/password/reset/' . $token);
        }

        User::update($row['user_id'], [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
        ]);

        PasswordReset::markUsed($token);

        Session::flash('success', 'Heslo bylo úspěšně změněno. Přihlaste se.');
        $this->redirect('/login');
    }

    private function buildEmail(string $name, string $link): string
    {
        $appName = defined('APP_NAME') ? APP_NAME : 'ShopCode';
        return <<<HTML
<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"></head>
<body style="background:#0f1117;font-family:sans-serif;color:#e5e7eb;margin:0;padding:40px 20px;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table width="560" style="background:#1a1d27;border-radius:12px;padding:40px;">
<tr><td>
  <p style="font-size:20px;font-weight:700;color:#fff;margin-top:0;">{$appName}</p>
  <h2 style="color:#fff;">Resetování hesla</h2>
  <p>Ahoj {$name},</p>
  <p>Obdrželi jsme žádost o reset hesla pro váš účet. Klikněte na tlačítko níže:</p>
  <p style="margin:32px 0;">
    <a href="{$link}"
       style="background:#3b82f6;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">
       Nastavit nové heslo
    </a>
  </p>
  <p style="color:#9ca3af;font-size:14px;">Odkaz je platný 24 hodin. Pokud jste o reset nepožádali, tento e-mail ignorujte.</p>
  <hr style="border-color:#374151;margin:24px 0;">
  <p style="color:#6b7280;font-size:12px;">Nebo zkopírujte tento odkaz do prohlížeče:<br>
    <a href="{$link}" style="color:#60a5fa;word-break:break-all;">{$link}</a>
  </p>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }
}
