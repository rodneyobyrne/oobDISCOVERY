<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$authLibrary = $home . '/oob-discovery-lib/discovery-auth.php';
$markerPath = $home . '/.oob-discovery-system-admin-bootstrapped';

if (!is_file($authLibrary)) {
    fwrite(STDERR, "Authentication library not found.\n");
    exit(2);
}
require_once $authLibrary;

if (is_file($markerPath)) {
    fwrite(STDOUT, "System administrator bootstrap already completed\n");
    exit(0);
}

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    if (!oobAccountAuthEnabled($accessConfig)) {
        throw new RuntimeException('Account authentication must be active before bootstrapping the system administrator.');
    }

    $smtp = oobSmtpConfig($accessConfig);
    $email = strtolower(trim((string)($smtp['username'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The SMTP account is not a valid administrator email.');
    }

    $pdo = oobDatabaseConnection($databaseConfig);
    $pdo->beginTransaction();

    $statement = $pdo->prepare('SELECT * FROM discovery_users WHERE email = :email LIMIT 1 FOR UPDATE');
    $statement->execute([':email' => $email]);
    $user = $statement->fetch() ?: null;

    if ($user) {
        $userId = (int)$user['id'];
        $promote = $pdo->prepare("UPDATE discovery_users SET is_system_admin = 1, status = 'active', email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id");
        $promote->execute([':id' => $userId]);
    } else {
        $localPart = strtolower((string)strtok($email, '@'));
        $base = preg_replace('/[^a-z0-9._-]+/', '-', $localPart) ?: 'admin';
        $base = trim($base, '-_.');
        if (strlen($base) < 3) $base = 'admin-' . $base;
        $base = substr($base, 0, 24);
        $username = $base;
        $counter = 1;
        while (oobUserByIdentifier($pdo, $username)) {
            $suffix = '-' . $counter++;
            $username = substr($base, 0, 32 - strlen($suffix)) . $suffix;
        }
        if (!oobValidUsername($username)) $username = 'system-admin';

        $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $insert = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status, is_system_admin, email_verified_at) VALUES (:email, :username, :password_hash, 'active', 1, UTC_TIMESTAMP())");
        $insert->execute([
            ':email' => $email,
            ':username' => $username,
            ':password_hash' => $unusablePassword,
        ]);
        $userId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    $token = oobCreateAccountToken($pdo, $userId, 'reset', 1440);
    $setupUrl = oobSiteUrl($accessConfig) . '/discovery/account/reset/?token=' . rawurlencode($token);

    $subject = 'Set up your oobCREATIVE Discovery administrator account';
    $text = "Your oobCREATIVE Discovery administrator account is ready.\n\nChoose your password within 24 hours:\n{$setupUrl}\n\nIf you did not expect this message, do not use the link.";
    $html = '<p>Your oobCREATIVE Discovery administrator account is ready.</p>'
        . '<p><a href="' . htmlspecialchars($setupUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Choose your password</a> within 24 hours.</p>'
        . '<p>If you did not expect this message, do not use the link.</p>';

    try {
        oobSendAccountEmail($accessConfig, $email, $subject, $html, $text);
    } catch (Throwable $mailError) {
        $cleanup = $pdo->prepare("UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND purpose = 'reset' AND used_at IS NULL");
        $cleanup->execute([':user_id' => $userId]);
        throw $mailError;
    }

    if (file_put_contents($markerPath, gmdate('c') . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Could not write the bootstrap completion marker.');
    }
    chmod($markerPath, 0600);

    fwrite(STDOUT, "System administrator bootstrap email sent\n");
    exit(0);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "System administrator bootstrap failed: " . $error->getMessage() . "\n");
    exit(1);
}
