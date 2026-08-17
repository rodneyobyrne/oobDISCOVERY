<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 3) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('Vary: Cookie');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; img-src 'self' https://skills.oobcreative.com; style-src 'self' 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function escapeHtml(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectTo(string $location): void
{
    header('Location: ' . $location, true, 303);
    exit;
}

function isSecureRequest(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validCsrf(): bool
{
    $submitted = $_POST['csrf'] ?? '';
    return is_string($submitted) && hash_equals(csrfToken(), $submitted);
}

function loginRateFile(): string
{
    $directory = rtrim(sys_get_temp_dir(), '/') . '/oobdiscovery-results-login';
    if (!is_dir($directory)) {
        @mkdir($directory, 0700, true);
    }
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return $directory . '/' . hash('sha256', $address) . '.json';
}

function loginRateLimited(): bool
{
    $file = loginRateFile();
    $state = json_decode((string)@file_get_contents($file), true);
    if (!is_array($state)) return false;
    $started = (int)($state['started'] ?? 0);
    $attempts = (int)($state['attempts'] ?? 0);
    return time() - $started < 900 && $attempts >= 8;
}

function recordFailedLogin(): void
{
    $file = loginRateFile();
    $handle = @fopen($file, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) return;
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '{}', true);
    $now = time();
    $started = (int)($state['started'] ?? 0);
    $attempts = (int)($state['attempts'] ?? 0);
    if ($started === 0 || $now - $started >= 900) {
        $started = $now;
        $attempts = 0;
    }
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode(['started' => $started, 'attempts' => $attempts + 1]));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    @chmod($file, 0600);
}

function clearLoginRateLimit(): void
{
    $file = loginRateFile();
    if (is_file($file)) @unlink($file);
}

