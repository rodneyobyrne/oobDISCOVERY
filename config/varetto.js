export const varettoConfig = {
  clientId: "varetto",
  clientLabel: "Varetto",
  questionnaireVersion: "varetto-2026-08-v2",
  intro: {
    title: "Help me understand the people Varetto can help especially well.",
    lead: "I’ve started developing early audience and persona hypotheses from our conversations, my understanding of your experience, what I’ve learned about behavioral health and recovery, and what I know about Alan’s House. This is a chance to strengthen, challenge, or redirect that thinking with what you have actually seen in the work.",
    purpose: "The goal is not to build a long list of everything Varetto could technically offer. I’m looking for patterns: people the practice understands well, situations where a strong therapeutic relationship tends to develop, work where progress is repeatedly possible, boundaries that matter, and the language people use before they have clinical terminology for what they are experiencing."
  },
  ownerQuestions: [
    ["businessOutcome", "What needs to become true for Varetto’s clinical practice to be a successful and sustainable expansion?"],
    ["strategicAudience", "Which people or referral situations are most important for the practice to reach first—and why?"],
    ["demandVsFit", "Where do you see demand that may not match Varetto’s strongest work, capacity, or desired direction?"],
    ["growthConstraint", "What capacity, staffing, payer, operational, or reputation constraint should shape what the website promises?"],
    ["launchSuccess", "Six months after launch, what evidence would tell you the website is attracting the right inquiries?"]
  ],
  archetypes: [
    { id:"taking-more-than-gives", title:"Something is taking more than it gives.", situation:"I’m beginning to wonder whether drinking or using is costing me more than I want to admit, even though my life has not completely fallen apart." },
    { id:"not-sure-treatment", title:"I don’t like what I’m doing, but I’m not sure I need treatment.", situation:"I’m uncomfortable with my behavior and the consequences around it, but labels such as addiction or treatment feel too big, too final, or not quite like me." },
    { id:"change-relationship-not-abstinence", title:"I want something to change without having the ending decided for me.", situation:"I want to change my relationship with substances, but I’m not yet sure whether abstinence is right for me and I do not want someone deciding that before understanding me." },
    { id:"functioning-minimization", title:"I’m functioning, so I keep telling myself it can’t be that serious.", situation:"Work, family, or responsibilities are still getting done, which makes it easier to minimize patterns that are becoming harder to control or ignore." },
    { id:"sober-not-solved", title:"I’m sober, but sobriety didn’t solve everything.", situation:"Substance use has changed, but anxiety, anger, shame, isolation, relationships, trauma, identity, or other patterns are still making life difficult." },
    { id:"understand-repeat", title:"I understand the pattern. I still keep repeating it.", situation:"I can explain why I do what I do, and I may have spent years thinking or talking about it, but insight has not translated into meaningful change." },
    { id:"substance-connected-distress", title:"The substance use is tangled up with something else.", situation:"Trauma, anxiety, depression, shame, relationship strain, or chronic stress seem connected to how I use substances or why change is difficult." },
    { id:"treatment-ended", title:"Treatment ended. Now what?", situation:"I’m leaving a treatment program or another structured level of care and I’m unsure how to carry change into ordinary life or what support should come next." },
    { id:"therapy-no-sober-living", title:"I want therapy. I don’t need sober living.", situation:"I want meaningful clinical support around substance use, recovery, mental health, or related patterns without living in a recovery residence." },
    { id:"therapy-plus-structure", title:"I need clinical help and a structured place to live.", situation:"Therapy alone may not be enough right now. I may benefit from clinical support while also living in an environment that supports recovery and accountability." },
    { id:"treatment-fatigue", title:"I’ve tried help before and I’m not sure I trust the process anymore.", situation:"I have treatment or therapy experience, but something did not fit, did not last, or left me wary of repeating another version of the same experience." },
    { id:"loved-one", title:"Someone I love is using, and I don’t know what helping means anymore.", situation:"I’m frightened, exhausted, angry, or confused by another person’s substance use and I’m struggling to understand what I can change, what I cannot, and how to respond." }
  ]
};
