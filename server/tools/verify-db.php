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
} catch (PDOException $e) {
    $mysqlCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    $reason = match ($mysqlCode) {
        1045 => 'authentication rejected (check database username/password)',
        1049 => 'database name not found',
        2002 => 'database host/socket unavailable',
        default => 'database connection rejected',
    };
    fwrite(STDERR, "Database connection failed: MySQL {$mysqlCode} - {$reason}.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed before MySQL authentication.\n");
    exit(1);
}
