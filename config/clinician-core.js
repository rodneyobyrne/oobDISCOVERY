export const clinicianCore = {
  principles: [
    "Treat each entry as a candidate audience pattern, not a finished persona or diagnosis.",
    "Separate relatively stable context from the state-dependent presentation that brings someone to care.",
    "Distinguish language heard or behavior observed from clinical inference and future-audience hypotheses.",
    "Use diagnoses, symptoms, occupations, and recovery stages as context—not automatic reasons to create separate archetypes.",
    "Let downstream analysis propose overlaps and distinctions; keep the clinician's source response intact."
  ],
  patternQuestions: [
    {
      number: 1,
      key: "evidence",
      type: "evidence",
      label: "What is this audience pattern based on?",
      help: "Separate repeated clinical experience from informed inference and an audience Varetto hopes to serve. This helps us weigh the response without treating every statement as equally established."
    },
    {
      number: 2,
      key: "audienceRole",
      label: "Who is the website speaking to in this pattern?",
      help: "Identify who would receive care, who recognizes the need, who searches, and who initiates contact. These may be different people—for example, a prospective patient, partner, parent, clinician, or other referral source."
    },
    {
      number: 3,
      key: "stableContext",
      label: "What relatively stable context shapes how this audience approaches help?",
      help: "Consider role obligations, values, identity, relationships, resources, culture, and pressures. Avoid turning a diagnosis, occupation, age, or current symptom state into a fixed personality trait."
    },
    {
      number: 4,
      key: "helpSeekingState",
      label: "What state, event, or change brings this person to the website now?",
      help: "Describe the presenting moment: an accumulation of strain, consequence, transition, recurrence, conversation, or shift in readiness. This is the help-seeking state—not necessarily the person's enduring character."
    },
    {
      number: 5,
      key: "observedAndPrivate",
      label: "What is observable, and what may be experienced privately?",
      help: "Distinguish behavior or functioning you have repeatedly observed from thoughts, emotions, or meanings clients have reported. Label a clinical inference as an inference rather than placing it in the person's voice."
    },
    {
      number: 6,
      key: "functionAndDesiredChange",
      label: "What function does the current pattern serve, and what does this person want instead?",
      help: "Describe what substance use or another coping behavior may regulate, avoid, preserve, or provide temporarily. Then describe the everyday life change the person wants—not only the symptom or behavior they want to stop."
    },
    {
      number: 7,
      key: "resistanceAndTrust",
      label: "What makes help feel risky, and what could establish enough trust to continue?",
      help: "Consider autonomy, stigma, shame, prior treatment experiences, confidentiality, cultural fit, practical access, safety, readiness, and fear of consequences."
    },
    {
      number: 8,
      key: "languageSignals",
      label: "What language belongs specifically to this audience?",
      help: "Include de-identified phrases you have heard, likely search language, what would communicate recognition, and words or framing that could feel inaccurate, stigmatizing, or alienating."
    },
    {
      number: 9,
      key: "clinicalContext",
      type: "conditions",
      label: "Which clinical concerns may modify this presentation?",
      help: "Treat these as possible dimensions or co-occurring contexts—not defining traits and not evidence that everyone in this audience has the same diagnosis."
    },
    {
      number: 10,
      key: "fitAndBoundary",
      label: "Why might Varetto fit, and where is the clinical or practical boundary?",
      help: "Connect the pattern to actual services, therapeutic strengths, level of care, scope, access, and referral needs. Do not infer fit from diagnosis alone."
    },
    {
      number: 11,
      key: "distinction",
      label: "What should we not carry over from another audience pattern?",
      help: "Name the motivation, decision process, language, trust requirement, or service pathway that makes this audience meaningfully different. If only diagnosis, occupation, age, or recovery stage differs, it may be one archetype in another context."
    }
  ]
};
