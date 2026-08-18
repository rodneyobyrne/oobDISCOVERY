<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$authLibrary = $home . '/oob-discovery-lib/discovery-auth.php';
$mailLibrary = $home . '/oob-discovery-lib/discovery-account-mail.php';
if (!is_file($authLibrary) || !is_file($mailLibrary)) {
    fwrite(STDERR, "Account libraries not found.\n");
    exit(2);
}
require_once $authLibrary;
require_once $mailLibrary;

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    if (!oobAccountAuthEnabled($accessConfig)) {
        throw new RuntimeException('Account auth is disabled.');
    }

    $pdo = oobDatabaseConnection($databaseConfig);
    $smtp = oobSmtpConfig($accessConfig);
    $email = strtolower(trim((string)($smtp['username'] ?? '')));
    $user = oobUserByEmail($pdo, $email);

    if (!$user || (string)$user['status'] !== 'active' || !(bool)$user['is_system_admin']) {
        throw new RuntimeException('The email-backed system administrator is unavailable.');
    }

    $expire = $pdo->prepare("UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND purpose = 'reset' AND used_at IS NULL");
    $expire->execute([':user_id' => (int)$user['id']]);

    $token = oobCreateAccountToken($pdo, (int)$user['id'], 'reset', 60);
    try {
        oobSendAccountLinkEmail($accessConfig, $user, $token, 'reset');
    } catch (Throwable $mailError) {
        $cleanup = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = :token_hash AND used_at IS NULL');
        $cleanup->execute([':token_hash' => oobTokenHash($token)]);
        throw $mailError;
    }

    fwrite(STDOUT, "Live administrator password-reset message accepted by Google Workspace SMTP\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "Live reset delivery verification failed: " . $error->getMessage() . "\n");
    exit(1);
}
