<?php
declare(strict_types=1);

$home = rtrim((string)(getenv('HOME') ?: '/home1/reaqfvmy'), '/');
$authLibrary = $home . '/oob-discovery-lib/discovery-auth.php';
if (!is_file($authLibrary)) {
    fwrite(STDERR, "Authentication library not found.\n");
    exit(2);
}
require_once $authLibrary;

try {
    [$databaseConfig, $accessConfig] = oobLoadRuntimeConfig();
    $pdo = oobDatabaseConnection($databaseConfig);
    $smtp = oobSmtpConfig($accessConfig);

    $primaryEmail = strtolower(trim((string)($smtp['username'] ?? '')));
    $aliasEmail = strtolower(trim((string)($smtp['from_email'] ?? '')));
    $aliasUsername = trim((string)($accessConfig['username'] ?? ''));
    $legacyHash = (string)($accessConfig['password_hash'] ?? '');
    $primaryUsername = strtolower((string)strtok($primaryEmail, '@'));

    if (!filter_var($primaryEmail, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The SMTP mailbox is not a valid Full Admin email.');
    }
    if (!filter_var($aliasEmail, FILTER_VALIDATE_EMAIL) || $aliasEmail === $primaryEmail) {
        throw new RuntimeException('DISCOVERY_SMTP_FROM_EMAIL must be a distinct Full Admin alias email.');
    }
    if (!oobValidUsername($primaryUsername) || !oobValidUsername($aliasUsername) || $primaryUsername === $aliasUsername) {
        throw new RuntimeException('The Full Admin usernames are invalid or not distinct.');
    }
    if ($legacyHash === '') {
        throw new RuntimeException('The configured results password hash is missing.');
    }

    $pdo->beginTransaction();
    try {
        // The previous bootstrap could combine rodney@... with username discovery.
        // If that record exists, preserve its password and account history but
        // rename it to the licensed mailbox username before creating discovery@.
        $primary = oobUserByEmail($pdo, $primaryEmail);
        if ($primary) {
            $primaryId = (int)$primary['id'];
            if ((string)$primary['username'] !== $primaryUsername) {
                $usernameOwner = oobUserByIdentifier($pdo, $primaryUsername);
                if ($usernameOwner && (int)$usernameOwner['id'] !== $primaryId) {
                    throw new RuntimeException('The primary Full Admin username is already assigned to another account.');
                }
                $rename = $pdo->prepare('UPDATE discovery_users SET username = :username WHERE id = :id');
                $rename->execute([':username' => $primaryUsername, ':id' => $primaryId]);
            }
            $promote = $pdo->prepare("UPDATE discovery_users SET password_hash = COALESCE(NULLIF(password_hash, ''), :password_hash), status = 'active', is_system_admin = 1, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id");
            $promote->execute([':password_hash' => $legacyHash, ':id' => $primaryId]);
        } else {
            $primaryByUsername = oobUserByIdentifier($pdo, $primaryUsername);
            if ($primaryByUsername) {
                throw new RuntimeException('The primary Full Admin username exists with a different email.');
            }
            $insertPrimary = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status, is_system_admin, email_verified_at) VALUES (:email, :username, :password_hash, 'active', 1, UTC_TIMESTAMP())");
            $insertPrimary->execute([':email' => $primaryEmail, ':username' => $primaryUsername, ':password_hash' => $legacyHash]);
        }

        $alias = oobUserByEmail($pdo, $aliasEmail);
        if ($alias) {
            $aliasId = (int)$alias['id'];
            if ((string)$alias['username'] !== $aliasUsername) {
                $usernameOwner = oobUserByIdentifier($pdo, $aliasUsername);
                if ($usernameOwner && (int)$usernameOwner['id'] !== $aliasId) {
                    throw new RuntimeException('The Discovery Full Admin username is already assigned to another account.');
                }
                $rename = $pdo->prepare('UPDATE discovery_users SET username = :username WHERE id = :id');
                $rename->execute([':username' => $aliasUsername, ':id' => $aliasId]);
            }
            $promote = $pdo->prepare("UPDATE discovery_users SET password_hash = COALESCE(NULLIF(password_hash, ''), :password_hash), status = 'active', is_system_admin = 1, email_verified_at = COALESCE(email_verified_at, UTC_TIMESTAMP()) WHERE id = :id");
            $promote->execute([':password_hash' => $legacyHash, ':id' => $aliasId]);
        } else {
            $aliasByUsername = oobUserByIdentifier($pdo, $aliasUsername);
            if ($aliasByUsername) {
                throw new RuntimeException('The Discovery Full Admin username exists with a different email.');
            }
            $insertAlias = $pdo->prepare("INSERT INTO discovery_users (email, username, password_hash, status, is_system_admin, email_verified_at) VALUES (:email, :username, :password_hash, 'active', 1, UTC_TIMESTAMP())");
            $insertAlias->execute([':email' => $aliasEmail, ':username' => $aliasUsername, ':password_hash' => $legacyHash]);
        }

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }

    $primary = oobUserByEmail($pdo, $primaryEmail);
    $alias = oobUserByEmail($pdo, $aliasEmail);
    foreach ([[$primary, $primaryUsername], [$alias, $aliasUsername]] as [$admin, $expectedUsername]) {
        if (!$admin || (string)$admin['username'] !== $expectedUsername || (string)$admin['status'] !== 'active' || !(bool)$admin['is_system_admin']) {
            throw new RuntimeException('A Full Admin account could not be verified.');
        }
    }

    fwrite(STDOUT, "Full Admin accounts OK: {$primaryUsername}, {$aliasUsername}\n");
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, "Results administrator bootstrap failed: " . $error->getMessage() . "\n");
    exit(1);
}
