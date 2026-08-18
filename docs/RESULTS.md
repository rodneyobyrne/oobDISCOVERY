# Protected discovery results

Discovery now has two private result experiences with different jobs.

## Respondent source review

The first stop after a successful questionnaire submission is the source-first review:

```text
https://api.oobcreative.com/discovery/response/?submission_id=<submission-id>
```

This page is intentionally conservative. It shows:

- a clear confirmation that the original response was preserved;
- a few direct early observations from that one response;
- the respondent's original answers in human-readable form;
- the exact stored JSON for inspection or download.

It does **not** present a single response as a finished persona, diagnosis, approved claim, marketing recommendation, or final website strategy.

See [`RESPONSE-EXPERIENCE.md`](RESPONSE-EXPERIENCE.md) for the governing functional spec.

## Internal/client results workspace

The broader results workspace remains available at:

```text
https://api.oobcreative.com/discovery/results/
```

Its purpose is evidence management and later synthesis. It provides:

- a private list of client-authorized responses;
- source response detail;
- exact source JSON downloads;
- LLM-ready JSON exports with provenance and analysis guardrails;
- invitation-based, client-scoped accounts;
- access management for system administrators;
- secure sessions, CSRF protection, rate limiting, and no-store/no-index response headers.

The browser never receives a database credential or a public list API. PHP reads the private database configuration on the server and renders only after authentication.

The deeper workspace should not be treated as the respondent's immediate post-submit experience. Early respondent review remains source-first; broader cross-response analysis comes later.

## Transitional shared access

The existing shared login is retained temporarily as a system-administrator fallback while invitation-based accounts are tested. Do not place the plaintext password in GitHub or send it in chat.

Repository Actions secrets:

```text
DISCOVERY_RESULTS_VIEWER
DISCOVERY_RESULTS_PASSWORD_HASH
```

The deploy workflow writes those values to `/home1/reaqfvmy/oob-discovery-results.php` with mode `600`. The file is outside the public web root and is never committed.

See [`AUTH.md`](AUTH.md) for the account flow, Google Workspace SMTP setup, deployment secrets, production test, and retirement sequence for the shared login.

## Source integrity

The questionnaire payload stored at submission time is the primary record.

`Source JSON` is that exact stored payload.

`LLM-ready JSON` wraps the source payload without changing it and may add:

- `export_schema`;
- intended analysis purpose;
- provenance;
- analysis guardrails;
- the untouched source payload.

Any later LLM analysis, research synthesis, persona development, or content recommendation must remain separate from the source response and be identifiable as interpretation.

## Privacy boundary

This remains professional discovery data. It is not a patient intake system and must not contain patient-identifying information or medical records. Downloads are unencrypted files and should be stored and shared carefully.
