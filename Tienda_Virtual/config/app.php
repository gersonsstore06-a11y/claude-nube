<?php

function appEnv(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function appArrayMergeRecursiveDistinct(array $base, array $overrides): array
{
    foreach ($overrides as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = appArrayMergeRecursiveDistinct($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function appDocumentBaseUrl(): string
{
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $projectRoot = realpath(__DIR__ . '/..');
    $normalizedDocumentRoot = $documentRoot ? str_replace('\\', '/', $documentRoot) : '';
    $normalizedProjectRoot = $projectRoot ? str_replace('\\', '/', $projectRoot) : '';

    if ($normalizedDocumentRoot === '' || $normalizedProjectRoot === '') {
        return '';
    }

    if (!str_starts_with($normalizedProjectRoot, $normalizedDocumentRoot)) {
        return '';
    }

    $relativePath = trim(substr($normalizedProjectRoot, strlen($normalizedDocumentRoot)), '/');

    return $relativePath === '' ? '' : '/' . $relativePath;
}

function appNormalizeBaseUrl(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $value)) {
        $path = (string) parse_url($value, PHP_URL_PATH);
        $value = $path;
    }

    $value = '/' . trim($value, '/');

    return $value === '/' ? '' : $value;
}

function appConfig(): array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = [
        'app' => [
            'name' => "Gerson's Store",
            'base_url' => appDocumentBaseUrl(),
            'currency_symbol' => 'L',
            'currency_code' => 'HNL',
        ],
        'db' => [
            'host' => '127.0.0.1',
            'port' => 3307,
            'name' => 'tienda_virtual',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
        'admin' => [
            'email' => 'admin@tienda.com',
            'name' => 'Administrador',
            'password_hash' => '$2y$10$IO85wfjU.yNAcBImjq2QZupqNaVKu7d6Sysf1vBZeZGcGNWCZpfDy',
        ],
    ];

    $localConfigPath = __DIR__ . '/local.php';
    $localConfig = [];

    if (is_file($localConfigPath)) {
        $loadedConfig = require $localConfigPath;
        if (is_array($loadedConfig)) {
            $localConfig = $loadedConfig;
        }
    }

    $config = appArrayMergeRecursiveDistinct($defaults, $localConfig);

    $envOverrides = [
        'app' => [
            'name' => (string) appEnv('APP_NAME', (string) ($config['app']['name'] ?? $defaults['app']['name'])),
            'base_url' => appNormalizeBaseUrl((string) appEnv('APP_BASE_URL', (string) ($config['app']['base_url'] ?? $defaults['app']['base_url']))),
            'currency_symbol' => (string) appEnv('APP_CURRENCY_SYMBOL', (string) ($config['app']['currency_symbol'] ?? $defaults['app']['currency_symbol'])),
            'currency_code' => strtoupper((string) appEnv('APP_CURRENCY_CODE', (string) ($config['app']['currency_code'] ?? $defaults['app']['currency_code']))),
        ],
        'db' => [
            'host' => (string) appEnv('DB_HOST', (string) ($config['db']['host'] ?? $defaults['db']['host'])),
            'port' => (int) appEnv('DB_PORT', (int) ($config['db']['port'] ?? $defaults['db']['port'])),
            'name' => (string) appEnv('DB_NAME', (string) ($config['db']['name'] ?? $defaults['db']['name'])),
            'user' => (string) appEnv('DB_USER', (string) ($config['db']['user'] ?? $defaults['db']['user'])),
            'pass' => (string) appEnv('DB_PASS', (string) ($config['db']['pass'] ?? $defaults['db']['pass'])),
            'charset' => (string) appEnv('DB_CHARSET', (string) ($config['db']['charset'] ?? $defaults['db']['charset'])),
        ],
        'admin' => [
            'email' => strtolower(trim((string) appEnv('ADMIN_AUTH_EMAIL', (string) ($config['admin']['email'] ?? $defaults['admin']['email'])))),
            'name' => (string) appEnv('ADMIN_AUTH_NAME', (string) ($config['admin']['name'] ?? $defaults['admin']['name'])),
            'password_hash' => (string) appEnv('ADMIN_AUTH_PASSWORD_HASH', (string) ($config['admin']['password_hash'] ?? $defaults['admin']['password_hash'])),
        ],
    ];

    $config = appArrayMergeRecursiveDistinct($config, $envOverrides);

    return $config;
}

function appConfigGet(string $path, mixed $default = null): mixed
{
    $segments = array_filter(explode('.', $path), static fn (string $segment): bool => $segment !== '');
    $value = appConfig();

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function appBaseUrl(): string
{
    return appNormalizeBaseUrl((string) appConfigGet('app.base_url', ''));
}

function appCurrencySymbol(): string
{
    $symbol = trim((string) appConfigGet('app.currency_symbol', 'L'));

    return $symbol !== '' ? $symbol : 'L';
}

function appCurrencyCode(): string
{
    $code = strtoupper(trim((string) appConfigGet('app.currency_code', 'HNL')));

    return $code !== '' ? $code : 'HNL';
}

function appMoney(float|int $amount, int $decimals = 2): string
{
    return appCurrencySymbol() . ' ' . number_format((float) $amount, $decimals);
}