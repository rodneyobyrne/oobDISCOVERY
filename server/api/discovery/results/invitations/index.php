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
        $action = (string)($_POST['action'] ?? 'create_invitation');
        try {
            if ($action === 'revoke') {
                $id = filter_input(INPUT_POST, 'invitation_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) throw new InvalidArgumentException('Choose a valid invitation.');
                oobRevokeInvitation($pdo, (int)$id);
                oobRedirect('/discovery/results/invitations/');
            }

            if ($action === 'create_project') {
                $projectName = trim((string)($_POST['project_name'] ?? ''));
                $projectId = strtolower(trim((string)($_POST['project_id'] ?? '')));
                $businessType = strtolower(trim((string)($_POST['client_business_type'] ?? '')));
                if ($projectName === '' || strlen($projectName) > 160) throw new InvalidArgumentException('Enter a project name.');
                if (!preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $projectId)) throw new InvalidArgumentException('Project ID must use lowercase letters, numbers, and hyphens.');
                if (!preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $businessType)) throw new InvalidArgumentException('Business type must use lowercase letters, numbers, and hyphens.');
                $insert = $pdo->prepare("INSERT INTO discovery_projects (project_id, project_name, client_business_type, status) VALUES (:project_id, :project_name, :client_business_type, 'active')");
                $insert->execute([
                    ':project_id' => $projectId,
                    ':project_name' => $projectName,
                    ':client_business_type' => $businessType,
                ]);
                $notice = 'Project created: ' . $projectName . '.';
            } elseif ($action === 'account_email') {
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
            } elseif ($action === 'create_invitation') {
                if (!oobAccountAuthEnabled($accessConfig)) throw new OobAuthException('Account email must be configured before invitation links can be created.', 503, 'account_auth_disabled');
                $projectRecordId = filter_input(INPUT_POST, 'project_record_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$projectRecordId) throw new InvalidArgumentException('Choose a project before creating an invitation.');
                $projectStatement = $pdo->prepare("SELECT id, project_id, project_name, client_business_type FROM discovery_projects WHERE id = :id AND status = 'active' LIMIT 1");
                $projectStatement->execute([':id' => (int)$projectRecordId]);
                $project = $projectStatement->fetch();
                if (!$project) throw new InvalidArgumentException('That project is not active.');
                $created = oobCreateInvitation(
                    $pdo,
                    (string)$project['project_id'],
                    (string)$project['project_name'],
                    'viewer',
                    (int)($_POST['days'] ?? 7),
                    (string)$principal['username']
                );
                $created['client_business_type'] = (string)$project['client_business_type'];
            } elseif (!in_array($action, ['create_project', 'account_email'], true)) {
                throw new InvalidArgumentException('Choose a valid user-management action.');
            }
        } catch (PDOException $exception) {
            $error = (string)$exception->getCode() === '23000'
                ? 'That project ID already exists. Choose the existing project or use a different ID.'
                : 'The project or user-management action could not be completed.';
        } catch (Throwable $exception) {
            $error = $exception instanceof OobAuthException || $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'The user-management action could not be completed.';
        }
    }
}

try {
    $projects = $pdo->query("SELECT p.id, p.project_id, p.project_name, p.client_business_type, p.status, p.created_at, COUNT(DISTINCT i.id) AS invitation_count, COUNT(DISTINCT s.id) AS submission_count FROM discovery_projects p LEFT JOIN discovery_invitations i ON i.client_id = p.project_id LEFT JOIN discovery_submissions s ON s.client_id = p.project_id GROUP BY p.id, p.project_id, p.project_name, p.client_business_type, p.status, p.created_at ORDER BY p.status = 'active' DESC, p.project_name ASC")->fetchAll();
    $users = $pdo->query("SELECT u.id, u.email, u.username, u.status, u.is_system_admin, u.created_at, COUNT(s.id) AS submission_count FROM discovery_users u LEFT JOIN discovery_submissions s ON s.owner_user_id = u.id GROUP BY u.id, u.email, u.username, u.status, u.is_system_admin, u.created_at ORDER BY u.is_system_admin DESC, u.username ASC")->fetchAll();
    $invitations = $pdo->query('SELECT i.*, u.username AS claimed_username, p.client_business_type FROM discovery_invitations i LEFT JOIN discovery_users u ON u.id = i.claimed_by_user_id LEFT JOIN discovery_projects p ON p.project_id = i.client_id ORDER BY i.created_at DESC, i.id DESC LIMIT 100')->fetchAll();
} catch (Throwable $loadError) {
    $projects = [];
    $users = [];
    $invitations = [];
    $error = 'Projects, users, and invitations could not be loaded.';
}

$activeProjects = array_values(array_filter($projects, static fn(array $project): bool => (string)$project['status'] === 'active'));

$body = '<a class="toplink" href="/discovery/results/">← Results workspace</a>';
if ($error) $body .= '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>';
if ($notice) $body .= '<p class="notice notice-success" role="status">' . oobEscape($notice) . '</p>';

$body .= '<section><p class="eyebrow">Projects</p><h2>Discovery projects</h2><p class="help">Create each project once before inviting users. The project is the stable connection between the client name, its internal project ID, its business type, invitations, and submissions.</p><ul class="list">';
if ($projects === []) $body .= '<li>No projects yet. Create the first project below.</li>';
foreach ($projects as $project) {
    $body .= '<li><strong>' . oobEscape((string)$project['project_name']) . '</strong><br><span class="meta">Project ID: ' . oobEscape((string)$project['project_id'])
        . ' · Business type: ' . oobEscape((string)$project['client_business_type'])
        . ' · ' . oobEscape(ucfirst((string)$project['status']))
        . ' · ' . (int)$project['submission_count'] . ' submission' . ((int)$project['submission_count'] === 1 ? '' : 's')
        . ' · ' . (int)$project['invitation_count'] . ' invitation' . ((int)$project['invitation_count'] === 1 ? '' : 's') . '</span></li>';
}
$body .= '</ul></section>';

