<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'admin' => $root . '/server/api/discovery/results/invitations/index.php',
    'results' => $root . '/server/api/discovery/results/index.php',
    'response' => $root . '/server/api/discovery/response/index.php',
    'project' => $root . '/server/api/discovery/project/index.php',
    'deploy' => $root . '/.github/workflows/deploy-bluehost-api.yml',
];

$sources = [];
foreach ($files as $key => $path) {
    $source = file_get_contents($path);
    if ($source === false) {
        fwrite(STDERR, "Project access contract could not read {$key}.\n");
        exit(1);
    }
    $sources[$key] = $source;
}

$required = [
    ['admin', "action === 'delete_user'"],
    ['admin', "action === 'delete_project'"],
    ['admin', 'Use normal words'],
    ['admin', '/discovery/project/?project_id='],
    ['results', 's.client_id IN ({$placeholders})'],
    ['results', 'Project responses'],
    ['response', 'JOIN discovery_user_clients uc ON uc.client_id = s.client_id'],
    ['response', '$isOwner'],
    ['project', 'Project visibility:'],
    ['project', 'everyone assigned to this project can review all responses'],
    ['deploy', 'server/api/discovery/project/index.php'],
];

foreach ($required as [$file, $needle]) {
    if (!str_contains($sources[$file], $needle)) {
        fwrite(STDERR, "Project access contract missing in {$file}: {$needle}\n");
        exit(1);
    }
}

if (str_contains($sources['admin'], 'pattern="[a-z0-9][a-z0-9-]{1,79}" required placeholder="plumbing"')) {
    fwrite(STDERR, "Business type is still constrained to slug syntax.\n");
    exit(1);
}

fwrite(STDOUT, "Project dashboard, project visibility, and admin controls contract OK\n");
