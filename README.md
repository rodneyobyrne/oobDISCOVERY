# oobDISCOVERY-CLINICIAN

An oobCREATIVE discovery system for defining therapy services and mapping the distinct audience patterns, lived-language insights, and boundaries needed for patient-centered website strategy.

## Purpose

This repository contains the first discovery type in the broader `discovery.oobcreative.com` system. The Varetto experience is designed for a small group of informed stakeholders to define the therapy offering and identify the people the website should understand especially well.

The intended reasoning flow is:

**original study → selected persona worksheet → practice reality → overlap/distinction review → communication archetypes → website content**

Questionnaire version 5 begins with the four personas in the pre-therapy **Varetto Recovery: Persona and Keyword Study**. A clinician chooses one persona to sharpen or starts a new therapy-audience hypothesis. The original audience description, feelings, questions, influencers, barriers, and recovery-housing search language remain visible as source material; the clinician's revisions are stored separately.

Each persona opens in its own worksheet to prevent characteristics from bleeding across audiences. The questions trace central tension, help-seeking threshold, coping function and cost, observable versus private experience, ambivalence, trust, the decision system, audience language, and a credible direction toward connection or healing. Diagnoses and clinical concerns remain contextual modifiers rather than automatic archetype boundaries. The form accepts up to five worksheets, but downstream analysis—not the form—proposes merges and meaningful distinctions for clinician review.

## Current status

- Frontend: implemented as static HTML/CSS/JavaScript for GitHub Pages.
- Draft saving: browser `localStorage`.
- Microphone dictation: browser SpeechRecognition when available.
- Submission contract: implemented.
- Production API/database: connected at `https://api.oobcreative.com/discovery/submit/`.
- Protected results workspace: implemented at `https://api.oobcreative.com/discovery/results/`.
- Results accounts: invitation-based, email-verified, password-reset capable, and scoped by client using Bluehost MySQL and Google Workspace SMTP (disabled until its SMTP secrets are configured).
- Results exports: exact source JSON and an LLM-ready JSON envelope with provenance and analysis guardrails.
- Custom domain: active at `https://discovery.oobcreative.com` with HTTPS enforced.
- Submission retries: idempotent through a draft-stable submission ID.
- Draft retention: local browser drafts expire after 14 days and can be cleared manually.

JSON files downloaded from the questionnaire or results workspace are unencrypted and should be handled carefully.

## Architecture

```text
GitHub Pages
  discovery.oobcreative.com
        |
        +-- /                 discovery hub
        +-- /clinician/       therapy website discovery
                                |
                                +--> POST submission JSON
                                         |
                                         v
                              Bluehost API
                                |        |
                                |        +-- /discovery/results/ (private)
                                v
                              private database
```

The production submission endpoint is:

```text
https://api.oobcreative.com/discovery/submit
```

The results workspace is:

```text
https://api.oobcreative.com/discovery/results/
```

See [`docs/RESULTS.md`](docs/RESULTS.md) for the results workspace and export details, and [`docs/AUTH.md`](docs/AUTH.md) for account rollout. The API URL remains configuration-driven in `src/system-config.js`.

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
index.html                                  Discovery hub
clinician/index.html                        Therapy discovery form shell
assets/styles.css                           Shared oobCREATIVE interface styling
src/app.js                                  Form rendering, autosave, dictation, validation, submit
src/system-config.js                        Shared runtime configuration
config/clinician-core.js                    Reusable audience-pattern questions
config/varetto.js                           Varetto services and evidence options
schema/submission.schema.json               Version 5 submission data contract
server/api/discovery/results/index.php      Protected results workspace
docs/METHOD.md                              Discovery methodology and guardrails
docs/VARETTO-OUTREACH.md                    Two-message clinician outreach sequence
docs/DATA-CONNECTION.md                     Bluehost/API handoff requirements
docs/RESULTS.md                             Results access and export contract
.github/workflows/pages.yml                 GitHub Pages deployment workflow
```

## Brand principles carried into this system

- Begin with recognizable human situations rather than requiring clinical vocabulary.
- Preserve stakeholder judgment and respondent agency.
- Separate observation, inference, and public claims.
- Do not equate technical capability with something that should be marketed.
- Use emotional insight with restraint.
- Keep source responses intact; later analysis should never overwrite them.
- Discussion and discovery are not publication approval.