$body .= '<section class="card"><p class="eyebrow">New project</p><h2>Create a project</h2><p class="help">The business type is machine-readable context for downstream research and LLM workflows. Use a stable category such as <code>plumbing</code>, <code>clinician</code>, or another intentionally defined type. It is not a user role.</p><form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="create_project">'
    . '<label for="project_name">Project / client name</label><input id="project_name" name="project_name" type="text" maxlength="160" required placeholder="Paul’s Plumbing">'
    . '<label for="project_id">Project ID</label><input id="project_id" name="project_id" type="text" maxlength="80" pattern="[a-z0-9][a-z0-9-]{1,79}" required placeholder="pauls-plumbing"><small>Stable internal key. Lowercase letters, numbers, and hyphens only.</small>'
    . '<label for="client_business_type">Client business type</label><input id="client_business_type" name="client_business_type" type="text" maxlength="80" pattern="[a-z0-9][a-z0-9-]{1,79}" required placeholder="plumbing"><small>Connects this project to reusable business-type knowledge for research, intake preparation, and downstream LLM work.</small>'
    . '<button type="submit">Create project</button></form></section><div class="rule"></div>';

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
    $questionnaireUrl = 'https://discovery.oobcreative.com/varetto/';
    $emailText = "Subject: Your private " . $created['client_label'] . " Discovery invitation\n\n"
        . "Hi [Name],\n\n"
        . "This is your individual invitation to " . $created['client_label'] . " Discovery.\n\n"
        . "1. Create your private Client account before " . $created['expires_at'] . " UTC:\n" . $claimUrl . "\n"
        . "2. Check your email and use the verification link.\n"
        . "3. Sign in to your Discovery workspace and open the questionnaire. Responses submitted while signed in will belong to your account, and only you and a Full Admin can view them.\n"
        . "Questionnaire: " . $questionnaireUrl . "\n\n"
        . "Do not include patient names or any detail that could identify an individual. This account link is single-use and intended only for you.\n\n"
        . "Thank you,\nRodney";
    $body .= '<section class="card"><p class="eyebrow">Created once</p><h2>Copy this invitation now</h2><p class="notice notice-success">For security, the complete link will not be shown again.</p><p><strong>Project:</strong> ' . oobEscape((string)$created['client_label']) . '<br><strong>Business type:</strong> ' . oobEscape((string)$created['client_business_type']) . '</p><p class="mono">' . oobEscape($claimUrl) . '</p><label for="email-copy"><strong>Formatted message</strong></label><p id="email-copy" class="mono">' . nl2br(oobEscape($emailText)) . '</p></section><div class="rule"></div>';
}

$accountNotice = oobAccountAuthEnabled($accessConfig) ? '' : '<p class="notice notice-info">Invited accounts are disabled until Google Workspace SMTP is configured.</p>';
$body .= $accountNotice . '<section class="card"><p class="eyebrow">New Client</p><h2>Invite a user</h2><p class="help">Choose a project that was already defined above. Invitations no longer create or rename projects.</p>';
if ($activeProjects === []) {
    $body .= '<p class="notice notice-info">Create an active project before generating a Client invitation.</p>';
} else {
    $body .= '<form method="post" class="form"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="create_invitation">'
        . '<label for="project_record_id">Project</label><select id="project_record_id" name="project_record_id" required><option value="">Choose a project</option>';
    foreach ($activeProjects as $project) {
        $body .= '<option value="' . (int)$project['id'] . '">' . oobEscape((string)$project['project_name']) . ' · ' . oobEscape((string)$project['client_business_type']) . '</option>';
    }
    $body .= '</select><label for="days">Invitation expires after</label><select id="days" name="days"><option value="7">7 days</option><option value="3">3 days</option><option value="14">14 days</option><option value="30">30 days</option></select><button type="submit">Create Client invitation</button></form>';
}
$body .= '</section>';

$body .= '<div class="rule"></div><section><p class="eyebrow">Recent activity</p><h2>Invitations</h2><ul class="list">';
if ($invitations === []) $body .= '<li>No invitations yet.</li>';
foreach ($invitations as $invitation) {
    $state = oobInvitationState($invitation);
    $body .= '<li><strong>' . oobEscape((string)$invitation['client_label']) . '</strong> · Client invitation<br><span class="meta">' . oobEscape((string)($invitation['client_business_type'] ?: 'unclassified')) . ' · ' . oobEscape(ucfirst($state)) . ' · expires ' . oobEscape((string)$invitation['expires_at']) . ' UTC · created by ' . oobEscape((string)$invitation['created_by']);
    if (!empty($invitation['claimed_username'])) $body .= ' · claimed by ' . oobEscape((string)$invitation['claimed_username']);
    $body .= '</span>';
    if ($state === 'active') {
        $body .= '<form method="post" class="actions"><input type="hidden" name="csrf" value="' . oobEscape(oobCsrfToken()) . '"><input type="hidden" name="action" value="revoke"><input type="hidden" name="invitation_id" value="' . (int)$invitation['id'] . '"><button class="button-secondary" type="submit">Revoke</button></form>';
    }
    $body .= '</li>';
}
$body .= '</ul></section>';
oobRenderAccountPage('Projects, users & access', 'Full Admin', 'Define projects once, connect each project to a business type, then invite individual Client users into the project.', $body, $error ? 400 : 200);
