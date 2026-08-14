# oobDISCOVERY-CLINICIAN

An oobCREATIVE discovery system for capturing clinician strengths, patient-fit patterns, lived-language insights, preferences, and boundaries to inform human-centered audience personas, messaging, and website strategy.

## Purpose

This repository contains the first discovery type in the broader `discovery.oobcreative.com` system. The clinician experience is designed to learn from professional judgment without turning credentials, diagnoses, or service inventories directly into marketing claims.

The intended reasoning flow is:

**source responses → observed patterns → persona hypotheses → validated audience strategy**

The public-facing form begins with recognizable patient situations and clinician fit, then moves into deeper observed patterns and finally practice realities. Clinical facts support the strategy; they do not automatically determine what should be marketed.

## Current status

- Frontend: implemented as static HTML/CSS/JavaScript for GitHub Pages.
- Draft saving: browser `localStorage`.
- Microphone dictation: browser SpeechRecognition when available.
- Submission contract: implemented.
- Production API/database: **not yet connected**.
- Custom domain: **not yet configured**. Do not add a `CNAME` file until DNS and Pages are ready.

Until a production submission endpoint is configured, the form will not present itself as successfully submitted. A JSON backup can be downloaded manually for testing.

## Architecture

```text
GitHub Pages
  discovery.oobcreative.com
        |
        +-- /                 discovery hub
        +-- /clinician/       clinician discovery
                                |
                                +--> POST submission JSON
                                         |
                                         v
                              Bluehost API (planned)
                                         |
                                         v
                              private database (planned)
```

The recommended production endpoint is conceptually:

```text
https://api.oobcreative.com/discovery/submit
```

The API URL is intentionally configuration-driven and blank in the current repository.

## Local preview

Because the form uses JavaScript modules, preview with a local HTTP server rather than opening the files directly.

```bash
python -m http.server 8080
```

Then open `http://localhost:8080/clinician/`.

## Important data boundary

This system is for professional discovery and generalized patient-pattern knowledge. Respondents are explicitly instructed not to provide names or other identifying patient information.

Do not extend this implementation to collect patient medical records or identifiable health information without treating that as a separate privacy, security, hosting, and compliance project.

## Repository structure

```text
index.html                         Discovery hub
clinician/index.html               Clinician form shell
assets/styles.css                  Shared oobCREATIVE interface styling
src/app.js                         Form rendering, autosave, dictation, validation, submit
src/system-config.js               Shared runtime configuration
config/clinician-core.js           Reusable clinician discovery model
config/varetto.js                  First client configuration / hypotheses
schema/submission.schema.json      Submission data contract
docs/METHOD.md                     Discovery methodology and guardrails
docs/DATA-CONNECTION.md            Bluehost/API handoff requirements
.github/workflows/pages.yml        GitHub Pages deployment workflow
```

## Brand principles carried into this system

- Begin with recognizable human situations rather than requiring clinical vocabulary.
- Preserve the clinician's judgment and the respondent's agency.
- Separate observation, inference, and public claims.
- Do not equate technical capability with something that should be marketed.
- Use emotional insight with restraint.
- Keep source responses intact; later analysis should never overwrite them.
- Discussion and discovery are not publication approval.
