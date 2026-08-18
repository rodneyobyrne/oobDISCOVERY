<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-account-mail.php';
require_once $library . '/discovery-ui.php';
oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Account help', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    oobRenderAccountPage('Access unavailable', 'Account help', 'Account help is temporarily unavailable.', '', 503);
}

$sent = false;
$sentAction = '';
$error = null;
$requestId = bin2hex(random_bytes(6));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!oobAccountAuthEnabled($accessConfig)) {
        $error = 'Account help is not enabled yet.';
    } else {
        $action = (string)($_POST['action'] ?? 'reset');
        if ($action === 'username') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter the email address used for this account.';
            } else {
                try {
                    $user = oobUserByEmail($pdo, $email);
                    if ($user && in_array((string)$user['status'], ['pending', 'active'], true)) {
                        try {
                            oobSendUsernameReminderEmail($accessConfig, $user);
                        } catch (Throwable $mailError) {
                            error_log('[oobDISCOVERY-account-help][' . $requestId . '] Username reminder delivery failed.');
                        }
                    }
                } catch (Throwable $accountError) {
                    error_log('[oobDISCOVERY-account-help][' . $requestId . '] Username reminder request could not be completed.');
                }
                $sent = true;
                $sentAction = 'username';
            }
        } elseif ($action === 'reset') {
            $identifier = trim((string)($_POST['identifier'] ?? ''));
            $validIdentifier = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false || oobValidUsername($identifier);
            if (!$validIdentifier) {
                $error = 'Enter the email address or username used for this account.';
            } else {
                try {
                    $user = oobUserByIdentifier($pdo, $identifier);
                    if ($user && in_array((string)$user['status'], ['pending', 'active'], true)) {
                        $purpose = (string)$user['status'] === 'pending' ? 'verify' : 'reset';
                        $recent = $pdo->prepare('SELECT 1 FROM discovery_account_tokens WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND) LIMIT 1');
                        $recent->execute([':user_id' => (int)$user['id'], ':purpose' => $purpose]);
                        if (!$recent->fetchColumn()) {
                            $token = oobCreateAccountToken($pdo, (int)$user['id'], $purpose, $purpose === 'verify' ? 1440 : 60);
                            try {
                                oobSendAccountLinkEmail($accessConfig, $user, $token, $purpose);
                            } catch (Throwable $mailError) {
                                $cleanup = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = :token_hash AND used_at IS NULL');
                                $cleanup->execute([':token_hash' => oobTokenHash($token)]);
                                error_log('[oobDISCOVERY-account-help][' . $requestId . '] Account email delivery failed.');
                            }
                        }
                    }
                } catch (Throwable $accountError) {
                    error_log('[oobDISCOVERY-account-help][' . $requestId . '] Account-help request could not be completed.');
                }
                $sent = true;
                $sentAction = 'reset';
            }
        } else {
            $error = 'Choose an account-help option.';
        }
    }
}

if ($sent) {
    if ($sentAction === 'username') {
        $body = '<p class="notice notice-success" role="status">If that email belongs to a Discovery account, an email with the username should arrive shortly.</p>'
            . '<p>The email will also remind you that either your username or account email can be used to sign in with the same password.</p>';
    } else {
        $body = '<p class="notice notice-success" role="status">If that email address or username belongs to an account, a verification or password-reset link should arrive at its registered email shortly.</p>'
            . '<p>The email will identify the account username and email address so you know exactly which sign-in the password belongs to.</p>';
    }
    $body .= '<div class="actions"><a class="button" href="/discovery/results/">Return to sign in</a><a class="button button-secondary" href="/discovery/account/forgot/">Another account-help request</a></div>';
} else {
    $notice = $error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '';
    $csrf = oobEscape(oobCsrfToken());
    $body = $notice . '<div class="split">'
        . '<section class="card"><p class="eyebrow">Password help</p><h2>Reset your password</h2><p class="help">Enter either the email address on your account or the username you created. Both identify the same account and use the same password.</p>'
        . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="reset">'
        . '<label for="identifier">Email address or username</label><input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="254" required autofocus><small>Use either one. The reset email will be sent to the email address registered on the account.</small><button type="submit">Send password-reset link</button></form></section>'
        . '<section class="card"><p class="eyebrow">Username help</p><h2>Forgot your username?</h2><p class="help">Enter the email address on your account and we’ll email your username to that address.</p>'
        . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="username">'
        . '<label for="email">Account email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required><button type="submit">Email my username</button></form></section></div>'
        . '<div class="actions"><a class="button button-secondary" href="/discovery/results/">Return to sign in</a></div>';
}
oobRenderAccountPage('Trouble signing in?', 'Account help', 'Your email address and username are two ways to sign in to the same Discovery account. They use the same password.', $body, $error ? 400 : 200);
