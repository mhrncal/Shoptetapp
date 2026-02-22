<?php

namespace ShopCode\Controllers;

use ShopCode\Core\{Request, View, Session, Response};

abstract class BaseController
{
    protected Request $request;
    protected ?array  $user;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->user    = Session::get('user');
    }

    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        // Přidáme aktivní moduly do dat
        if ($this->user && $layout === 'main') {
            $data['activeModules'] = \ShopCode\Models\UserModule::getActiveNamesForUser($this->user['id']);
        }
        View::render($view, $data, $layout);
    }

    protected function redirect(string $url): never
    {
        Response::redirect($url);
    }

    protected function json(mixed $data, int $code = 200): never
    {
        Response::json($data, $code);
    }

    protected function validateCsrf(): void
    {
        $token = $this->request->post('_csrf') ?? $this->request->get('_csrf') ?? '';
        if (!Session::validateCsrf($token)) {
            Session::flash('error', 'Neplatný bezpečnostní token. Zkuste akci opakovat.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/');
        }
    }

    protected function isAdmin(): bool
    {
        return ($this->user['role'] ?? '') === 'superadmin';
    }

    // Vrátí true pokud user impersonuje někoho jiného
    protected function isImpersonating(): bool
    {
        return Session::has('impersonating_as');
    }
}
