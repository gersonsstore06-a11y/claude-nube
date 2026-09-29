<?php
require_once __DIR__ . '/../config/admin_auth.php';

class AuthController
{
    public function login(array $credentials): bool
    {
        return adminAuthLogin(
            (string) ($credentials['email'] ?? ''),
            (string) ($credentials['password'] ?? '')
        );
    }

    public function registro(array $data): bool
    {
        return !empty($data['nombre']) && !empty($data['email']) && !empty($data['password']);
    }

    public function logout(): void
    {
        adminAuthLogout();
    }
}
