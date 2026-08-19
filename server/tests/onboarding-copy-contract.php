<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
$ui = file_get_contents($root . '/server/lib/discovery-ui.php');
$index = file_get_contents($root . '/varetto/index.html');
$onboarding = file_get_contents($root . '/src/varetto-onboarding.js');
if ($ui === false || $index === false || $onboarding === false) {
    fwrite(STDERR, "Onboarding copy contract could not read source files.\n");
    exit(1);
}

$requiredUi = [
    'Customer perspective discovery',
    'Private invitation',
    'invitation-project-name',
    'Discovery is a way for the people closest to the work to help us better understand the people they serve.',
    'The more we understand about the questions, concerns, experiences, and challenges an audience brings',
    'Your perspective will be considered alongside other team perspectives',
    '.primary-account-card{order:1',
    '.invitation-assurance{order:2',
    '.existing-account{order:4',
    '.shell:has(.invitation-experience) .rule{height:2px',
];
foreach ($requiredUi as $needle) {
    if (!str_contains($ui, $needle)) {
        fwrite(STDERR, "Invitation education or compact hierarchy is missing: {$needle}\n");
        exit(1);
    }
}

$requiredIndex = [
    'Varetto Recovery · Private Discovery',
    'src/varetto-onboarding.js',
];
foreach ($requiredIndex as $needle) {
    if (!str_contains($index, $needle)) {
        fwrite(STDERR, "Questionnaire onboarding shell is missing: {$needle}\n");
        exit(1);
    }
}

$requiredOnboarding = [
    'Varetto Recovery · Audience Discovery',
    'Help us better understand the people Varetto serves.',
    'structured meet and greet with your audience',
    'Share what you actually notice.',
    'The goal is not to make the existing research look correct. The goal is to make our understanding more accurate.',
    'Describe patterns, not individual people.',
    'Unsure and unanswered questions are acceptable.',
];
foreach ($requiredOnboarding as $needle) {
    if (!str_contains($onboarding, $needle)) {
        fwrite(STDERR, "Questionnaire onboarding education is missing: {$needle}\n");
        exit(1);
    }
}

if (str_contains($onboarding, '—') || str_contains($index, '—') || str_contains($ui, '—')) {
    fwrite(STDERR, "New onboarding copy contains an em dash.\n");
    exit(1);
}

fwrite(STDOUT, "Purpose-first invitation and questionnaire onboarding copy contract OK\n");
