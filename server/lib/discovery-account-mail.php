<?php
declare(strict_types=1);

function oobSendConfiguredAccountEmail(array $accessConfig, string $recipient, string $subject, string $html, string $text): void
{
    if (!oobAccountAuthEnabled($accessConfig)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_auth_disabled');
    }

    require_once oobMailerAutoloadPath($accessConfig);
    $smtp = oobSmtpConfig($accessConfig);
    $username = strtolower(trim((string)($smtp['username'] ?? '')));
    $fromEmail = strtolower(trim((string)($smtp['from_email'] ?? '')));
    $fromName = (string)($smtp['from_name'] ?? 'oobCREATIVE Discovery');
    $recipient = strtolower(trim($recipient));

    if (!filter_var($username, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_email_invalid');
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

        // Authenticate and return bounces through the licensed Workspace user,
        // while presenting the approved Discovery alias as the visible sender.
        $mailer->setFrom($fromEmail, $fromName);
        $mailer->Sender = $username;
        if ($fromEmail !== $username) $mailer->addReplyTo($fromEmail, $fromName);

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

function oobAccountEmailHtml(string $eyebrow, string $title, string $intro, string $body, string $ctaLabel, string $safeUrl, string $footer): string
{
    return '<!doctype html><html><body style="margin:0;padding:0;background:#f1f0ec;color:#111;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $intro . '</div>'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f1f0ec;padding:28px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="620" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#fff;border-top:6px solid #111;box-shadow:10px 10px 0 rgba(0,0,0,.06);">'
        . '<tr><td style="padding:24px 30px 18px;border-bottom:1px solid #d5d3cd;">'
        . '<div style="font-size:16px;font-weight:700;letter-spacing:-.3px;"><span style="color:#888;font-weight:400;">oob</span>CREATIVE</div>'
        . '<div style="margin-top:4px;font-size:10px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;">Discovery</div>'
        . '</td></tr>'
        . '<tr><td style="padding:30px;">'
        . '<div style="margin:0 0 8px;font-size:11px;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:#4e4e4e;">' . $eyebrow . '</div>'
        . '<h1 style="margin:0 0 14px;font-size:32px;line-height:1.08;letter-spacing:-1.2px;color:#111;">' . $title . '</h1>'
        . '<p style="margin:0 0 22px;font-size:16px;line-height:1.6;color:#333;">' . $intro . '</p>'
        . $body
        . '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 20px;"><tr><td bgcolor="#111111" style="background:#111;padding:0;"><a href="' . $safeUrl . '" style="display:inline-block;padding:14px 20px;color:#fff;text-decoration:none;font-size:16px;font-weight:800;">' . $ctaLabel . '</a></td></tr></table>'
        . '<p style="margin:0;font-size:13px;line-height:1.6;color:#666;">If the button does not open, copy and paste this address into your browser:<br><a href="' . $safeUrl . '" style="color:#333;word-break:break-all;">' . $safeUrl . '</a></p>'
        . '</td></tr>'
        . '<tr><td style="padding:20px 30px 24px;background:#f8f4ee;border-top:1px solid #ded4c8;font-size:12px;line-height:1.6;color:#5a5651;">' . $footer . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

function oobSendAccountLinkEmail(array $accessConfig, array $user, string $token, string $purpose, array $context = []): void
{
    if (!in_array($purpose, ['verify', 'reset'], true)) {
        throw new InvalidArgumentException('Invalid account-email purpose.');
    }

    $recipient = strtolower(trim((string)($user['email'] ?? '')));
    $accountUsername = trim((string)($user['username'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !oobValidUsername($accountUsername)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_email_invalid');
    }

    $isVerification = $purpose === 'verify';
    $projectLabel = trim((string)($context['project_label'] ?? ''));
    $url = oobSiteUrl($accessConfig)
        . ($isVerification ? '/discovery/account/confirm/?token=' : '/discovery/account/reset/?token=')
        . rawurlencode($token);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeUsername = htmlspecialchars($accountUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeRecipient = htmlspecialchars($recipient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeProject = htmlspecialchars($projectLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    if ($isVerification) {
        $subject = 'Activate your Discovery access';
        $projectIntro = $projectLabel !== ''
            ? 'You created secure Discovery access for <strong>' . $safeProject . '</strong>. One final step is required before that access can be activated.'
            : 'You created a secure Discovery account. One final step is required before your project access can be activated.';
        $body = '<div style="margin:0 0 20px;padding:14px 16px;background:#f8f4ee;border-left:4px solid #111;font-size:15px;line-height:1.55;"><strong>Action required:</strong> Your email address must be verified before your project access is activated.</div>'
            . ($projectLabel !== '' ? '<p style="margin:0 0 8px;font-size:14px;line-height:1.55;"><strong>Project:</strong> ' . $safeProject . '</p>' : '')
            . '<p style="margin:0 0 18px;font-size:14px;line-height:1.55;"><strong>Account email:</strong> ' . $safeRecipient . '</p>'
            . '<p style="margin:0;font-size:15px;line-height:1.65;color:#333;">After activation, you will enter your Discovery workspace. From there, you can ' . ($projectLabel !== '' ? 'open ' . $safeProject . ', ' : '') . 'review perspectives already shared by your team, and contribute your own when you are ready.</p>';
        $footer = 'This activation link expires in 24 hours. If you did not create this Discovery account, no action is needed.<br><br>Discovery helps teams build a clearer understanding of the people they serve so communication can better connect real needs with useful support.';
        $html = oobAccountEmailHtml('Private Discovery access', 'Activate your Discovery access', $projectIntro, $body, 'Activate my Discovery access', $safeUrl, $footer);
        $text = "oobCREATIVE Discovery\nPRIVATE DISCOVERY ACCESS\n\nActivate your Discovery access\n\n"
            . ($projectLabel !== '' ? "You created secure Discovery access for {$projectLabel}. One final step is required before that access can be activated.\n\n" : "You created a secure Discovery account. One final step is required before your project access can be activated.\n\n")
            . "ACTION REQUIRED: Your email address must be verified before your project access is activated.\n\n"
            . ($projectLabel !== '' ? "Project: {$projectLabel}\n" : '')
            . "Account email: {$recipient}\n\n"
            . "Activate my Discovery access:\n{$url}\n\n"
            . "After activation, you will enter your Discovery workspace. From there, you can " . ($projectLabel !== '' ? "open {$projectLabel}, " : '') . "review perspectives already shared by your team, and contribute your own when you are ready.\n\n"
            . "This activation link expires in 24 hours. If you did not create this Discovery account, no action is needed.\n\n"
            . "Discovery helps teams build a clearer understanding of the people they serve so communication can better connect real needs with useful support.";
    } else {
        $subject = 'Reset your Discovery password';
        $body = '<div style="margin:0 0 20px;padding:14px 16px;background:#f8f4ee;border-left:4px solid #111;font-size:15px;line-height:1.55;"><strong>Action requested:</strong> Use the button below to choose a new password for your Discovery account.</div>'
            . '<p style="margin:0 0 8px;font-size:14px;line-height:1.55;"><strong>Account email:</strong> ' . $safeRecipient . '</p>'
            . '<p style="margin:0;font-size:13px;line-height:1.55;color:#666;">Secondary sign-in username: ' . $safeUsername . '</p>';
        $footer = 'This password-reset link expires in one hour. If you did not request a password reset, no action is needed.';
        $html = oobAccountEmailHtml('Private account recovery', 'Reset your Discovery password', 'A password reset was requested for your oobCREATIVE Discovery account.', $body, 'Reset my password', $safeUrl, $footer);
        $text = "oobCREATIVE Discovery\nPRIVATE ACCOUNT RECOVERY\n\nReset your Discovery password\n\nA password reset was requested for your oobCREATIVE Discovery account.\n\nAccount email: {$recipient}\nSecondary sign-in username: {$accountUsername}\n\nReset my password:\n{$url}\n\nThis password-reset link expires in one hour. If you did not request a password reset, no action is needed.";
    }

    oobSendConfiguredAccountEmail($accessConfig, $recipient, $subject, $html, $text);
}

function oobSendUsernameReminderEmail(array $accessConfig, array $user): void
{
    $recipient = strtolower(trim((string)($user['email'] ?? '')));
    $accountUsername = trim((string)($user['username'] ?? ''));
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !oobValidUsername($accountUsername)) {
        throw new OobAuthException('Account email is not configured.', 503, 'account_email_invalid');
    }

    $safeUsername = htmlspecialchars($accountUsername, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeRecipient = htmlspecialchars($recipient, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $signInUrl = 'https://discovery.oobcreative.com/';
    $safeSignInUrl = htmlspecialchars($signInUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $subject = 'Your Discovery sign-in details';
    $body = '<p style="margin:0 0 10px;font-size:14px;line-height:1.55;"><strong>Account email:</strong> ' . $safeRecipient . '</p>'
        . '<p style="margin:0 0 18px;font-size:14px;line-height:1.55;"><strong>Secondary username:</strong> ' . $safeUsername . '</p>'
        . '<p style="margin:0;font-size:15px;line-height:1.65;color:#333;">Your email address is the simplest way to sign in. The secondary username above works with the same password if you ever need it.</p>';
    $footer = 'This message contains sign-in information for your private Discovery account. If you did not request it, no account changes were made.';
    $html = oobAccountEmailHtml('Private account help', 'Your Discovery sign-in details', 'Here are the sign-in details associated with your oobCREATIVE Discovery account.', $body, 'Open Discovery sign in', $safeSignInUrl, $footer);
    $text = "oobCREATIVE Discovery\nPRIVATE ACCOUNT HELP\n\nYour Discovery sign-in details\n\nAccount email: {$recipient}\nSecondary username: {$accountUsername}\n\nYour email address is the simplest way to sign in. The secondary username works with the same password if you ever need it.\n\nOpen Discovery sign in:\n{$signInUrl}\n\nIf you did not request this message, no account changes were made.";

    oobSendConfiguredAccountEmail($accessConfig, $recipient, $subject, $html, $text);
}
