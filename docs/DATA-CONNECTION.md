# Production Data Connection — Bluehost Handoff

## Current state

The production frontend, API, and private MySQL connection are configured. GitHub Pages serves the form at `https://discovery.oobcreative.com/clinician/`; Bluehost serves the submission endpoint.

## Recommended topology

```text
https://discovery.oobcreative.com/clinician/
        GitHub Pages frontend
                 |
                 | HTTPS POST application/json
                 v
https://api.oobcreative.com/discovery/submit
        Bluehost-hosted endpoint
                 |
                 v
        private MySQL database
```

Using `api.oobcreative.com` is a recommendation, not a requirement. A same-domain Bluehost endpoint can also work if routing and certificates are appropriate.

## API contract

### Request

- Method: `POST`
- Content-Type: `application/json`
- Body: must conform to `schema/submission.schema.json`

### Success

Use `201 Created` or `200 OK` and return JSON such as:

```json
{
  "ok": true,
  "submissionId": "the-client-supplied-id",
  "storedAt": "2026-08-13T00:00:00Z"
}
```

### Failure

Return a non-2xx status. Do not return database credentials, SQL errors, filesystem paths, or stack traces to the browser.

## Minimum server-side requirements

1. Require HTTPS.
2. Accept POST only (plus OPTIONS for CORS preflight if needed).
3. Validate `Origin`; production should allow `https://discovery.oobcreative.com`, not `*`.
4. Enforce JSON content type and a reasonable payload-size ceiling.
5. Parse and validate required fields server-side. Browser validation is not security.
6. Make insertion idempotent. A retry with an already stored `submissionId` returns success without creating another row.
7. Store timestamps server-side as well as retaining client timestamps.
8. Use prepared SQL statements.
9. Keep database credentials in Bluehost server configuration, never in GitHub or frontend JavaScript.
10. Rate-limit the public endpoint. The current implementation uses a server-side IP hash and a configurable request window.
11. Log operational errors without logging more submission content than necessary.
12. Back up the database.

## Suggested database model

```sql
CREATE TABLE discovery_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id VARCHAR(80) NOT NULL UNIQUE,
  discovery_type VARCHAR(40) NOT NULL,
  client_id VARCHAR(80) NOT NULL,
  respondent_name VARCHAR(160) NOT NULL,
  respondent_email VARCHAR(254) NULL,
  questionnaire_version VARCHAR(80) NOT NULL,
  payload_json JSON NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'received',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

If the installed MySQL/MariaDB version does not support native JSON as expected, use `LONGTEXT` and validate JSON in the application layer.

## Preserve source and analysis separately

Do not add generated personas or LLM interpretation into `payload_json` after receipt.

A later analysis table can reference `submission_id`, for example:

```text
discovery_analysis
  submission_id
  analysis_version
  model_or_process
  generated_at
  analysis_json
  human_review_status
```

This keeps the original stakeholder response immutable and distinguishable from interpretation.

## Privacy boundary

The form asks respondents for generalized patterns and explicitly says not to provide identifying patient information. Keep that boundary.

If the product later intentionally collects identifiable patient/client health information, stop and redesign the hosting, privacy, permissions, contracts, retention, and compliance model rather than extending this endpoint casually.

## Frontend activation

The configured frontend value is:

```js
submissionEndpoint: "https://api.oobcreative.com/discovery/submit"
```

in `src/system-config.js`. Re-test CORS and database receipt after API or hosting changes.
