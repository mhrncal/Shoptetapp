<?php

namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\User;

class ShoptetSettingsController extends BaseController
{
    public function index(): void
    {
        $userId = $this->user['id'];
        $user = User::findById($userId);
        
        $hasCredentials = User::hasShoptetCredentials($userId);
        
        $this->view('settings/shoptet', [
            'pageTitle' => 'Shoptet Integrace',
            'user' => $user,
            'hasCredentials' => $hasCredentials,
            'success' => Session::flash('success'),
            'error' => Session::flash('error'),
            'csrfToken' => $this->generateCsrfToken(),
        ]);
    }
    
    public function update(): void
    {
        $this->validateCsrf();
        
        $userId = $this->user['id'];
        $shoptetUrl = trim($this->request->post('shoptet_url', ''));
        $shoptetEmail = trim($this->request->post('shoptet_email', ''));
        $shoptetPassword = trim($this->request->post('shoptet_password', ''));
        $autoImport = (bool)$this->request->post('auto_import', false);
        
        // Validace
        if (empty($shoptetUrl) || !filter_var($shoptetUrl, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Zadejte platnou Shoptet URL.');
            $this->redirect('/settings/shoptet');
        }
        
        if (empty($shoptetEmail) || !filter_var($shoptetEmail, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Zadejte platný email.');
            $this->redirect('/settings/shoptet');
        }
        
        // Pokud heslo není zadané a credentials již existují, použij staré heslo
        if (empty($shoptetPassword)) {
            if (!User::hasShoptetCredentials($userId)) {
                Session::flash('error', 'Zadejte heslo.');
                $this->redirect('/settings/shoptet');
            }
            
            // Použij staré heslo
            $shoptetPassword = User::getShoptetPassword($userId);
            
            if (!$shoptetPassword) {
                Session::flash('error', 'Nepodařilo se načíst staré heslo.');
                $this->redirect('/settings/shoptet');
            }
        }
        
        // Ulož credentials
        try {
            $success = User::updateShoptetCredentials(
                $userId,
                $shoptetEmail,
                $shoptetPassword,
                $shoptetUrl,
                $autoImport
            );
            
            if ($success) {
                Session::flash('success', 'Shoptet integrace byla úspěšně nastavena!');
            } else {
                Session::flash('error', 'Nepodařilo se uložit nastavení.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Chyba: ' . $e->getMessage());
        }
        
        $this->redirect('/settings/shoptet');
    }
    
    public function delete(): void
    {
        $this->validateCsrf();
        
        $userId = $this->user['id'];
        
        try {
            $success = User::deleteShoptetCredentials($userId);
            
            if ($success) {
                Session::flash('success', 'Shoptet credentials byly smazány.');
            } else {
                Session::flash('error', 'Nepodařilo se smazat credentials.');
            }
        } catch (\Throwable $e) {
            Session::flash('error', 'Chyba: ' . $e->getMessage());
        }
        
        $this->redirect('/settings/shoptet');
    }
}
