<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$claim = file_get_contents($root . '/server/api/discovery/account/claim/index.php');
$ui = file_get_contents($root . '/server/lib/discovery-ui.php');
if ($claim === false || $ui === false) {
    fwrite(STDERR, "Invitation experience contract could not read source files.\n");
    exit(1);
}

$requiredClaim = [
    'Private Discovery invitation',
    'You’re invited to ',
    'Create your secure access',
    'Create my Discovery account',
    'single-use invitation',
    'Shared project visibility',
    'Only the account that submitted a response can edit that response.',
    'Already have a Discovery account? Sign in to connect this project.',
    '$invitationUsernameForEmail',
    'oobUserByEmail($pdo, $email)',
    'That email already has a Discovery account.',
];
foreach ($requiredClaim as $needle) {
    if (!str_contains($claim, $needle)) {
        fwrite(STDERR, "Invitation experience contract missing claim behavior: {$needle}\n");
        exit(1);
    }
}

if (str_contains($claim, 'id="username"') || str_contains($claim, 'name="username"')) {
    fwrite(STDERR, "First-time invitation still asks the user to invent a username.\n");
    exit(1);
}

$requiredUi = [
    '.shell:has(.invitation-experience)',
    '.invitation-assurance',
    '.primary-account-card',
    '.existing-account',
    '.invitation-existing-link',
];
foreach ($requiredUi as $needle) {
    if (!str_contains($ui, $needle)) {
        fwrite(STDERR, "Invitation experience contract missing UI behavior: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Invitation trust, hierarchy, and first-run account experience contract OK\n");
