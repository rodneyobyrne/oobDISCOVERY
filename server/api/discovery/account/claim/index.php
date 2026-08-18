<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-account-mail.php';
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

// The questionnaire lives on discovery.oobcreative.com while authenticated
// account sessions live on api.oobcreative.com. This small JSON mode lets the
// questionnaire link a validated submission to the signed-in account and load
// that same owner's response for editing. It does not replace the submission API.
$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$apiMode = (string)($_GET['account_submission'] ?? '') === '1'
    || (string)($_GET['mode'] ?? '') === 'session'
    || str_starts_with($contentType, 'application/json')
    || $_SERVER['REQUEST_METHOD'] === 'OPTIONS';

if ($apiMode) {
    $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowedOrigin = 'https://discovery.oobcreative.com';
    if ($origin === $allowedOrigin) {
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
    }
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        if ($origin !== $allowedOrigin) {
            http_response_code(403);
            exit;
        }
        http_response_code(204);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $json = static function (int $status, array $body): never {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    };
    if ($origin !== '' && $origin !== $allowedOrigin) $json(403, ['ok' => false, 'error' => 'Origin not allowed.']);

    $principal = oobCurrentPrincipal($accessConfig, $pdo);
    $accountPrincipal = $principal && ($principal['mode'] ?? '') === 'account' && (int)($principal['user_id'] ?? 0) > 0;

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && (string)($_GET['mode'] ?? '') === 'session') {
        $json(200, [
            'ok' => true,
            'authenticated' => $accountPrincipal,
            'user' => $accountPrincipal ? [
                'id' => (int)$principal['user_id'],
                'username' => (string)$principal['username'],
                'email' => (string)$principal['email'],
                'systemAdmin' => (bool)$principal['system_admin'],
            ] : null,
        ]);
    }

    if (!$accountPrincipal) $json(401, ['ok' => false, 'error' => 'Sign in to your Discovery account first.']);
    $userId = (int)$principal['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $submissionId = trim((string)($_GET['submission_id'] ?? ''));
        if (preg_match('/^[A-Za-z0-9-]{10,80}$/', $submissionId) !== 1) $json(400, ['ok' => false, 'error' => 'A valid submission is required.']);
        $statement = $pdo->prepare("SELECT submission_id, payload_json FROM discovery_submissions WHERE submission_id = :submission_id AND discovery_type = 'clinician' AND owner_user_id = :owner_user_id LIMIT 1");
        $statement->execute([':submission_id' => $submissionId, ':owner_user_id' => $userId]);
        $row = $statement->fetch();
        if (!$row) $json(404, ['ok' => false, 'error' => 'This response is not available to your account.']);
        try {
            $payload = json_decode((string)$row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            $json(500, ['ok' => false, 'error' => 'The saved response could not be loaded.']);
        }
        $json(200, ['ok' => true, 'submissionId' => $submissionId, 'payload' => $payload]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $json(405, ['ok' => false, 'error' => 'Method not allowed.']);
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '' || strlen($raw) > 300000) $json(400, ['ok' => false, 'error' => 'A valid response payload is required.']);
    try {
        $request = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $json(400, ['ok' => false, 'error' => 'Invalid JSON.']);
    }
    $action = is_array($request) ? (string)($request['action'] ?? '') : '';
    $payload = is_array($request['payload'] ?? null) ? $request['payload'] : null;
    if (!in_array($action, ['claim', 'update'], true) || !$payload) $json(400, ['ok' => false, 'error' => 'A valid account submission action is required.']);

    $submissionId = trim((string)($payload['submissionId'] ?? ''));
    $system = (string)($payload['system'] ?? '');
    $discoveryType = (string)($payload['discoveryType'] ?? '');
    $questionnaireVersion = (string)($payload['questionnaireVersion'] ?? '');
    $clientId = trim((string)($payload['client']['id'] ?? ''));
    $respondentName = trim((string)($payload['respondent']['name'] ?? ''));
    $respondentEmail = strtolower(trim((string)($payload['respondent']['email'] ?? '')));
    if (preg_match('/^[A-Za-z0-9-]{10,80}$/', $submissionId) !== 1
        || $system !== 'oobDISCOVERY'
        || $discoveryType !== 'clinician'
        || $questionnaireVersion === ''
        || !is_array($payload['patientPatterns'] ?? null)
        || !is_array($payload['sourceIntegrity'] ?? null)
        || $clientId === '') {
        $json(422, ['ok' => false, 'error' => 'The response payload is incomplete.']);
    }
    if ($respondentEmail !== '' && filter_var($respondentEmail, FILTER_VALIDATE_EMAIL) === false) $json(422, ['ok' => false, 'error' => 'The response email is invalid.']);
    try {
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        $json(422, ['ok' => false, 'error' => 'The response payload could not be encoded.']);
    }

    $lookup = $pdo->prepare("SELECT id, owner_user_id, client_id, payload_json FROM discovery_submissions WHERE submission_id = :submission_id AND discovery_type = 'clinician' LIMIT 1");
    $lookup->execute([':submission_id' => $submissionId]);
    $row = $lookup->fetch();
    if (!$row) $json(404, ['ok' => false, 'error' => 'The validated response was not found.']);

    $existingOwner = (int)($row['owner_user_id'] ?? 0);
    if ($action === 'claim') {
        if ($existingOwner > 0 && $existingOwner !== $userId) $json(403, ['ok' => false, 'error' => 'This response already belongs to another account.']);
        if ($existingOwner === 0) {
            if (!hash_equals((string)$row['payload_json'], $canonical)) $json(409, ['ok' => false, 'error' => 'The stored response does not match this account claim.']);
            $assign = $pdo->prepare('UPDATE discovery_submissions SET owner_user_id = :owner_user_id WHERE id = :id AND owner_user_id IS NULL');
            $assign->execute([':owner_user_id' => $userId, ':id' => (int)$row['id']]);
            if ($assign->rowCount() !== 1) $json(409, ['ok' => false, 'error' => 'The response could not be linked to this account.']);
        }
        $json(200, ['ok' => true, 'submissionId' => $submissionId, 'owned' => true]);
    }

    if ($existingOwner !== $userId) $json(403, ['ok' => false, 'error' => 'Only the account that submitted this response can edit it.']);
    if ((string)$row['client_id'] !== $clientId) $json(409, ['ok' => false, 'error' => 'The response client cannot be changed.']);
    $update = $pdo->prepare('UPDATE discovery_submissions SET respondent_name = :respondent_name, respondent_email = :respondent_email, questionnaire_version = :questionnaire_version, payload_json = :payload_json, status = :status WHERE id = :id AND owner_user_id = :owner_user_id');
    $update->execute([
        ':respondent_name' => $respondentName,
        ':respondent_email' => $respondentEmail === '' ? null : $respondentEmail,
        ':questionnaire_version' => $questionnaireVersion,
        ':payload_json' => $canonical,
        ':status' => 'received',
        ':id' => (int)$row['id'],
        ':owner_user_id' => $userId,
    ]);
    $json(200, ['ok' => true, 'submissionId' => $submissionId, 'updated' => true]);
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
                if ($identifier === '' || $password === '') throw new OobAuthException('Enter your email address or username and your password.', 400, 'fields_missing');
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
            oobSendAccountLinkEmail($accessConfig, $created['user'], (string)$created['token'], 'verify');
            $body = '<p class="notice notice-success" role="status"><strong>Check your email.</strong><br>Use the verification link we sent to finish your account and open the results.</p>'
                . '<p>Your email will show both your username and account email. Either can be used to sign in, and both use the password you just created.</p>';
            oobRenderAccountPage('Verify your email', 'Account created', 'Your invitation is connected to your new Client account.', $body);
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
    . '<section class="card"><p class="eyebrow">New Client account</p><h2>Create your account</h2><p class="help">Your email address is used for verification and account recovery. Your username is a shorter sign-in name you create. After setup, you can sign in with either one; both use the same password.</p><form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="token" value="' . $safeToken . '"><input type="hidden" name="action" value="create">'
    . '<label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="254" required value="' . oobEscape((string)($_POST['email'] ?? '')) . '"><small>We’ll use this address for verification, password resets, and username reminders.</small>'
    . '<label for="username">Username</label><input id="username" name="username" type="text" autocomplete="username" minlength="3" maxlength="32" pattern="[A-Za-z0-9][A-Za-z0-9._-]{2,31}" required value="' . oobEscape((string)($_POST['username'] ?? '')) . '"><small>Create a sign-in name with 3–32 letters, numbers, periods, underscores, or hyphens. You can also sign in with your email address.</small>'
    . '<label for="password">Create password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><small>Use at least 12 characters. This password works with either your username or email address.</small>'
    . '<label for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" maxlength="128" required><button type="submit">Create Client account</button></form></section>'
    . '<section class="card"><p class="eyebrow">Already registered</p><h2>Use your existing account</h2><p class="help">Sign in to connect this invitation to your existing Discovery account. Use either the email address on the account or the username you created.</p><form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="token" value="' . $safeToken . '"><input type="hidden" name="action" value="existing">'
    . '<label for="identifier">Email address or username</label><input id="identifier" name="identifier" type="text" autocomplete="username" required><small>Either one signs into the same account.</small><label for="existing_password">Password</label><input id="existing_password" name="existing_password" type="password" autocomplete="current-password" required><button type="submit">Sign in and connect invitation</button></form><p class="help"><a href="/discovery/account/forgot/">Trouble signing in?</a></p></section></div>';
oobRenderAccountPage('Your invitation', 'Private discovery results', 'Invitations create Client accounts. Clients can view and edit only the Discovery responses they submit through their own account.', $body, $error ? 400 : 200);
