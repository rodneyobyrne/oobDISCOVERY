<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!oobAccountAuthEnabled($accessConfig)) {
        $error = 'Password reset is not enabled yet.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            try {
                oobAccountHelpRequest($pdo, $accessConfig, $email);
            } catch (Throwable $ignored) {
                // Keep this response identical so account existence is not disclosed.
            }
            $sent = true;
        }
    }
}

if ($sent) {
    $body = '<p class="notice notice-success" role="status">If that email belongs to an account, the appropriate verification or password-reset link is on its way.</p><div class="actions"><a class="button" href="/discovery/results/">Return to sign in</a></div>';
} else {
    $body = ($error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '')
        . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><label for="email">Account email</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required autofocus><button type="submit">Send reset link</button></form><div class="actions"><a class="button button-secondary" href="/discovery/results/">Return to sign in</a></div>';
}
oobRenderAccountPage('Reset your password', 'Account help', 'Enter your account email. For security, we send a reset link rather than revealing or emailing a password.', $body, $error ? 400 : 200);
