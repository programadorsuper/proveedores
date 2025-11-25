<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Users.php';

class UsersController extends ProtectedController
{
    protected Users $users;

    public function __construct()
    {
        parent::__construct();
        $this->users = new Users();
    }

    public function index(): void
    {
        if (!$this->ensureModule('users_admin')) {
            return;
        }

        if (empty($this->permissions['users'])) {
            $this->renderModule('users/index', [
                'title' => 'Usuarios',
                'users' => [],
                'accessDenied' => true,
            ], 'users_admin');
            return;
        }
        
        $isSuperAdmin = !empty($this->user['is_super_admin']);
        $list = $this->users->listFor($this->user);

        $this->renderModule('users/index', [
            'title' => 'Usuarios',
            'users' => $list,
            'isSuperAdmin' => $isSuperAdmin,
        ], 'users_admin');
    }
}
