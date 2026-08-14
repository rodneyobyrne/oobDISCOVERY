<?php
declare(strict_types=1);

$configPath = '/home1/reaqfvmy/oob-discovery-config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Private config file not found.\n");
    exit(2);
}

$config = require $configPath;
$db = $config['database'] ?? [];

try {
    new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    fwrite(STDOUT, "Database connection OK\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . get_class($e) . "\n");
    exit(1);
}
