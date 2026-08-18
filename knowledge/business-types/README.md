# oobDISCOVERY Business-Type Knowledge

This directory stores reusable, business-type-level context for oobDISCOVERY.

A Discovery **project** belongs to one `client_business_type`. The business type is not a user role, project name, or public navigation category. It is machine-readable context that can help an LLM or agent prepare for intake, identify likely research needs, recognize recurring decision patterns, and ask better questions before project-specific evidence is complete.

## Intended use

Business-type knowledge may support:

- pre-intake research plans
- likely customer or stakeholder groups to investigate
- common buying or help-seeking situations to test
- vocabulary and terminology to clarify
- operational or regulatory questions that commonly matter
- recurring proof requirements and trust signals to verify
- likely objections, decision friction, and information gaps
- project-specific interview/questionnaire adaptation
- hypotheses worth stress-testing across multiple projects

## Evidence rule

Business-type files must distinguish among:

1. **Established / sourced knowledge** — supported by a named source, dataset, regulation, project evidence, or other traceable basis.
2. **Repeated project pattern** — observed across multiple Discovery projects, with the count and context recorded where practical.
3. **Working hypothesis** — useful enough to test, but not treated as fact.
4. **Project-specific fact** — belongs to one client and must not automatically be generalized to the business type.
5. **Unknown / research gap** — intentionally unresolved.

A repeated assumption does not become true merely because it appears in several projects. Statistical, cultural, geographic, regulatory, market, and sample-selection differences must remain visible.

## Downstream rule

Business-type knowledge may accelerate preparation, but it does not replace project intake or human judgment. The system should use prior knowledge to ask sharper questions and reduce unnecessary blank-page work—not to force a new client into an inherited profile.

## File naming

Use the exact `client_business_type` value as the filename when practical:

- `plumbing.md`
- `clinician.md`
- `behavioral-health.md`

Use `_TEMPLATE.md` when creating a new business-type knowledge file.
