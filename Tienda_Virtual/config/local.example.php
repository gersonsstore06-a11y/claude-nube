<?php

return [
    'app' => [
        'name' => "Gerson's Store",
        'base_url' => '/Tienda_Virtual',
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