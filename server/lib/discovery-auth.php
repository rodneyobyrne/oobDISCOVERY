<?php
declare(strict_types=1);

final class OobAuthException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 500, public readonly string $codeName = 'auth_error')
    {
        parent::__construct($message);
    }
}

function oobDatabaseConnection(array $config): PDO
{
    $db = $config['database'] ?? [];
    return new PDO(
        'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
        (string)($db['user'] ?? ''),
        (string)($db['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

function oobLoadRuntimeConfig(): array
{
    $home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
    $databasePath = $home . '/oob-discovery-config.php';
    $accessPath = $home . '/oob-discovery-results.php';
    if (!is_file($databasePath) || !is_file($accessPath)) throw new OobAuthException('Results access is not configured.', 503, 'configuration_missing');
    $database = require $databasePath;
    $access = require $accessPath;
    if (!is_array($database) || !is_array($access)) throw new OobAuthException('Results access is not configured.', 503, 'configuration_invalid');
    return [$database, $access];
}

function oobStartDiscoverySession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('oob_discovery_account');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/discovery/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function oobCsrfToken(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function oobValidCsrf(): bool
{
    $submitted = $_POST['csrf'] ?? '';
    return is_string($submitted) && hash_equals(oobCsrfToken(), $submitted);
}

function oobAccountAuthConfig(array $accessConfig): array
{
    $account = $accessConfig['account_auth'] ?? [];
    return is_array($account) ? $account : [];
}

function oobSmtpConfig(array $accessConfig): array
{
    $smtp = oobAccountAuthConfig($accessConfig)['smtp'] ?? [];
    return is_array($smtp) ? $smtp : [];
}

function oobAccountAuthEnabled(array $accessConfig): bool
{
    $account = oobAccountAuthConfig($accessConfig);
    $smtp = oobSmtpConfig($accessConfig);
    return ($account['enabled'] ?? false) === true
        && trim((string)($smtp['host'] ?? '')) !== ''
        && trim((string)($smtp['username'] ?? '')) !== ''
        && trim((string)($smtp['password'] ?? '')) !== ''
        && filter_var((string)($smtp['from_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
}

function oobSiteUrl(array $accessConfig): string
{
    return rtrim((string)(oobAccountAuthConfig($accessConfig)['site_url'] ?? 'https://api.oobcreative.com'), '/');
}

function oobMailerAutoloadPath(array $accessConfig): string
{
    $configured = trim((string)(oobAccountAuthConfig($accessConfig)['vendor_autoload'] ?? ''));
    foreach (array_filter([$configured, dirname(__DIR__) . '/vendor/autoload.php', dirname(__DIR__) . '/oob-discovery-vendor/autoload.php']) as $path) {
        if (is_file($path)) return $path;
    }
    throw new OobAuthException('Account email is temporarily unavailable.', 503, 'mailer_missing');
}

function oobSendAccountEmail(array $accessConfig, string $recipient, string $subject, string $html, string $text): void
{
    if (!oobAccountAuthEnabled($accessConfig)) throw new OobAuthException('Account email is not configured.', 503, 'account_auth_disabled');
    require_once oobMailerAutoloadPath($accessConfig);
    $smtp = oobSmtpConfig($accessConfig);
    try {
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = (string)$smtp['host'];
        $mailer->Port = (int)($smtp['port'] ?? 587);
        $mailer->SMTPAuth = true;
        $mailer->AuthType = 'LOGIN';
        $mailer->Username = (string)$smtp['username'];
        $mailer->Password = (string)$smtp['password'];
        $mailer->SMTPSecure = $mailer->Port === 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Timeout = 20;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom((string)$smtp['from_email'], (string)($smtp['from_name'] ?? 'oobCREATIVE Discovery'));
        $mailer->addAddress($recipient);
        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        $mailer->send();
    } catch (Throwable $error) {
        error_log('[oobDISCOVERY-mail] SMTP delivery failed.');
        throw new OobAuthException('Account email could not be sent. Try again in a few minutes.', 503, 'email_delivery_failed');
    }
}

function oobStoreAccountSession(array $user): void
{
    $id = (int)($user['id'] ?? 0);
    $email = strtolower(trim((string)($user['email'] ?? '')));
    if ($id < 1 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new OobAuthException('The account session could not be established.', 400, 'session_missing');
    session_regenerate_id(true);
    $_SESSION['auth_mode'] = 'account';
    $_SESSION['authenticated'] = true;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['account_user'] = ['id' => $id, 'email' => $email];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function oobClearAuthSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function oobValidUsername(string $username): bool { return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,31}$/', $username) === 1; }
function oobValidClientId(string $clientId): bool { return preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $clientId) === 1; }
function oobPasswordError(string $password): ?string
{
    if (strlen($password) < 12) return 'Use at least 12 characters.';
    if (strlen($password) > 128) return 'Use no more than 128 characters.';
    return null;
}
function oobRandomUrlToken(): string { return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='); }
function oobTokenHash(string $token): string { return hash('sha256', $token); }
function oobInvitationToken(): string { return oobRandomUrlToken(); }
function oobInvitationTokenHash(string $token): string { return oobTokenHash($token); }

function oobInvitationByToken(PDO $pdo, string $token, bool $forUpdate = false): ?array
{
    if (preg_match('/^[A-Za-z0-9_-]{40,100}$/', $token) !== 1) return null;
    $sql = 'SELECT * FROM discovery_invitations WHERE token_hash = :token_hash LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute([':token_hash' => oobInvitationTokenHash($token)]);
    return $statement->fetch() ?: null;
}

function oobInvitationState(?array $invitation): string
{
    if (!$invitation) return 'invalid';
    if (!empty($invitation['revoked_at'])) return 'revoked';
    if (!empty($invitation['claimed_at'])) return 'claimed';
    try {
        $utc = new DateTimeZone('UTC');
        if (new DateTimeImmutable((string)$invitation['expires_at'], $utc) <= new DateTimeImmutable('now', $utc)) return 'expired';
    } catch (Throwable $error) { return 'invalid'; }
    return 'active';
}

function oobCreateInvitation(PDO $pdo, string $clientId, string $clientLabel, string $role, int $days, string $createdBy): array
{
    if (!oobValidClientId($clientId)) throw new InvalidArgumentException('Enter a valid client ID.');
    $clientLabel = trim($clientLabel);
    if ($clientLabel === '' || strlen($clientLabel) > 160) throw new InvalidArgumentException('Enter a client name.');
    if (!in_array($role, ['viewer', 'admin'], true)) $role = 'viewer';
    $days = min(30, max(1, $days));
    $token = oobInvitationToken();
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
    $statement = $pdo->prepare('INSERT INTO discovery_invitations (token_hash, client_id, client_label, role, expires_at, created_by) VALUES (:token_hash, :client_id, :client_label, :role, :expires_at, :created_by)');
    $statement->execute([':token_hash' => oobInvitationTokenHash($token), ':client_id' => $clientId, ':client_label' => $clientLabel, ':role' => $role, ':expires_at' => $expiresAt, ':created_by' => $createdBy]);
    return ['id' => (int)$pdo->lastInsertId(), 'token' => $token, 'expires_at' => $expiresAt, 'client_id' => $clientId, 'client_label' => $clientLabel, 'role' => $role];
}

function oobRevokeInvitation(PDO $pdo, int $id): void
{
    $statement = $pdo->prepare('UPDATE discovery_invitations SET revoked_at = UTC_TIMESTAMP() WHERE id = :id AND claimed_at IS NULL AND revoked_at IS NULL');
    $statement->execute([':id' => $id]);
}

function oobUserById(PDO $pdo, int $userId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM discovery_users WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $userId]);
    return $statement->fetch() ?: null;
}

function oobUserByEmail(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT * FROM discovery_users WHERE email = :email LIMIT 1');
    $statement->execute([':email' => strtolower(trim($email))]);
    return $statement->fetch() ?: null;
}

function oobUserByIdentifier(PDO $pdo, string $identifier): ?array
{
    $identifier = trim($identifier);
    $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    $statement = $isEmail ? $pdo->prepare('SELECT * FROM discovery_users WHERE email = :identifier LIMIT 1') : $pdo->prepare('SELECT * FROM discovery_users WHERE username = :identifier LIMIT 1');
    $statement->execute([':identifier' => $isEmail ? strtolower($identifier) : $identifier]);
    return $statement->fetch() ?: null;
}

function oobClientAccessForUser(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT client_id, role FROM discovery_user_clients WHERE user_id = :user_id ORDER BY client_id');
    $statement->execute([':user_id' => $userId]);
    return $statement->fetchAll();
}

function oobCurrentPrincipal(array $accessConfig, PDO $pdo): ?array
{
    if (($_SESSION['auth_mode'] ?? '') === 'legacy' || (!isset($_SESSION['auth_mode']) && !empty($_SESSION['authenticated']))) {
        return ['mode' => 'legacy', 'username' => (string)($_SESSION['username'] ?? 'Viewer'), 'email' => '', 'system_admin' => true, 'clients' => []];
    }
    if (($_SESSION['auth_mode'] ?? '') !== 'account' || !oobAccountAuthEnabled($accessConfig)) return null;
    $user = oobUserById($pdo, (int)($_SESSION['account_user']['id'] ?? 0));
    if (!$user || (string)$user['status'] !== 'active') return null;
    $clients = oobClientAccessForUser($pdo, (int)$user['id']);
    if (!(bool)$user['is_system_admin'] && $clients === []) return null;
    return ['mode' => 'account', 'user_id' => (int)$user['id'], 'username' => (string)$user['username'], 'email' => (string)$user['email'], 'system_admin' => (bool)$user['is_system_admin'], 'clients' => $clients];
}

function oobAccountLogin(PDO $pdo, string $identifier, string $password): array
{
    $user = oobUserByIdentifier($pdo, $identifier);
    $hash = is_array($user) ? (string)($user['password_hash'] ?? '') : '';
    if (!$user || (string)$user['status'] !== 'active' || $hash === '' || !password_verify($password, $hash)) throw new OobAuthException('The email/username or password was not recognized.', 401, 'invalid_credentials');
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $statement = $pdo->prepare('UPDATE discovery_users SET password_hash = :password_hash WHERE id = :id');
        $statement->execute([':password_hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int)$user['id']]);
    }
    oobStoreAccountSession($user);
    return $user;
}

function oobUsernameOrEmailExists(PDO $pdo, string $username, string $email): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM discovery_users WHERE username = :username OR email = :email LIMIT 1');
    $statement->execute([':username' => $username, ':email' => strtolower($email)]);
    return (bool)$statement->fetchColumn();
}

function oobCreateAccountToken(PDO $pdo, int $userId, string $purpose, int $minutes): string
{
    if (!in_array($purpose, ['verify', 'reset'], true)) throw new InvalidArgumentException('Invalid account-token purpose.');
    $token = oobRandomUrlToken();
    $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+' . max(5, $minutes) . ' minutes')->format('Y-m-d H:i:s');
    $invalidate = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL');
    $invalidate->execute([':user_id' => $userId, ':purpose' => $purpose]);
    $insert = $pdo->prepare('INSERT INTO discovery_account_tokens (user_id, purpose, token_hash, expires_at) VALUES (:user_id, :purpose, :token_hash, :expires_at)');
    $insert->execute([':user_id' => $userId, ':purpose' => $purpose, ':token_hash' => oobTokenHash($token), ':expires_at' => $expiresAt]);
    return $token;
}

function oobAccountTokenByPlain(PDO $pdo, string $token, string $purpose, bool $forUpdate = false): ?array
{
    if (preg_match('/^[A-Za-z0-9_-]{40,100}$/', $token) !== 1 || !in_array($purpose, ['verify', 'reset'], true)) return null;
    $sql = 'SELECT t.*, u.email, u.username, u.status FROM discovery_account_tokens t JOIN discovery_users u ON u.id = t.user_id WHERE t.token_hash = :token_hash AND t.purpose = :purpose LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $statement = $pdo->prepare($sql);
    $statement->execute([':token_hash' => oobTokenHash($token), ':purpose' => $purpose]);
    return $statement->fetch() ?: null;
}

function oobAccountTokenState(?array $token): string
{
    if (!$token) return 'invalid';
    if (!empty($token['used_at'])) return 'used';
    try {
        $utc = new DateTimeZone('UTC');
        if (new DateTimeImmutable((string)$token['expires_at'], $utc) <= new DateTimeImmutable('now', $utc)) return 'expired';
    } catch (Throwable $error) { return 'invalid'; }
    return 'active';
}

function oobCreateInvitedUser(PDO $pdo, string $invitationToken, string $email, string $username, string $password): array
{
    $pdo->beginTransaction();
    try {
        $invitation = oobInvitationByToken($pdo, $invitationToken, true);
        if (oobInvitationState($invitation) !== 'active') throw new OobAuthException('This invitation is no longer available.', 410, 'invitation_unavailable');
        if (oobUsernameOrEmailExists($pdo, $username, $email)) throw new OobAuthException('That email or username is already registered. Use the existing-account form instead.', 409, 'account_exists');
        $insert = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status) VALUES (:email, :username, :password_hash, 'pending')");
        $insert->execute([':email' => strtolower($email), ':username' => $username, ':password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $userId = (int)$pdo->lastInsertId();
        $access = $pdo->prepare('INSERT INTO discovery_user_clients (user_id, client_id, role) VALUES (:user_id, :client_id, :role)');
        $access->execute([':user_id' => $userId, ':client_id' => (string)$invitation['client_id'], ':role' => (string)$invitation['role']]);
        $claim = $pdo->prepare('UPDATE discovery_invitations SET claimed_at = UTC_TIMESTAMP(), claimed_by_user_id = :user_id WHERE id = :id');
        $claim->execute([':user_id' => $userId, ':id' => (int)$invitation['id']]);
        $verificationToken = oobCreateAccountToken($pdo, $userId, 'verify', 1440);
        $pdo->commit();
        return ['user' => ['id' => $userId, 'email' => strtolower($email), 'username' => $username, 'status' => 'pending'], 'token' => $verificationToken];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function oobBindInvitationForUser(PDO $pdo, string $invitationToken, int $userId): array
{
    $pdo->beginTransaction();
    try {
        $invitation = oobInvitationByToken($pdo, $invitationToken, true);
        if (oobInvitationState($invitation) !== 'active') throw new OobAuthException('This invitation is no longer available.', 410, 'invitation_unavailable');
        $user = oobUserById($pdo, $userId);
        if (!$user || (string)$user['status'] !== 'active') throw new OobAuthException('This account is not active.', 403, 'account_inactive');
        $access = $pdo->prepare('INSERT INTO discovery_user_clients (user_id, client_id, role) VALUES (:user_id, :client_id, :role) ON DUPLICATE KEY UPDATE role = VALUES(role)');
        $access->execute([':user_id' => $userId, ':client_id' => (string)$invitation['client_id'], ':role' => (string)$invitation['role']]);
        $claim = $pdo->prepare('UPDATE discovery_invitations SET claimed_at = UTC_TIMESTAMP(), claimed_by_user_id = :user_id WHERE id = :id');
        $claim->execute([':user_id' => $userId, ':id' => (int)$invitation['id']]);
        $pdo->commit();
        return ['client_id' => (string)$invitation['client_id'], 'client_label' => (string)$invitation['client_label']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function oobVerifyAccount(PDO $pdo, string $plainToken): array
{
    $pdo->beginTransaction();
    try {
        $token = oobAccountTokenByPlain($pdo, $plainToken, 'verify', true);
        if (oobAccountTokenState($token) !== 'active') throw new OobAuthException('This verification link is invalid or expired.', 410, 'verification_unavailable');
        $userId = (int)$token['user_id'];
        $activate = $pdo->prepare("UPDATE discovery_users SET status = 'active', email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id");
        $activate->execute([':id' => $userId]);
        $used = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id');
        $used->execute([':id' => (int)$token['id']]);
        $pdo->commit();
        $user = oobUserById($pdo, $userId);
        if (!$user) throw new OobAuthException('The account could not be activated.', 500, 'activation_failed');
        return $user;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function oobResetPassword(PDO $pdo, string $plainToken, string $password): array
{
    $pdo->beginTransaction();
    try {
        $token = oobAccountTokenByPlain($pdo, $plainToken, 'reset', true);
        if (oobAccountTokenState($token) !== 'active' || (string)$token['status'] !== 'active') throw new OobAuthException('This reset link is invalid or expired.', 410, 'reset_unavailable');
        $userId = (int)$token['user_id'];
        $update = $pdo->prepare('UPDATE discovery_users SET password_hash = :password_hash WHERE id = :id');
        $update->execute([':password_hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => $userId]);
        $used = $pdo->prepare('UPDATE discovery_account_tokens SET used_at = UTC_TIMESTAMP() WHERE id = :id');
        $used->execute([':id' => (int)$token['id']]);
        $pdo->commit();
        $user = oobUserById($pdo, $userId);
        if (!$user) throw new OobAuthException('The password could not be updated.', 500, 'reset_failed');
        return $user;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function oobSendVerificationEmail(array $accessConfig, array $user, string $token): void
{
    $url = oobSiteUrl($accessConfig) . '/discovery/account/confirm/?token=' . rawurlencode($token);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<p>Confirm your oobCREATIVE Discovery account:</p><p><a href="' . $safeUrl . '">Verify email and open discovery results</a></p><p>This link expires in 24 hours. If you did not create this account, you can ignore this email.</p>';
    $text = "Confirm your oobCREATIVE Discovery account:\n\n{$url}\n\nThis link expires in 24 hours. If you did not create this account, you can ignore this email.";
    oobSendAccountEmail($accessConfig, (string)$user['email'], 'Confirm your Discovery account', $html, $text);
}

function oobSendPasswordResetEmail(array $accessConfig, array $user, string $token): void
{
    $url = oobSiteUrl($accessConfig) . '/discovery/account/reset/?token=' . rawurlencode($token);
    $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = '<p>Use this link to choose a new oobCREATIVE Discovery password:</p><p><a href="' . $safeUrl . '">Reset my password</a></p><p>This link expires in one hour. If you did not request it, you can ignore this email.</p>';
    $text = "Choose a new oobCREATIVE Discovery password:\n\n{$url}\n\nThis link expires in one hour. If you did not request it, you can ignore this email.";
    oobSendAccountEmail($accessConfig, (string)$user['email'], 'Reset your Discovery password', $html, $text);
}

function oobAccountHelpRequest(PDO $pdo, array $accessConfig, string $email): void
{
    $user = oobUserByEmail($pdo, $email);
    if (!$user || !in_array((string)$user['status'], ['pending', 'active'], true)) return;
    $purpose = (string)$user['status'] === 'pending' ? 'verify' : 'reset';
    $recent = $pdo->prepare('SELECT 1 FROM discovery_account_tokens WHERE user_id = :user_id AND purpose = :purpose AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 60 SECOND) LIMIT 1');
    $recent->execute([':user_id' => (int)$user['id'], ':purpose' => $purpose]);
    if ($recent->fetchColumn()) return;
    $token = oobCreateAccountToken($pdo, (int)$user['id'], $purpose, $purpose === 'verify' ? 1440 : 60);
    $purpose === 'verify' ? oobSendVerificationEmail($accessConfig, $user, $token) : oobSendPasswordResetEmail($accessConfig, $user, $token);
}
