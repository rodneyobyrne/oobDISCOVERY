<?php
declare(strict_types=1);

final class OobAuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 500,
        public readonly string $codeName = 'auth_error'
    ) {
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
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
}

function oobLoadRuntimeConfig(): array
{
    $home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
    $databasePath = $home . '/oob-discovery-config.php';
    $accessPath = $home . '/oob-discovery-results.php';
    if (!is_file($databasePath) || !is_file($accessPath)) {
        throw new OobAuthException('Results access is not configured.', 503, 'configuration_missing');
    }
    $database = require $databasePath;
    $access = require $accessPath;
    if (!is_array($database) || !is_array($access)) {
        throw new OobAuthException('Results access is not configured.', 503, 'configuration_invalid');
    }
    return [$database, $access];
}

function oobStartDiscoverySession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    // A new cookie name avoids conflicts with the legacy results-only cookie path.
    session_name('oob_discovery_account');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/discovery/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function oobCsrfToken(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function oobValidCsrf(): bool
{
    $submitted = $_POST['csrf'] ?? '';
    return is_string($submitted) && hash_equals(oobCsrfToken(), $submitted);
}

function oobManagedAuthConfig(array $accessConfig): array
{
    $managed = $accessConfig['managed_auth'] ?? [];
    return is_array($managed) ? $managed : [];
}

function oobManagedAuthEnabled(array $accessConfig): bool
{
    $managed = oobManagedAuthConfig($accessConfig);
    return ($managed['enabled'] ?? false) === true
        && trim((string)($managed['supabase_url'] ?? '')) !== ''
        && trim((string)($managed['supabase_anon_key'] ?? '')) !== '';
}

function oobSiteUrl(array $accessConfig): string
{
    $managed = oobManagedAuthConfig($accessConfig);
    return rtrim((string)($managed['site_url'] ?? 'https://api.oobcreative.com'), '/');
}

function oobAuthRequest(
    array $accessConfig,
    string $method,
    string $path,
    ?array $body = null,
    ?string $accessToken = null,
    array $query = []
): array {
    if (!oobManagedAuthEnabled($accessConfig)) {
        throw new OobAuthException('Managed account access is not enabled.', 503, 'managed_auth_disabled');
    }
    if (!extension_loaded('curl')) {
        throw new OobAuthException('Managed account access is unavailable.', 503, 'curl_unavailable');
    }
    $managed = oobManagedAuthConfig($accessConfig);
    $base = rtrim((string)$managed['supabase_url'], '/');
    $key = (string)$managed['supabase_anon_key'];
    $url = $base . '/auth/v1/' . ltrim($path, '/');
    if ($query !== []) $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'apikey: ' . $key,
        'X-Client-Info: oob-discovery-php/1.0',
    ];
    if ($accessToken !== null && $accessToken !== '') $headers[] = 'Authorization: Bearer ' . $accessToken;

    $handle = curl_init($url);
    if ($handle === false) throw new OobAuthException('Managed account access is unavailable.', 503, 'curl_init_failed');
    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    $raw = curl_exec($handle);
    $curlError = curl_error($handle);
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $contentType = (string)curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);

    if ($raw === false || $curlError !== '') {
        error_log('[oobDISCOVERY-auth] Upstream connection failed.');
        throw new OobAuthException('Managed account access is temporarily unavailable.', 503, 'upstream_unavailable');
    }
    $decoded = [];
    if ($raw !== '' && str_contains(strtolower($contentType), 'json')) {
        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            $decoded = [];
        }
    }
    if (!is_array($decoded)) $decoded = [];
    if ($status < 200 || $status >= 300) {
        $code = (string)($decoded['error_code'] ?? $decoded['code'] ?? 'upstream_rejected');
        $safeMessage = match ($code) {
            'email_not_confirmed' => 'Confirm your email before signing in.',
            'over_request_rate_limit', 'over_email_send_rate_limit' => 'Too many requests. Wait a few minutes and try again.',
            'weak_password' => 'Choose a stronger password.',
            'user_already_exists', 'email_exists' => 'An account may already use this email. Sign in instead.',
            default => 'The account request could not be completed.',
        };
        throw new OobAuthException($safeMessage, $status ?: 400, $code);
    }
    return $decoded;
}

function oobSupabaseSignUp(array $accessConfig, string $email, string $password, string $username): array
{
    return oobAuthRequest(
        $accessConfig,
        'POST',
        'signup',
        ['email' => $email, 'password' => $password, 'data' => ['username' => $username]],
        null,
        ['redirect_to' => oobSiteUrl($accessConfig) . '/discovery/account/confirm/']
    );
}

function oobSupabaseSignIn(array $accessConfig, string $email, string $password): array
{
    return oobAuthRequest($accessConfig, 'POST', 'token', ['email' => $email, 'password' => $password], null, ['grant_type' => 'password']);
}

function oobSupabaseRefresh(array $accessConfig, string $refreshToken): array
{
    return oobAuthRequest($accessConfig, 'POST', 'token', ['refresh_token' => $refreshToken], null, ['grant_type' => 'refresh_token']);
}

