# Invitation-based results accounts

The discovery results workspace uses the existing Bluehost PHP/MySQL environment for client-scoped accounts. A recipient receives a unique invitation link, then chooses their own email, username, and password. There is no default password and no external identity database.

Google Workspace SMTP delivers account-verification and password-reset links. PHPMailer provides the SMTP client. Passwords are stored only as PHP `PASSWORD_DEFAULT` hashes; verification, reset, and invitation secrets are stored only as SHA-256 hashes.

The existing shared results login remains available during rollout. Keep `DISCOVERY_RESULTS_VIEWER` and `DISCOVERY_RESULTS_PASSWORD_HASH` until the complete invitation, verification, sign-in, password-reset, and client-access flow has been tested in production. Retire the shared login in a later change after that verification.

## Access flow

1. A system administrator opens `/discovery/results/invitations/` from the results workspace.
2. The administrator chooses the client, access level, and expiration period. Seven days is the default.
3. The system displays the unique link once. The administrator copies the formatted message and sends it to the intended recipient.
4. The recipient creates an account with their own email, username, and password.
5. The system emails a 24-hour verification link through Google Workspace SMTP.
6. After verification, the user can sign in with either their email or username.
7. An existing account holder can use another invitation to add access to another client.
8. The results workspace filters every list, detail, and export query to the clients assigned to that account.

Invitations are single-use, revocable, client-scoped, and stored only as hashes. Creating or submitting a questionnaire never creates a results account and never grants results access.

Forgot-password requests send a one-hour reset link. A pending user receives a new verification link through the same account-help form. Passwords cannot be retrieved or emailed because plaintext passwords are never stored.

## Google Workspace setup

Use an existing licensed Google Workspace user as the SMTP-authenticated account. Optionally add `discovery@oobcreative.com` as an email alias on that user; an alias does not require another paid seat.

1. Confirm two-step verification is enabled on the licensed Google account.
2. Create an app password named `oobDISCOVERY Bluehost`.
3. If using an alias, confirm that the authenticated user is permitted to send as that alias.
4. Never commit or paste the app password into chat.

The deployment uses:

```text
Host: smtp.gmail.com
Port: 587
Encryption: STARTTLS
Authentication: LOGIN
```

Add these repository Actions secrets:

```text
DISCOVERY_SMTP_USERNAME       Primary licensed Google Workspace email
DISCOVERY_SMTP_PASSWORD       Dedicated 16-character Google app password
DISCOVERY_SMTP_FROM_EMAIL     discovery@oobcreative.com or the primary address
DISCOVERY_ACCOUNT_AUTH_ENABLED
```

Keep `DISCOVERY_ACCOUNT_AUTH_ENABLED` absent or set to `false` during initial deployment. After SMTP is configured, set it to `true` and manually run the `Deploy Bluehost API` workflow. Changing a GitHub secret alone does not trigger deployment.

## Database and deployment

The deployment workflow runs an idempotent migration and maintains:

- `discovery_users` for email, username, password hash, status, and system-administrator state;
- `discovery_user_clients` for per-client viewer/admin access;
- `discovery_invitations` for hashed invitation state;
- `discovery_account_tokens` for hashed verification and reset links.

It installs PHPMailer during the GitHub Actions build, deploys it outside the public web root, lints all deployed PHP, and verifies account-table read/write/rollback behavior. SMTP credentials are written to `/home1/reaqfvmy/oob-discovery-results.php` with mode `600`; they are never committed.

## Production activation test

1. Sign in with the existing shared administrator login.
2. Open **Manage access** and create a seven-day Varetto invitation.
3. Send the link to a secondary email address you control.
4. Create an account, receive the Google Workspace email, verify it, and confirm only Varetto results are visible.
5. Sign out and test both email and username sign-in.
6. Request a password reset and confirm the old password stops working.
7. Create another invitation and confirm the existing account can claim it.
8. Revoke an unused invitation and confirm it cannot be claimed.

Do not send a client invitation until this complete test passes. Keep the shared administrator login until a separate follow-up removes it.

## Security notes

- Never send invitation tokens, passwords, app passwords, or password hashes in chat or commit them to the repository.
- Send each invitation only to its intended recipient and revoke unused links when plans change.
- Email and username are both accepted at sign-in; email remains the recovery address.
- Passwords must contain 12–128 characters.
- Account-help responses do not disclose whether an email is registered.
- Sessions use secure, HTTP-only, SameSite cookies and expire from the results workspace after eight hours.
- The shared login is a temporary system-administrator fallback, not a client account.
