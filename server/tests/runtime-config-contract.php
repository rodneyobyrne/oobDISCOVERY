<?php
declare(strict_types=1);

$configDir = dirname(__DIR__) . '/config';
$submissionPath = $configDir . '/oob-discovery-submit-config.php';
$resultsPath = $configDir . '/oob-discovery-results.php';

function writePhpConfig(string $path, array $value): void
{
    file_put_contents($path, "<?php\ndeclare(strict_types=1);\nreturn " . var_export($value, true) . ";\n");
}

function expectValue($actual, $expected, string $message): void
{
    if ($actual !== $expected) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

try {
    writePhpConfig($submissionPath, [
        'database' => [
            'host' => 'localhost',
            'name' => 'discovery',
            'user' => 'submit_user',
            'password' => 'submit_password',
        ],
    ]);
    writePhpConfig($resultsPath, [
        'results_database' => [
            'user' => 'results_user',
            'password' => 'results_password',
        ],
    ]);

    putenv('OOB_DISCOVERY_DB_ROLE=submission');
    $_SERVER['REQUEST_URI'] = '';
    $config = require $configDir . '/runtime-config.php';
    expectValue($config['database']['user'] ?? null, 'submit_user', 'Forced submission role must retain the submission database user.');
    expectValue($config['runtime']['database_role'] ?? null, 'submission', 'Forced submission role must be reported as submission.');

    putenv('OOB_DISCOVERY_DB_ROLE');
    $_SERVER['REQUEST_URI'] = '/discovery/submit/';
    $config = require $configDir . '/runtime-config.php';
    expectValue($config['database']['user'] ?? null, 'submit_user', 'Public submission requests must retain the submission database user.');
    expectValue($config['runtime']['database_role'] ?? null, 'submission', 'Public submission requests must be reported as submission.');

    $_SERVER['REQUEST_URI'] = '/discovery/results/';
    $config = require $configDir . '/runtime-config.php';
    expectValue($config['database']['user'] ?? null, 'results_user', 'Private results requests must use the private results database user when configured.');
    expectValue($config['runtime']['database_role'] ?? null, 'results', 'Private results requests must be reported as results.');

    writePhpConfig($resultsPath, ['results_database' => ['user' => '', 'password' => '']]);
    $config = require $configDir . '/runtime-config.php';
    expectValue($config['database']['user'] ?? null, 'submit_user', 'Missing private results credentials must fall back without changing the submission credential.');
    expectValue($config['runtime']['database_role'] ?? null, 'submission-fallback', 'Missing private results credentials must report the fallback role.');

    fwrite(STDOUT, "Runtime database role contract OK\n");
} finally {
    @unlink($submissionPath);
    @unlink($resultsPath);
    putenv('OOB_DISCOVERY_DB_ROLE');
    unset($_SERVER['REQUEST_URI']);
}
