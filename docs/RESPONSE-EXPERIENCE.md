# Discovery response experience

This document defines the first-phase user experience after a clinician submits an oobDISCOVERY questionnaire.

## Governing rule

The original questionnaire response is the primary record.

A submission must remain available exactly as received. Any observation, clustering, LLM analysis, persona development, marketing direction, or website recommendation is secondary material and must never replace or silently rewrite the source response.

At this phase, a single response should not be presented as if the system has already completed persona development or strategic synthesis.

## 1. Account recovery

The invited-account flow must include a usable recovery path.

- Active accounts receive a one-hour password-reset link.
- Pending accounts receive a new verification link instead of a reset link.
- The browser response does not disclose whether an account exists.
- A failed email delivery must not leave a fresh token that blocks an immediate retry.
- The user is told to retry if the message does not arrive after a short wait.
- Passwords are never retrieved or emailed.

Production account-email failures should be logged server-side without exposing email addresses, tokens, or credentials in public responses.

## 2. Submission confirmation

A successful form submission should feel like completion, not merely display a technical status string.

The confirmation area should:

- clearly acknowledge that the response was received;
- distinguish a newly stored response from an idempotent duplicate without implying an error;
- reinforce that the original response was preserved;
- provide a primary action to review the submitted response;
- provide a secondary action to download a copy;
- move focus and viewport to the confirmation panel so the user does not have to discover it below the form.

The post-submit call to action should point to the source-first response review, not directly into an advanced synthesis workspace.

## 3. Source-first response review

The respondent-facing review is:

```text
https://api.oobcreative.com/discovery/response/?submission_id=<submission-id>
```

The page requires an authorized Discovery account and applies the same client-access boundary as the private results system.

The review page should show:

1. a clear statement that the response has not been converted into a persona, diagnosis, marketing recommendation, or final website direction;
2. a small **Early observations** section limited to visible facts inside the submission;
3. the respondent's source answers in human-readable form, using the original answer text without rewriting it;
4. the exact stored JSON as an inspectable/downloadable source record.

### Allowed early observations in phase one

Early observations may include:

- number of services selected;
- number and names of priority situations selected;
- recurring words that appear across separate written answers;
- other similarly direct patterns that can be traced to the response itself.

They should be labeled as observations or signals, not conclusions.

### Not appropriate in phase one

Do not automatically present a single submission as:

- a finished audience persona;
- a clinical interpretation;
- a diagnosis;
- an approved marketing claim;
- a recommended treatment position;
- a final website architecture or messaging strategy.

Those may be developed later through cross-response comparison, research, human judgment, and explicit approval.

## Admin workspace versus respondent review

The existing protected results workspace remains an internal/client-authorized evidence workspace. It may support response lists, access management, source exports, and later synthesis tools.

The respondent-facing review is intentionally simpler. A person who has just completed the questionnaire should first be able to answer two questions:

- **Did you capture what I said?**
- **What, if anything, is already visible in what I said?**

Everything more interpretive comes later.
