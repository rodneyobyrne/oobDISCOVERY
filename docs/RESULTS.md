# Protected discovery results

The results workspace is a private, server-rendered interface at:

```text
https://api.oobcreative.com/discovery/results/
```

It provides:

- a high-contrast oobCREATIVE response list and detail view;
- service, audience, pattern, and website-language sections;
- an exact source JSON download;
- an LLM-ready JSON export with submission provenance and analysis guardrails;
- password authentication, secure sessions, CSRF protection, rate limiting, and no-store/no-index response headers.

The browser never receives a database credential or a public list API. PHP reads the private database configuration on the server and renders only after authentication.

## One-time access setup

Generate a password hash locally. Do not place the plaintext password in GitHub or send it in chat.

```bash
php -r "echo password_hash('choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Add these repository Actions secrets:

```text
DISCOVERY_RESULTS_VIEWER
DISCOVERY_RESULTS_PASSWORD_HASH
```

The deploy workflow writes those values to `/home1/reaqfvmy/oob-discovery-results.php` with mode `600`. The file is outside the public web root and is never committed.

## Export contract

`Source JSON` is the exact questionnaire payload stored at submission time.

`LLM-ready JSON` wraps that source payload without changing it and adds:

- `export_schema`: stable export contract identifier;
- `purpose`: intended analysis use;
- `provenance`: client, response, respondent, questionnaire version, and submission time;
- `analysis_guardrails`: instructions that separate stakeholder evidence from diagnosis, verified fact, approved copy, and treatment claims;
- `source_payload`: the untouched questionnaire response.

This structure supports downstream synthesis while keeping source material traceable.

## Privacy boundary

This remains professional discovery data. It is not a patient intake system and must not contain patient-identifying information or medical records. Downloads are unencrypted files and should be stored and shared carefully.
