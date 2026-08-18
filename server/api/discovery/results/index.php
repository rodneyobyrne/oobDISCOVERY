<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 3) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';

oobApplySecurityHeaders();
if (!oobIsSecureRequest()) {
    oobRenderAccountPage('Secure connection required', 'Discovery responses', 'Open this page using HTTPS.', '', 400);
}
oobStartDiscoverySession();

function resultsEscape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function resultsRedirect(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

function resultsRateFile(): string
{
    $directory = rtrim(sys_get_temp_dir(), '/') . '/oobdiscovery-results-login';
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    return $directory . '/' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '.json';
}

function resultsRateLimited(): bool
{
    $state = json_decode((string)@file_get_contents(resultsRateFile()), true);
    if (!is_array($state)) return false;
    return time() - (int)($state['started'] ?? 0) < 900 && (int)($state['attempts'] ?? 0) >= 8;
}

function resultsRecordFailedLogin(): void
{
    $file = resultsRateFile();
    $state = json_decode((string)@file_get_contents($file), true);
    $now = time();
    $started = is_array($state) ? (int)($state['started'] ?? 0) : 0;
    $attempts = is_array($state) ? (int)($state['attempts'] ?? 0) : 0;
    if ($started === 0 || $now - $started >= 900) { $started = $now; $attempts = 0; }
    @file_put_contents($file, json_encode(['started' => $started, 'attempts' => $attempts + 1]), LOCK_EX);
    @chmod($file, 0600);
}

function resultsClearRateLimit(): void
{
    $file = resultsRateFile();
    if (is_file($file)) @unlink($file);
}

function resultsFormatDate(?string $value, string $timezone): string
{
    if (!$value) return 'Unknown date';
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone($timezone))->format('M j, Y · g:i a T');
    } catch (Throwable $error) {
        return (string)$value;
    }
}

function resultsCss(): string
{
    return <<<'CSS'
:root{--ink:#111;--paper:#fff;--muted:#656565;--line:#c9c9c9;--soft:#f4f4f1;--accent:#d99f6c;color-scheme:light;font-family:Arial,Helvetica,sans-serif}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);line-height:1.6}a{color:inherit;text-underline-offset:.18em}button,.button,input{font:inherit}.topbar{background:#111;color:#fff;border-bottom:6px solid var(--accent)}.topbar-inner,.page{width:min(1120px,calc(100% - 2rem));margin:auto}.topbar-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:1rem}.brand{font-weight:800}.top-actions{display:flex;align-items:center;flex-wrap:wrap;gap:.6rem}.account-name{font-size:.82rem;color:#ddd}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:.65rem .9rem;border:1px solid #111;background:#111;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.topbar .button,.topbar button{border-color:#fff}.button.secondary{background:#fff;color:#111}.page{padding:clamp(2.5rem,6vw,5rem) 0 5rem}.eyebrow{margin:0 0 .6rem;font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{margin:.2rem 0 1rem;font-size:clamp(2.8rem,6vw,5.5rem);line-height:.94;letter-spacing:-.055em}h2{margin:0;font-size:1.5rem;letter-spacing:-.025em}.lede{max-width:52rem;color:#333;font-size:1.08rem}.rule-note{margin:2rem 0;padding:1.15rem 1.25rem;border:1px solid #111;border-left:6px solid #111;background:var(--soft)}.response-count{margin:2.5rem 0 1rem;font-size:.78rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.response-list{display:grid;gap:1rem}.response-card{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:1.25rem;padding:1.25rem;border:1px solid #111}.response-card p{margin:.25rem 0 0}.meta{color:var(--muted);font-size:.87rem}.client{display:inline-block;margin-top:.55rem;padding:.2rem .45rem;border:1px solid #aaa;font-size:.72rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.empty{padding:2rem;border:1px solid var(--line);background:var(--soft)}.login-shell{width:min(560px,calc(100% - 2rem));margin:8vh auto;padding:2rem;border:1px solid #111}.login-shell h1{font-size:clamp(2.3rem,6vw,4rem)}.login-form{display:grid;gap:.75rem}.login-form label{font-weight:700}.login-form input{width:100%;padding:.75rem;border:1px solid #999}.login-help{margin:.1rem 0 .35rem;color:var(--muted);font-size:.9rem}.notice{padding:1rem;border:1px solid #7a1e1e;color:#7a1e1e}@media(max-width:760px){.topbar-inner{align-items:flex-start;flex-direction:column;padding:1rem 0}.response-card{grid-template-columns:1fr}.response-card .button{width:100%}}
CSS;
}

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    oobRenderAccountPage('Access unavailable', 'Discovery responses', 'The response workspace is temporarily unavailable.', '', 503);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'logout') {
    if (!oobValidCsrf()) {
        oobRenderAccountPage('Session expired', 'Sign out', 'Refresh the page and try again.', '', 400);
    }
    oobClearAuthSession();
    resultsRedirect('/discovery/results/');
}

$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'login') {
    if (!oobValidCsrf()) {
        $loginError = 'Your session expired. Refresh and try again.';
    } elseif (resultsRateLimited()) {
        $loginError = 'Too many sign-in attempts. Wait a few minutes and try again.';
    } else {
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $authenticated = false;

        if (oobAccountAuthEnabled($accessConfig)) {
            try {
                oobAccountLogin($pdo, $identifier, $password);
                $authenticated = true;
            } catch (Throwable $ignored) {
                // Fall through to the temporary shared administrator login.
            }
        }

        if (!$authenticated) {
            $configuredUsername = trim((string)($accessConfig['username'] ?? ''));
            $configuredHash = (string)($accessConfig['password_hash'] ?? '');
            if ($configuredUsername !== '' && $configuredHash !== '' && hash_equals($configuredUsername, $identifier) && password_verify($password, $configuredHash)) {
                session_regenerate_id(true);
                $_SESSION['auth_mode'] = 'legacy';
                $_SESSION['authenticated'] = true;
                $_SESSION['authenticated_at'] = time();
                $_SESSION['username'] = $configuredUsername;
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                $authenticated = true;
            }
        }

        if ($authenticated) {
            resultsClearRateLimit();
            resultsRedirect('/discovery/results/');
        }
        resultsRecordFailedLogin();
        $loginError = 'The email address, username, or password was not recognized.';
    }
}

