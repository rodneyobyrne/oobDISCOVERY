<?php
declare(strict_types=1);

$submissionConfigPath = __DIR__ . '/oob-discovery-submit-config.php';
if (!is_file($submissionConfigPath)) {
    throw new RuntimeException('Submission database configuration is missing.');
}

$config = require $submissionConfigPath;
if (!is_array($config)) {
    throw new RuntimeException('Submission database configuration is invalid.');
}

$accessPath = __DIR__ . '/oob-discovery-results.php';
$access = is_file($accessPath) ? require $accessPath : [];
if (!is_array($access)) $access = [];

$requestPath = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '');
$forceSubmission = getenv('OOB_DISCOVERY_DB_ROLE') === 'submission';
$publicSubmission = $requestPath !== '' && str_starts_with($requestPath, '/discovery/submit');
$useSubmission = $forceSubmission || $publicSubmission;

$resultsDatabase = $access['results_database'] ?? [];
$resultsReady = is_array($resultsDatabase)
    && trim((string)($resultsDatabase['user'] ?? '')) !== ''
    && trim((string)($resultsDatabase['password'] ?? '')) !== '';

if (!$useSubmission && $resultsReady) {
    $config['database']['user'] = (string)$resultsDatabase['user'];
    $config['database']['password'] = (string)$resultsDatabase['password'];
}

$config['runtime'] = array_merge(
    is_array($config['runtime'] ?? null) ? $config['runtime'] : [],
    [
        'results_database_ready' => $resultsReady,
        'database_role' => $useSubmission ? 'submission' : ($resultsReady ? 'results' : 'submission-fallback'),
    ]
);

return $config;
