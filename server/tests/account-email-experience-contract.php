<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$mail = file_get_contents($root . '/server/lib/discovery-account-mail.php');
$confirm = file_get_contents($root . '/server/api/discovery/account/confirm/index.php');
$home = file_get_contents($root . '/src/home-login.js');
if ($mail === false || $confirm === false || $home === false) {
    fwrite(STDERR, "Account email experience contract could not read source files.\n");
    exit(1);
}

$requiredMail = [
    'Activate your Discovery access',
    'Activate my Discovery access',
    'Action required',
    'Your email address must be verified before your project access is activated.',
    'review perspectives already shared by your team',
    '$mailer->Body = $html;',
    '$mailer->AltBody = $text;',
];
foreach ($requiredMail as $needle) {
    if (!str_contains($mail, $needle)) {
        fwrite(STDERR, "Account email experience is missing: {$needle}\n");
        exit(1);
    }
}

foreach (['Verify email and open discovery results', 'Confirm your oobCREATIVE Discovery account:'] as $needle) {
    if (str_contains($mail, $needle)) {
        fwrite(STDERR, "Account email still contains misleading verification language: {$needle}\n");
        exit(1);
    }
}

if (!str_contains($confirm, "oobRedirect('https://discovery.oobcreative.com/?verified=1')")) {
    fwrite(STDERR, "Verification does not return the user to the Discovery front door.\n");
    exit(1);
}

foreach (['Your email is verified. Your Discovery access is active.', 'Review team perspectives', 'Contribute my perspective'] as $needle) {
    if (!str_contains($home, $needle)) {
        fwrite(STDERR, "Verified Client landing is missing: {$needle}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Branded verification email and post-activation handoff contract OK\n");
