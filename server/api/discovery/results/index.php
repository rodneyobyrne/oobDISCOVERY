<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 3) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';

oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Discovery responses', 'Open this page using HTTPS.', '', 400);
oobStartDiscoverySession();

function resultsEscape(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function resultsRedirect(string $location): void { header('Location: ' . $location, true, 303); exit; }
function resultsRateFile(): string {
    $directory = rtrim(sys_get_temp_dir(), '/') . '/oobdiscovery-results-login';
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    return $directory . '/' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '.json';
}
function resultsRateLimited(): bool {
    $state = json_decode((string)@file_get_contents(resultsRateFile()), true);
    return is_array($state) && time() - (int)($state['started'] ?? 0) < 900 && (int)($state['attempts'] ?? 0) >= 8;
}
function resultsRecordFailedLogin(): void {
    $file = resultsRateFile();
    $state = json_decode((string)@file_get_contents($file), true);
    $now = time(); $started = is_array($state) ? (int)($state['started'] ?? 0) : 0; $attempts = is_array($state) ? (int)($state['attempts'] ?? 0) : 0;
    if ($started === 0 || $now - $started >= 900) { $started = $now; $attempts = 0; }
    @file_put_contents($file, json_encode(['started' => $started, 'attempts' => $attempts + 1]), LOCK_EX); @chmod($file, 0600);
}
function resultsClearRateLimit(): void { $file = resultsRateFile(); if (is_file($file)) @unlink($file); }
function resultsFormatDate(?string $value, string $timezone): string {
    if (!$value) return 'Unknown date';
    try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone))->format('M j, Y · g:i a T'); }
    catch (Throwable $error) { return (string)$value; }
}
function resultsCss(): string {
    return <<<'CSS'
:root{--ink:#111;--paper:#fff;--muted:#656565;--line:#c9c9c9;--soft:#f4f4f1;color-scheme:light;font-family:Arial,Helvetica,sans-serif}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);line-height:1.6}a{color:inherit;text-underline-offset:.18em}button,.button,input{font:inherit}.topbar{position:sticky;z-index:1000;top:0;background:#111;color:#fff}.topbar::after{content:"";position:absolute;right:0;bottom:-5px;left:0;height:10px;background:#fff;clip-path:polygon(0 34%,8% 27%,17% 42%,28% 30%,40% 42%,52% 29%,64% 42%,77% 29%,89% 41%,100% 34%,100% 67%,89% 60%,77% 71%,64% 61%,52% 71%,40% 61%,28% 72%,16% 60%,0 68%)}.topbar-inner,.page{width:min(1180px,calc(100% - 2rem));margin:auto}.topbar-inner{min-height:104px;display:flex;align-items:center;justify-content:space-between;gap:2rem}.workspace-brand{display:inline-flex;align-items:center;gap:1rem;color:#fff;text-decoration:none}.workspace-brand img{display:block;width:auto;height:82px;object-fit:contain;filter:invert(1)}.brand-copy{display:grid;line-height:1}.brand-name{font-size:1rem;font-weight:700;letter-spacing:-.04em}.brand-name span{color:#929292;font-weight:400}.brand-sub{margin-top:.45rem;font-size:.65rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase}.top-actions,.card-actions{display:flex;align-items:center;flex-wrap:wrap;gap:.65rem}.account-identity{display:grid;justify-items:end;line-height:1.15;margin-right:.35rem}.account-name{font-size:.84rem;font-weight:700;color:#fff}.account-role{margin-top:.3rem;font-size:.66rem;font-weight:700;letter-spacing:.13em;text-transform:uppercase;color:#aaa}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:.65rem .9rem;border:1px solid #111;background:#111;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.topbar .button,.topbar button{border-color:#fff}.button.secondary{background:#fff;color:#111}.page{padding:clamp(2.5rem,6vw,5rem) 0 5rem}.eyebrow{margin:0 0 .6rem;font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:.2rem 0 1rem;font-size:clamp(2.8rem,6vw,5.5rem);line-height:.94;letter-spacing:-.055em}h2{margin:0;font-size:1.5rem;letter-spacing:-.025em}.lede{max-width:52rem;color:#333;font-size:1.08rem}.rule-note{margin:2rem 0;padding:1.15rem 1.25rem;border:1px solid #111;border-left:6px solid #111;background:var(--soft)}.response-count{margin:2.5rem 0 1rem;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.response-list{display:grid;gap:1rem}.response-card{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1.25rem;padding:1.25rem;border:1px solid #111}.response-card p{margin:.25rem 0 0}.meta{color:var(--muted);font-size:.87rem}.client{display:inline-block;margin-top:.55rem;padding:.2rem .45rem;border:1px solid #aaa;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.empty{padding:2rem;border:1px solid var(--line);background:var(--soft)}.login-shell{width:min(560px,calc(100% - 2rem));margin:8vh auto;padding:2rem;border:1px solid #111}.login-shell h1{font-size:clamp(2.3rem,6vw,4rem)}.login-form{display:grid;gap:.75rem}.login-form label{font-weight:700}.login-form input{width:100%;padding:.75rem;border:1px solid #999}.login-help{margin:.1rem 0 .35rem;color:var(--muted);font-size:.9rem}.notice{padding:1rem;border:1px solid #7a1e1e;color:#7a1e1e}@media(max-width:760px){.topbar-inner{align-items:flex-start;flex-direction:column;padding:1rem 0}.workspace-brand img{height:68px}.top-actions{width:100%;justify-content:flex-start}.account-identity{justify-items:start;width:100%;margin-right:0}.response-card{grid-template-columns:1fr}.card-actions .button{width:100%}}
CSS;
}

try { [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig(); $pdo = oobDatabaseConnection($databaseConfig); }
catch (Throwable $error) { oobRenderAccountPage('Access unavailable', 'Discovery responses', 'The response workspace is temporarily unavailable.', '', 503); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'logout') {
    if (!oobValidCsrf()) oobRenderAccountPage('Session expired', 'Sign out', 'Refresh the page and try again.', '', 400);
    oobClearAuthSession(); resultsRedirect('/discovery/results/');
}

$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'login') {
    if (!oobValidCsrf()) $loginError = 'Your session expired. Refresh and try again.';
    elseif (resultsRateLimited()) $loginError = 'Too many sign-in attempts. Wait a few minutes and try again.';
    else {
        $identifier = trim((string)($_POST['identifier'] ?? '')); $password = (string)($_POST['password'] ?? ''); $authenticated = false;
        if (oobAccountAuthEnabled($accessConfig)) {
            try { oobAccountLogin($pdo, $identifier, $password); $authenticated = true; } catch (Throwable $ignored) {}
        }
        if (!$authenticated) {
            $configuredUsername = trim((string)($accessConfig['username'] ?? '')); $configuredHash = (string)($accessConfig['password_hash'] ?? '');
            if ($configuredUsername !== '' && $configuredHash !== '' && hash_equals($configuredUsername, $identifier) && password_verify($password, $configuredHash)) {
                session_regenerate_id(true); $_SESSION['auth_mode'] = 'legacy'; $_SESSION['authenticated'] = true; $_SESSION['authenticated_at'] = time(); $_SESSION['username'] = $configuredUsername; $_SESSION['csrf'] = bin2hex(random_bytes(32)); $authenticated = true;
            }
        }
        if ($authenticated) { resultsClearRateLimit(); resultsRedirect('/discovery/results/'); }
        resultsRecordFailedLogin(); $loginError = 'The email address, username, or password was not recognized.';
    }
}

$authenticatedAt = (int)($_SESSION['authenticated_at'] ?? 0);
if (!empty($_SESSION['authenticated']) && ($authenticatedAt === 0 || time() - $authenticatedAt > 28800)) { oobClearAuthSession(); oobStartDiscoverySession(); }
$principal = oobCurrentPrincipal($accessConfig, $pdo);
if (!$principal) {
    http_response_code($loginError ? 401 : 200); header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Discovery responses · oobCREATIVE</title><style><?= resultsCss() ?></style></head><body><main class="login-shell"><p class="eyebrow">Private workspace</p><h1>Discovery responses</h1><p class="lede">Sign in to review the responses available to your account.</p><?php if ($loginError): ?><p class="notice" role="alert"><?= resultsEscape($loginError) ?></p><?php endif; ?><form method="post" class="login-form"><input type="hidden" name="csrf" value="<?= resultsEscape(oobCsrfToken()) ?>"><input type="hidden" name="action" value="login"><label for="identifier">Email address or username</label><input id="identifier" name="identifier" autocomplete="username" required autofocus><p class="login-help">Use either the email address on your account or the username you created. They sign into the same account.</p><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in</button></form><?php if (oobAccountAuthEnabled($accessConfig)): ?><p><a href="/discovery/account/forgot/">Trouble signing in?</a></p><?php endif; ?></main></body></html>
<?php exit; }

$timezone = (string)($accessConfig['timezone'] ?? 'America/Denver');
$filterUser = null;
$projects = [];
try {
    if ($principal['system_admin']) {
        $projects = $pdo->query("SELECT project_id, project_name, client_business_type FROM discovery_projects WHERE status = 'active' ORDER BY project_name")->fetchAll();
        $requestedUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($requestedUserId) {
            $filterUser = oobUserById($pdo, (int)$requestedUserId);
            if (!$filterUser) throw new RuntimeException('User not found.');
            $statement = $pdo->prepare("SELECT s.*, u.username AS owner_username, u.email AS owner_email, COALESCE(p.project_name, s.client_id) AS project_name, COALESCE(p.client_business_type, s.client_business_type, 'unclassified') AS business_type FROM discovery_submissions s LEFT JOIN discovery_users u ON u.id = s.owner_user_id LEFT JOIN discovery_projects p ON p.project_id = s.client_id WHERE s.client_id <> 'deployment-check' AND s.owner_user_id = :owner_user_id ORDER BY s.updated_at DESC, s.id DESC LIMIT 100");
            $statement->execute([':owner_user_id' => (int)$requestedUserId]);
        } else {
            $statement = $pdo->query("SELECT s.*, u.username AS owner_username, u.email AS owner_email, COALESCE(p.project_name, s.client_id) AS project_name, COALESCE(p.client_business_type, s.client_business_type, 'unclassified') AS business_type FROM discovery_submissions s LEFT JOIN discovery_users u ON u.id = s.owner_user_id LEFT JOIN discovery_projects p ON p.project_id = s.client_id WHERE s.client_id <> 'deployment-check' ORDER BY s.updated_at DESC, s.id DESC LIMIT 100");
        }
    } else {
        $clientIds = array_values(array_unique(array_map(static fn(array $access): string => (string)$access['client_id'], $principal['clients'])));
        if ($clientIds !== []) {
            $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
            $projectStatement = $pdo->prepare("SELECT project_id, project_name, client_business_type FROM discovery_projects WHERE status = 'active' AND project_id IN ({$placeholders}) ORDER BY project_name");
            $projectStatement->execute($clientIds);
            $projects = $projectStatement->fetchAll();
        }
        $statement = $pdo->prepare("SELECT s.*, u.username AS owner_username, u.email AS owner_email, COALESCE(p.project_name, s.client_id) AS project_name, COALESCE(p.client_business_type, s.client_business_type, 'unclassified') AS business_type FROM discovery_submissions s LEFT JOIN discovery_users u ON u.id = s.owner_user_id LEFT JOIN discovery_projects p ON p.project_id = s.client_id WHERE s.owner_user_id = :owner_user_id ORDER BY s.updated_at DESC, s.id DESC LIMIT 100");
        $statement->execute([':owner_user_id' => (int)$principal['user_id']]);
    }
    $submissions = $statement->fetchAll();
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-results] Response index failed.');
    oobRenderAccountPage('Responses unavailable', 'Discovery responses', 'The response list could not be loaded.', '', 503);
}

