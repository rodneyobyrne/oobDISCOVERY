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

## Internal/client response index

The protected response index remains available at:

```text
https://api.oobcreative.com/discovery/results/
```

At this phase its job is deliberately narrow:

- show the client-authorized response list;
- confirm who responded and when;
- open each submission in the source-first response review;
- provide account and access management for authorized administrators.

The index no longer tries to present a single questionnaire response as if synthesis has already happened.

The browser never receives a database credential or a public list API. PHP reads the private database configuration on the server and renders only after authentication.

## Later synthesis capability

The system can later support LLM-ready exports, cross-response comparison, clustering, persona development, and website-content synthesis. Those are downstream capabilities, not part of the immediate respondent review.

When an LLM-ready export is introduced or reintroduced, it must wrap the untouched source payload with provenance and analysis guardrails rather than alter the source record.

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

`Source JSON` is that exact stored payload. Any later LLM analysis, research synthesis, persona development, or content recommendation must remain separate from the source response and be identifiable as interpretation.

## Privacy boundary

This remains professional discovery data. It is not a patient intake system and must not contain patient-identifying information or medical records. Downloads are unencrypted files and should be stored and shared carefully.
