export const clinicianCore = {
  principles: [
    "A clinician's capability is broader than what a website should actively market.",
    "Patient situations should be recognizable before they are clinical.",
    "Competence, strong fit, desire, and marketing priority are separate judgments.",
    "Clinician observations create persona hypotheses; they do not become final personas by themselves.",
    "Original responses are source material and should remain distinguishable from later interpretation."
  ],
  relationshipOptions: [
    { value: "can-serve", label: "Can responsibly serve" },
    { value: "strong-fit", label: "Strong fit / some of my best work" },
    { value: "refer", label: "Usually refer elsewhere" },
    { value: "unsure", label: "Depends / needs more context" }
  ],
  caseloadOptions: [
    { value: "more", label: "Want more" },
    { value: "neutral", label: "No preference" },
    { value: "less", label: "Want less" }
  ],
  patternQuestions: [
    ["turningPoint", "What was changing, failing, or getting harder to ignore when this person finally looked for help?"],
    ["initialVsUnderlying", "What might they initially say the problem is, and what often turns out to be underneath it?"],
    ["preclinicalLanguage", "How would they describe what is happening before they know or use clinical language?"],
    ["permissionStory", "What story might they tell themselves that makes it possible to keep going as they are?"],
    ["fearOfChange", "What are they afraid they could lose if they change?"],
    ["riskOfNoChange", "What are they increasingly at risk of losing if nothing changes?"],
    ["priorAttempts", "What have they already tried, and what did those attempts fail to give them?"],
    ["reliefVsChange", "What immediate relief are they hoping for, and what deeper change might eventually become possible?"],
    ["trustNeed", "What do they need from a clinician before they are likely to become genuinely honest?"],
    ["withdrawalTrigger", "What could a provider say or do that would make this person withdraw, become defensive, or stop engaging?"],
    ["othersAffected", "Who else tends to be affected, and who may notice the problem before the patient does?"],
    ["actionMoment", "What tends to happen immediately before this person becomes willing to act?"],
    ["realQuestion", "What is the real question underneath the question they usually ask out loud?"],
    ["searchLanguage", "If they were searching privately late at night, what might they type into Google?"],
    ["sentenceCompletions", "Complete any that feel revealing: “I’m not sure I need treatment, but …” / “I keep telling myself …” / “I want things to change, but I’m afraid …” / “I would ask for help if I knew …” / “I don’t want someone to tell me …” / “What I really want is …”"]
  ]
};
