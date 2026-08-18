<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-account-mail.php';
require_once $library . '/discovery-ui.php';
oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'User management', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $principal = oobCurrentPrincipal($accessConfig, $pdo);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-users] Bootstrap failed.');
    oobRenderAccountPage('Access unavailable', 'User management', 'User management is temporarily unavailable.', '', 503);
}

if (!$principal || !$principal['system_admin']) oobRedirect('/discovery/results/');

$error = null;
$notice = null;
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

            if ($action === 'account_email') {
                $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$userId) throw new InvalidArgumentException('Choose a valid user.');
                $user = oobUserById($pdo, (int)$userId);
                if (!$user || !in_array((string)$user['status'], ['pending', 'active'], true)) throw new InvalidArgumentException('That user is not available for account recovery.');
                $purpose = (string)$user['status'] === 'pending' ? 'verify' : 'reset';
                $token = oobCreateAccountToken($pdo, (int)$user['id'], $purpose, $purpose === 'verify' ? 1440 : 60);
                try {
                    oobSendAccountLinkEmail($accessConfig, $user, $token, $purpose);
                } catch (Throwable $mailError) {
                    $cleanup = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE token_hash = :token_hash AND used_at IS NULL');
                    $cleanup->execute([':token_hash' => oobTokenHash($token)]);
                    throw $mailError;
                }
                $notice = $purpose === 'reset'
                    ? 'Password-reset email sent to ' . (string)$user['email'] . '.'
                    : 'Verification email sent to ' . (string)$user['email'] . '.';
            } elseif ($action === 'create') {
                if (!oobAccountAuthEnabled($accessConfig)) throw new OobAuthException('Account email must be configured before invitation links can be created.', 503, 'account_auth_disabled');
                $created = oobCreateInvitation(
                    $pdo,
                    strtolower(trim((string)($_POST['client_id'] ?? ''))),
                    trim((string)($_POST['client_label'] ?? '')),
                    'viewer',
                    (int)($_POST['days'] ?? 7),
                    (string)$principal['username']
                );
            } elseif ($action !== 'account_email') {
                throw new InvalidArgumentException('Choose a valid user-management action.');
            }
        } catch (Throwable $exception) {
            $error = $exception instanceof OobAuthException || $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The user-management action could not be completed.';
        }
    }
}

try {
    $users = $pdo->query("SELECT u.id, u.email, u.username, u.status, u.is_system_admin, u.created_at, COUNT(s.id) AS submission_count FROM discovery_users u LEFT JOIN discovery_submissions s ON s.owner_user_id = u.id AND s.discovery_type = 'clinician' GROUP BY u.id, u.email, u.username, u.status, u.is_system_admin, u.created_at ORDER BY u.is_system_admin DESC, u.username ASC")->fetchAll();
    $invitations = $pdo->query('SELECT i.*, u.username AS claimed_username FROM discovery_invitations i LEFT JOIN discovery_users u ON u.id = i.claimed_by_user_id ORDER BY i.created_at DESC, i.id DESC LIMIT 100')->fetchAll();
} catch (Throwable $loadError) {
    $users = [];
    $invitations = [];
    $error = 'Users and invitations could not be loaded.';
}

$body = '<a class="toplink" href="/discovery/results/">← Results workspace</a>';
if ($error) $body .= '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>';
if ($notice) $body .= '<p class="notice notice-success" role="status">' . oobEscape($notice) . '</p>';

$body .= '<section><p class="eyebrow">Accounts</p><h2>Users</h2><p class="help">There are only two account types: <strong>Full Admin</strong> and <strong>Client</strong>. Only Full Admins can invite users. Clients can see and edit only submissions owned by their account.</p><ul class="list">';
if ($users === []) $body .= '<li>No users found.</li>';
foreach ($users as $user) {
    $type = (bool)$user['is_system_admin'] ? 'Full Admin' : 'Client';
    $count = (int)$user['submission_count'];
    $body .= '<li><strong>' . oobEscape((string)$user['username']) . '</strong> · ' . oobEscape($type)
        . '<br><span class="meta">' . oobEscape((string)$user['email']) . ' · ' . oobEscape(ucfirst((string)$user['status'])) . ' · ' . $count . ' owned submission' . ($count === 1 ? '' : 's') . '</span>'
        . '<div class="actions"><a class="button button-secondary" href="/discovery/results/?user_id=' . (int)$user['id'] . '">View submissions</a>';
    if (in_array((string)$user['status'], ['pending', 'active'], true)) {
        $button = (string)$user['status'] === 'active' ? 'Send password reset' : 'Resend verification';
        $body .= '<form method="post"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="account_email"><input type="hidden" name="user_id" value="' . (int)$user['id'] . '"><button class="button-secondary" type="submit">' . oobEscape($button) . '</button></form>';
    }
    $body .= '</div></li>';
}
$body .= '</ul></section><div class="rule"></div>';

