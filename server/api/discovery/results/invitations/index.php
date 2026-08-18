<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';
oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Invite management', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $principal = oobCurrentPrincipal($accessConfig, $pdo);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-invitations] Bootstrap failed.');
    oobRenderAccountPage('Access unavailable', 'Invite management', 'Invite management is temporarily unavailable.', '', 503);
}

if (!$principal || !$principal['system_admin']) {
    oobRedirect('/discovery/results/');
}

$error = null;
$created = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!oobValidCsrf()) {
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'create');
        try {
            if ($action === 'revoke') {
                $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) throw new InvalidArgumentException('Choose a valid invitation.');
                oobRevokeInvitation($pdo, (int)$id);
                oobRedirect('/discovery/results/invitations/');
            }
            if (!oobAccountAuthEnabled($accessConfig)) throw new OobAuthException('Account email must be configured before invitation links can be created.', 503, 'account_auth_disabled');
            $created = oobCreateInvitation(
                $pdo,
                strtolower(trim((string)($_POST['client_id'] ?? ''))),
                trim((string)($_POST['client_label'] ?? '')),
                (string)($_POST['role'] ?? 'viewer'),
                (int)($_POST['days'] ?? 7),
                (string)$principal['username']
            );
        } catch (Throwable $exception) {
            $error = $exception instanceof OobAuthException || $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The invitation could not be created.';
        }
    }
}

try {
    $invitations = $pdo->query('SELECT i.*, u.username AS claimed_username FROM discovery_invitations i LEFT JOIN discovery_users u ON u.id = i.claimed_by_user_id ORDER BY i.created_at DESC, i.id DESC LIMIT 100')->fetchAll();
} catch (Throwable $error) {
    $invitations = [];
    $error = $error instanceof PDOException ? 'Run the account database migration before managing invitations.' : 'Invitations could not be loaded.';
}

$body = '<a class="toplink" href="/discovery/results/">← Results workspace</a>';
if ($error) $body .= '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>';
if ($created) {
    $claimUrl = oobSiteUrl($accessConfig) . '/discovery/account/claim/?token=' . rawurlencode((string)$created['token']);
    $questionnaireUrl = 'https://discovery.oobcreative.com/clinician/';
    $emailText = "Subject: Your private " . $created['client_label'] . " Discovery invitation\n\n"
        . "Hi [Name],\n\n"
        . "This is your individual invitation to the " . $created['client_label'] . " Discovery process.\n\n"
        . "1. Create your private account before " . $created['expires_at'] . " UTC:\n" . $claimUrl . "\n"
        . "2. Check your email and use the verification link.\n"
        . "3. Open the persona questionnaire:\n" . $questionnaireUrl . "\n\n"
        . "Please plan for 30–45 minutes for one persona worksheet. Use the microphone if it helps you respond naturally. Nuance, uncertainty, and your own word choice are useful; polished marketing language is not required.\n\n"
        . "Do not include patient names or any detail that could identify an individual. This account link is single-use and intended only for you.\n\n"
        . "Thank you,\nRodney";
    $body .= '<section class="card"><p class="eyebrow">Created once</p><h2>Copy this invitation now</h2><p class="notice notice-success">For security, the complete link will not be shown again.</p><p class="mono">' . oobEscape($claimUrl) . '</p><label for="email-copy"><strong>Formatted message</strong></label><p id="email-copy" class="mono">' . nl2br(oobEscape($emailText)) . '</p></section><div class="rule"></div>';
}

$accountNotice = oobAccountAuthEnabled($accessConfig)
    ? ''
    : '<p class="notice notice-info">Invited accounts are disabled until the Google Workspace SMTP secrets are configured.</p>';
$body .= $accountNotice . '<section class="card"><p class="eyebrow">New invitation</p><h2>Grant client access</h2><form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="create">'
    . '<label for="client_label">Client name</label><input id="client_label" name="client_label" type="text" maxlength="160" required value="' . oobEscape((string)($_POST['client_label'] ?? 'Varetto Recovery')) . '">'
    . '<label for="client_id">Client ID</label><input id="client_id" name="client_id" type="text" maxlength="80" pattern="[a-z0-9][a-z0-9-]{1,79}" required value="' . oobEscape((string)($_POST['client_id'] ?? 'varetto')) . '"><small>Must exactly match the questionnaire client ID.</small>'
    . '<div class="split"><div><label for="role">Access level</label><select id="role" name="role"><option value="viewer">Viewer</option><option value="admin">Client admin</option></select></div><div><label for="days">Expires after</label><select id="days" name="days"><option value="7">7 days</option><option value="3">3 days</option><option value="14">14 days</option><option value="30">30 days</option></select></div></div><button type="submit">Create unique link</button></form></section>';

$body .= '<div class="rule"></div><section><p class="eyebrow">Recent activity</p><h2>Invitations</h2><ul class="list">';
if ($invitations === []) $body .= '<li>No invitations yet.</li>';
foreach ($invitations as $invitation) {
    $state = oobInvitationState($invitation);
    $body .= '<li><strong>' . oobEscape((string)$invitation['client_label']) . '</strong> · ' . oobEscape((string)$invitation['role']) . '<br><span class="meta">' . oobEscape(ucfirst($state)) . ' · expires ' . oobEscape((string)$invitation['expires_at']) . ' UTC · created by ' . oobEscape((string)$invitation['created_by']);
    if (!empty($invitation['claimed_username'])) $body .= ' · claimed by ' . oobEscape((string)$invitation['claimed_username']);
    $body .= '</span>';
    if ($state === 'active') {
        $body .= '<form method="post" class="actions"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="revoke"><input type="hidden" name="invitation_id" value="' . (int)$invitation['id'] . '"><button class="button-secondary" type="submit">Revoke</button></form>';
    }
    $body .= '</li>';
}
$body .= '</ul></section>';
oobRenderAccountPage('Invite users', 'System administration', 'Create a single-use link. The recipient chooses their own email, username, and password; no default password is assigned.', $body, $error ? 400 : 200);
