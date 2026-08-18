<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/discovery-auth.php';

function assertContract(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

assertContract(oobValidUsername('rodney.oob'), 'Expected a valid username.');
assertContract(oobValidUsername('a_b-3'), 'Expected punctuation in a valid username.');
assertContract(!oobValidUsername('ab'), 'Expected a short username to fail.');
assertContract(!oobValidUsername('bad user'), 'Expected spaces to fail.');
assertContract(oobValidClientId('varetto-recovery'), 'Expected a valid client ID.');
assertContract(!oobValidClientId('Varetto'), 'Expected uppercase client IDs to fail.');
assertContract(oobPasswordError(str_repeat('x', 12)) === null, 'Expected a 12-character password to pass.');
assertContract(oobPasswordError('too-short') !== null, 'Expected a short password to fail.');

$token = oobInvitationToken();
assertContract(strlen($token) >= 40, 'Expected an adequately sized invitation token.');
assertContract(strlen(oobInvitationTokenHash($token)) === 64, 'Expected a SHA-256 invitation hash.');
assertContract(oobInvitationState(null) === 'invalid', 'Expected a missing invitation to be invalid.');
assertContract(oobInvitationState(['revoked_at' => '2026-01-01', 'claimed_at' => null, 'expires_at' => '2099-01-01 00:00:00']) === 'revoked', 'Expected a revoked invitation.');
assertContract(oobInvitationState(['revoked_at' => null, 'claimed_at' => '2026-01-01', 'expires_at' => '2099-01-01 00:00:00']) === 'claimed', 'Expected a claimed invitation.');
assertContract(oobInvitationState(['revoked_at' => null, 'claimed_at' => null, 'expires_at' => '2000-01-01 00:00:00']) === 'expired', 'Expected an expired invitation.');
assertContract(oobInvitationState(['revoked_at' => null, 'claimed_at' => null, 'expires_at' => '2099-01-01 00:00:00']) === 'active', 'Expected an active invitation.');
assertContract(!oobManagedAuthEnabled(['managed_auth' => ['enabled' => false]]), 'Expected managed auth to default off.');
assertContract(oobManagedAuthEnabled(['managed_auth' => ['enabled' => true, 'supabase_url' => 'https://example.supabase.co', 'supabase_anon_key' => 'key']]), 'Expected complete managed auth config to be enabled.');

fwrite(STDOUT, "Authentication contracts OK\n");