$legacySelectedId = filter_input(INPUT_GET, 'submission', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($legacySelectedId) foreach ($submissions as $row) if ((int)$row['id'] === (int)$legacySelectedId) resultsRedirect('/discovery/response/?submission_id=' . rawurlencode((string)$row['submission_id']));

$isAdmin = (bool)$principal['system_admin'];
$title = $isAdmin ? ($filterUser ? 'User submissions' : 'Discovery responses') : 'Your Discovery responses';
$lede = $isAdmin
    ? ($filterUser ? 'Showing responses owned by ' . (string)$filterUser['username'] . ' (' . (string)$filterUser['email'] . ').' : 'Full Admin view includes all Discovery submissions, including historical responses that are not assigned to a user account.')
    : 'Only responses submitted through this signed-in Client account appear here. You can review or edit your own saved work.';
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= resultsEscape($title) ?> · oobCREATIVE</title><style><?= resultsCss() ?></style></head><body>
<header class="topbar"><div class="topbar-inner"><a class="workspace-brand" href="https://discovery.oobcreative.com/" aria-label="oobCREATIVE Discovery home"><img src="https://skills.oobcreative.com/branding/Mark-black.svg" alt=""><span class="brand-copy"><span class="brand-name"><span>oob</span>CREATIVE</span><span class="brand-sub">Discovery</span></span></a><div class="top-actions"><span class="account-identity"><span class="account-name"><?= resultsEscape((string)$principal['username']) ?></span><span class="account-role"><?= $isAdmin ? 'Full Admin' : 'Client' ?></span></span><a class="button" href="/discovery/results/">Responses</a><?php if ($isAdmin): ?><a class="button secondary" href="/discovery/results/invitations/">Projects, users & access</a><?php endif; ?><?php if (!$isAdmin): foreach ($projects as $project): ?><a class="button secondary" href="https://discovery.oobcreative.com/<?= rawurlencode((string)$project['project_id']) ?>/">Start <?= resultsEscape((string)$project['project_name']) ?> response</a><?php endforeach; endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= resultsEscape(oobCsrfToken()) ?>"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form></div></div></header>
<main class="page"><p class="eyebrow"><?= $isAdmin ? 'Full Admin' : 'Client account' ?></p><h1><?= resultsEscape($title) ?></h1><p class="lede"><?= resultsEscape($lede) ?></p>
<?php if ($isAdmin && $filterUser): ?><p><a href="/discovery/results/">← All submissions</a> · <a href="/discovery/results/invitations/">Projects, users & access</a></p><?php endif; ?>
<div class="rule-note"><strong>Access rule:</strong> Full Admins can review every response. Clients can review and edit only submissions owned by their own account.</div>
<p class="response-count"><?= count($submissions) ?> response<?= count($submissions) === 1 ? '' : 's' ?> available</p>
<?php if ($submissions === []): ?><div class="empty"><?= $isAdmin && $filterUser ? 'This user has no owned submissions yet.' : 'No Discovery responses are available for this account yet.' ?></div><?php else: ?><div class="response-list">
<?php foreach ($submissions as $row): $name = trim((string)$row['respondent_name']) ?: 'Unnamed response'; ?>
<article class="response-card"><div><h2><?= resultsEscape($name) ?></h2><p class="meta"><?= resultsEscape((string)$row['project_name']) ?> · <?= resultsEscape((string)$row['business_type']) ?> · <?= resultsEscape(resultsFormatDate((string)($row['updated_at'] ?: $row['created_at']), $timezone)) ?></p><?php if ($row['respondent_email']): ?><p class="meta"><?= resultsEscape((string)$row['respondent_email']) ?></p><?php endif; ?><?php if ($isAdmin): ?><p class="meta">Owner: <?= $row['owner_username'] ? resultsEscape((string)$row['owner_username']) . ' · ' . resultsEscape((string)$row['owner_email']) : 'Unassigned historical submission' ?></p><?php endif; ?></div><div class="card-actions"><a class="button" href="/discovery/response/?submission_id=<?= rawurlencode((string)$row['submission_id']) ?>">Review response</a><?php if (!$isAdmin): ?><a class="button secondary" href="https://discovery.oobcreative.com/<?= rawurlencode((string)$row['client_id']) ?>/?edit=<?= rawurlencode((string)$row['submission_id']) ?>">Edit response</a><?php endif; ?></div></article>
<?php endforeach; ?></div><?php endif; ?>
</main></body></html>
