<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'HESTIA Engine',
        'version' => '0.1.0',
        'env' => 'dev',
    ],

    'server' => [
        'host' => '127.0.0.1',
        'port' => 8787,
        'base_url' => 'http://127.0.0.1:8787',
    ],

    'paths' => [
        'storage' => __DIR__ . '/../storage',
        'db' => __DIR__ . '/../storage/db/hestia.db',
        'logs' => __DIR__ . '/../storage/logs',
        'cache' => __DIR__ . '/../storage/cache',
    ],

    'scan_defaults' => [
        'follow_symlinks' => false,
        'max_depth' => 20,
        'exclude_names' => [
            '.git',
            'node_modules',
            'vendor',
            '.idea',
            '.vscode',
        ],
    ],
];
