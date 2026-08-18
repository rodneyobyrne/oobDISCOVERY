<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$authLibrary = $home . '/oob-discovery-lib/discovery-auth.php';
if (!is_file($authLibrary)) {
    fwrite(STDERR, "Authentication library not found.\n");
    exit(2);
}
require_once $authLibrary;

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $smtp = oobSmtpConfig($accessConfig);

    $email = strtolower(trim((string)($smtp['username'] ?? '')));
    $username = trim((string)($accessConfig['username'] ?? ''));
    $legacyHash = (string)($accessConfig['password_hash'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The SMTP mailbox is not a valid administrator recovery email.');
    }
    if (!oobValidUsername($username)) {
        throw new RuntimeException('The configured results username cannot be migrated to an account.');
    }
    if ($legacyHash === '') {
        throw new RuntimeException('The configured results password hash is missing.');
    }

    $pdo->beginTransaction();
    try {
        $byEmail = oobUserByEmail($pdo, $email);
        $byUsername = oobUserByIdentifier($pdo, $username);
        $user = $byEmail ?: $byUsername;

        if (!$user) {
            $insert = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status, is_system_admin, email_verified_at) VALUES (:email, :username, :password_hash, 'active', 1, UTC_TIMESTAMP())");
            $insert->execute([
                ':email' => $email,
                ':username' => $username,
                ':password_hash' => $legacyHash,
            ]);
        } else {
            $userId = (int)$user['id'];
            if (!$byEmail) {
                $assignEmail = $pdo->prepare('UPDATE discovery_users SET email = :email WHERE id = :id');
                $assignEmail->execute([':email' => $email, ':id' => $userId]);
            }
            $promote = $pdo->prepare("UPDATE discovery_users SET password_hash = COALESCE(NULLIF(password_hash, ''), :password_hash), status = 'active', is_system_admin = 1, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id");
            $promote->execute([':password_hash' => $legacyHash, ':id' => $userId]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $admin = oobUserByEmail($pdo, $email);
    if (!$admin || (string)$admin['status'] !== 'active' || !(bool)$admin['is_system_admin']) {
        throw new RuntimeException('The email-backed administrator could not be verified.');
    }

    fwrite(STDOUT, "Email-backed results administrator OK\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "Results administrator bootstrap failed: " . $error->getMessage() . "\n");
    exit(1);
}
