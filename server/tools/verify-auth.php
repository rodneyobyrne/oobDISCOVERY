<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$configPath = $home . '/oob-discovery-config.php';
$accessPath = $home . '/oob-discovery-results.php';
$authLibrary = $home . '/oob-discovery-lib/discovery-auth.php';
if (!is_file($configPath) || !is_file($accessPath) || !is_file($authLibrary)) {
    fwrite(STDERR, "Private account configuration not found.\n");
    exit(2);
}
require_once $authLibrary;

try {
    $databaseConfig = require $configPath;
    $accessConfig = require $accessPath;
    $pdo = oobDatabaseConnection($databaseConfig);
    $probe = bin2hex(random_bytes(16));
    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status) VALUES (:email, :username, :password_hash, 'pending')");
        $user->execute([
            ':email' => 'deployment-' . $probe . '@example.invalid',
            ':username' => 'deploy_' . substr($probe, 0, 20),
            ':password_hash' => password_hash($probe, PASSWORD_DEFAULT),
        ]);
        $userId = (int)$pdo->lastInsertId();
        $invitation = $pdo->prepare('INSERT INTO discovery_invitations (token_hash, client_id, client_label, role, expires_at, created_by) VALUES (:token_hash, :client_id, :client_label, :role, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY), :created_by)');
        $invitation->execute([
            ':token_hash' => hash('sha256', $probe),
            ':client_id' => 'deployment-check',
            ':client_label' => 'Deployment check',
            ':role' => 'viewer',
            ':created_by' => 'deployment',
        ]);
        $access = $pdo->prepare('INSERT INTO discovery_user_clients (user_id, client_id, role) VALUES (:user_id, :client_id, :role)');
        $access->execute([':user_id' => $userId, ':client_id' => 'deployment-check', ':role' => 'viewer']);
        $accountToken = $pdo->prepare("INSERT INTO discovery_account_tokens (user_id, purpose, token_hash, expires_at) VALUES (:user_id, 'verify', :token_hash, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY))");
        $accountToken->execute([':user_id' => $userId, ':token_hash' => hash('sha256', 'account-' . $probe)]);
        $read = $pdo->prepare('SELECT COUNT(*) FROM discovery_user_clients WHERE user_id = :user_id');
        $read->execute([':user_id' => $userId]);
        if ((int)$read->fetchColumn() !== 1) throw new RuntimeException('Account access probe was not readable.');
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    if (oobAccountAuthEnabled($accessConfig) && !class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        require_once oobMailerAutoloadPath($accessConfig);
    }
    if (oobAccountAuthEnabled($accessConfig) && !class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        throw new RuntimeException('PHPMailer is required for account email.');
    }
    fwrite(STDOUT, oobAccountAuthEnabled($accessConfig)
        ? "Account storage and SMTP prerequisites OK\n"
        : "Account storage OK; invited accounts are disabled\n");
    exit(0);
} catch (PDOException $error) {
    $mysqlCode = isset($error->errorInfo[1]) ? (int)$error->errorInfo[1] : 0;
    fwrite(STDERR, "Account verification failed: MySQL {$mysqlCode}.\n");
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, "Account verification failed: " . $error->getMessage() . "\n");
    exit(1);
}
