<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\{User, AuditLog};

class ProfileController extends BaseController
{
    public function edit(): void
    {
        $user = User::findById($this->user['id']);
        $this->view('profile/edit', ['user' => $user]);
    }

    public function update(): void
    {
        $this->validateCsrf();

        $userId = $this->user['id'];
        $user   = User::findById($userId);

        $data = [
            'first_name'    => trim($this->request->post('first_name', '')),
            'last_name'     => trim($this->request->post('last_name', '')),
            'shop_name'     => trim($this->request->post('shop_name', '')),
            'shop_url'      => trim($this->request->post('shop_url', '')),
            'xml_feed_url'  => trim($this->request->post('xml_feed_url', '')),
        ];

        $errors = [];
        if (empty($data['first_name']) || empty($data['last_name'])) {
            $errors[] = 'Jméno a příjmení jsou povinné.';
        }

        // Změna hesla (nepovinné)
        $newPassword  = $this->request->post('new_password', '');
        $currPassword = $this->request->post('current_password', '');

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                $errors[] = 'Nové heslo musí mít alespoň 8 znaků.';
            } elseif (!password_verify($currPassword, $user['password_hash'])) {
                $errors[] = 'Aktuální heslo není správné.';
            }
        }

        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('/profile');
        }

        User::update($userId, $data);

        if (!empty($newPassword) && empty($errors)) {
            User::updatePassword($userId, $newPassword);
        }

        // Aktualizuj session
        $updated = Session::get('user');
        $updated['first_name'] = $data['first_name'];
        $updated['last_name']  = $data['last_name'];
        $updated['shop_name']  = $data['shop_name'];
        Session::set('user', $updated);
        $this->user = $updated;

        AuditLog::log('profile_updated', 'user', (string)$userId);
        Session::flash('success', 'Profil byl úspěšně uložen.');
        $this->redirect('/profile');
    }
}
