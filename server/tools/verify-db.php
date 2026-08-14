<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$configPath = $home . '/oob-discovery-config.php';
if (!is_file($configPath)) {
    fwrite(STDERR, "Private config file not found.\n");
    exit(2);
}

$config = require $configPath;
$db = $config['database'] ?? [];

try {
    $pdo = new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $probeId = 'deployment-check-' . bin2hex(random_bytes(10));
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare('INSERT INTO discovery_submissions (submission_id, discovery_type, client_id, respondent_name, respondent_email, questionnaire_version, payload_json) VALUES (:submission_id, :discovery_type, :client_id, :respondent_name, :respondent_email, :questionnaire_version, :payload_json)');
        $statement->execute([
            ':submission_id' => $probeId,
            ':discovery_type' => 'clinician',
            ':client_id' => 'deployment-check',
            ':respondent_name' => '',
            ':respondent_email' => null,
            ':questionnaire_version' => 'deployment-check-v3',
            ':payload_json' => '{}',
        ]);
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }

    fwrite(STDOUT, "Database storage contract OK\n");
    exit(0);
} catch (PDOException $e) {
    $mysqlCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
    $reason = match ($mysqlCode) {
        1045 => 'authentication rejected (check database username/password)',
        1049 => 'database name not found',
        1054 => 'submission table is missing a required column',
        1142 => 'database user cannot write to the submission table',
        1146 => 'submission table not found',
        2002 => 'database host/socket unavailable',
        default => 'submission storage contract rejected',
    };
    fwrite(STDERR, "Database verification failed: MySQL {$mysqlCode} - {$reason}.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, "Database verification failed before completion.\n");
    exit(1);
}
