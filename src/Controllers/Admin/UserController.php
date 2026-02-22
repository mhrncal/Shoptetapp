<?php

namespace ShopCode\Controllers\Admin;

use ShopCode\Controllers\BaseController;
use ShopCode\Core\{Session, Response};
use ShopCode\Models\{User, UserModule, AuditLog};

class UserController extends BaseController
{
    public function index(): void
    {
        $page    = max(1, (int)$this->request->get('page', 1));
        $search  = $this->request->get('search', '');
        $status  = $this->request->get('status', '');

        $filters = array_filter(['search' => $search, 'status' => $status]);
        $users   = User::all($filters, $page, 25);
        $total   = User::count($filters);

        $this->view('admin/users/index', [
            'users'      => $users,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => 25,
            'search'     => $search,
            'statusFilter' => $status,
        ], 'admin');
    }

    public function detail(): void
    {
        $user = $this->getUserOr404();
        $modules = UserModule::getForUser($user['id']);

        $this->view('admin/users/detail', [
            'targetUser' => $user,
            'modules'    => $modules,
        ], 'admin');
    }

    public function edit(): void
    {
        $user = $this->getUserOr404();
        $this->view('admin/users/edit', ['targetUser' => $user], 'admin');
    }

    public function update(): void
    {
        $this->validateCsrf();
        $user = $this->getUserOr404();

        $data = [
            'first_name' => trim($this->request->post('first_name', '')),
            'last_name'  => trim($this->request->post('last_name', '')),
            'shop_name'  => trim($this->request->post('shop_name', '')),
            'shop_url'   => trim($this->request->post('shop_url', '')),
            'status'     => $this->request->post('status', $user['status']),
        ];

        // Superadmin nemůže změnit roli jiného superadmina
        if ($user['role'] !== 'superadmin') {
            $data['role'] = $this->request->post('role', 'user');
        }

        User::update($user['id'], $data);
        AuditLog::log('admin_user_updated', 'user', (string)$user['id'], $user, $data);
        Session::flash('success', 'Uživatel byl upraven.');
        $this->redirect('/admin/users/' . $user['id']);
    }

    public function approve(): void
    {
        $this->validateCsrf();
        $user = $this->getUserOr404();

        User::update($user['id'], ['status' => 'approved']);
        AuditLog::log('user_approved', 'user', (string)$user['id']);
        Session::flash('success', 'Uživatel byl schválen.');
        $this->redirect('/admin/users');
    }

    public function reject(): void
    {
        $this->validateCsrf();
        $user = $this->getUserOr404();

        User::update($user['id'], ['status' => 'rejected']);
        AuditLog::log('user_rejected', 'user', (string)$user['id']);
        Session::flash('warning', 'Uživatel byl zamítnut.');
        $this->redirect('/admin/users');
    }

    public function delete(): void
    {
        $this->validateCsrf();
        $user = $this->getUserOr404();

        if ($user['role'] === 'superadmin') {
            Session::flash('error', 'Superadmina nelze smazat.');
            $this->redirect('/admin/users');
        }

        AuditLog::log('admin_user_deleted', 'user', (string)$user['id'], $user);
        User::delete($user['id']);
        Session::flash('success', 'Uživatel byl smazán.');
        $this->redirect('/admin/users');
    }

    public function impersonate(): void
    {
        $this->validateCsrf();
        $targetUser = $this->getUserOr404();

        if ($targetUser['role'] === 'superadmin') {
            Session::flash('error', 'Nelze impersonovat jiného superadmina.');
            $this->redirect('/admin/users');
        }

        // Uložíme aktuálního admina
        Session::set('impersonating_as', Session::get('user'));

        // Přihlásíme se jako cílový uživatel
        Session::set('user', [
            'id'         => $targetUser['id'],
            'email'      => $targetUser['email'],
            'first_name' => $targetUser['first_name'],
            'last_name'  => $targetUser['last_name'],
            'role'       => $targetUser['role'],
            'status'     => $targetUser['status'],
            'shop_name'  => $targetUser['shop_name'],
        ]);

        AuditLog::log('impersonate_start', 'user', (string)$targetUser['id']);
        Session::flash('info', 'Nyní zobrazujete aplikaci jako ' . User::fullName($targetUser));
        $this->redirect('/dashboard');
    }

    public function stopImpersonate(): void
    {
        $this->validateCsrf();

        if (!Session::has('impersonating_as')) {
            $this->redirect('/dashboard');
        }

        $adminData = Session::get('impersonating_as');
        Session::delete('impersonating_as');
        Session::set('user', $adminData);

        AuditLog::log('impersonate_stop', 'user');
        Session::flash('success', 'Impersonace ukončena.');
        $this->redirect('/admin/users');
    }

    private function getUserOr404(): array
    {
        $id   = (int)$this->request->param('id');
        $user = User::findById($id);
        if (!$user) {
            Response::notFound();
        }
        return $user;
    }
}