function databaseConnection(array $config): PDO
{
    $db = $config['database'] ?? [];
    return new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function formatDate(?string $value, string $timezone): string
{
    if (!$value) return 'Unknown date';
    try {
        return (new DateTimeImmutable($value))
            ->setTimezone(new DateTimeZone($timezone))
            ->format('M j, Y · g:i a T');
    } catch (Throwable $error) {
        return $value;
    }
}

function displayName(array $row): string
{
    $name = trim((string)($row['respondent_name'] ?? ''));
    return $name !== '' ? $name : 'Unnamed response';
}

function llmExport(array $row, array $payload): array
{
    return [
        'export_schema' => 'oob.discovery.llm.v1',
        'purpose' => 'Source-grounded website discovery analysis. Treat respondent statements as stakeholder evidence, not clinical diagnoses, verified facts, or approved marketing claims.',
        'provenance' => [
            'system' => 'oobDISCOVERY-CLINICIAN',
            'client_id' => (string)($row['client_id'] ?? ''),
            'discovery_type' => (string)($row['discovery_type'] ?? ''),
            'submission_id' => (string)($row['submission_id'] ?? ''),
            'questionnaire_version' => (string)($row['questionnaire_version'] ?? ''),
            'submitted_at' => (string)($row['created_at'] ?? ''),
            'respondent' => [
                'name' => (string)($row['respondent_name'] ?? ''),
                'email' => (string)($row['respondent_email'] ?? ''),
            ],
        ],
        'analysis_guardrails' => [
            'Preserve distinctions between direct statements, interpretation, and recommendations.',
            'Do not infer a diagnosis for any individual.',
            'Do not create treatment promises or unsupported outcome claims.',
            'Use patient-language patterns to support navigation and recognition, not self-diagnosis.',
        ],
        'source_payload' => $payload,
    ];
}

function textBlock(string $label, $value): void
{
    $text = is_string($value) ? trim($value) : '';
    if ($text === '') return;
    echo '<div class="answer"><dt>' . escapeHtml($label) . '</dt><dd>' . nl2br(escapeHtml($text)) . '</dd></div>';
}

function tagList(string $label, $values): void
{
    if (!is_array($values) || $values === []) return;
    $items = array_values(array_filter(array_map(static fn($item) => is_string($item) ? trim($item) : '', $values)));
    if ($items === []) return;
    echo '<div class="answer"><dt>' . escapeHtml($label) . '</dt><dd><ul class="tags">';
    foreach ($items as $item) echo '<li>' . escapeHtml($item) . '</li>';
    echo '</ul></dd></div>';
}

function renderLogin(array $accessConfig, ?string $error = null, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $token = escapeHtml(csrfToken());
    $errorMarkup = $error ? '<p class="notice notice-error" role="alert">' . escapeHtml($error) . '</p>' : '';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Discovery results · oobCREATIVE</title>';
    $managedHelp = oobManagedAuthEnabled($accessConfig) ? '<p class="login-help"><a href="/discovery/account/forgot/">Forgot your password?</a></p>' : '';
    echo '<style>' . dashboardCss() . '</style></head><body class="login-page"><main class="login-shell"><img class="login-mark" src="https://skills.oobcreative.com/branding/Mark-black.svg" alt="oobCREATIVE"><p class="eyebrow">Private workspace</p><h1>Discovery results</h1><p class="lede">Sign in to review the client discovery responses available to your account.</p>' . $errorMarkup;
    echo '<form method="post" class="login-form"><input type="hidden" name="csrf" value="' . $token . '"><input type="hidden" name="action" value="login"><label for="username">Email or username</label><input id="username" name="username" type="text" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in</button></form>' . $managedHelp . '<p class="privacy-note">Responses are private research material. Do not share or copy patient-identifying information into this system.</p></main></body></html>';
    exit;
}

function dashboardCss(): string
{
    return <<<'CSS'
:root{--ink:#0b0b0b;--paper:#fff;--soft:#f1f0ec;--line:#c9c7c0;--muted:#606060;--accent:#d99f6c;--max:1240px;color-scheme:light}
*{box-sizing:border-box}
html{font-family:Arial,Helvetica,sans-serif;background:var(--paper);color:var(--ink)}
body{margin:0;font-size:16px;line-height:1.65;background:var(--paper)}
a{color:inherit;text-underline-offset:.18em}
button,.button,input{font:inherit}
button,.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;border:1px solid var(--ink);border-radius:0;background:var(--ink);color:#fff;padding:.65rem 1rem;font-weight:700;text-decoration:none;cursor:pointer}
button:hover,.button:hover{background:#333}.button-secondary{background:#fff;color:var(--ink)}.button-secondary:hover{background:var(--soft)}
:focus-visible{outline:3px solid var(--accent);outline-offset:3px}
.skip-link{position:absolute;left:-999px;top:0}.skip-link:focus{left:1rem;top:1rem;z-index:100;background:#fff;padding:.5rem}
.topbar{background:#000;color:#fff;border-bottom:6px solid var(--accent)}.topbar-inner{max-width:var(--max);margin:auto;min-height:82px;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem}.brand{display:flex;align-items:center;gap:.7rem;font-weight:800;letter-spacing:-.01em}.brand-mark{width:32px;height:32px;filter:invert(1)}.top-actions{display:flex;align-items:center;gap:.75rem}.top-actions form{margin:0}.top-actions button{border-color:#fff}.top-actions .button{border-color:#fff}
.page{max-width:var(--max);margin:auto;padding:clamp(2rem,6vw,5rem) 1.5rem 5rem}.page-head{position:relative;display:flex;align-items:end;justify-content:space-between;gap:2rem;margin-bottom:2.5rem;padding-bottom:2rem}.page-head:after{content:"";position:absolute;left:-.45rem;right:1.5rem;bottom:.35rem;height:5px;background:var(--ink);transform:rotate(-.3deg);transform-origin:left center}.page-head:before{content:"";position:absolute;left:12%;width:34%;bottom:0;height:2px;background:var(--accent);transform:rotate(.65deg)}.eyebrow{margin:0 0 .55rem;font-size:.76rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}.page h1,.login-shell h1{margin:0;font-size:clamp(2.5rem,6vw,5.1rem);line-height:.94;letter-spacing:-.06em}.lede{max-width:50rem;margin:1rem 0 0;color:#363636;font-size:1.08rem}.count{font-size:2.5rem;font-weight:800;line-height:1}.count-label{display:block;color:var(--muted);font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.workspace{display:grid;grid-template-columns:minmax(260px,350px) minmax(0,1fr);border-top:3px solid var(--ink)}
.submission-list{border-right:1px solid var(--line);padding-right:1.5rem}.submission-list h2,.detail h2{font-size:.78rem;letter-spacing:.12em;text-transform:uppercase;margin:1.25rem 0}.submission-list ul{list-style:none;margin:0;padding:0}.submission-link{display:block;padding:1rem;border-top:1px solid var(--line);text-decoration:none}.submission-link:hover{background:var(--soft)}.submission-link[aria-current="page"]{background:var(--ink);color:#fff}.submission-link strong,.submission-link span{display:block}.submission-link span{font-size:.85rem;color:var(--muted)}.submission-link[aria-current="page"] span{color:#ddd}
.detail{min-width:0;padding-left:clamp(1.5rem,4vw,3rem)}.detail-head{display:flex;align-items:start;justify-content:space-between;gap:1rem;border-bottom:1px solid var(--line);padding:1.2rem 0 1.5rem}.detail-head h2{font-size:clamp(1.55rem,3vw,2.7rem);line-height:1.02;letter-spacing:-.04em;text-transform:none;margin:.25rem 0}.detail-meta{margin:0;color:var(--muted);font-size:.9rem}.export-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:.5rem}.signal-strip{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border:2px solid var(--ink);margin:1.5rem 0 0}.signal-strip div{padding:1rem;border-right:1px solid var(--ink)}.signal-strip div:last-child{border-right:0}.signal-strip strong,.signal-strip span{display:block}.signal-strip strong{font-size:2rem;line-height:1;font-weight:800}.signal-strip span{margin-top:.35rem;font-size:.72rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase}.section-nav{display:flex;flex-wrap:wrap;gap:.35rem .9rem;padding:1rem 0;border-bottom:1px solid var(--line)}.section-nav a{font-size:.78rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.section{scroll-margin-top:1rem;padding:2rem 0;border-bottom:2px solid var(--ink)}.section h3{font-size:1.55rem;line-height:1.1;margin:0 0 1.25rem;letter-spacing:-.025em}.answer{display:grid;grid-template-columns:minmax(140px,210px) 1fr;gap:1.5rem;padding:.75rem 0}.answer dt{font-size:.76rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.answer dd{margin:0;white-space:normal}.tags{display:flex;flex-wrap:wrap;gap:.45rem;list-style:none;margin:0;padding:0}.tags li{border:1px solid var(--ink);background:var(--paper);padding:.25rem .55rem;font-weight:700}.pattern{position:relative;padding:1.5rem;background:var(--soft);border-left:5px solid var(--ink);margin:1rem 0}.pattern:nth-of-type(even){border-left-color:var(--accent)}.pattern h4{font-size:1.25rem;margin:0 0 .75rem;letter-spacing:-.02em}.pattern .answer{grid-template-columns:minmax(130px,180px) 1fr}.raw{scroll-margin-top:1rem;margin-top:2rem;border-top:3px solid var(--ink);padding-top:1rem}.raw summary{cursor:pointer;font-weight:800}.raw-note{color:var(--muted);font-size:.88rem}.raw pre{overflow:auto;background:#111;color:#f6f6f6;padding:1rem;font-size:.78rem;line-height:1.5}.empty{padding:2rem 0;color:var(--muted)}
.login-page{min-height:100vh;display:grid;place-items:center;background:var(--soft);padding:1.5rem}.login-shell{width:min(100%,520px);background:#fff;border-top:7px solid #000;padding:clamp(2rem,6vw,4rem)}.login-mark{width:48px;height:48px;object-fit:contain;margin-bottom:2.5rem}.login-shell h1{font-size:clamp(2.6rem,9vw,4.5rem)}.login-form{display:grid;gap:.45rem;margin-top:2rem}.login-form label{font-weight:700;margin-top:.6rem}.login-form input{width:100%;min-height:48px;border:1px solid #777;border-radius:0;padding:.65rem;background:#fff}.login-form button{margin-top:1rem}.login-help{margin:.75rem 0}.notice{padding:.8rem 1rem;border-left:5px solid}.notice-error{background:#fff0ed;border-color:#ad2f1b}.privacy-note{font-size:.82rem;color:var(--muted);margin:2rem 0 0}.account-name{font-size:.82rem;color:#ddd}.client-code{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em}
@media(max-width:780px){.topbar-inner,.page-head,.detail-head{align-items:flex-start;flex-direction:column}.workspace{display:block}.submission-list{border-right:0;border-bottom:1px solid var(--ink);padding:0 0 1.5rem}.detail{padding-left:0;padding-top:1rem}.answer,.pattern .answer{grid-template-columns:1fr;gap:.3rem}.top-actions,.export-actions{justify-content:flex-start;flex-wrap:wrap}.signal-strip{grid-template-columns:1fr}.signal-strip div{border-right:0;border-bottom:1px solid var(--ink)}.signal-strip div:last-child{border-bottom:0}}
@media print{.topbar,.submission-list,.detail-head .button,.raw{display:none}.page{max-width:none;padding:0}.workspace{display:block;border:0}.detail{padding:0}.section{break-inside:avoid}}
CSS;
}

if (!isSecureRequest()) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'HTTPS is required.';
    exit;
}

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Results access is not configured.';
    exit;
}
$configuredUsername = (string)($accessConfig['username'] ?? '');
$configuredHash = (string)($accessConfig['password_hash'] ?? '');
$timezone = (string)($accessConfig['timezone'] ?? 'America/Denver');
if ($configuredUsername === '' || $configuredHash === '') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Results access is not configured.';
    exit;
}

oobStartDiscoverySession();

if (!empty($_SESSION['password_recovery'])) {
    redirectTo('/discovery/account/reset/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf()) renderLogin($accessConfig, 'Your session expired. Refresh the page and try again.', 400);
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'logout') {
        oobClearAuthSession();
        redirectTo('/discovery/results/');
    }
    if ($action === 'login') {
        if (loginRateLimited()) renderLogin($accessConfig, 'Too many sign-in attempts. Wait 15 minutes and try again.', 429);
        $username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        $legacyMatches = hash_equals($configuredUsername, $username) && password_verify($password, $configuredHash);
        $managedMatches = false;
        if (!$legacyMatches && oobManagedAuthEnabled($accessConfig)) {
            try {
                oobManagedLogin($accessConfig, $pdo, $username, $password);
                $managedMatches = true;
            } catch (Throwable $error) {
                $managedMatches = false;
            }
        }
        if (!$legacyMatches && !$managedMatches) {
            recordFailedLogin();
            renderLogin($accessConfig, 'The email/username or password was not recognized.', 401);
        }
        clearLoginRateLimit();
        if ($legacyMatches) {
            session_regenerate_id(true);
            $_SESSION['auth_mode'] = 'legacy';
            $_SESSION['authenticated'] = true;
            $_SESSION['authenticated_at'] = time();
            $_SESSION['username'] = $configuredUsername;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        redirectTo('/discovery/results/');
    }
}

$authenticatedAt = (int)($_SESSION['authenticated_at'] ?? 0);
if (empty($_SESSION['authenticated']) || $authenticatedAt === 0 || time() - $authenticatedAt > 28800) {
    $_SESSION = [];
    renderLogin($accessConfig);
}

try {
    $principal = oobCurrentPrincipal($accessConfig, $pdo);
    if (!$principal) {
        $_SESSION = [];
        renderLogin($accessConfig);
    }
    $clientIds = array_values(array_unique(array_map(static fn(array $access): string => (string)$access['client_id'], $principal['clients'])));
    if ($principal['system_admin']) {
        $scopeSql = "discovery_type = 'clinician' AND client_id <> 'deployment-check'";
        $scopeParameters = [];
    } else {
        if ($clientIds === []) throw new RuntimeException('No client access is assigned.');
        $scopeSql = "discovery_type = 'clinician' AND client_id IN (" . implode(',', array_fill(0, count($clientIds), '?')) . ')';
        $scopeParameters = $clientIds;
    }
    $listStatement = $pdo->prepare("SELECT id, submission_id, client_id, respondent_name, respondent_email, questionnaire_version, status, created_at FROM discovery_submissions WHERE {$scopeSql} ORDER BY created_at DESC, id DESC LIMIT 100");
    $listStatement->execute($scopeParameters);
    $submissions = $listStatement->fetchAll();

    $selectedId = filter_input(INPUT_GET, 'submission', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $downloadId = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $downloadFormat = (string)($_GET['format'] ?? 'source');
    $requestedId = $downloadId ?: $selectedId ?: (isset($submissions[0]['id']) ? (int)$submissions[0]['id'] : null);
    $selected = null;
    $payload = null;
    if ($requestedId) {
        $detailStatement = $pdo->prepare("SELECT * FROM discovery_submissions WHERE id = ? AND {$scopeSql} LIMIT 1");
        $detailStatement->execute(array_merge([$requestedId], $scopeParameters));
        $selected = $detailStatement->fetch() ?: null;
        if ($selected) {
            $payload = json_decode((string)$selected['payload_json'], true, 64, JSON_THROW_ON_ERROR);
        }
    }
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-results] ' . $error->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Results could not be loaded.';
    exit;
}

if ($downloadId) {
    if (!$selected || !is_array($payload)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Submission not found.';
        exit;
    }
    $filenameId = preg_replace('/[^a-zA-Z0-9_-]/', '-', (string)$selected['submission_id']);
    $downloadPayload = $downloadFormat === 'llm' ? llmExport($selected, $payload) : $payload;
    $filenamePrefix = $downloadFormat === 'llm' ? 'discovery-llm-' : 'discovery-source-';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filenamePrefix . $filenameId . '.json"');
    echo json_encode($downloadPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
$selectedNumericId = $selected ? (int)$selected['id'] : 0;
$clientLabel = is_array($payload) ? (string)($payload['client']['label'] ?? 'Varetto Recovery') : 'Varetto Recovery';
$services = is_array($payload) ? ($payload['services'] ?? []) : [];
$audiences = is_array($payload) ? ($payload['audiences'] ?? []) : [];
$patterns = is_array($payload) ? ($payload['patientPatterns'] ?? []) : [];
$narrative = is_array($payload) ? ($payload['narrative'] ?? []) : [];
$serviceCount = is_array($services['offered'] ?? null) ? count($services['offered']) : 0;
$audienceCount = is_array($audiences) ? count($audiences) : 0;
$patternCount = is_array($patterns) ? count($patterns) : 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Discovery results · oobCREATIVE</title>
  <style><?= dashboardCss() ?></style>
</head>
<body>
  <a class="skip-link" href="#results-detail">Skip to selected response</a>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand"><img class="brand-mark" src="https://skills.oobcreative.com/branding/Mark-black.svg" alt="">oobCREATIVE · Discovery</div>
      <div class="top-actions">
        <span class="account-name">Signed in as <?= escapeHtml((string)$principal['username']) ?></span>
        <?php if ($principal['system_admin']): ?><a class="button button-secondary" href="/discovery/results/invitations/">Manage access</a><?php endif; ?>
        <a class="button button-secondary" href="https://discovery.oobcreative.com/clinician/">Open questionnaire</a>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= escapeHtml(csrfToken()) ?>">
          <input type="hidden" name="action" value="logout">
          <button type="submit">Sign out</button>
        </form>
      </div>
    </div>
  </header>
  <main class="page">
    <div class="page-head">
      <div>
        <p class="eyebrow">Private stakeholder research</p>
        <h1>Discovery results</h1>
        <p class="lede">A human-readable evidence workspace with structured source exports for later synthesis. These answers are discovery evidence—not diagnoses, approved claims, or final website copy.</p>
      </div>
      <div><span class="count"><?= count($submissions) ?></span><span class="count-label">responses shown</span></div>
    </div>
    <div class="workspace">
      <aside class="submission-list" aria-label="Submitted responses">
        <h2>Responses</h2>
        <?php if ($submissions === []): ?>
          <p class="empty">No responses have been submitted yet.</p>
        <?php else: ?>
          <ul>
            <?php foreach ($submissions as $row): $rowId = (int)$row['id']; ?>
              <li><a class="submission-link" href="?submission=<?= $rowId ?>" <?= $rowId === $selectedNumericId ? 'aria-current="page"' : '' ?>><strong><?= escapeHtml(displayName($row)) ?></strong><span class="client-code"><?= escapeHtml((string)$row['client_id']) ?></span><span><?= escapeHtml(formatDate((string)$row['created_at'], $timezone)) ?></span><span><?= escapeHtml((string)$row['questionnaire_version']) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </aside>
      <article class="detail" id="results-detail">
        <?php if (!$selected || !is_array($payload)): ?>
          <p class="empty">Select a response to review its details.</p>
        <?php else: ?>
          <div class="detail-head">
            <div>
              <p class="eyebrow"><?= escapeHtml($clientLabel) ?></p>
              <h2><?= escapeHtml(displayName($selected)) ?></h2>
              <p class="detail-meta"><?= escapeHtml((string)($selected['respondent_email'] ?: 'No email provided')) ?> · <?= escapeHtml(formatDate((string)$selected['created_at'], $timezone)) ?></p>
            </div>
            <div class="export-actions" aria-label="Export this response">
              <a class="button" href="?download=<?= $selectedNumericId ?>&amp;format=llm">LLM-ready JSON</a>
              <a class="button button-secondary" href="?download=<?= $selectedNumericId ?>&amp;format=source">Source JSON</a>
            </div>
          </div>

          <div class="signal-strip" aria-label="Response summary">
            <div><strong><?= $serviceCount ?></strong><span>services</span></div>
            <div><strong><?= $audienceCount ?></strong><span>priority situations</span></div>
            <div><strong><?= $patternCount ?></strong><span>human patterns</span></div>
          </div>

          <nav class="section-nav" aria-label="Sections in this response">
            <a href="#service-definition">Services</a>
            <a href="#priority-situations">Situations</a>
            <a href="#human-patterns">Patterns</a>
            <a href="#language-direction">Language</a>
            <a href="#source-data">Source data</a>
          </nav>

          <section class="section" id="service-definition">
            <h3>Service definition</h3>
            <dl>
              <?php tagList('Services offered', $services['offered'] ?? []); ?>
              <?php tagList('People served', $services['recipients'] ?? []); ?>
              <?php textBlock('Boundaries and exclusions', $services['boundaries'] ?? ''); ?>
            </dl>
          </section>

          <section class="section" id="priority-situations">
            <h3>Priority situations</h3>
            <?php if (!is_array($audiences) || $audiences === []): ?><p class="empty">No priority situations selected.</p><?php endif; ?>
            <?php foreach ((array)$audiences as $audience): ?>
              <div class="pattern"><h4><?= escapeHtml((string)($audience['title'] ?? 'Priority audience')) ?></h4><p><?= nl2br(escapeHtml((string)($audience['situation'] ?? ''))) ?></p></div>
            <?php endforeach; ?>
          </section>

          <section class="section" id="human-patterns">
            <h3>Human patterns</h3>
            <?php if (!is_array($patterns) || $patterns === []): ?><p class="empty">No detailed audience patterns were added.</p><?php endif; ?>
            <?php foreach ((array)$patterns as $pattern): ?>
              <div class="pattern">
                <h4><?= escapeHtml((string)($pattern['title'] ?? 'Audience pattern')) ?></h4>
                <dl>
                  <?php textBlock('Situation', $pattern['situation'] ?? ''); ?>
                  <?php textBlock('Help-seeking moment', $pattern['helpSeekingMoment'] ?? ''); ?>
                  <?php textBlock('What others see', $pattern['outsideView'] ?? ''); ?>
                  <?php textBlock('Private experience', $pattern['privateExperience'] ?? ''); ?>
                  <?php textBlock('Repeated pattern', $pattern['repeatedPattern'] ?? ''); ?>
                  <?php textBlock('Temporary function', $pattern['temporaryFunction'] ?? ''); ?>
                  <?php textBlock('Desired change', $pattern['desiredChange'] ?? ''); ?>
                  <?php textBlock('Contact hesitation', $pattern['contactHesitation'] ?? ''); ?>
                  <?php textBlock('Service fit', $pattern['serviceFit'] ?? ''); ?>
                  <?php tagList('Associated conditions', $pattern['associatedConditions'] ?? []); ?>
                  <?php textBlock('Referral boundary', $pattern['referralBoundary'] ?? ''); ?>
                </dl>
              </div>
            <?php endforeach; ?>
          </section>

          <section class="section" id="language-direction">
            <h3>Website language direction</h3>
            <dl>
              <?php textBlock('Likely search language', $narrative['searchLanguage'] ?? ''); ?>
              <?php textBlock('What they need to recognize', $narrative['recognitionNeed'] ?? ''); ?>
              <?php textBlock('Language to avoid', $narrative['languageToAvoid'] ?? ''); ?>
              <?php textBlock('Honest promise', $narrative['honestPromise'] ?? ''); ?>
            </dl>
          </section>

          <details class="raw" id="source-data"><summary>View exact submitted JSON</summary><p class="raw-note">This is the unaltered questionnaire payload. Use the LLM-ready export when you want provenance and analysis guardrails included.</p><pre><?= escapeHtml(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
        <?php endif; ?>
      </article>
    </div>
  </main>
</body>
</html>
