<?php
declare(strict_types=1);

function oobSendAccountLinkEmail(array $accessConfig, array $user, string $token, string $purpose): void
{
    if (!in_array($purpose, ['verify', 'reset'], true)) {
        throw new InvalidArgumentException('Invalid account-email purpose.');
    }
    if (!oobAccountAuthEnabled($accessConfig)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_auth_disabled');
    }

    require_once oobMailerAutoloadPath($accessConfig);
    $smtp = oobSmtpConfig($accessConfig);
    $username = strtolower(trim((string)($smtp['username'] ?? '')));
    $replyTo = strtolower(trim((string)($smtp['from_email'] ?? '')));
    $recipient = strtolower(trim((string)($user['email'] ?? '')));
    $fromName = (string)($smtp['from_name'] ?? 'oobCREATIVE Discovery');

    if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_email_invalid');
    }

    $isVerification = $purpose === 'verify';
    $url = oobSiteUrl($accessConfig)
        . ($isVerification ? '/discovery/account/confirm/?token=' : '/discovery/account/reset/?token=')
        . rawurlencode($token);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($isVerification) {
        $subject = 'Confirm your Discovery account';
        $html = '<p>Confirm your oobCREATIVE Discovery account:</p><p><a href="' . $safeUrl . '">Verify email and open discovery results</a></p><p>This link expires in 24 hours. If you did not create this account, you can ignore this email.</p>';
        $text = "Confirm your oobCREATIVE Discovery account:\n\n{$url}\n\nThis link expires in 24 hours. If you did not create this account, you can ignore this email.";
    } else {
        $subject = 'Reset your Discovery password';
        $html = '<p>Use this link to choose a new oobCREATIVE Discovery password:</p><p><a href="' . $safeUrl . '">Reset my password</a></p><p>This link expires in one hour. If you did not request it, you can ignore this email.</p>';
        $text = "Choose a new oobCREATIVE Discovery password:\n\n{$url}\n\nThis link expires in one hour. If you did not request it, you can ignore this email.";
    }

    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string)$smtp['host'];
        $mailer->Port = (int)($smtp['port'] ?? 587);
        $mailer->SMTPAuth = true;
        $mailer->AuthType = 'LOGIN';
        $mailer->Username = $username;
        $mailer->Password = (string)$smtp['password'];
        $mailer->SMTPSecure = $mailer->Port === 465
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 20;
        $mailer->CharSet = 'UTF-8';

        // Authentication and envelope sender use the licensed Workspace mailbox.
        // The discovery alias remains the reply identity without depending on
        // Google accepting the alias as an SMTP From address.
        $mailer->setFrom($username, $fromName);
        $mailer->Sender = $username;
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL) && $replyTo !== $username) {
            $mailer->addReplyTo($replyTo, $fromName);
        }

        $mailer->addAddress($recipient);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        $mailer->send();
    } catch (Throwable $error) {
        error_log('[oobDISCOVERY-account-mail] Delivery failed.');
        throw new OobAuthException('Account email could not be sent. Try again in a few minutes.', 503, 'email_delivery_failed');
    }
}
