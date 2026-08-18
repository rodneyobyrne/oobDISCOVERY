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

$token = trim((string)($_REQUEST['token'] ?? ''));
$tokenRecord = oobAccountTokenByPlain($pdo, $token, 'reset');
if (oobAccountTokenState($tokenRecord) !== 'active') {
    $body = '<p class="notice notice-error">Start with a current password-reset link from your email.</p><div class="actions"><a class="button" href="/discovery/account/forgot/">Request a reset link</a></div>';
    oobRenderAccountPage('Reset link required', 'Password reset', 'A verified reset session is required before a password can be changed.', $body, 403);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['password_confirmation'] ?? '');
        $error = $password !== $confirmation ? 'The passwords do not match.' : oobPasswordError($password);
        if ($error === null) {
            try {
                $user = oobResetPassword($pdo, $token, $password);
                oobStoreAccountSession($user);
                oobRedirect('/discovery/results/');
            } catch (OobAuthException $exception) {
                $error = $exception->getMessage();
            } catch (Throwable $exception) {
                $error = 'The password could not be updated. Request a new reset link and try again.';
            }
        }
    }
}

$body = ($error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '')
    . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="token" value="' . oobEscape($token) . '"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required autofocus><small>Use at least 12 characters.</small><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><button type="submit">Save new password</button></form>';
oobRenderAccountPage('Choose a new password', 'Password reset', 'Your reset link was verified. Create the password you will use for future sign-ins.', $body, $error ? 400 : 200);
