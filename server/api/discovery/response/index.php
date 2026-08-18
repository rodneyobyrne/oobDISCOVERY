<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 3) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';

oobApplySecurityHeaders();
if (!oobIsSecureRequest()) {
    oobRenderAccountPage('Secure connection required', 'Submitted response', 'Open this page using HTTPS.', '', 400);
}
oobStartDiscoverySession();

function responseEscape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function responseRateFile(): string
{
    $directory = rtrim(sys_get_temp_dir(), '/') . '/oobdiscovery-response-login';
    if (!is_dir($directory)) @mkdir($directory, 0700, true);
    return $directory . '/' . hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown')) . '.json';
}

function responseRateLimited(): bool
{
    $state = json_decode((string)@file_get_contents(responseRateFile()), true);
    if (!is_array($state)) return false;
    return time() - (int)($state['started'] ?? 0) < 900 && (int)($state['attempts'] ?? 0) >= 8;
}

function responseRecordFailedLogin(): void
{
    $file = responseRateFile();
    $state = json_decode((string)@file_get_contents($file), true);
    $now = time();
    $started = is_array($state) ? (int)($state['started'] ?? 0) : 0;
    $attempts = is_array($state) ? (int)($state['attempts'] ?? 0) : 0;
    if ($started === 0 || $now - $started >= 900) { $started = $now; $attempts = 0; }
    @file_put_contents($file, json_encode(['started' => $started, 'attempts' => $attempts + 1]), LOCK_EX);
    @chmod($file, 0600);
}

function responseClearRateLimit(): void
{
    $file = responseRateFile();
    if (is_file($file)) @unlink($file);
}

function sourceText(string $label, $value): void
{
    $text = is_string($value) ? trim($value) : '';
    echo '<div class="source-row"><dt>' . responseEscape($label) . '</dt><dd>' . ($text === '' ? '<span class="empty-answer">No response</span>' : nl2br(responseEscape($text))) . '</dd></div>';
}

function sourceList(string $label, $values): void
{
    $items = is_array($values) ? array_values(array_filter(array_map(static fn($item) => is_string($item) ? trim($item) : '', $values))) : [];
    echo '<div class="source-row"><dt>' . responseEscape($label) . '</dt><dd>';
    if ($items === []) echo '<span class="empty-answer">None selected</span>';
    else echo '<ul class="plain-list"><li>' . implode('</li><li>', array_map('responseEscape', $items)) . '</li></ul>';
    echo '</dd></div>';
}

function responseTextBlocks(array $payload): array
{
    $blocks = [];
    $add = static function ($value, string $label) use (&$blocks): void {
        if (is_string($value) && trim($value) !== '') $blocks[] = ['label' => $label, 'text' => trim($value)];
    };
    $add($payload['services']['currentPopulation'] ?? '', 'Current population');
    $add($payload['services']['intendedPopulation'] ?? '', 'Intended population');
    $add($payload['services']['boundaries'] ?? '', 'Service limits');
    foreach ((array)($payload['patientPatterns'] ?? []) as $index => $pattern) {
        if (!is_array($pattern)) continue;
        $title = trim((string)($pattern['workingLabel'] ?? $pattern['title'] ?? '')) ?: 'Audience pattern ' . ($index + 1);
        foreach (['helpSeekingMoment','outsideView','privateExperience','repeatedPattern','temporaryFunction','desiredChange','contactHesitation','serviceFit','referralBoundary','audienceRole','stableContext','helpSeekingState','observedAndPrivate','functionAndDesiredChange','resistanceAndTrust','languageSignals','fitAndBoundary','distinction','centralTension','helpSeekingThreshold','functionAndCost','ambivalence','trustBridge','decisionSystem','healingDirectionAndFit'] as $key) {
            $add($pattern[$key] ?? '', $title);
        }
    }
    foreach ((array)($payload['narrative'] ?? []) as $value) $add($value, 'Website language');
    return $blocks;
}

function recurringLanguage(array $blocks): array
{
    $stop = array_flip(['about','after','again','also','because','being','could','does','doing','from','have','into','just','more','most','other','really','should','some','than','that','their','them','then','there','these','they','this','those','through','very','want','what','when','where','which','while','with','would','your','people','person','client','patient','therapy','therapist','varetto']);
    $blockTerms = [];
    foreach ($blocks as $i => $block) {
        $words = preg_split('/[^a-z0-9]+/i', strtolower((string)$block['text'])) ?: [];
        $seen = [];
        foreach ($words as $word) {
            if (strlen($word) < 5 || isset($stop[$word])) continue;
            $seen[$word] = true;
        }
        foreach (array_keys($seen) as $word) $blockTerms[$word][$i] = true;
    }
    $counts = [];
    foreach ($blockTerms as $word => $seen) if (count($seen) >= 2) $counts[$word] = count($seen);
    arsort($counts);
    return array_slice($counts, 0, 6, true);
}

