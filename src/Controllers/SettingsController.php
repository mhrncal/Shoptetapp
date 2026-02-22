<?php
namespace ShopCode\Controllers;

use ShopCode\Core\{Session, Database};
use ShopCode\Models\{User, UserModule, Module};

class SettingsController extends BaseController
{
    public function index(): void
    {
        $userId      = $this->user['id'];
        $user        = User::findById($userId);
        $modules     = Module::all();
        $userModules = UserModule::getForUser($userId);

        // Indexujeme userModules podle module_id
        $activeMap = [];
        foreach ($userModules as $um) {
            $activeMap[$um['module_id']] = $um['status'] === 'active';
        }

        $this->view('settings/index', [
            'pageTitle'  => 'Nastavení',
            'user'       => $user,
            'modules'    => $modules,
            'activeMap'  => $activeMap,
        ]);
    }

    public function updateProfile(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];

        $data = [
            'first_name'   => trim($this->request->post('first_name', '')),
            'last_name'    => trim($this->request->post('last_name', '')),
            'shop_name'    => trim($this->request->post('shop_name', '')),
            'shop_url'     => trim($this->request->post('shop_url', '')),
            'xml_feed_url' => trim($this->request->post('xml_feed_url', '')),
        ];

        // Validace
        if (empty($data['first_name']) || empty($data['last_name'])) {
            Session::flash('error', 'Jméno a příjmení jsou povinné.');
            $this->redirect('/settings');
        }

        User::update($userId, $data);
        // Aktualizuj session
        $_SESSION['user'] = array_merge($_SESSION['user'], $data);
        Session::flash('success', 'Profil byl uložen.');
        $this->redirect('/settings');
    }

    public function updatePassword(): void
    {
        $this->validateCsrf();
        $userId  = $this->user['id'];
        $user    = User::findById($userId);
        $current = $this->request->post('current_password', '');
        $new     = $this->request->post('new_password', '');
        $confirm = $this->request->post('confirm_password', '');

        if (!password_verify($current, $user['password_hash'])) {
            Session::flash('error', 'Aktuální heslo není správné.');
            $this->redirect('/settings#password');
        }
        if (strlen($new) < 8) {
            Session::flash('error', 'Nové heslo musí mít alespoň 8 znaků.');
            $this->redirect('/settings#password');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'Hesla se neshodují.');
            $this->redirect('/settings#password');
        }

        User::update($userId, ['password_hash' => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12])]);
        Session::flash('success', 'Heslo bylo změněno.');
        $this->redirect('/settings');
    }

    public function deleteAccount(): void
    {
        $this->validateCsrf();
        $userId   = $this->user['id'];
        $user     = User::findById($userId);
        $password = $this->request->post('confirm_password', '');

        if (!password_verify($password, $user['password_hash'])) {
            Session::flash('error', 'Heslo není správné. Účet nebyl smazán.');
            $this->redirect('/settings#danger');
        }

        // Superadmin se nemůže smazat
        if ($user['role'] === 'superadmin') {
            Session::flash('error', 'Superadmin účet nelze smazat.');
            $this->redirect('/settings');
        }

        Session::destroy();
        User::delete($userId);
        $this->redirect('/login');
    }
}
