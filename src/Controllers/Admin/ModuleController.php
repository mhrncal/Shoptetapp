<?php

namespace ShopCode\Controllers\Admin;

use ShopCode\Controllers\BaseController;
use ShopCode\Core\Session;
use ShopCode\Models\{Module, UserModule, User, AuditLog};

class ModuleController extends BaseController
{
    public function index(): void
    {
        $modules = Module::all();
        $users   = User::all(['status' => 'approved'], 1, 1000);

        // Pro každého uživatele načteme jeho moduly
        $userModules = [];
        foreach ($users as $u) {
            $userModules[$u['id']] = UserModule::getActiveNamesForUser($u['id']);
        }

        $this->view('admin/modules/index', [
            'modules'     => $modules,
            'users'       => $users,
            'userModules' => $userModules,
        ], 'admin');
    }

    public function assign(): void
    {
        $this->validateCsrf();

        $userId   = (int)$this->request->post('user_id');
        $moduleId = (int)$this->request->post('module_id');
        $status   = $this->request->post('status') === 'active' ? 'active' : 'inactive';

        $user   = User::findById($userId);
        $module = Module::all();

        if (!$user) {
            Session::flash('error', 'Uživatel nenalezen.');
            $this->redirect('/admin/modules');
        }

        UserModule::setStatus($userId, $moduleId, $status);

        $label = $status === 'active' ? 'aktivován' : 'deaktivován';
        AuditLog::log("module_{$status}", 'user_module', "{$userId}:{$moduleId}");
        Session::flash('success', "Modul byl {$label}.");

        // AJAX odpověď
        if ($this->request->isAjax()) {
            $this->json(['success' => true, 'status' => $status]);
        }

        $this->redirect('/admin/users/' . $userId);
    }
}
