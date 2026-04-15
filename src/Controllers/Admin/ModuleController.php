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

        $user = User::findById($userId);
        if (!$user) {
            $this->json(['success' => false, 'error' => 'Uživatel nenalezen.'], 404);
        }

        UserModule::setStatus($userId, $moduleId, $status);
        AuditLog::log("module_{$status}", 'user_module', "{$userId}:{$moduleId}");

        $this->json(['success' => true, 'status' => $status]);
    }
}
