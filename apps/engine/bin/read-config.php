<?php
declare(strict_types=1);

/**
 * Usage:
 *   php read-config.php server.host
 *   php read-config.php server.port
 *   php read-config.php server.base_url
 */

$config = require __DIR__ . '/../config/app.php';

if ($argc < 2) {
    fwrite(STDERR, "Missing config key\n");
    exit(1);
}

$key = $argv[1];
$parts = explode('.', $key);

$value = $config;
foreach ($parts as $part) {
    if (!is_array($value) || !array_key_exists($part, $value)) {
        fwrite(STDERR, "Config key not found: $key\n");
        exit(1);
    }
    $value = $value[$part];
}

echo is_scalar($value) ? (string)$value : json_encode($value);
