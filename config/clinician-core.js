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
  patternQuestionGroups: [
    {
      id: "observed",
      label: "What you have observed",
      help: "Use recurring behavior, language, and circumstances you have actually encountered.",
      questions: [
        ["turningPoint", "What was changing, failing, or getting harder to ignore when this person finally looked for help?"],
        ["initialVsUnderlying", "What might they initially say the problem is, and what often becomes clearer through the work?"],
        ["preclinicalLanguage", "What words have you heard people use before they know or use clinical language?"],
        ["priorAttempts", "What have they commonly tried already, and what did those attempts fail to give them?"],
        ["othersAffected", "Who else tends to be affected, and who may notice the problem before the patient does?"],
        ["actionMoment", "What tends to happen immediately before this person becomes willing to act?"]
      ]
    },
    {
      id: "hypothesis",
      label: "Your informed hypothesis",
      help: "These answers can include clinical judgment. They will be treated as hypotheses to validate, not as patient quotations or settled facts.",
      questions: [
        ["permissionStory", "What story might they tell themselves that makes it possible to keep going as they are?"],
        ["fearOfChange", "What are they afraid they could lose if they change?"],
        ["riskOfNoChange", "What are they increasingly at risk of losing if nothing changes?"],
        ["reliefVsChange", "What immediate relief are they hoping for, and what deeper change might eventually become possible?"],
        ["trustNeed", "What do they need from a clinician before they are likely to become genuinely honest?"],
        ["withdrawalTrigger", "What could a provider say or do that would make this person withdraw, become defensive, or stop engaging?"],
        ["realQuestion", "What may be the real question underneath the question they usually ask out loud?"],
        ["searchLanguage", "Based on language you have heard, what might they type into a private late-night search?"],
        ["sentenceCompletions", "Complete any that feel revealing: “I’m not sure I need treatment, but …” / “I keep telling myself …” / “I want things to change, but I’m afraid …” / “I would ask for help if I knew …” / “I don’t want someone to tell me …” / “What I really want is …”"]
      ]
    }
  ]
};
