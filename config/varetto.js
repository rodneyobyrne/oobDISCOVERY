export const varettoConfig = {
  clientId: "varetto",
  clientLabel: "Varetto Recovery",
  questionnaireVersion: "varetto-2026-08-v3",
  intro: {
    title: "Help me understand who Varetto Therapy is built to help.",
    lead: "Your answers will help define the therapy services Varetto will offer and the people the new website should speak to most clearly.",
    purpose: "Begin with what people experience in daily life—not with diagnoses, credentials, or treatment terminology. I will use your answers to identify audience patterns, connect those patterns to appropriate clinical concerns, and develop patient-centered website content."
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
  audienceSituations: [
    { id: "questioning-control", title: "Questioning whether substance use is becoming a problem", situation: "Something is beginning to feel harder to control or easier to hide, even though life may not have completely fallen apart." },
    { id: "ambivalent-change", title: "Wanting change but feeling uncertain about stopping", situation: "The person wants something to change without having the ending decided before they feel understood." },
    { id: "functioning-privately-struggling", title: "Functioning outwardly while struggling privately", situation: "Work, family, or responsibilities are still getting done, but maintaining that appearance is becoming exhausting." },
    { id: "transitioning-treatment", title: "Transitioning out of treatment", situation: "Structure is ending, ordinary life is returning, and the person is unsure how to carry change forward." },
    { id: "early-recovery", title: "Struggling in early recovery", situation: "Substance use has changed, but emotions, relationships, routines, or identity have not caught up." },
    { id: "after-recurrence", title: "Returning after recurrence or relapse", situation: "The person is discouraged or ashamed and may fear that asking for help means starting over or admitting failure." },
    { id: "distress-after-stopping", title: "Stopping substance use does not resolve the underlying distress", situation: "Anxiety, sadness, anger, memories, sleeplessness, restlessness, or other difficulties become more visible when use decreases." },
    { id: "co-occurring-recovery", title: "Managing mental-health concerns alongside recovery", situation: "Anxiety, depression, trauma, grief, sleep, attention, pain, or another concern is intertwined with substance use or recovery." },
    { id: "relationship-repair", title: "Rebuilding relationships affected by substance use", situation: "Trust, communication, boundaries, intimacy, or family roles have been shaped by substance use and its consequences." },
    { id: "family-seeking-help", title: "A family member or partner seeking help", situation: "Someone is worried, exhausted, angry, or confused by another person's substance use and no longer knows what helping means." },
    { id: "recovery-identity-purpose", title: "Stable in recovery but struggling with identity, relationships, or purpose", situation: "The immediate crisis is quieter, but building a meaningful and sustainable life remains difficult." },
    { id: "other", title: "Another important audience situation", situation: "Use the response fields to describe an audience that is not represented above." }
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
