<?php

require_once __DIR__ . '/../core/AuthManager.php';

class Auth
{
    public static function check(): void
    {
        AuthManager::requireAuth(true);
    }
}