function responseCss(): string
{
    return <<<'CSS'
:root{--ink:#111;--paper:#fff;--muted:#686868;--line:#cacaca;--soft:#f4f4f1;--accent:#d99f6c;color-scheme:light;font-family:Arial,Helvetica,sans-serif}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);line-height:1.62}a{color:inherit;text-underline-offset:.18em}.topbar{border-bottom:6px solid var(--ink);padding:1rem 1.5rem}.topbar-inner,.page{width:min(1040px,calc(100% - 2rem));margin:auto}.topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:1rem}.brand{font-weight:800}.account{font-size:.85rem;color:var(--muted)}.page{padding:clamp(2.5rem,6vw,5rem) 0 5rem}.eyebrow{margin:0 0 .6rem;font-size:.75rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{max-width:13ch;margin:.2rem 0 1rem;font-size:clamp(2.7rem,6vw,5.3rem);line-height:.94;letter-spacing:-.055em}h2{margin:0 0 1rem;font-size:clamp(1.7rem,3vw,2.6rem);line-height:1;letter-spacing:-.035em}h3{margin:0 0 .85rem;font-size:1.3rem}.lede{max-width:48rem;color:#333;font-size:1.08rem}.actions{display:flex;flex-wrap:wrap;gap:.6rem;margin:1.25rem 0 0}.button,button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:.7rem 1rem;border:1px solid var(--ink);background:var(--ink);color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.button.secondary{background:#fff;color:var(--ink)}.source-note{margin:2rem 0;padding:1.15rem 1.25rem;border:1px solid var(--ink);border-left:6px solid var(--ink);background:var(--soft)}.observations{margin:2rem 0;padding:clamp(1.25rem,3vw,2rem);border:1px solid var(--ink)}.observations ul{margin:.75rem 0 0;padding-left:1.2rem}.observations li+li{margin-top:.6rem}.source-section{padding:2rem 0;border-top:2px solid var(--ink)}.source-section:first-of-type{margin-top:1rem}.source-row{display:grid;grid-template-columns:minmax(150px,220px) 1fr;gap:1.5rem;padding:.75rem 0;border-top:1px solid var(--line)}.source-row dt{font-size:.76rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.source-row dd{margin:0}.plain-list{margin:0;padding-left:1.1rem}.empty-answer{color:var(--muted);font-style:italic}.situation{margin:1rem 0;padding:1.2rem;border:1px solid var(--line);background:#fff}.situation h3{margin-bottom:.4rem}.exact-source{margin-top:2rem;padding-top:1rem;border-top:3px solid var(--ink)}.exact-source summary{font-weight:800;cursor:pointer}.exact-source pre{overflow:auto;padding:1rem;background:#111;color:#f6f6f6;font-size:.78rem}.login-shell{width:min(560px,calc(100% - 2rem));margin:8vh auto;padding:2rem;border:1px solid var(--ink)}.form{display:grid;gap:.75rem}.form label{font-weight:700}.form input{width:100%;padding:.75rem;border:1px solid #999;font:inherit}.notice{padding:1rem;border:1px solid #7a1e1e;color:#7a1e1e}.meta{color:var(--muted);font-size:.88rem}@media(max-width:700px){.source-row{grid-template-columns:1fr;gap:.25rem}.topbar-inner{align-items:flex-start;flex-direction:column}}
CSS;
}

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
} catch (Throwable $error) {
    oobRenderAccountPage('Access unavailable', 'Submitted response', 'This response is temporarily unavailable.', '', 503);
}

$submissionId = trim((string)($_REQUEST['submission_id'] ?? ''));
if (preg_match('/^[A-Za-z0-9-]{10,80}$/', $submissionId) !== 1) {
    oobRenderAccountPage('Response link required', 'Submitted response', 'Open a response from your Discovery workspace.', '', 400);
}

$authenticatedAt = (int)($_SESSION['authenticated_at'] ?? 0);
if (!empty($_SESSION['authenticated']) && $authenticatedAt > 0 && time() - $authenticatedAt > 28800) {
    oobClearAuthSession();
    oobStartDiscoverySession();
}

