<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 3) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';

oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Project dashboard', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $principal = oobCurrentPrincipal($accessConfig, $pdo);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-project] Bootstrap failed.');
    oobRenderAccountPage('Access unavailable', 'Project dashboard', 'This project is temporarily unavailable.', '', 503);
}

if (!$principal) oobRedirect('/discovery/results/');

$projectId = trim((string)($_GET['project_id'] ?? ''));
if (!oobValidClientId($projectId)) oobRenderAccountPage('Project required', 'Project dashboard', 'Open a project from your Discovery workspace.', '', 400, '', true);

$isAdmin = (bool)$principal['system_admin'];
$allowed = $isAdmin;
if (!$allowed) {
    foreach ((array)$principal['clients'] as $access) {
        if ((string)($access['client_id'] ?? '') === $projectId) { $allowed = true; break; }
    }
}
if (!$allowed) oobRenderAccountPage('Project unavailable', 'Project dashboard', 'This project is not available to your account.', '', 403, '', true);

$projectStatement = $pdo->prepare('SELECT id, project_id, project_name, client_business_type, status, created_at, updated_at FROM discovery_projects WHERE project_id = :project_id LIMIT 1');
$projectStatement->execute([':project_id' => $projectId]);
$project = $projectStatement->fetch();
if (!$project) oobRenderAccountPage('Project unavailable', 'Project dashboard', 'This project definition no longer exists.', '', 404, '', true);

$error = null;
$notice = null;
$flash = $_SESSION['project_flash'][$projectId] ?? null;
if (isset($_SESSION['project_flash'][$projectId])) {
    unset($_SESSION['project_flash'][$projectId]);
    if (empty($_SESSION['project_flash'])) unset($_SESSION['project_flash']);
}
if (is_array($flash)) {
    $message = trim((string)($flash['message'] ?? ''));
    if ($message !== '') {
        if ((string)($flash['type'] ?? '') === 'error') $error = $message;
        else $notice = $message;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        oobRenderAccountPage('Admin access required', 'Project dashboard', 'Only a Full Admin can change project membership.', '', 403, '', true);
    }

    $flashType = 'success';
    $flashMessage = '';
    if (!oobValidCsrf()) {
        $flashType = 'error';
        $flashMessage = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$userId) throw new InvalidArgumentException('Choose a valid Client account.');
            $user = oobUserById($pdo, (int)$userId);
            if (!$user || (bool)$user['is_system_admin']) throw new InvalidArgumentException('Choose a Client account.');

            if ($action === 'add_member') {
                if ((string)$user['status'] !== 'active') throw new InvalidArgumentException('Only active Client accounts can be added to a project.');
                $statement = $pdo->prepare("INSERT INTO discovery_user_clients (user_id, client_id, role) VALUES (:user_id, :client_id, 'viewer') ON DUPLICATE KEY UPDATE role = 'viewer', updated_at = CURRENT_TIMESTAMP");
                $statement->execute([':user_id' => (int)$userId, ':client_id' => $projectId]);
                $flashMessage = (string)$user['username'] . ' now has access to ' . (string)$project['project_name'] . '.';
            } elseif ($action === 'remove_member') {
                $statement = $pdo->prepare('DELETE FROM discovery_user_clients WHERE user_id = :user_id AND client_id = :client_id');
                $statement->execute([':user_id' => (int)$userId, ':client_id' => $projectId]);
                $flashMessage = (string)$user['username'] . ' was removed from this project. Their account and submitted data were preserved.';
            } else {
                throw new InvalidArgumentException('Choose a valid project membership action.');
            }
        } catch (Throwable $memberError) {
            $flashType = 'error';
            $flashMessage = $memberError instanceof InvalidArgumentException
                ? $memberError->getMessage()
                : 'Project membership could not be updated.';
        }
    }

    $_SESSION['project_flash'][$projectId] = [
        'type' => $flashType,
        'message' => $flashMessage,
    ];
    oobRedirect('/discovery/project/?project_id=' . rawurlencode($projectId));
}

$memberStatement = $pdo->prepare("SELECT u.id, u.username, u.email, u.status, COUNT(s.id) AS owned_submission_count FROM discovery_user_clients uc JOIN discovery_users u ON u.id = uc.user_id LEFT JOIN discovery_submissions s ON s.owner_user_id = u.id AND s.client_id = uc.client_id WHERE uc.client_id = :project_id AND u.is_system_admin = 0 GROUP BY u.id, u.username, u.email, u.status ORDER BY u.username");
$memberStatement->execute([':project_id' => $projectId]);
$members = $memberStatement->fetchAll();

$availableClients = [];
if ($isAdmin) {
    $availableStatement = $pdo->prepare("SELECT u.id, u.username, u.email FROM discovery_users u WHERE u.is_system_admin = 0 AND u.status = 'active' AND NOT EXISTS (SELECT 1 FROM discovery_user_clients uc WHERE uc.user_id = u.id AND uc.client_id = :project_id) ORDER BY u.username");
    $availableStatement->execute([':project_id' => $projectId]);
    $availableClients = $availableStatement->fetchAll();
}

