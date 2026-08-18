<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$submissionConfigPath = $home . '/oob-discovery-submit-config.php';
$resultsDbPath = $home . '/oob-discovery-results-db.php';

if (!is_file($submissionConfigPath)) {
    fwrite(STDERR, "Submission database configuration is missing.\n");
    exit(2);
}

$submissionConfig = require $submissionConfigPath;
$db = is_array($submissionConfig) ? ($submissionConfig['database'] ?? []) : [];
$databaseName = trim((string)($db['name'] ?? ''));
if ($databaseName === '') {
    fwrite(STDERR, "Submission database name is unavailable.\n");
    exit(2);
}

$cpanelUser = trim((string)get_current_user());
if ($cpanelUser === '') {
    fwrite(STDERR, "Could not determine the cPanel account username.\n");
    exit(2);
}

$uapi = null;
foreach (['/usr/local/cpanel/bin/uapi', '/usr/bin/uapi'] as $candidate) {
    if (is_file($candidate) && is_executable($candidate)) {
        $uapi = $candidate;
        break;
    }
}
if ($uapi === null) {
    fwrite(STDERR, "cPanel UAPI is not available on this hosting account.\n");
    exit(3);
}

function runUapi(string $binary, string $cpanelUser, string $module, string $function, array $parameters = []): array
{
    $command = [$binary, '--output=json', '--user=' . $cpanelUser, $module, $function];
    foreach ($parameters as $key => $value) {
        $command[] = $key . '=' . $value;
    }

    $pipes = [];
    $process = proc_open(
        $command,
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        null,
        ['bypass_shell' => true]
    );
    if (!is_resource($process)) {
        throw new RuntimeException("Could not start cPanel UAPI for {$module}.{$function}.");
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $decoded = json_decode((string)$stdout, true);
    $status = is_array($decoded) ? (int)($decoded['result']['status'] ?? 0) : 0;
    if ($exitCode !== 0 || $status !== 1) {
        $errors = is_array($decoded) ? ($decoded['result']['errors'] ?? null) : null;
        $reason = is_array($errors) ? implode('; ', array_map('strval', $errors)) : trim((string)$errors);
        if ($reason === '') $reason = trim((string)$stderr);
        if ($reason === '') $reason = 'cPanel rejected the request.';
        throw new RuntimeException("{$module}.{$function} failed: {$reason}");
    }
    return $decoded;
}

try {
    if (is_file($resultsDbPath)) {
        $credentials = require $resultsDbPath;
        if (!is_array($credentials)) throw new RuntimeException('Existing private results database credentials are invalid.');
        $databaseUser = trim((string)($credentials['user'] ?? ''));
        $databasePassword = (string)($credentials['password'] ?? '');
        if ($databaseUser === '' || $databasePassword === '') throw new RuntimeException('Existing private results database credentials are incomplete.');
    } else {
        $suffix = 'oobr' . substr(bin2hex(random_bytes(4)), 0, 8);
        $databasePassword = bin2hex(random_bytes(20)) . 'A!9';
        $prefix = str_starts_with($databaseName, $cpanelUser . '_') ? $cpanelUser . '_' : '';
        $databaseUser = $prefix . $suffix;

        runUapi($uapi, $cpanelUser, 'Mysql', 'create_user', [
            'name' => $suffix,
            'password' => $databasePassword,
        ]);

        $payload = [
            'user' => $databaseUser,
            'password' => $databasePassword,
            'database' => $databaseName,
            'created_at' => gmdate('c'),
        ];
        file_put_contents(
            $resultsDbPath,
            "<?php\ndeclare(strict_types=1);\nreturn " . var_export($payload, true) . ";\n",
            LOCK_EX
        );
        chmod($resultsDbPath, 0600);
    }

    runUapi($uapi, $cpanelUser, 'Mysql', 'set_privileges_on_database', [
        'user' => $databaseUser,
        'database' => $databaseName,
        'privileges' => 'SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,REFERENCES',
    ]);

    if (!is_file($resultsDbPath) || (fileperms($resultsDbPath) & 0777) !== 0600) {
        throw new RuntimeException('Private results database credential file permissions are invalid.');
    }

    fwrite(STDOUT, "Private results database role provisioned\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "Private results database provisioning failed: " . $error->getMessage() . "\n");
    exit(1);
}
