<?php
namespace ShopCode\Controllers;
class StatisticsController extends BaseController {
    public function index(): void { $this->view('errors/404', [], 'auth'); }
    public function store(): void { $this->redirect('/dashboard'); }
    public function edit(): void  { $this->view('errors/404', [], 'auth'); }
    public function update(): void { $this->redirect('/dashboard'); }
    public function delete(): void { $this->redirect('/dashboard'); }
    public function detail(): void { $this->view('errors/404', [], 'auth'); }
    public function start(): void  { $this->redirect('/xml'); }
    public function status(): void { $this->json(['status' => 'not_implemented']); }
}