$authenticatedAt = (int)($_SESSION['authenticated_at'] ?? 0);
if (!empty($_SESSION['authenticated']) && ($authenticatedAt === 0 || time() - $authenticatedAt > 28800)) {
    oobClearAuthSession();
    oobStartDiscoverySession();
}

$principal = oobCurrentPrincipal($accessConfig, $pdo);
if (!$principal) {
    http_response_code($loginError ? 401 : 200);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Discovery responses · oobCREATIVE</title><style><?= resultsCss() ?></style></head><body><main class="login-shell"><p class="eyebrow">Private workspace</p><h1>Discovery responses</h1><p class="lede">Sign in to review the source responses available to your account.</p><?php if ($loginError): ?><p class="notice" role="alert"><?= resultsEscape($loginError) ?></p><?php endif; ?><form method="post" class="login-form"><input type="hidden" name="csrf" value="<?= resultsEscape(oobCsrfToken()) ?>"><input type="hidden" name="action" value="login"><label for="identifier">Email address or username</label><input id="identifier" name="identifier" autocomplete="username" required autofocus><p class="login-help">Use either the email address on your account or the username you created. They sign into the same account.</p><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in</button></form><?php if (oobAccountAuthEnabled($accessConfig)): ?><p><a href="/discovery/account/forgot/">Trouble signing in?</a></p><?php endif; ?></main></body></html>
<?php
    exit;
}

$timezone = (string)($accessConfig['timezone'] ?? 'America/Denver');
try {
    $clientIds = array_values(array_unique(array_map(static fn(array $access): string => (string)$access['client_id'], $principal['clients'])));
    if ($principal['system_admin']) {
        $statement = $pdo->query("SELECT id, submission_id, client_id, respondent_name, respondent_email, questionnaire_version, status, created_at FROM discovery_submissions WHERE discovery_type = 'clinician' AND client_id <> 'deployment-check' ORDER BY created_at DESC, id DESC LIMIT 100");
    } else {
        if ($clientIds === []) throw new RuntimeException('No client access is assigned.');
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $statement = $pdo->prepare("SELECT id, submission_id, client_id, respondent_name, respondent_email, questionnaire_version, status, created_at FROM discovery_submissions WHERE discovery_type = 'clinician' AND client_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC LIMIT 100");
        $statement->execute($clientIds);
    }
    $submissions = $statement->fetchAll();
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-results] Response index failed.');
    oobRenderAccountPage('Responses unavailable', 'Discovery responses', 'The response list could not be loaded.', '', 503);
}

// Preserve old bookmarked numeric response links by moving them into the source-first review.
$legacySelectedId = filter_input(INPUT_GET, 'submission', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($legacySelectedId) {
    foreach ($submissions as $row) {
        if ((int)$row['id'] === (int)$legacySelectedId) {
            resultsRedirect('/discovery/response/?submission_id=' . rawurlencode((string)$row['submission_id']));
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Discovery responses · oobCREATIVE</title><style><?= resultsCss() ?></style></head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand">oobCREATIVE · Discovery</div><div class="top-actions"><span class="account-name">Signed in as <?= resultsEscape((string)$principal['username']) ?></span><?php if ($principal['system_admin']): ?><a class="button secondary" href="/discovery/results/invitations/">Manage access</a><?php endif; ?><a class="button secondary" href="https://discovery.oobcreative.com/clinician/">Open questionnaire</a><form method="post"><input type="hidden" name="csrf" value="<?= resultsEscape(oobCsrfToken()) ?>"><input type="hidden" name="action" value="logout"><button type="submit">Sign out</button></form></div></div></header>
<main class="page">
  <p class="eyebrow">Private evidence index</p>
  <h1>Discovery responses</h1>
  <p class="lede">This page is now deliberately simple: it confirms what was captured and opens each response in a source-first review. Persona development, marketing recommendations, and broader synthesis happen later and separately.</p>
  <div class="rule-note"><strong>Source-first rule:</strong> opening a response shows the original answers plus only a few directly traceable early observations. The source submission remains the primary record.</div>
  <p class="response-count"><?= count($submissions) ?> response<?= count($submissions) === 1 ? '' : 's' ?> available</p>
  <?php if ($submissions === []): ?>
    <div class="empty">No clinician discovery responses have been submitted for this account yet.</div>
  <?php else: ?>
    <div class="response-list">
      <?php foreach ($submissions as $row): $name = trim((string)$row['respondent_name']) ?: 'Unnamed response'; ?>
        <article class="response-card">
          <div><h2><?= resultsEscape($name) ?></h2><p class="meta"><?= resultsEscape((string)($row['respondent_email'] ?: 'No email provided')) ?> · <?= resultsEscape(resultsFormatDate((string)$row['created_at'], $timezone)) ?></p><span class="client"><?= resultsEscape((string)$row['client_id']) ?></span></div>
          <a class="button" href="/discovery/response/?submission_id=<?= rawurlencode((string)$row['submission_id']) ?>">Review source response</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
</body>
</html>