$loginError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'login') {
    if (!oobValidCsrf()) {
        $loginError = 'Your session expired. Refresh and try again.';
    } elseif (responseRateLimited()) {
        $loginError = 'Too many sign-in attempts. Wait a few minutes and try again.';
    } else {
        try {
            oobAccountLogin($pdo, trim((string)($_POST['identifier'] ?? '')), (string)($_POST['password'] ?? ''));
            responseClearRateLimit();
            header('Location: /discovery/response/?submission_id=' . rawurlencode($submissionId), true, 303);
            exit;
        } catch (Throwable $error) {
            responseRecordFailedLogin();
            $loginError = 'The email address, username, or password was not recognized.';
        }
    }
}

$principal = oobCurrentPrincipal($accessConfig, $pdo);
if (!$principal) {
    http_response_code($loginError ? 401 : 200);
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Review submitted response · oobCREATIVE</title><style><?= responseCss() ?></style></head><body><main class="login-shell"><p class="eyebrow">Private response</p><h2>Sign in to review this response.</h2><p class="lede">Clients can open only responses owned by their own Discovery account. Full Admins can review all responses.</p><?php if ($loginError): ?><p class="notice" role="alert"><?= responseEscape($loginError) ?></p><?php endif; ?><form method="post" class="form"><input type="hidden" name="csrf" value="<?= responseEscape(oobCsrfToken()) ?>"><input type="hidden" name="action" value="login"><input type="hidden" name="submission_id" value="<?= responseEscape($submissionId) ?>"><label for="identifier">Email address or username</label><input id="identifier" name="identifier" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in and review response</button></form><p class="meta"><a href="/discovery/account/forgot/">Trouble signing in?</a></p></main></body></html>
<?php
    exit;
}