if ($created) {
    $claimUrl = oobSiteUrl($accessConfig) . '/discovery/account/claim/?token=' . rawurlencode((string)$created['token']);
    $questionnaireUrl = 'https://discovery.oobcreative.com/clinician/';
    $emailText = "Subject: Your private " . $created['client_label'] . " Discovery invitation\n\n"
        . "Hi [Name],\n\n"
        . "This is your individual invitation to the " . $created['client_label'] . " Discovery process.\n\n"
        . "1. Create your private Client account before " . $created['expires_at'] . " UTC:\n" . $claimUrl . "\n"
        . "2. Check your email and use the verification link.\n"
        . "3. Sign in to your Discovery workspace and open the questionnaire. Responses submitted while signed in will belong to your account, and only you and a Full Admin can view them.\n"
        . "Questionnaire: " . $questionnaireUrl . "\n\n"
        . "Do not include patient names or any detail that could identify an individual. This account link is single-use and intended only for you.\n\n"
        . "Thank you,\nRodney";
    $body .= '<section class="card"><p class="eyebrow">Created once</p><h2>Copy this invitation now</h2><p class="notice notice-success">For security, the complete link will not be shown again.</p><p class="mono">' . oobEscape($claimUrl) . '</p><label for="email-copy"><strong>Formatted message</strong></label><p id="email-copy" class="mono">' . nl2br(oobEscape($emailText)) . '</p></section><div class="rule"></div>';
}

$accountNotice = oobAccountAuthEnabled($accessConfig) ? '' : '<p class="notice notice-info">Invited accounts are disabled until Google Workspace SMTP is configured.</p>';
$body .= $accountNotice . '<section class="card"><p class="eyebrow">New Client</p><h2>Invite a user</h2><p class="help">Every invitation creates a Client account. There is no separate viewer or client-admin role.</p><form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="create">'
    . '<label for="client_label">Client / project name</label><input id="client_label" name="client_label" type="text" maxlength="160" required value="' . oobEscape((string)($_POST['client_label'] ?? 'Varetto Recovery')) . '">'
    . '<label for="client_id">Client ID</label><input id="client_id" name="client_id" type="text" maxlength="80" pattern="[a-z0-9][a-z0-9-]{1,79}" required value="' . oobEscape((string)($_POST['client_id'] ?? 'varetto')) . '"><small>Must exactly match the questionnaire client ID.</small>'
    . '<label for="days">Invitation expires after</label><select id="days" name="days"><option value="7">7 days</option><option value="3">3 days</option><option value="14">14 days</option><option value="30">30 days</option></select><button type="submit">Create Client invitation</button></form></section>';

$body .= '<div class="rule"></div><section><p class="eyebrow">Recent activity</p><h2>Invitations</h2><ul class="list">';
if ($invitations === []) $body .= '<li>No invitations yet.</li>';
foreach ($invitations as $invitation) {
    $state = oobInvitationState($invitation);
    $body .= '<li><strong>' . oobEscape((string)$invitation['client_label']) . '</strong> · Client invitation<br><span class="meta">' . oobEscape(ucfirst($state)) . ' · expires ' . oobEscape((string)$invitation['expires_at']) . ' UTC · created by ' . oobEscape((string)$invitation['created_by']);
    if (!empty($invitation['claimed_username'])) $body .= ' · claimed by ' . oobEscape((string)$invitation['claimed_username']);
    $body .= '</span>';
    if ($state === 'active') {
        $body .= '<form method="post" class="actions"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="revoke"><input type="hidden" name="invitation_id" value="' . (int)$invitation['id'] . '"><button class="button-secondary" type="submit">Revoke</button></form>';
    }
    $body .= '</li>';
}
$body .= '</ul></section>';
oobRenderAccountPage('Users & access', 'Full Admin', 'Manage Discovery accounts, review each user’s submissions, send account-recovery emails, and create Client invitations.', $body, $error ? 400 : 200);
