export const varettoConfig = {
  clientId: "varetto",
  clientLabel: "Varetto Recovery",
  questionnaireVersion: "varetto-2026-08-v4",
  maxAudiencePatterns: 4,
  intro: {
    title: "Map the audiences Varetto needs to understand.",
    lead: "This clinical audience-mapping exercise will help turn recurring practice observations into clear, evidence-informed website audiences.",
    purpose: "Describe anonymous patterns rather than a particular patient. Separate relatively stable context from the temporary state that brings someone to care. After submission, oobCREATIVE will compare the patterns, identify likely overlap, and propose a smaller set of communication archetypes for clinician review.",
    boundary: "This is not a patient assessment, diagnostic instrument, or request to create a fictional personality. Diagnoses and symptoms may inform the context, but they do not define the archetype."
  },
  serviceOptions: [
    { id: "individual", label: "Individual therapy" },
    { id: "couples", label: "Couples therapy" },
    { id: "family", label: "Family therapy" },
    { id: "group", label: "Group therapy" },
    { id: "substance-assessment", label: "Substance use assessment" },
    { id: "mental-health-assessment", label: "Mental health assessment" },
    { id: "co-occurring", label: "Therapy for substance use and co-occurring concerns" },
    { id: "continuing-care", label: "Continuing-care therapy following treatment" },
    { id: "recurrence-support", label: "Recurrence or relapse-response support" },
    { id: "family-support", label: "Therapy for family members or partners" },
    { id: "recovery-growth", label: "Recovery maintenance and personal growth" },
    { id: "other", label: "Other or still being determined" }
  ],
  recipientOptions: [
    { id: "questioning-use", label: "Someone beginning to question their substance use" },
    { id: "ambivalent", label: "Someone who wants change but feels uncertain about stopping" },
    { id: "currently-using", label: "Someone currently using substances" },
    { id: "preparing-treatment", label: "Someone preparing to enter treatment" },
    { id: "leaving-treatment", label: "Someone transitioning from residential or intensive treatment" },
    { id: "early-recovery", label: "Someone in early recovery" },
    { id: "established-recovery", label: "Someone established in recovery" },
    { id: "after-recurrence", label: "Someone returning after a recurrence or relapse" },
    { id: "affected-other", label: "Someone affected by another person's substance use" },
    { id: "couples-families", label: "Couples or families" },
    { id: "other", label: "Other or still being determined" }
  ],
  audienceBasisOptions: [
    { id: "currently-served", label: "Currently represented in Varetto's clinical experience" },
    { id: "intended-audience", label: "An audience Varetto intentionally wants to serve" },
    { id: "current-and-intended", label: "Both current and intended" },
    { id: "unsure", label: "Unsure or still being defined" }
  ],
  evidenceSourceOptions: [
    { id: "direct-language", label: "Language heard directly from multiple clients" },
    { id: "repeated-observation", label: "Recurring clinical observation" },
    { id: "collateral", label: "Family, referral-source, or other collateral observation" },
    { id: "clinical-inference", label: "Professional inference" },
    { id: "future-hypothesis", label: "Future-audience hypothesis" },
    { id: "unsure", label: "Unsure" }
  ],
  conditionOptions: [
    { id: "alcohol-use", label: "Alcohol use" },
    { id: "opioid-use", label: "Opioid use" },
    { id: "stimulant-use", label: "Stimulant use" },
    { id: "cannabis-use", label: "Cannabis use" },
    { id: "other-substance-use", label: "Another substance-use concern" },
    { id: "anxiety", label: "Anxiety or chronic stress" },
    { id: "depression", label: "Depression or mood concerns" },
    { id: "trauma", label: "Trauma-related distress" },
    { id: "grief", label: "Grief or loss" },
    { id: "sleep", label: "Sleep disruption" },
    { id: "attention", label: "Attention or executive-function difficulty" },
    { id: "pain", label: "Chronic pain or health-related distress" },
    { id: "relationships", label: "Relationship or family distress" },
    { id: "identity", label: "Identity, purpose, or adjustment concerns" },
    { id: "other", label: "Other or unsure" }
  ]
};
