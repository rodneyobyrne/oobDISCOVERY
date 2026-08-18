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
    [, $accessConfig] = oobLoadRuntimeConfig();
    if (!oobAccountAuthEnabled($accessConfig)) {
        fwrite(STDOUT, "Account auth is disabled; SMTP verification skipped\n");
        exit(0);
    }

    require_once oobMailerAutoloadPath($accessConfig);
    $smtp = oobSmtpConfig($accessConfig);
    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    $mailer->isSMTP();
    $mailer->Host = (string)$smtp['host'];
    $mailer->Port = (int)($smtp['port'] ?? 587);
    $mailer->SMTPAuth = true;
    $mailer->AuthType = 'LOGIN';
    $mailer->Username = (string)$smtp['username'];
    $mailer->Password = (string)$smtp['password'];
    $mailer->SMTPSecure = $mailer->Port === 465
        ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Timeout = 20;

    if (!$mailer->smtpConnect()) {
        throw new RuntimeException('SMTP connection or authentication was rejected.');
    }
    $mailer->smtpClose();
    fwrite(STDOUT, "Google Workspace SMTP connection and authentication OK\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "SMTP verification failed. Check the Google Workspace username, app password, From address, and account permissions.\n");
    exit(1);
}
