<?php
namespace ShopCode\Controllers;
class SettingsController extends BaseController {
    public function index(): void { $this->view('errors/404', [], 'auth'); }
    public function update(): void { $this->redirect('/settings'); }
}