function oobSupabaseRecover(array $accessConfig, string $email): void
{
    oobAuthRequest(
        $accessConfig,
        'POST',
        'recover',
        ['email' => $email],
        null,
        ['redirect_to' => oobSiteUrl($accessConfig) . '/discovery/account/confirm/']
    );
}

function oobSupabaseVerify(array $accessConfig, string $tokenHash, string $type): array
{
    return oobAuthRequest($accessConfig, 'POST', 'verify', ['token_hash' => $tokenHash, 'type' => $type]);
}

function oobSupabaseUpdatePassword(array $accessConfig, string $accessToken, string $password): array
{
    return oobAuthRequest($accessConfig, 'PUT', 'user', ['password' => $password], $accessToken);
}

function oobExtractAuthUser(array $response): ?array
{
    $user = $response['user'] ?? $response;
    return is_array($user) && trim((string)($user['id'] ?? '')) !== '' ? $user : null;
}

function oobStoreManagedSession(array $response): void
{
    $user = oobExtractAuthUser($response);
    $accessToken = (string)($response['access_token'] ?? '');
    $refreshToken = (string)($response['refresh_token'] ?? '');
    if (!$user || $accessToken === '' || $refreshToken === '') {
        throw new OobAuthException('The account session could not be established.', 400, 'session_missing');
    }
    $expiresAt = (int)($response['expires_at'] ?? 0);
    if ($expiresAt <= 0) $expiresAt = time() + max(60, (int)($response['expires_in'] ?? 3600));
    session_regenerate_id(true);
    $_SESSION['auth_mode'] = 'managed';
    $_SESSION['authenticated'] = true;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['managed_auth'] = [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_at' => $expiresAt,
        'user_id' => (string)$user['id'],
        'email' => strtolower(trim((string)($user['email'] ?? ''))),
    ];
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function oobRefreshManagedSessionIfNeeded(array $accessConfig): bool
{
    $session = $_SESSION['managed_auth'] ?? null;
    if (!is_array($session)) return false;
    if ((int)($session['expires_at'] ?? 0) > time() + 120) return true;
    $refreshToken = (string)($session['refresh_token'] ?? '');
    if ($refreshToken === '') return false;
    try {
        oobStoreManagedSession(oobSupabaseRefresh($accessConfig, $refreshToken));
        return true;
    } catch (Throwable $error) {
        return false;
    }
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

function oobValidUsername(string $username): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{2,31}$/', $username) === 1;
}

function oobValidClientId(string $clientId): bool
{
    return preg_match('/^[a-z0-9][a-z0-9-]{1,79}$/', $clientId) === 1;
}

function oobPasswordError(string $password): ?string
{
    $length = strlen($password);
    if ($length < 12) return 'Use at least 12 characters.';
    if ($length > 128) return 'Use no more than 128 characters.';
    return null;
}

function oobInvitationToken(): string
{
    return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

function oobInvitationTokenHash(string $token): string
{
    return hash('sha256', $token);
}

function oobInvitationByToken(PDO $pdo, string $token, bool $forUpdate = false): ?array
{
    if (preg_match('/^[A-Za-z0-9_-]{40,100}$/', $token) !== 1) return null;
    $sql = 'SELECT * FROM discovery_invitations WHERE token_hash = :token_hash LIMIT 1';
    if ($forUpdate) $sql .= ' FOR UPDATE';
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
    } catch (Throwable $error) {
        return 'invalid';
    }
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
    $statement->execute([
        ':token_hash' => oobInvitationTokenHash($token),
        ':client_id' => $clientId,
        ':client_label' => $clientLabel,
        ':role' => $role,
        ':expires_at' => $expiresAt,
        ':created_by' => $createdBy,
    ]);
    return ['id' => (int)$pdo->lastInsertId(), 'token' => $token, 'expires_at' => $expiresAt, 'client_id' => $clientId, 'client_label' => $clientLabel, 'role' => $role];
}

function oobRevokeInvitation(PDO $pdo, int $id): void
{
    $statement = $pdo->prepare('UPDATE discovery_invitations SET revoked_at = UTC_TIMESTAMP() WHERE id = :id AND claimed_at IS NULL AND revoked_at IS NULL');
    $statement->execute([':id' => $id]);
}

function oobResolveLoginEmail(PDO $pdo, string $identifier): ?string
{
    $identifier = trim($identifier);
    if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) return strtolower($identifier);
    $statement = $pdo->prepare("SELECT email FROM discovery_users WHERE username = :username AND status = 'active' LIMIT 1");
    $statement->execute([':username' => $identifier]);
    $value = $statement->fetchColumn();
    return is_string($value) && $value !== '' ? strtolower($value) : null;
}

