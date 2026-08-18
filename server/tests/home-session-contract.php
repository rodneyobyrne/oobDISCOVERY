<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/api/discovery/account/claim/index.php';
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "Homepage session endpoint source could not be read.\n");
    exit(1);
}

$required = [
    "mode'] ?? '') === 'session'",
    "\$action === 'logout'",
    "\$action !== 'login'",
    "'accountType' => (bool)\$principal['system_admin'] ? 'Full Admin' : 'Client'",
    'discovery_projects',
    'discovery_user_clients',
    'Access-Control-Allow-Credentials',
    'https://discovery.oobcreative.com',
];

foreach ($required as $needle) {
    if (!str_contains($source, $needle)) {
        fwrite(STDERR, "Homepage session contract missing: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Homepage session contract OK\n");