$submissionStatement = $pdo->prepare("SELECT s.id, s.submission_id, s.respondent_name, s.respondent_email, s.owner_user_id, s.questionnaire_version, s.status, s.created_at, s.updated_at, u.username AS owner_username, u.email AS owner_email FROM discovery_submissions s LEFT JOIN discovery_users u ON u.id = s.owner_user_id WHERE s.client_id = :project_id AND s.client_id <> 'deployment-check' ORDER BY s.updated_at DESC, s.id DESC LIMIT 250");
$submissionStatement->execute([':project_id' => $projectId]);
$submissions = $submissionStatement->fetchAll();

$currentUserId = (int)($principal['user_id'] ?? 0);
$csrf = oobEscape(oobCsrfToken());
$headerActions = '<span class="header-context">' . oobEscape((string)$principal['username']) . ' · ' . ($isAdmin ? 'Full Admin' : 'Client') . '</span>'
    . '<a class="button" href="/discovery/results/">Responses</a>';
if ($isAdmin) $headerActions .= '<a class="button button-secondary" href="/discovery/results/invitations/">Projects, users &amp; access</a>';
$headerActions .= '<form method="post" action="/discovery/results/"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form>';

$body = '<p class="meta">Project ID: ' . oobEscape((string)$project['project_id']) . ' · Business type: ' . oobEscape((string)$project['client_business_type']) . ' · ' . oobEscape(ucfirst((string)$project['status'])) . '</p>';
$body .= '<p class="notice notice-info"><strong>Project visibility:</strong> everyone assigned to this project can review all responses in the project. Only the account that originally submitted a response can edit it. Full Admins can review all projects.</p>';
if ($error) $body .= '<p class="notice notice-error" role="alert">' . oobEscape($error) . '</p>';
if ($notice) $body .= '<p class="notice notice-success" role="status">' . oobEscape($notice) . '</p>';

$body .= '<section class="card"><p class="eyebrow">Project people</p><h2>Members</h2><p class="help">Client membership controls who can review this project’s shared Discovery data. Removing a Client from the project does not delete their account or their submitted data.</p><ul class="list">';
if ($members === []) $body .= '<li>No Client accounts are currently assigned to this project.</li>';
foreach ($members as $member) {
    $body .= '<li><strong>' . oobEscape((string)$member['username']) . '</strong> · Client'
        . '<br><span class="meta">' . oobEscape((string)$member['email']) . ' · ' . oobEscape(ucfirst((string)$member['status'])) . ' · ' . (int)$member['owned_submission_count'] . ' submitted response' . ((int)$member['owned_submission_count'] === 1 ? '' : 's') . '</span>';
    if ($isAdmin) {
        $body .= '<form method="post" class="actions"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="remove_member"><input type="hidden" name="user_id" value="' . (int)$member['id'] . '"><button class="button-secondary" type="submit">Remove from project</button></form>';
    }
    $body .= '</li>';
}
$body .= '</ul>';

if ($isAdmin) {
    $body .= '<div class="rule"></div><p class="eyebrow">Admin</p><h2>Add Client access</h2>';
    if ($availableClients === []) {
        $body .= '<p class="help">Every active Client account is already assigned to this project, or there are no additional active Client accounts yet.</p>';
    } else {
        $body .= '<form method="post" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="add_member"><label for="user_id">Client account</label><select id="user_id" name="user_id" required><option value="">Choose a Client</option>';
        foreach ($availableClients as $client) {
            $body .= '<option value="' . (int)$client['id'] . '">' . oobEscape((string)$client['username']) . ' · ' . oobEscape((string)$client['email']) . '</option>';
        }
        $body .= '</select><button type="submit">Add to project</button></form>';
    }
    $body .= '<p class="help">Need a new Client account? <a href="/discovery/results/invitations/">Create an invitation</a>, then return here to manage project membership.</p>';
}
$body .= '</section><div class="rule"></div>';

$body .= '<section><p class="eyebrow">Project data</p><h2>Responses</h2><p class="help">These are all stored Discovery responses associated with this project, regardless of which project member submitted them.</p><ul class="list">';
if ($submissions === []) $body .= '<li>No responses have been submitted to this project yet.</li>';
foreach ($submissions as $row) {
    $name = trim((string)$row['respondent_name']) ?: 'Unnamed response';
    $owner = $row['owner_username'] ? (string)$row['owner_username'] . ' · ' . (string)$row['owner_email'] : 'Unassigned / historical';
    $body .= '<li><strong>' . oobEscape($name) . '</strong><br><span class="meta">Owner: ' . oobEscape($owner) . ' · Updated ' . oobEscape((string)($row['updated_at'] ?: $row['created_at'])) . '</span>'
        . '<div class="actions"><a class="button" href="/discovery/response/?submission_id=' . rawurlencode((string)$row['submission_id']) . '">Review response</a>';
    if (!$isAdmin && (int)$row['owner_user_id'] === $currentUserId) {
        $body .= '<a class="button button-secondary" href="https://discovery.oobcreative.com/' . rawurlencode($projectId) . '/?edit=' . rawurlencode((string)$row['submission_id']) . '">Edit my response</a>';
    }
    $body .= '</div></li>';
}
$body .= '</ul></section>';

oobRenderAccountPage((string)$project['project_name'], 'Project dashboard', 'Project-level Discovery data and account access.', $body, 200, $headerActions, true);
