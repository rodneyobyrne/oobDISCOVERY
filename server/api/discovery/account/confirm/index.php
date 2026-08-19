<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$localLibrary = dirname(__DIR__, 4) . '/lib';
$library = is_dir($localLibrary) ? $localLibrary : $home . '/oob-discovery-lib';
require_once $library . '/discovery-auth.php';
require_once $library . '/discovery-ui.php';
oobApplySecurityHeaders();
if (!oobIsSecureRequest()) oobRenderAccountPage('Secure connection required', 'Account verification', 'Open this link using HTTPS.', '', 400);
oobStartDiscoverySession();

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '') {
        throw new OobAuthException('This verification link is invalid.', 400, 'verification_invalid');
    }
    $user = oobVerifyAccount($pdo, $token);
    oobStoreAccountSession($user);
    oobRedirect('https://discovery.oobcreative.com/?verified=1');
} catch (OobAuthException $error) {
    $body = '<p class="notice notice-error" role="alert">' . oobEscape($error->getMessage()) . '</p><div class="actions"><a class="button" href="https://discovery.oobcreative.com/">Return to Discovery</a></div>';
    oobRenderAccountPage('Link unavailable', 'Account verification', 'The link may be invalid, expired, or already used.', $body, $error->status >= 400 && $error->status < 600 ? $error->status : 400);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-confirm] Verification failed.');
    oobRenderAccountPage('Verification unavailable', 'Account verification', 'We could not verify this link right now. Try again later.', '', 503);
}
