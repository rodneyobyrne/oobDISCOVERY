# Invitation-based results accounts

The discovery results workspace supports client-scoped accounts backed by Supabase Auth. A recipient receives a unique invitation link, then chooses their own email, username, and password. There is no default password.

The existing shared results login remains available during rollout. Keep `DISCOVERY_RESULTS_VIEWER` and `DISCOVERY_RESULTS_PASSWORD_HASH` until the complete invitation, verification, sign-in, password-reset, and client-access flow has been tested in production. Retire the shared login in a later change after that verification.

## Access flow

1. A system administrator opens `/discovery/results/invitations/` from the results workspace.
2. The administrator chooses the client, access level, and expiration period. Seven days is the default.
3. The system displays the unique link once. The administrator copies the formatted message and sends it to the intended recipient.
4. The recipient creates an account with their own email, username, and password, then verifies their email.
5. An existing account holder can use another invitation to add access to another client.
6. The results workspace filters every list, detail, and export query to the clients assigned to that account.

Invitations are single-use, revocable, client-scoped, and stored as SHA-256 hashes. Creating or submitting a questionnaire never creates a results account and never grants results access.

Forgot-password requests send a reset link. Passwords cannot be retrieved or emailed because the application never stores plaintext passwords.

## Supabase Auth setup

Create a Supabase project and configure Auth before enabling managed accounts. The application uses only the project URL and publishable/anonymous key from server-side PHP; it does not use a service-role key.

In Supabase Auth:

- enable email/password sign-in;
- require email confirmation;
- set the site URL to `https://api.oobcreative.com`;
- allow the redirect URL `https://api.oobcreative.com/discovery/account/confirm/`;
- configure a production SMTP provider before inviting real clients;
- use the custom confirmation and recovery links below in the corresponding email templates.

Confirmation template link:

```html
<a href="https://api.oobcreative.com/discovery/account/confirm/?token_hash={{ .TokenHash }}&type=signup">Confirm your discovery account</a>
```

Recovery template link:

```html
<a href="https://api.oobcreative.com/discovery/account/confirm/?token_hash={{ .TokenHash }}&type=recovery">Reset your discovery password</a>
```

Add these repository Actions secrets:

```text
DISCOVERY_SUPABASE_URL
DISCOVERY_SUPABASE_ANON_KEY
DISCOVERY_MANAGED_AUTH_ENABLED
```

Set `DISCOVERY_MANAGED_AUTH_ENABLED` to `true` only after the URL, key, redirect allow-list, templates, and SMTP configuration are complete. With the switch absent or false, the new account endpoints remain disabled and the shared login continues working.

## Database and deployment

The deployment workflow runs the idempotent database migration and creates:

- `discovery_users` for the local profile that maps to a Supabase Auth user;
- `discovery_user_clients` for per-client viewer/admin access;
- `discovery_invitations` for hashed invitation state.

It deploys shared authentication code outside the public web root with mode `600`, lints all deployed PHP, verifies database read/write/rollback behavior, and checks that cURL is available when managed authentication is enabled.

## Security and operating notes

- Never send invitation tokens, passwords, Supabase keys, or password hashes in chat or commit them to the repository.
- Send each invitation only to its intended recipient. Revoke unused links when plans change.
- Email and username are both accepted at sign-in; email remains the recovery address.
- Passwords must contain 12–128 characters.
- Sessions use secure, HTTP-only, SameSite cookies and expire from the results workspace after eight hours.
- The shared login is a temporary system-administrator fallback, not a client account.
