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

$allowed = (bool)$principal['system_admin'];
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

$memberStatement = $pdo->prepare("SELECT u.id, u.username, u.email, u.status, u.is_system_admin, COUNT(s.id) AS owned_submission_count FROM discovery_user_clients uc JOIN discovery_users u ON u.id = uc.user_id LEFT JOIN discovery_submissions s ON s.owner_user_id = u.id AND s.client_id = uc.client_id WHERE uc.client_id = :project_id GROUP BY u.id, u.username, u.email, u.status, u.is_system_admin ORDER BY u.username");
$memberStatement->execute([':project_id' => $projectId]);
$members = $memberStatement->fetchAll();

$submissionStatement = $pdo->prepare("SELECT s.id, s.submission_id, s.respondent_name, s.respondent_email, s.owner_user_id, s.questionnaire_version, s.status, s.created_at, s.updated_at, u.username AS owner_username, u.email AS owner_email FROM discovery_submissions s LEFT JOIN discovery_users u ON u.id = s.owner_user_id WHERE s.client_id = :project_id AND s.client_id <> 'deployment-check' ORDER BY s.updated_at DESC, s.id DESC LIMIT 250");
$submissionStatement->execute([':project_id' => $projectId]);
$submissions = $submissionStatement->fetchAll();

$isAdmin = (bool)$principal['system_admin'];
$currentUserId = (int)($principal['user_id'] ?? 0);
$csrf = oobEscape(oobCsrfToken());
$headerActions = '<span class="header-context">' . oobEscape((string)$principal['username']) . ' · ' . ($isAdmin ? 'Full Admin' : 'Client') . '</span>'
    . '<a class="button" href="/discovery/results/">Responses</a>';
if ($isAdmin) $headerActions .= '<a class="button button-secondary" href="/discovery/results/invitations/">Projects, users &amp; access</a>';
$headerActions .= '<form method="post" action="/discovery/results/"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form>';

$body = '<p class="meta">Project ID: ' . oobEscape((string)$project['project_id']) . ' · Business type: ' . oobEscape((string)$project['client_business_type']) . ' · ' . oobEscape(ucfirst((string)$project['status'])) . '</p>';
$body .= '<p class="notice notice-info"><strong>Project visibility:</strong> everyone assigned to this project can review all responses in the project. Only the account that originally submitted a response can edit it. Full Admins can review all projects.</p>';

$body .= '<section class="card"><p class="eyebrow">Project people</p><h2>Members</h2><ul class="list">';
if ($members === []) $body .= '<li>No Client accounts are currently assigned to this project.</li>';
foreach ($members as $member) {
    $body .= '<li><strong>' . oobEscape((string)$member['username']) . '</strong> · ' . ((bool)$member['is_system_admin'] ? 'Full Admin' : 'Client')
        . '<br><span class="meta">' . oobEscape((string)$member['email']) . ' · ' . oobEscape(ucfirst((string)$member['status'])) . ' · ' . (int)$member['owned_submission_count'] . ' submitted response' . ((int)$member['owned_submission_count'] === 1 ? '' : 's') . '</span></li>';
}
$body .= '</ul></section><div class="rule"></div>';

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
