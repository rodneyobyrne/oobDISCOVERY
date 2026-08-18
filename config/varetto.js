export const varettoConfig = {
  clientId: "varetto",
  clientLabel: "Varetto Recovery",
  questionnaireVersion: "varetto-2026-08-v5",
  maxAudiencePatterns: 5,
  intro: {
    title: "Map the audiences Varetto needs to understand.",
    lead: "This clinical audience-mapping exercise will help turn recurring practice observations into clear, evidence-informed website audiences.",
    purpose: "Begin with one persona from Varetto’s original study—or create a new one—then sharpen it with anonymous, recurring clinical observations. Each persona stays in its own worksheet so we can compare patterns without blending unlike traits. After submission, oobCREATIVE will identify likely overlap and propose a smaller set of communication archetypes for clinician review.",
    boundary: "This is not a patient assessment, diagnostic instrument, or request to create a fictional personality. Diagnoses and symptoms may inform the context, but they do not define the archetype."
  },
  baselineStudy: {
    title: "Varetto Recovery: Persona and Keyword Study",
    context: "The original study was completed before Varetto added therapy services. Treat each profile as a working hypothesis: preserve what remains clinically and strategically useful, correct what no longer fits, combine profiles that share the same underlying motivation and decision process, or retire a profile that should not guide therapy communication.",
    decisionOptions: [
      { id: "retain", label: "Retain as a useful starting archetype" },
      { id: "revise", label: "Revise for therapy services" },
      { id: "combine", label: "Combine with another archetype" },
      { id: "retire", label: "Retire from the current strategy" },
      { id: "unsure", label: "Unsure; needs more evidence" }
    ],
    scopeOptions: [
      { id: "therapy-and-recovery", label: "Relevant to both therapy and recovery services" },
      { id: "therapy", label: "Relevant primarily to therapy services" },
      { id: "recovery", label: "Relevant primarily to recovery services" },
      { id: "not-relevant", label: "Not relevant to either current service line" },
      { id: "unsure", label: "Unsure" }
    ],
    legacyKeywordContext: "The original keyword recommendations emphasized men’s sober living, recovery housing, admissions, rules, costs, family influence, structure, confidentiality, and relapse prevention. Because they predate therapy services, they are prompts to confirm or replace—not an approved therapy keyword strategy.",
    legacyKeywordGroups: [
      { title: "Program and service language", items: ["men’s sober living", "men’s recovery house", "structured sober living", "transitional housing for men"] },
      { title: "Intent questions", items: ["what is sober living", "how does sober living work", "sober living rules", "difference between rehab and sober living"] },
      { title: "Influencer questions", items: ["how to help my husband, son, or brother get sober", "how to support someone in recovery", "questions to ask a sober living home", "is sober living safe"] },
      { title: "Emotional and support themes", items: ["brotherhood in recovery", "support network", "trauma-informed program", "accountability", "family-inclusive recovery"] }
    ],
    archetypes: [
      {
        id: "young-achiever",
        title: "Young Achiever",
        summary: "Originally described men ages 18–25 in college or early career who value autonomy, fear judgment or social loss, and may seek structure after detox or short-term treatment.",
        originalSignals: "Autonomy, peer perception, stigma, uncertainty about rules and treatment pathways, and hope for a fresh start. Parents, partners, and sober peers may influence the decision.",
        feelings: [
          "Shame or denial while trying to appear strong and in control",
          "Anxiety about peer judgment, social standing, or missing out",
          "Hope for structure, a fresh start, and renewed goals"
        ],
        questions: [
          "What is sober living, and how is it different from rehab?",
          "What do I have to do to get in, and how long does it take?",
          "What are the rules, and what does a structured environment mean?",
          "How do I know a provider or program is legitimate?"
        ],
        influencers: ["Parents", "Partners", "Peers or sober role models"],
        influencerQuestions: ["Parents: safety, timing, cost or insurance, staff credentials, and whether assessment and planning are individualized", "Partners: how to support without enabling, what expectations apply, and how recurrence or relapse is handled"],
        barriers: ["Stigma and masculine expectations", "Financial or academic pressure", "Denial, self-reliance, and a peer environment that normalizes use"]
      },
      {
        id: "working-class-dad",
        title: "Working-Class Dad",
        summary: "Originally described men ages 30–45 balancing employment, parenting, finances, and possible legal or workplace pressure while using substances to cope with stress.",
        originalSignals: "Provider identity, fear of job or income loss, shame, family repair, scheduling, and practical access. Partners, children, employers, unions, and legal contacts may influence the decision.",
        feelings: [
          "Overwhelmed by the combined responsibilities of work and parenting",
          "Shame about failing as a provider or role model",
          "Hope for family repair alongside fear about cost and time away from work"
        ],
        questions: [
          "Can I keep working while receiving care?",
          "Will I lose my job, and do leave or insurance benefits apply?",
          "Can I maintain contact with my children?",
          "What are the commitments, schedule, costs, and family supports?"
        ],
        influencers: ["Spouse or former partner", "Children", "Employer or union representative", "Probation officer or legal counsel"],
        influencerQuestions: ["Partners: safety, structure, finances, parenting or visitation, duration, and how to support without returning to codependent patterns", "Employers or unions: reliability, work-compatible scheduling, safety, and testing requirements"],
        barriers: ["Financial risk and limited leave", "Pride, stigma, and provider identity", "Legal pressure", "Strained family relationships"]
      },
      {
        id: "veteran-first-responder",
        title: "Veteran / First Responder",
        summary: "Originally described men with military or first-responder backgrounds for whom trauma exposure, service culture, confidentiality, and trust in provider credibility may shape help-seeking.",
        originalSignals: "Trauma-related distress, distrust of outsiders, occupational consequences, benefits navigation, and peer credibility. Partners, service peers, case managers, and chaplains may influence the decision.",
        feelings: [
          "Pressure to remain resilient and avoid visible vulnerability",
          "Trauma-related distress, grief, or fear of re-experiencing",
          "Distrust that someone outside the service culture will understand"
        ],
        questions: [
          "Do you understand military or first-responder culture and trauma?",
          "Will benefits or insurance cover care?",
          "Will seeking help affect my job?",
          "Can I trust the confidentiality?"
        ],
        influencers: ["Partner", "Veteran or first-responder peers", "Case manager or chaplain", "Family"],
        influencerQuestions: ["Partners: trauma-informed care, peer environment, communication during care, integrated mental-health support, and family therapy", "Service peers: provider credibility, evidence-informed care, cultural understanding, and family support"],
        barriers: ["Service-culture stigma and expectations of toughness", "Clinical complexity", "Benefits navigation, shifts, or deployment logistics"]
      },
      {
        id: "professional-under-pressure",
        title: "Professional Under Pressure",
        summary: "Originally described men in professional or entrepreneurial roles who may use substances in response to stress while protecting competence, reputation, privacy, and work continuity.",
        originalSignals: "Burnout, fear of exposure, confidentiality, flexible care, self-reliance, and evidence of effectiveness. Partners, trusted colleagues, physicians, therapists, and EAPs may influence the decision.",
        feelings: [
          "Stress or burnout within a high-performance identity",
          "Fear that seeking help could expose a problem or damage reputation",
          "A strong need for discretion, flexibility, and continued functioning"
        ],
        questions: [
          "Is care confidential, and who can access my information?",
          "Can I continue working, and is scheduling flexible?",
          "What evidence supports the approach, and how are setbacks handled?",
          "Can a partner or family member be involved appropriately?"
        ],
        influencers: ["Partner", "Trusted colleague or mentor", "Physician or therapist", "Employee assistance program"],
        influencerQuestions: ["Partners: privacy, family involvement, supporting recovery without sacrificing their own well-being, and what happens after a setback", "Colleagues or mentors: confidentiality, time commitment, career protection, and how to raise concern appropriately"],
        barriers: ["Reputation risk", "Time constraints", "Self-reliance and fear of losing control"]
      }
    ]
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
