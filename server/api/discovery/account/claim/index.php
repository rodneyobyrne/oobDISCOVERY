<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';

oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Account access', 'Open this invitation using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-claim] Bootstrap failed.');
    oobRenderAccountPage('Access unavailable', 'Account access', 'Account access is temporarily unavailable. Try again later.', '', 503);
}

$token = is_string($_REQUEST['token'] ?? null) ? trim($_REQUEST['token']) : '';
$invitation = oobInvitationByToken($pdo, $token);
$state = oobInvitationState($invitation);
if ($state !== 'active') {
    $message = match ($state) {
        'expired' => 'This invitation has expired. Ask the sender for a new link.',
        'claimed' => 'This invitation has already been used. Sign in with your account or ask the sender for a new link.',
        'revoked' => 'This invitation was revoked. Ask the sender for a new link.',
        default => 'This invitation link is invalid.',
    };
    $body = '<p class="notice notice-error" role="alert">' . oobEscape($message) . '</p><div class="actions"><a class="button" href="/discovery/results/">Sign in</a></div>';
    oobRenderAccountPage('Invitation unavailable', 'Private discovery results', 'A current invitation is required to create or extend an account.', $body, $state === 'invalid' ? 404 : 410);
}

if (!oobAccountAuthEnabled($accessConfig)) {
    oobRenderAccountPage('Invitation not active yet', 'Private discovery results', 'The new account system is being configured. Ask the sender to let you know when this link is ready.', '<p class="notice notice-info">No account has been created and this invitation has not been used.</p>', 503);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'create');
        try {
            if ($action === 'existing') {
                $identifier = trim((string)($_POST['identifier'] ?? ''));
                $password = (string)($_POST['existing_password'] ?? '');
                if ($identifier === '' || $password === '') throw new OobAuthException('Enter your email/username and password.', 400, 'fields_missing');
                $localUser = oobAccountLogin($pdo, $identifier, $password);
                oobBindInvitationForUser($pdo, $token, (int)$localUser['id']);
                oobRedirect('/discovery/results/');
            }

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $confirmation = (string)($_POST['password_confirmation'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new OobAuthException('Enter a valid email address.', 400, 'email_invalid');
            if (!oobValidUsername($username)) throw new OobAuthException('Use 3–32 letters, numbers, periods, underscores, or hyphens for your username.', 400, 'username_invalid');
            if ($password !== $confirmation) throw new OobAuthException('The passwords do not match.', 400, 'password_mismatch');
            $passwordError = oobPasswordError($password);
            if ($passwordError !== null) throw new OobAuthException($passwordError, 400, 'password_invalid');
            $created = oobCreateInvitedUser($pdo, $token, $email, $username, $password);
            oobSendVerificationEmail($accessConfig, $created['user'], (string)$created['token']);
            $body = '<p class="notice notice-success" role="status"><strong>Check your email.</strong><br>Use the verification link we sent to finish your account and open the results.</p>';
            oobRenderAccountPage('Verify your email', 'Account created', 'Your invitation is connected to your new account.', $body);
        } catch (OobAuthException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('[oobDISCOVERY-claim] Claim failed.');
            $error = 'The invitation could not be claimed. Try again or ask the sender for a new link.';
        }
    }
}

$clientLabel = (string)$invitation['client_label'];
$notice = $error ? '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>' : '';
$csrf = oobEscape(oobCsrfToken());
$safeToken = oobEscape($token);
$body = $notice . '<div class="split">'
    . '<section class="card"><p class="eyebrow">New account</p><h2>Choose your sign-in</h2><form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="token" value="' . $safeToken . '"><input type="hidden" name="action" value="create">'
    . '<label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="' . oobEscape((string)($_POST['email'] ?? '')) . '">'
    . '<label for="username">Username</label><input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}" required value="' . oobEscape((string)($_POST['username'] ?? '')) . '"><small>3–32 characters. Letters, numbers, periods, underscores, and hyphens.</small>'
    . '<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><small>Use at least 12 characters. There is no default password.</small>'
    . '<label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><button type="submit">Create account</button></form></section>'
    . '<section class="card"><p class="eyebrow">Already registered</p><h2>Add this client</h2><p class="help">Sign in to add ' . oobEscape($clientLabel) . ' to your existing discovery account.</p><form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="token" value="' . $safeToken . '"><input type="hidden" name="action" value="existing">'
    . '<label for="identifier">Email or username</label><input id="identifier" name="identifier" type="text" autocomplete="username" required><label for="existing_password">Password</label><input id="existing_password" name="existing_password" type="password" autocomplete="current-password" required><button type="submit">Sign in and add client</button></form><p class="help"><a href="/discovery/account/forgot/">Forgot your password?</a></p></section></div>';
oobRenderAccountPage('Your invitation', 'Private discovery results', 'Create an account to view ' . $clientLabel . ' results, or connect this invitation to an account you already use.', $body, $error ? 400 : 200);
