<?php
namespace ShopCode\Controllers;

use ShopCode\Core\Session;
use ShopCode\Models\ApiToken;

class ApiTokenController extends BaseController
{
    public function index(): void
    {
        $this->view('api-tokens/index', [
            'pageTitle'   => 'API tokeny',
            'tokens'      => ApiToken::allForUser($this->user['id']),
            'permissions' => ApiToken::PERMISSIONS,
            'newToken'    => Session::get('new_token'),
        ]);
        Session::delete('new_token');
    }

    public function store(): void
    {
        $this->validateCsrf();
        $userId = $this->user['id'];

        $name        = trim($this->request->post('name', ''));
        $permissions = $this->request->post('permissions', []);
        $expiresAt   = $this->request->post('expires_at', '') ?: null;
        if ($expiresAt) $expiresAt = date('Y-m-d 23:59:59', strtotime($expiresAt));

        if (empty($name)) {
            Session::flash('error', 'Zadejte název tokenu.');
            $this->redirect('/api-tokens');
        }
        if (empty($permissions) || !is_array($permissions)) {
            Session::flash('error', 'Vyberte alespoň jedno oprávnění.');
            $this->redirect('/api-tokens');
        }

        // Filtr jen povolených hodnot
        $permissions = array_values(array_intersect($permissions, ApiToken::PERMISSIONS));

        $result = ApiToken::create($userId, $name, $permissions, $expiresAt);

        // Uložíme plaintext do session — zobrazíme jednou, pak zapomeneme
        Session::set('new_token', $result['plaintext']);
        Session::flash('success', 'Token byl vytvořen. Zkopírujte ho — nebude znovu zobrazen!');
        $this->redirect('/api-tokens');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $id = (int)$this->request->param('id');
        ApiToken::delete($id, $this->user['id']);
        Session::flash('success', 'Token byl smazán.');
        $this->redirect('/api-tokens');
    }
}
