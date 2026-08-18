<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-account-mail.php';
require_once $library . '/discovery-ui.php';
oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Password reset', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    oobRenderAccountPage('Access unavailable', 'Password reset', 'Password reset is temporarily unavailable.', '', 503);
}

$sent = false;
$error = null;
$requestId = bin2hex(random_bytes(6));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!oobAccountAuthEnabled($accessConfig)) {
        $error = 'Password reset is not enabled yet.';
    } else {
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
                            // Do not leave a failed-send token looking like a recent successful request.
                            // Mark it used so the person can retry immediately without waiting for the throttle window.
                            $cleanup = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = :token_hash AND used_at IS NULL');
                            $cleanup->execute([':token_hash' => oobTokenHash($token)]);
                            error_log('[oobDISCOVERY-account-help][' . $requestId . '] Account email delivery failed.');
                        }
                    }
                }
            } catch (Throwable $accountError) {
                // Keep the browser response identical whether or not an account exists.
                error_log('[oobDISCOVERY-account-help][' . $requestId . '] Account-help request could not be completed.');
            }
            $sent = true;
        }
    }
}

if ($sent) {
    $body = '<p class="notice notice-success" role="status">If that email or username belongs to an account, a verification or password-reset link should arrive at its registered email shortly.</p>'
        . '<p>If nothing arrives after a couple of minutes, check spam and try once more. A failed delivery does not block the next request.</p>'
        . '<div class="actions"><a class="button" href="/discovery/results/">Return to sign in</a></div>';
} else {
    $body = ($error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '')
        . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><label for="identifier">Account email or username</label><input id="identifier" name="identifier" type="text" autocomplete="username" maxlength="254" required autofocus><button type="submit">Send account link</button></form><div class="actions"><a class="button button-secondary" href="/discovery/results/">Return to sign in</a></div>';
}
oobRenderAccountPage('Get back into your account', 'Account help', 'Enter your account email or username. Active accounts receive a one-hour password-reset link; accounts still waiting for verification receive a new verification link.', $body, $error ? 400 : 200);
