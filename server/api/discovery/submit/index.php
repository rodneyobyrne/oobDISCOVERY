<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$requestId = bin2hex(random_bytes(8));

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(int $status, string $message, string $requestId): void
{
    respond($status, [
        'ok' => false,
        'error' => $message,
        'requestId' => $requestId,
    ]);
}

function isAssocArray($value): bool
{
    if (!is_array($value)) return false;
    if ($value === []) return true;
    return array_keys($value) !== range(0, count($value) - 1);
}

function requireString(array $source, string $key, int $maxLength, array &$errors, bool $allowEmpty = false): string
{
    if (!array_key_exists($key, $source) || !is_string($source[$key])) {
        $errors[] = $key . ' must be a string.';
        return '';
    }
    $value = trim($source[$key]);
    if (!$allowEmpty && $value === '') $errors[] = $key . ' is required.';
    if (strlen($value) > $maxLength) $errors[] = $key . ' is too long.';
    return $value;
}

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$configPath = $home . '/oob-discovery-config.php';
if (!is_file($configPath)) {
    error_log('[oobDISCOVERY][' . $requestId . '] Private configuration missing.');
    fail(503, 'Service is not configured.', $requestId);
}

$config = require $configPath;
$allowedOrigins = $config['allowed_origins'] ?? [];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Max-Age: 600');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if ($origin === '' || !in_array($origin, $allowedOrigins, true)) fail(403, 'Origin not allowed.', $requestId);
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    fail(405, 'Method not allowed.', $requestId);
}
if ($origin === '' || !in_array($origin, $allowedOrigins, true)) fail(403, 'Origin not allowed.', $requestId);

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (!$isHttps) fail(400, 'HTTPS is required.', $requestId);

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
if (strpos($contentType, 'application/json') !== 0) fail(415, 'Content-Type must be application/json.', $requestId);

$maxBytes = (int)($config['max_payload_bytes'] ?? 262144);
$contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > $maxBytes) fail(413, 'Request body is too large.', $requestId);

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') fail(400, 'Request body is required.', $requestId);
if (strlen($raw) > $maxBytes) fail(413, 'Request body is too large.', $requestId);

try {
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fail(400, 'Invalid JSON.', $requestId);
}
if (!isAssocArray($payload)) fail(400, 'Submission must be a JSON object.', $requestId);

$errors = [];
$submissionId = requireString($payload, 'submissionId', 80, $errors);
$system = requireString($payload, 'system', 40, $errors);
$discoveryType = requireString($payload, 'discoveryType', 40, $errors);
$questionnaireVersion = requireString($payload, 'questionnaireVersion', 80, $errors);
if ($system !== 'oobDISCOVERY') $errors[] = 'system is invalid.';
if ($discoveryType !== 'clinician') $errors[] = 'discoveryType is invalid.';

$client = $payload['client'] ?? null;
if (!isAssocArray($client)) { $errors[] = 'client must be an object.'; $client = []; }
$clientId = requireString($client, 'id', 80, $errors);
requireString($client, 'label', 160, $errors);

