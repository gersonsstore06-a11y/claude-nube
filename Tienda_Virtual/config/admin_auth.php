<?php
require_once __DIR__ . '/app.php';

const ADMIN_AUTH_SESSION_KEY = 'tienda_virtual_admin';

if (!defined('ADMIN_AUTH_EMAIL')) {
    define('ADMIN_AUTH_EMAIL', (string) appConfigGet('admin.email', 'admin@tienda.com'));
}

if (!defined('ADMIN_AUTH_NAME')) {
    define('ADMIN_AUTH_NAME', (string) appConfigGet('admin.name', 'Administrador'));
}

if (!defined('ADMIN_AUTH_PASSWORD_HASH')) {
    define('ADMIN_AUTH_PASSWORD_HASH', (string) appConfigGet('admin.password_hash', ''));
}

function adminAuthBaseUrl(): string
{
    return appBaseUrl();
}

function adminAuthStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        $cookiePath = adminAuthBaseUrl();
        session_set_cookie_params([
            'path' => $cookiePath !== '' ? $cookiePath . '/' : '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function adminAuthDefaultRedirect(): string
{
    return adminAuthBaseUrl() . '/admin/dashboard.php';
}

function adminAuthNormalizeRedirect(?string $redirect): string
{
    $redirect = trim((string) $redirect);

    if ($redirect === '') {
        return adminAuthDefaultRedirect();
    }

    if (preg_match('/^https?:\/\//i', $redirect)) {
        return adminAuthDefaultRedirect();
    }

    if (str_starts_with($redirect, adminAuthBaseUrl() . '/')) {
        return $redirect;
    }

    if (str_starts_with($redirect, '/')) {
        return $redirect;
    }

    return adminAuthDefaultRedirect();
}

function adminAuthRedirect(string $location): void
{
    header('Location: ' . $location);
    exit;
}

function adminAuthIsLoggedIn(): bool
{
    adminAuthStartSession();

    return isset($_SESSION[ADMIN_AUTH_SESSION_KEY]['role'])
        && $_SESSION[ADMIN_AUTH_SESSION_KEY]['role'] === 'admin';
}

function adminAuthCurrentUser(): ?array
{
    if (!adminAuthIsLoggedIn()) {
        return null;
    }

    $user = $_SESSION[ADMIN_AUTH_SESSION_KEY];

    return is_array($user) ? $user : null;
}

function adminAuthLogin(string $email, string $password): bool
{
    adminAuthStartSession();

    $normalizedEmail = strtolower(trim($email));

    if (!hash_equals(ADMIN_AUTH_EMAIL, $normalizedEmail)) {
        return false;
    }

    if (!password_verify($password, ADMIN_AUTH_PASSWORD_HASH)) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION[ADMIN_AUTH_SESSION_KEY] = [
        'email' => ADMIN_AUTH_EMAIL,
        'name' => ADMIN_AUTH_NAME,
        'role' => 'admin',
        'logged_at' => time(),
    ];

    return true;
}

function adminAuthLogout(): void
{
    adminAuthStartSession();
    unset($_SESSION[ADMIN_AUTH_SESSION_KEY]);
    session_regenerate_id(true);
}

function adminAuthRequireLogin(): void
{
    if (adminAuthIsLoggedIn()) {
        return;
    }

    $redirect = $_SERVER['REQUEST_URI'] ?? adminAuthDefaultRedirect();
    $loginUrl = adminAuthBaseUrl() . '/views/login.php?auth=required&redirect=' . rawurlencode($redirect);
    adminAuthRedirect($loginUrl);
}