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

$user = oobUserById($pdo, (int)($tokenRecord['user_id'] ?? 0));
$accountUsername = trim((string)($user['username'] ?? ''));
$accountEmail = strtolower(trim((string)($user['email'] ?? '')));
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

$accountNote = '';
if (oobValidUsername($accountUsername) && filter_var($accountEmail, FILTER_VALIDATE_EMAIL)) {
    $accountNote = '<p class="notice notice-info"><strong>Account:</strong> ' . oobEscape($accountUsername) . '<br><strong>Email:</strong> ' . oobEscape($accountEmail) . '<br>You can sign in with either one. They use the same password.</p>';
}
$body = ($error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '')
    . $accountNote
    . '<form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="token" value="' . oobEscape($token) . '"><label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required autofocus><small>Use at least 12 characters. This will be the password for both your username and email sign-in.</small><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><button type="submit">Save new password</button></form>';
oobRenderAccountPage('Choose a new password', 'Password reset', 'Your reset link was verified. You are changing the password for one Discovery account; its username and email are two ways to sign in to that same account.', $body, $error ? 400 : 200);