$respondent = $payload['respondent'] ?? null;
if (!isAssocArray($respondent)) { $errors[] = 'respondent must be an object.'; $respondent = []; }
$respondentName = requireString($respondent, 'name', 160, $errors);
$respondentEmail = requireString($respondent, 'email', 254, $errors, true);
requireString($respondent, 'role', 160, $errors, true);
if ($respondentEmail !== '' && filter_var($respondentEmail, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'respondent.email is invalid.';

$timing = $payload['timing'] ?? null;
if (!isAssocArray($timing)) $errors[] = 'timing must be an object.';
else { requireString($timing, 'startedAt', 64, $errors); requireString($timing, 'generatedAt', 64, $errors); }

$archetypes = $payload['archetypes'] ?? null;
if (!is_array($archetypes)) $errors[] = 'archetypes must be an array.';
else {
    $relationshipAllowed = ['', 'can-serve', 'strong-fit', 'refer', 'unsure'];
    $caseloadAllowed = ['', 'more', 'neutral', 'less'];
    $priorityAllowed = ['', 'yes', 'no'];
    foreach ($archetypes as $i => $item) {
        if (!isAssocArray($item)) { $errors[] = 'archetypes[' . $i . '] must be an object.'; continue; }
        foreach (['id', 'title', 'situation', 'relationship', 'caseload', 'websitePriority', 'note'] as $key) {
            if (!array_key_exists($key, $item) || !is_string($item[$key])) $errors[] = 'archetypes[' . $i . '].' . $key . ' must be a string.';
        }
        if (isset($item['relationship']) && !in_array($item['relationship'], $relationshipAllowed, true)) $errors[] = 'archetypes[' . $i . '].relationship is invalid.';
        if (isset($item['caseload']) && !in_array($item['caseload'], $caseloadAllowed, true)) $errors[] = 'archetypes[' . $i . '].caseload is invalid.';
        if (isset($item['websitePriority']) && !in_array($item['websitePriority'], $priorityAllowed, true)) $errors[] = 'archetypes[' . $i . '].websitePriority is invalid.';
    }
}

$patientPatterns = $payload['patientPatterns'] ?? null;
if (!is_array($patientPatterns)) $errors[] = 'patientPatterns must be an array.';
else foreach ($patientPatterns as $i => $pattern) {
    if (!isAssocArray($pattern)) { $errors[] = 'patientPatterns[' . $i . '] must be an object.'; continue; }
    foreach ($pattern as $key => $value) if (!is_string($key) || !is_string($value)) { $errors[] = 'patientPatterns[' . $i . '] may contain string values only.'; break; }
}

$narrative = $payload['narrative'] ?? null;
if (!isAssocArray($narrative)) $errors[] = 'narrative must be an object.';
else foreach ($narrative as $key => $value) if (!is_string($key) || !is_string($value)) { $errors[] = 'narrative may contain string values only.'; break; }

$sourceIntegrity = $payload['sourceIntegrity'] ?? null;
if (!isAssocArray($sourceIntegrity)
    || ($sourceIntegrity['responseType'] ?? null) !== 'clinician-self-report'
    || ($sourceIntegrity['patientIdentifyingInformationRequested'] ?? null) !== false
    || ($sourceIntegrity['interpretationIncluded'] ?? null) !== false) {
    $errors[] = 'sourceIntegrity is invalid.';
}

if ($errors !== []) respond(422, ['ok' => false, 'error' => 'Submission validation failed.', 'details' => array_slice($errors, 0, 20), 'requestId' => $requestId]);

try {
    $db = $config['database'] ?? [];
    $pdo = new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $statement = $pdo->prepare('INSERT INTO discovery_submissions (submission_id, discovery_type, client_id, respondent_name, respondent_email, questionnaire_version, payload_json) VALUES (:submission_id, :discovery_type, :client_id, :respondent_name, :respondent_email, :questionnaire_version, :payload_json)');
    $statement->execute([
        ':submission_id' => $submissionId,
        ':discovery_type' => $discoveryType,
        ':client_id' => $clientId,
        ':respondent_name' => $respondentName,
        ':respondent_email' => $respondentEmail === '' ? null : $respondentEmail,
        ':questionnaire_version' => $questionnaireVersion,
        ':payload_json' => $canonicalPayload,
    ]);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') fail(409, 'This submission has already been received.', $requestId);
    error_log('[oobDISCOVERY][' . $requestId . '] Database error: ' . $e->getMessage());
    fail(500, 'Submission could not be stored.', $requestId);
} catch (Throwable $e) {
    error_log('[oobDISCOVERY][' . $requestId . '] Server error: ' . $e->getMessage());
    fail(500, 'Submission could not be stored.', $requestId);
}

respond(201, ['ok' => true, 'submissionId' => $submissionId, 'storedAt' => gmdate('c')]);
