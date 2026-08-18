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
    $tokenHash = trim((string)($_GET['token_hash'] ?? ''));
    $type = trim((string)($_GET['type'] ?? ''));
    if ($tokenHash === '' || !in_array($type, ['signup', 'email', 'recovery'], true)) {
        throw new OobAuthException('This verification link is invalid.', 400, 'verification_invalid');
    }
    $response = oobSupabaseVerify($accessConfig, $tokenHash, $type);
    $authUser = oobExtractAuthUser($response);
    if (!$authUser) throw new OobAuthException('This verification link could not be completed.', 400, 'verification_incomplete');
    oobStoreManagedSession($response);
    if ($type === 'recovery') {
        $_SESSION['password_recovery'] = true;
        oobRedirect('/discovery/account/reset/');
    }
    oobActivateVerifiedUser($pdo, $authUser);
    oobRedirect('/discovery/results/');
} catch (OobAuthException $error) {
    $body = '<p class="notice notice-error" role="alert">' . oobEscape($error->getMessage()) . '</p><div class="actions"><a class="button" href="/discovery/results/">Return to sign in</a></div>';
    oobRenderAccountPage('Link unavailable', 'Account verification', 'The link may be invalid, expired, or already used.', $body, $error->status >= 400 && $error->status < 600 ? $error->status : 400);
} catch (Throwable $error) {
    error_log('[oobDISCOVERY-confirm] Verification failed.');
    oobRenderAccountPage('Verification unavailable', 'Account verification', 'We could not verify this link right now. Try again later.', '', 503);
}
