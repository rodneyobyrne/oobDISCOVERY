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

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discovery_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auth_user_id CHAR(36) NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  username VARCHAR(32) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  is_system_admin TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_discovery_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $columnCheck = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :schema_name AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
    $columnCheck->execute([':schema_name' => (string)($db['name'] ?? ''), ':table_name' => 'discovery_users', ':column_name' => 'password_hash']);
    if ((int)$columnCheck->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE discovery_users ADD COLUMN password_hash VARCHAR(255) NULL AFTER username');
    }
    $pdo->exec('ALTER TABLE discovery_users MODIFY auth_user_id CHAR(36) NULL');

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discovery_user_clients (
  user_id BIGINT UNSIGNED NOT NULL,
  client_id VARCHAR(80) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'viewer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, client_id),
  INDEX idx_discovery_user_clients_client (client_id),
  CONSTRAINT fk_discovery_user_clients_user FOREIGN KEY (user_id) REFERENCES discovery_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discovery_invitations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL UNIQUE,
  client_id VARCHAR(80) NOT NULL,
  client_label VARCHAR(160) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'viewer',
  expires_at DATETIME NOT NULL,
  claimed_at DATETIME NULL,
  claimed_by_user_id BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  created_by VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_discovery_invitations_client (client_id),
  INDEX idx_discovery_invitations_expiry (expires_at),
  CONSTRAINT fk_discovery_invitations_user FOREIGN KEY (claimed_by_user_id) REFERENCES discovery_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discovery_account_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  purpose VARCHAR(20) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_discovery_account_tokens_user_purpose (user_id, purpose),
  INDEX idx_discovery_account_tokens_expiry (expires_at),
  CONSTRAINT fk_discovery_account_tokens_user FOREIGN KEY (user_id) REFERENCES discovery_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    fwrite(STDOUT, "Database and account migrations OK\n");
    exit(0);
} catch (PDOException $e) {
    $mysqlCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    fwrite(STDERR, "Database migration failed: MySQL {$mysqlCode}.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Database migration failed before completion.\n");
    exit(1);
}
