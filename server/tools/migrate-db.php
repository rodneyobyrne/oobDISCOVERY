<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$configPath = $home . '/oob-discovery-config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Private config file not found.\n");
    exit(2);
}

$config = require $configPath;
$db = $config['database'] ?? [];

try {
    $pdo = new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    // LONGTEXT keeps the storage contract compatible with older MariaDB versions.
    // The API validates and canonicalizes JSON before it reaches this table.
    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discovery_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id VARCHAR(80) NOT NULL UNIQUE,
  discovery_type VARCHAR(40) NOT NULL,
  client_id VARCHAR(80) NOT NULL,
  respondent_name VARCHAR(160) NOT NULL,
  respondent_email VARCHAR(254) NULL,
  questionnaire_version VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'received',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    fwrite(STDOUT, "Database migration OK\n");
    exit(0);
} catch (PDOException $e) {
    $mysqlCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    fwrite(STDERR, "Database migration failed: MySQL {$mysqlCode}.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Database migration failed before completion.\n");
    exit(1);
}
