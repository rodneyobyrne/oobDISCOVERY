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
    ['admin', "\$_SESSION['admin_flash']"],
    ['admin', "'created' => \$flashCreated"],
    ['admin', "oobRedirect('/discovery/results/invitations/')"],
    ['results', 's.client_id IN ({$placeholders})'],
    ['results', 'Project responses'],
    ['response', 'JOIN discovery_user_clients uc ON uc.client_id = s.client_id'],
    ['response', '$isOwner'],
    ['project', 'Project visibility:'],
    ['project', 'everyone assigned to this project can review all responses'],
    ['project', "action === 'add_member'"],
    ['project', "action === 'remove_member'"],
    ['project', 'Add to project'],
    ['project', 'Remove from project'],
    ['project', 'u.is_system_admin = 0'],
    ['project', 'Their account and submitted data were preserved'],
    ['project', "\$_SESSION['project_flash'][\$projectId]"],
    ['project', "oobRedirect('/discovery/project/?project_id=' . rawurlencode(\$projectId))"],
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

fwrite(STDOUT, "Project dashboard, project visibility, member management, redirect-after-post navigation, admin navigation, and admin controls contract OK\n");