function oobUserByAuthId(PDO $pdo, string $authUserId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM discovery_users WHERE auth_user_id = :auth_user_id LIMIT 1');
    $statement->execute([':auth_user_id' => $authUserId]);
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
    if (($_SESSION['auth_mode'] ?? '') !== 'managed' || !oobManagedAuthEnabled($accessConfig)) return null;
    if (!oobRefreshManagedSessionIfNeeded($accessConfig)) return null;
    $authUserId = (string)($_SESSION['managed_auth']['user_id'] ?? '');
    $user = $authUserId !== '' ? oobUserByAuthId($pdo, $authUserId) : null;
    if (!$user || (string)$user['status'] !== 'active') return null;
    $clients = oobClientAccessForUser($pdo, (int)$user['id']);
    if (!(bool)$user['is_system_admin'] && $clients === []) return null;
    return [
        'mode' => 'managed',
        'user_id' => (int)$user['id'],
        'auth_user_id' => $authUserId,
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'system_admin' => (bool)$user['is_system_admin'],
        'clients' => $clients,
    ];
}

function oobManagedLogin(array $accessConfig, PDO $pdo, string $identifier, string $password): array
{
    $email = oobResolveLoginEmail($pdo, $identifier);
    if ($email === null) throw new OobAuthException('The email/username or password was not recognized.', 401, 'invalid_credentials');
    $response = oobSupabaseSignIn($accessConfig, $email, $password);
    $user = oobExtractAuthUser($response);
    if (!$user) throw new OobAuthException('The email/username or password was not recognized.', 401, 'invalid_credentials');
    $local = oobUserByAuthId($pdo, (string)$user['id']);
    if (!$local || (string)$local['status'] !== 'active') throw new OobAuthException('This account does not have results access.', 403, 'access_not_granted');
    oobStoreManagedSession($response);
    return $local;
}

function oobUsernameOrEmailExists(PDO $pdo, string $username, string $email): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM discovery_users WHERE username = :username OR email = :email LIMIT 1');
    $statement->execute([':username' => $username, ':email' => strtolower($email)]);
    return (bool)$statement->fetchColumn();
}

function oobBindInvitation(PDO $pdo, string $plainToken, array $authUser, string $username, bool $verified): array
{
    $authUserId = trim((string)($authUser['id'] ?? ''));
    $email = strtolower(trim((string)($authUser['email'] ?? '')));
    if ($authUserId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !oobValidUsername($username)) {
        throw new OobAuthException('The account could not be connected to this invitation.', 400, 'claim_invalid');
    }
    $pdo->beginTransaction();
    try {
        $invitation = oobInvitationByToken($pdo, $plainToken, true);
        if (oobInvitationState($invitation) !== 'active') throw new OobAuthException('This invitation is no longer available.', 410, 'invitation_unavailable');

        $user = oobUserByAuthId($pdo, $authUserId);
        if (!$user) {
            $insert = $pdo->prepare('INSERT INTO discovery_users (auth_user_id, email, username, status, email_verified_at) VALUES (:auth_user_id, :email, :username, :status, :verified_at)');
            $insert->execute([
                ':auth_user_id' => $authUserId,
                ':email' => $email,
                ':username' => $username,
                ':status' => $verified ? 'active' : 'pending',
                ':verified_at' => $verified ? gmdate('Y-m-d H:i:s') : null,
            ]);
            $userId = (int)$pdo->lastInsertId();
        } else {
            $userId = (int)$user['id'];
            $update = $pdo->prepare("UPDATE discovery_users SET email = :email, username = :username, status = CASE WHEN :verified_status = 1 THEN 'active' ELSE status END, email_verified_at = CASE WHEN :verified_email = 1 THEN COALESCE(email_verified_at, UTC_TIMESTAMP()) ELSE email_verified_at END WHERE id = :id");
            $update->execute([':email' => $email, ':username' => $username, ':verified_status' => $verified ? 1 : 0, ':verified_email' => $verified ? 1 : 0, ':id' => $userId]);
        }

        $access = $pdo->prepare('INSERT INTO discovery_user_clients (user_id, client_id, role) VALUES (:user_id, :client_id, :role) ON DUPLICATE KEY UPDATE role = VALUES(role)');
        $access->execute([':user_id' => $userId, ':client_id' => (string)$invitation['client_id'], ':role' => (string)$invitation['role']]);

        $claim = $pdo->prepare('UPDATE discovery_invitations SET claimed_at = UTC_TIMESTAMP(), claimed_by_user_id = :user_id WHERE id = :id');
        $claim->execute([':user_id' => $userId, ':id' => (int)$invitation['id']]);
        $pdo->commit();
        return ['user_id' => $userId, 'client_id' => (string)$invitation['client_id'], 'client_label' => (string)$invitation['client_label']];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function oobActivateVerifiedUser(PDO $pdo, array $authUser): void
{
    $authUserId = trim((string)($authUser['id'] ?? ''));
    $email = strtolower(trim((string)($authUser['email'] ?? '')));
    if ($authUserId === '') return;
    $statement = $pdo->prepare("UPDATE discovery_users SET email = CASE WHEN :email_empty = '' THEN email ELSE :email_value END, status = 'active', email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE auth_user_id = :auth_user_id");
    $statement->execute([':email_empty' => $email, ':email_value' => $email, ':auth_user_id' => $authUserId]);
}