try {
    if ($principal['system_admin']) {
        $statement = $pdo->prepare("SELECT * FROM discovery_submissions WHERE submission_id = :submission_id AND discovery_type = 'clinician' LIMIT 1");
        $statement->execute([':submission_id' => $submissionId]);
    } else {
        $statement = $pdo->prepare("SELECT * FROM discovery_submissions WHERE submission_id = :submission_id AND discovery_type = 'clinician' AND owner_user_id = :owner_user_id LIMIT 1");
        $statement->execute([':submission_id' => $submissionId, ':owner_user_id' => (int)$principal['user_id']]);
    }
    $row = $statement->fetch() ?: null;
    if (!$row) throw new RuntimeException('Response not found.');
    $payload = json_decode((string)$row['payload_json'], true, 64, JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    oobRenderAccountPage('Response unavailable', 'Submitted response', 'This response is not available to your account.', '<div class="actions"><a class="button" href="/discovery/results/">Open results workspace</a></div>', 404);
}

if ((string)($_GET['download'] ?? '') === 'source') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="discovery-source-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $submissionId) . '.json"');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$services = is_array($payload['services'] ?? null) ? $payload['services'] : [];
$audiences = is_array($payload['audiences'] ?? null) ? $payload['audiences'] : [];
$patterns = is_array($payload['patientPatterns'] ?? null) ? $payload['patientPatterns'] : [];
$narrative = is_array($payload['narrative'] ?? null) ? $payload['narrative'] : [];
$questionnaireVersion = (string)($payload['questionnaireVersion'] ?? '');
$usesBaselineReviewV5 = str_ends_with($questionnaireVersion, '-v5');
$usesAudienceMap = str_ends_with($questionnaireVersion, '-v4') || $usesBaselineReviewV5;
$blocks = responseTextBlocks($payload);
$recurring = recurringLanguage($blocks);
$clientLabel = (string)($payload['client']['label'] ?? 'Discovery');
$name = trim((string)($payload['respondent']['name'] ?? '')) ?: 'Submitted response';
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Your submitted response · oobCREATIVE</title><style><?= responseCss() ?></style></head>
<body>
<header class="topbar"><div class="topbar-inner"><div class="brand">oobCREATIVE · Discovery</div><div class="account">Signed in as <?= responseEscape((string)$principal['username']) ?> · <?= $principal['system_admin'] ? 'Full Admin' : 'Client' ?></div></div></header>
<main class="page">
  <p class="eyebrow"><?= responseEscape($clientLabel) ?> · Submitted response</p>
  <h1>Here’s what you shared.</h1>
  <p class="lede">Your original answers are kept intact below. We have not turned this response into a persona, marketing recommendation, diagnosis, or final website direction.</p>
  <div class="actions"><a class="button" href="#source-response">Review source response</a><?php if (!$principal['system_admin']): ?><a class="button" href="https://discovery.oobcreative.com/clinician/?edit=<?= rawurlencode($submissionId) ?>">Edit response</a><?php endif; ?><a class="button secondary" href="?submission_id=<?= rawurlencode($submissionId) ?>&amp;download=source">Download exact source</a><a class="button secondary" href="/discovery/results/">Results workspace</a></div>

  <div class="source-note"><strong>Source-first rule:</strong> this page shows the response as submitted. The short observations below are deliberately limited to visible emphasis and recurring language inside this one response.</div>

  <section class="observations" aria-labelledby="observations-heading">
    <p class="eyebrow">Early observations</p>
    <h2 id="observations-heading">A few things visible in this response</h2>
    <ul>
      <?php if ($usesBaselineReviewV5): ?><li>You opened <strong><?= count($audiences) ?></strong> persona <?= count($audiences) === 1 ? 'worksheet' : 'worksheets' ?> from the original study or a new therapy-audience hypothesis.</li><?php else: ?><li>You selected <strong><?= count((array)($services['offered'] ?? [])) ?></strong> services and described <strong><?= count($audiences) ?></strong> <?= $usesAudienceMap ? 'candidate audience patterns' : 'priority situations' ?>.</li><?php endif; ?>
      <?php if ($audiences !== []): ?><li>Your <?= $usesAudienceMap ? 'working audience labels' : 'selected priority situations' ?> were: <strong><?= responseEscape(implode(', ', array_values(array_filter(array_map(static fn($item) => is_array($item) ? (string)($item['title'] ?? '') : '', $audiences))))) ?></strong>.</li><?php endif; ?>
      <?php if ($recurring !== []): ?><li>Words that recur across separate written answers include: <strong><?= responseEscape(implode(', ', array_keys($recurring))) ?></strong>. That is a language signal, not an interpretation of what those words mean.</li><?php else: ?><li>No strong recurring-word pattern appears across separate written answers yet. That is useful too; one response does not need to produce a conclusion.</li><?php endif; ?>
    </ul>
  </section>

  <div id="source-response">
    <section class="source-section"><p class="eyebrow">Source response</p><h2>Practice reality</h2><dl><?php sourceList('Services offered', $services['offered'] ?? []); sourceList('Care or recovery contexts', $services['recipients'] ?? []); if ($usesAudienceMap) { sourceText('Populations already represented', $services['currentPopulation'] ?? ''); sourceText('Populations Varetto wants to serve more', $services['intendedPopulation'] ?? ''); } sourceText('Clinical, practical, or access boundaries', $services['boundaries'] ?? ''); if ($usesAudienceMap) sourceText('Honest promise about the experience of care', $narrative['honestPromise'] ?? ''); ?></dl></section>

    <section class="source-section"><h2><?= $usesBaselineReviewV5 ? 'Persona worksheets' : ($usesAudienceMap ? 'Candidate audience patterns' : 'Priority situations you selected') ?></h2><?php if ($audiences === []): ?><p class="empty-answer">None added</p><?php endif; ?><?php foreach ($audiences as $audience): if (!is_array($audience)) continue; ?><div class="situation"><h3><?= responseEscape((string)($audience['title'] ?? 'Audience pattern')) ?></h3><p><?= nl2br(responseEscape((string)($audience['situation'] ?? ''))) ?></p></div><?php endforeach; ?></section>

    <section class="source-section"><h2>What you wrote about each pattern</h2><?php if ($patterns === []): ?><p class="empty-answer">No detailed audience response.</p><?php endif; ?><?php foreach ($patterns as $pattern): if (!is_array($pattern)) continue; ?><div class="situation"><h3><?= responseEscape((string)($pattern['workingLabel'] ?? $pattern['title'] ?? 'Audience pattern')) ?></h3><dl><?php if ($usesBaselineReviewV5) { sourceText('Starting persona from the original study', ($pattern['sourceArchetypeId'] ?? '') === 'new' ? 'New therapy audience' : ($pattern['sourceArchetypeTitle'] ?? '')); sourceText('Direction for the original persona', $pattern['reviewDecision'] ?? ''); sourceText('Current service relevance', $pattern['therapyScope'] ?? ''); sourceText('Relationship to the intended practice', $pattern['audienceBasis'] ?? ''); sourceText('Retained life stage and context', $pattern['profileLifeContext'] ?? ''); sourceText('Removed life stage and context', $pattern['profileLifeContextExcluded'] ?? ''); sourceText('Retained internal experience', $pattern['profileInternalExperience'] ?? ''); sourceText('Removed internal experience', $pattern['profileInternalExperienceExcluded'] ?? ''); sourceText('Retained questions and language', $pattern['profileQuestions'] ?? ''); sourceText('Removed questions and language', $pattern['profileQuestionsExcluded'] ?? ''); sourceText('Retained influences', $pattern['profileInfluencers'] ?? ''); sourceText('Removed influences', $pattern['profileInfluencersExcluded'] ?? ''); sourceText('Retained barriers', $pattern['profileBarriers'] ?? ''); sourceText('Removed barriers', $pattern['profileBarriersExcluded'] ?? ''); sourceList('Sources informing the description', $pattern['evidenceSources'] ?? []); sourceList('Clinical context', $pattern['clinicalContext'] ?? []); sourceText('Central organizing tension', $pattern['centralTension'] ?? ''); sourceText('Help-seeking threshold', $pattern['helpSeekingThreshold'] ?? ''); sourceText('Short-term function and longer-term cost', $pattern['functionAndCost'] ?? ''); sourceText('Observable and private experience', $pattern['observedAndPrivate'] ?? ''); sourceText('Ambivalence', $pattern['ambivalence'] ?? ''); sourceText('Bridge to trust and connection', $pattern['trustBridge'] ?? ''); sourceText('Recognition and decision system', $pattern['decisionSystem'] ?? ''); sourceText('Audience-specific language', $pattern['languageSignals'] ?? ''); sourceText('Healing direction and Varetto fit', $pattern['healingDirectionAndFit'] ?? ''); sourceText('What should not carry over', $pattern['distinction'] ?? ''); } elseif ($usesAudienceMap) { sourceText('Relationship to the intended practice', $pattern['audienceBasis'] ?? ''); sourceList('Sources informing the description', $pattern['evidenceSources'] ?? []); sourceText('Who the website is speaking to', $pattern['audienceRole'] ?? ''); sourceText('Relatively stable context', $pattern['stableContext'] ?? ''); sourceText('Help-seeking state or change', $pattern['helpSeekingState'] ?? ''); sourceText('Observable and private experience', $pattern['observedAndPrivate'] ?? ''); sourceText('Function and desired change', $pattern['functionAndDesiredChange'] ?? ''); sourceText('Resistance and trust', $pattern['resistanceAndTrust'] ?? ''); sourceText('Audience-specific language', $pattern['languageSignals'] ?? ''); sourceList('Clinical context', $pattern['clinicalContext'] ?? []); sourceText('Varetto fit and boundary', $pattern['fitAndBoundary'] ?? ''); sourceText('What should not carry over', $pattern['distinction'] ?? ''); } else { sourceText('What brings them to look for help', $pattern['helpSeekingMoment'] ?? ''); sourceText('What other people may see', $pattern['outsideView'] ?? ''); sourceText('What may be happening privately', $pattern['privateExperience'] ?? ''); sourceText('Repeated pattern', $pattern['repeatedPattern'] ?? ''); sourceText('What the behavior may provide temporarily', $pattern['temporaryFunction'] ?? ''); sourceText('What they want to change', $pattern['desiredChange'] ?? ''); sourceText('What may make them hesitate to contact someone', $pattern['contactHesitation'] ?? ''); sourceText('Why Varetto may or may not fit', $pattern['serviceFit'] ?? ''); sourceList('Conditions you associated', $pattern['associatedConditions'] ?? []); sourceText('When you would refer elsewhere', $pattern['referralBoundary'] ?? ''); } ?></dl></div><?php endforeach; ?></section>

    <?php if (!$usesAudienceMap): ?><section class="source-section"><h2>Website language answers</h2><dl><?php sourceText('What they might search or say', $narrative['searchLanguage'] ?? ''); sourceText('What they need to read to feel understood', $narrative['recognitionNeed'] ?? ''); sourceText('Language to avoid', $narrative['languageToAvoid'] ?? ''); sourceText('What Varetto can honestly promise about the experience', $narrative['honestPromise'] ?? ''); ?></dl></section><?php endif; ?>
  </div>

  <details class="exact-source"><summary>View exact submitted JSON</summary><p class="meta">This is the current stored payload for this submission.</p><pre><?= responseEscape(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre></details>
</main>
</body>
</html>
