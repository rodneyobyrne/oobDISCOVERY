export const varettoConfig = {
  clientId: "varetto",
  clientLabel: "Varetto Recovery",
  questionnaireVersion: "varetto-2026-08-persona-review-v5",
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
      { id: "retain", icon: "👍", label: "Yes—review and refine" },
      { id: "revise", icon: "✏️", label: "Needs significant revision" },
      { id: "retire", icon: "○", label: "This persona is no longer relevant" }
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
        personName: "Dylan",
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
        barriers: ["Stigma and masculine expectations", "Financial or academic pressure", "Denial, self-reliance, and a peer environment that normalizes use"],
        profile: {
          lifeContext: ["Young men aged 18–25", "In college or early in their careers", "Often misuse alcohol or drugs recreationally", "May have completed detox or short-term rehab", "Seek autonomy", "Fear stigma or judgement"],
          internalExperience: ["Shame and denial: cultural expectations encourage them to appear strong and in control, making it hard to admit they have a problem. They may feel embarrassed about relapse or fear being seen as weak.", "Anxiety about peer perception: they worry that entering sober living will cost them friendships or social standing. Fear of missing out on college life can be strong.", "Hope for a fresh start: once motivated, they crave structured support to rebuild their lives and achieve personal goals."],
          questions: ["What is sober living?", "Are all sober houses the same?", "What do I have to do to get in?", "How long does it take to get moved in?", "Are there rules?", "What does a structured environment mean?", "How do I avoid illegitimate sober houses?", "Is sober living different from rehab?"],
          influencers: ["Mothers and Fathers: Men often discuss distress with mothers or parents; mothers encourage them to seek help and may make the initial call to a program.", "Girlfriends or Partners: Significant others are powerful motivators; supportive partners legitimize help-seeking and overcome masculine stereotypes.", "Peers/Sober Role Models: Seeing other young men seek help legitimizes treatment. Peers who have succeeded in recovery can influence them."],
          influencerConcerns: ["Parents: Fear for their son’s safety and future; confusion about the difference between rehab and sober living. They ask: How soon can treatment begin? What are the costs and does insurance cover it? Are staff credentialed? Is there a comprehensive assessment and personalized plan?", "Partners: Feel exhausted and anxious. They ask: How can I help without enabling? What rules will he need to follow? They also wonder about relapse management and support options."],
          barriers: ["Stigma and Masculine Ideals: Fear of being seen as weak or losing independence.", "Financial and Academic Pressure: Concerns about pausing studies or losing scholarships; fear of legal or disciplinary consequences.", "Denial and Social Influence: Belief they can handle the problem themselves; peer groups normalize substance use."]
        },
        fullStudyHtml: `<p class="persona-study-name"><strong>DYLAN</strong><br>The Young Achiever</p>
          <h4>Audience</h4>
          <p>Young men aged 18–25 who are in college or early in their careers. They often misuse alcohol or drugs recreationally and may have completed detox or short‑term rehab. They seek autonomy and fear stigma or judgement.</p>
          <h4>What They Are Feeling</h4>
          <ul>
            <li>Shame and denial: cultural expectations encourage them to appear strong and in control, making it hard to admit they have a problem <a href="https://www.ncbi.nlm.nih.gov/books/NBK144290/#:~:text=gender%20later%20in%20this%20chapter%29,are%20discussed%20in%20%2025">ncbi.nlm.nih.gov</a>. They may feel embarrassed about relapse or fear being seen as weak <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li>Anxiety about peer perception: they worry that entering sober living will cost them friendships or social standing. Fear of missing out on college life can be strong.</li>
            <li>Hope for a fresh start: once motivated, they crave structured support to rebuild their lives and achieve personal goals.</li>
          </ul>
          <h4>Questions They Are Asking:</h4>
          <p>Online searches show typical questions from prospective residents, which align with this persona:</p>
          <ul>
            <li>“<strong>What is sober living?</strong>” and “<strong>Are all sober houses the same?</strong>” — they want definitions and to compare options <a href="https://www.intoactionsoberliving.com/faq-with-anchors.html#:~:text=,%2F%20illegitimate%C2%A0%27sober%20house">intoactionsoberliving.com</a>.</li>
            <li>“<strong>What do I have to do to get in?</strong>” and “<strong>How long does it take to get moved in?</strong>” — they need clear admissions steps and timelines <a href="https://www.intoactionsoberliving.com/faq-with-anchors.html#:~:text=,%2F%20illegitimate%C2%A0%27sober%20house">intoactionsoberliving.com</a>.</li>
            <li>“<strong>Are there rules?</strong>” and “<strong>What does a structured environment mean?</strong>” — they are concerned about house expectations and personal freedom <a href="https://www.intoactionsoberliving.com/faq-with-anchors.html#:~:text=,%2F%20illegitimate%C2%A0%27sober%20house">intoactionsoberliving.com</a>.</li>
            <li>“<strong>How do I avoid illegitimate sober houses?</strong>” — they want assurance that the house is reputable <a href="https://www.intoactionsoberliving.com/faq-with-anchors.html#:~:text=%EF%BB%BFAre%20all%20sober%20hous%EF%BB%BF%EF%BB%BFes%EF%BB%BF%EF%BB%BF%20the,are%20comfortable%20with%20the%20answers">intoactionsoberliving.com</a>.</li>
            <li>“<strong>Is sober living different from rehab?</strong>” — they seek clarity about continued care vs. initial treatment.</li>
          </ul>
          <h4>They are Influenced by:</h4>
          <ul>
            <li><strong>Mothers and Fathers:</strong> Research shows men often discuss distress with mothers or parents; mothers encourage them to seek help and may make the initial call to a program <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC8661038/#:~:text=and%20seek%20out%20formal%20help,seeking%2C%20as%20Samuel%20explained">pmc.ncbi.nlm.nih.gov</a>.</li>
            <li><strong>Girlfriends or Partners:</strong> Significant others are powerful motivators; supportive partners legitimize help-seeking and overcome masculine stereotypes <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC8661038/#:~:text=their%20lives%20to%20make%20sense,masculine%20stereotypes%20and%20stigma%20attached">pmc.ncbi.nlm.nih.gov</a>.</li>
            <li><strong>Peers/Sober Role Models:</strong> Seeing other young men seek help legitimizes treatment <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC8661038/#:~:text=their%20lives%20to%20make%20sense,masculine%20stereotypes%20and%20stigma%20attached">pmc.ncbi.nlm.nih.gov</a>. Peers who have succeeded in recovery can influence them.</li>
          </ul>
          <h4>Influencer Feelings &amp; Questions</h4>
          <ul>
            <li><strong>Parents:</strong> Fear for their son’s safety and future; confusion about the difference between rehab and sober living. They ask questions from the NIAAA treatment navigator: <strong>“How soon can treatment begin?,” “What are the costs and does insurance cover it?,” “Are staff credentialed?,” “Is there a comprehensive assessment and personalized plan?”</strong> <a href="https://alcoholtreatment.niaaa.nih.gov/how-to-find-alcohol-treatment/how-to-search-what-to-ask/step-2-ask-10-recommended-questions">alcoholtreatment.niaaa.nih.gov</a>. They also gather information about age, substance use patterns, other health issues and legal involvement before calling <a href="https://alcoholtreatment.niaaa.nih.gov/how-to-find-alcohol-treatment/how-to-search-what-to-ask/step-2-ask-10-recommended-questions#:~:text=You%20might%20be%20asked%20to,person%20in%20need%20of%20treatment">alcoholtreatment.niaaa.nih.gov</a>.</li>
            <li><strong>Partners:</strong> Feel exhausted and anxious; they want to know <strong>“How can I help without enabling?”</strong> and <strong>“What rules will he need to follow?”</strong>. They also wonder about relapse management and support options <a href="https://alcoholtreatment.niaaa.nih.gov/how-to-find-alcohol-treatment/how-to-search-what-to-ask/step-2-ask-10-recommended-questions">alcoholtreatment.niaaa.nih.gov</a>.</li>
          </ul>
          <h4>Barriers to Treatment</h4>
          <ul>
            <li><strong>Stigma and Masculine Ideals:</strong> Fear of being seen as weak or losing independence <a href="https://www.ncbi.nlm.nih.gov/books/NBK144290/#:~:text=gender%20later%20in%20this%20chapter%29,are%20discussed%20in%20%2025">ncbi.nlm.nih.gov</a> <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li><strong>Financial and Academic Pressure:</strong> Concerns about pausing studies or losing scholarships; fear of legal or disciplinary consequences.</li>
            <li><strong>Denial and Social Influence:</strong> Belief they can handle the problem themselves; peer groups normalize substance use <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Peer%20Pressure%20and%20Social%20Environment">neworigins.org</a>.</li>
          </ul>`
      },
      {
        id: "working-class-dad",
        personName: "Scott",
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
        barriers: ["Financial risk and limited leave", "Pride, stigma, and provider identity", "Legal pressure", "Strained family relationships"],
        profile: {
          lifeContext: ["Men aged 30–45", "Employed in blue-collar or service jobs", "Often have children", "May be separated or divorced", "Substance use often stems from work stress or functions as a coping mechanism", "May be mandated to seek treatment due to legal issues or workplace interventions"],
          internalExperience: ["Overwhelmed by dual responsibilities: they juggle jobs and parenting while hiding addiction. They fear losing employment and financial stability.", "Shame about failing as a provider or role model. Masculine expectations discourage them from admitting vulnerability.", "Hopeful yet cautious: they desire to rebuild trust with family but worry about costs and time away from work."],
          questions: ["Can I keep working while in sober living?", "Will I lose my job?", "Do I qualify for leave or insurance?", "Can I have my kids visit?", "What are the house rules?", "How long can I stay?", "Do they offer family therapy?"],
          influencers: ["Spouse or Ex-Partner: Often the primary initiator. She may deliver ultimatums or arrange interventions; her actions are driven by concern for children’s safety and family stability. She frequently handles health decisions and finances.", "Children: Men often cite regaining custody and being a better father as motivation; children act as emotional anchors.", "Employers/Union Representatives: Workplace policies or union support may push for treatment to protect job performance and safety.", "Probation Officers/Legal Counsel: Legal mandates can compel them to enter programs."],
          influencerConcerns: ["Spouse/Partner: Worries about domestic stability, finances and co-parenting. She asks: Is this house safe and structured? What are visitation policies? How do I support his recovery without relapsing into codependency?", "Employers/Union Reps: Focused on worker safety and productivity. They ask: Will he return to work sober and reliable? Can he get treatment outside of work hours? Is there drug testing?"],
          barriers: ["Financial Concerns: Fear of losing income or paying for treatment; limited sick leave.", "Stigma and Pride: Embarrassment about needing help; reluctance to admit failure as a father/provider.", "Legal Pressures: Worry about court consequences; sometimes mandated treatment feels punitive.", "Family Dynamics: Strained relationships require therapy; men worry about facing past mistakes."]
        },
        fullStudyHtml: `<p class="persona-study-name"><strong>Scott</strong><br>Working‑Class Dad</p>
          <h4>Audience</h4>
          <p>Men aged 30–45 employed in blue‑collar or service jobs. They often have children and may be separated or divorced. Their substance use often stems from work stress or as a coping mechanism. They might be mandated to seek treatment due to legal issues (DUI) or workplace interventions.</p>
          <h4>What They Are Feeling</h4>
          <ul>
            <li>Overwhelmed by dual responsibilities: they juggle jobs and parenting while hiding addiction. They fear losing employment and financial stability <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Work%20and%20Financial%20Responsibilities">neworigins.org</a>.</li>
            <li>Shame about failing as a provider or role model. Masculine expectations discourage them from admitting vulnerability <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li>Hopeful yet cautious: they desire to rebuild trust with family but worry about costs and time away from work.</li>
          </ul>
          <h4>Questions They Are Asking</h4>
          <ul>
            <li>“<strong>Can I keep working while in sober living?</strong>” — they need flexible schedules and may prefer outpatient or evening programs <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Work%20and%20Financial%20Responsibilities">neworigins.org</a>.</li>
            <li>“<strong>Will I lose my job?</strong>” or “<strong>Do I qualify for leave or insurance?</strong>” — financial concerns dominate <a href="https://adcare.com/addiction-demographics/men/#:~:text=Potential%20barriers%20to%20treatment%20and,in%20addiction%20recovery%20can%20include">adcare.com</a>.</li>
            <li>“<strong>Can I have my kids visit?</strong>” — they want to maintain parental relationships.</li>
            <li>“<strong>What are the house rules?</strong>” and “<strong>How long can I stay?</strong>” — they seek clarity on commitments.</li>
            <li>“<strong>Do they offer family therapy?</strong>” — interest in mending relationships <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Family%20Dynamics%20and%20Relationships">neworigins.org</a>.</li>
          </ul>
          <h4>They are Influenced by:</h4>
          <ul>
            <li><strong>Spouse or Ex-Partner:</strong> Often the primary initiator. She may deliver ultimatums or arrange interventions; her actions are driven by concern for children’s safety and family stability. She frequently handles health decisions and finances.</li>
            <li><strong>Children:</strong> Men often cite regaining custody and being a better father as motivation; children act as emotional anchors.</li>
            <li><strong>Employers/Union Representatives:</strong> Workplace policies or union support may push for treatment to protect job performance and safety.</li>
            <li><strong>Probation Officers/Legal Counsel:</strong> Legal mandates can compel them to enter programs <a href="https://www.ncbi.nlm.nih.gov/books/NBK144290/#:~:text=gender%20later%20in%20this%20chapter%29,are%20discussed%20in%20%2025">ncbi.nlm.nih.gov</a>.</li>
          </ul>
          <h4>Influencer Feelings &amp; Questions</h4>
          <ul>
            <li><strong>Spouse/Partner:</strong> Worries about domestic stability, finances and co‑parenting. She asks <strong>“Is this house safe and structured?,” “What are visitation policies?”</strong> and <strong>“How do I support his recovery without relapsing into codependency?”</strong>. She also wants to know about program duration, costs and success rates <a href="https://alcoholtreatment.niaaa.nih.gov/how-to-find-alcohol-treatment/how-to-search-what-to-ask/step-2-ask-10-recommended-questions">alcoholtreatment.niaaa.nih.gov</a>.</li>
            <li><strong>Employers/Union Reps:</strong> Focused on worker safety and productivity. Questions include <strong>“Will he return to work sober and reliable?,” “Can he get treatment outside of work hours?”</strong> and <strong>“Is there drug testing?”</strong>.</li>
          </ul>
          <h4>Barriers to Treatment</h4>
          <ul>
            <li><strong>Financial Concerns:</strong> Fear of losing income or paying for treatment; limited sick leave <a href="https://adcare.com/addiction-demographics/men/#:~:text=Potential%20barriers%20to%20treatment%20and,in%20addiction%20recovery%20can%20include">adcare.com</a>.</li>
            <li><strong>Stigma and Pride:</strong> Embarrassment about needing help; reluctance to admit failure as a father/provider <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li><strong>Legal Pressures:</strong> Worry about court consequences; sometimes mandated treatment feels punitive.</li>
            <li><strong>Family Dynamics:</strong> Strained relationships require therapy; men worry about facing past mistakes <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Family%20Dynamics%20and%20Relationships">neworigins.org</a>.</li>
          </ul>`
      },
      {
        id: "veteran-first-responder",
        personName: "Robert",
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
        barriers: ["Service-culture stigma and expectations of toughness", "Clinical complexity", "Benefits navigation, shifts, or deployment logistics"],
        profile: {
          lifeContext: ["Men aged 25–50", "Military or first-responder backgrounds", "Often struggle with post-traumatic stress disorder (PTSD) or trauma-related disorders", "May turn to alcohol or prescription drugs", "May be uncomfortable seeking help outside their service circle"],
          internalExperience: ["Hyper-masculine pressure: service cultures emphasise resilience and discourage vulnerability, leading to shame and isolation.", "Trauma and grief: unresolved PTSD or traumatic experiences intensify substance use; they may fear re-experiencing trauma through treatment.", "Distrust of outsiders: they worry civilian clinicians won’t understand their experiences."],
          questions: ["Do you offer veteran-specific programs?", "Are staff trained in trauma-informed care?", "Is this covered by the VA?", "How do I access my benefits?", "Will my job be protected?", "Can I trust the confidentiality?"],
          influencers: ["Spouse/Partner: Highlights changes in behaviour and pushes for help; manages home life during deployments or shifts.", "Fellow Veterans/First Responders: Peer credibility is vital; men are more likely to accept help when encouraged by someone with similar experiences.", "VA Case Managers or Chaplains: Provide referrals and help navigate benefits.", "Mothers/Siblings: Emotional anchors who provide unconditional support."],
          influencerConcerns: ["Spouse/Partner: Experiences compassion fatigue; fears for partner’s safety and mental health. They ask: Is there a trauma-informed environment? Are other veterans in the program? How will we communicate during treatment?", "Veteran Peers: Want to ensure the program honours service culture and offers peer support. They ask: Is this program credible? Does it use evidence-based approaches? Is there a support group for family?"],
          barriers: ["Stigma and Camaraderie: Fear of appearing weak within the service community; belief they must “tough it out.”", "Mental-Health Complexity: PTSD and co-occurring disorders require specialized care.", "Logistical Issues: Navigating VA benefits and coverage; scheduling treatment around shifts or deployments."]
        },
        fullStudyHtml: `<p class="persona-study-name"><strong>Robert</strong><br>Veteran / First Responder</p>
          <h4>Audience</h4>
          <p>Men aged 25–50 with military or first‑responder backgrounds. They often struggle with post‑traumatic stress disorder (PTSD) or trauma-related disorders and turn to alcohol or prescription drugs. The camaraderie of service may make them uncomfortable seeking help outside their circle.</p>
          <h4>What They Are Feeling</h4>
          <ul>
            <li>Hyper‑masculine pressure: service cultures emphasise resilience and discourage vulnerability, leading to shame and isolation <a href="https://www.ncbi.nlm.nih.gov/books/NBK144290/#:~:text=gender%20later%20in%20this%20chapter%29,are%20discussed%20in%20%2025">ncbi.nlm.nih.gov</a>.</li>
            <li>Trauma and grief: unresolved PTSD or traumatic experiences intensify substance use; they may fear re‑experiencing trauma through treatment <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Emotional%20and%20Mental%20Health%20Barriers">neworigins.org</a>.</li>
            <li>Distrust of outsiders: they worry civilian clinicians won’t understand their experiences.</li>
          </ul>
          <h4>Questions They Are Asking</h4>
          <ul>
            <li>“<strong>Do you offer veteran‑specific programs?</strong>” or “<strong>Are staff trained in trauma‑informed care?</strong>” — they require specialized support <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Emotional%20and%20Mental%20Health%20Barriers">neworigins.org</a>.</li>
            <li>“<strong>Is this covered by the VA?</strong>” and “<strong>How do I access my benefits?</strong>” — financial and benefit navigation.</li>
            <li>“<strong>Will my job be protected?</strong>” — first responders need assurance that their career won’t be jeopardized.</li>
            <li>“<strong>Can I trust the confidentiality?</strong>” — concerns about stigma within their professional community.</li>
          </ul>
          <h4>They are Influenced by:</h4>
          <ul>
            <li><strong>Spouse/Partner:</strong> Highlights changes in behaviour and pushes for help; manages home life during deployments or shifts.</li>
            <li><strong>Fellow Veterans/First Responders:</strong> Peer credibility is vital; men are more likely to accept help when encouraged by someone with similar experiences <a href="https://pmc.ncbi.nlm.nih.gov/articles/PMC8661038/#:~:text=their%20lives%20to%20make%20sense,masculine%20stereotypes%20and%20stigma%20attached">pmc.ncbi.nlm.nih.gov</a>.</li>
            <li><strong>VA Case Managers or Chaplains:</strong> Provide referrals and help navigate benefits.</li>
            <li><strong>Mothers/Siblings:</strong> Emotional anchors who provide unconditional support.</li>
          </ul>
          <h4>Influencer Feelings &amp; Questions</h4>
          <ul>
            <li><strong>Spouse/Partner:</strong> Experiences compassion fatigue; fears for partner’s safety and mental health; questions include <strong>“Is there a trauma‑informed environment?,” “Are other veterans in the program?”</strong> and <strong>“How will we communicate during treatment?”</strong>. She may also ask about integrated mental‑health services and family therapy <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Emotional%20and%20Mental%20Health%20Barriers">neworigins.org</a>.</li>
            <li><strong>Veteran Peers:</strong> Want to ensure the program honours service culture and offers peer support; they ask <strong>“Is this program credible?,” “Does it use evidence-based approaches?”</strong> and <strong>“Is there a support group for family?”</strong>.</li>
          </ul>
          <h4>Barriers to Treatment</h4>
          <ul>
            <li><strong>Stigma and Camaraderie:</strong> Fear of appearing weak within the service community; belief they must “tough it out” <a href="https://www.ncbi.nlm.nih.gov/books/NBK144290/#:~:text=gender%20later%20in%20this%20chapter%29,are%20discussed%20in%20%2025">ncbi.nlm.nih.gov</a>.</li>
            <li><strong>Mental‑Health Complexity:</strong> PTSD and co‑occurring disorders require specialized care <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Emotional%20and%20Mental%20Health%20Barriers">neworigins.org</a>.</li>
            <li><strong>Logistical Issues:</strong> Navigating VA benefits and coverage; scheduling treatment around shifts or deployments.</li>
          </ul>`
      },
      {
        id: "professional-under-pressure",
        personName: "James",
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
        barriers: ["Reputation risk", "Time constraints", "Self-reliance and fear of losing control"],
        profile: {
          lifeContext: ["Men aged 25–40", "White-collar jobs or entrepreneurial roles", "May use alcohol or stimulants to cope with stress", "Image-conscious and concerned about reputational risk", "May have high incomes and private insurance"],
          internalExperience: ["Stress and burnout: high performance culture leads to anxiety and reliance on substances to maintain productivity.", "Fear of exposure: they worry that entering treatment will damage their career or professional reputation.", "Desire for discretion: they want confidentiality and flexible scheduling."],
          questions: ["Is sober living confidential?", "Will this affect my job?", "Can I continue working?", "What is the success rate?", "How do you handle relapse?", "Can my partner/family be involved?"],
          influencers: ["Spouse/Partner: Often handles healthcare decisions and may threaten separation if behavior continues; women make up to 90% of healthcare decisions and 59% make healthcare decisions for others.", "Trusted Colleagues or Mentors: Workplace peers who have addressed their own addictions or mental-health struggles can encourage them to seek help.", "Primary Care Physicians/Therapists: They may refer to treatment; confidentiality from these professionals provides reassurance.", "Employee Assistance Programs (EAPs): EAP counsellors may be the first point of contact for discreet help."],
          influencerConcerns: ["Partners: Concerned about financial stability and emotional health. They ask: Will the program respect our privacy? How can I support him without harming my own well-being? What happens if he relapses?", "Colleagues/Mentors: Want to protect his career. They ask about confidentiality and time commitment, and may search: how to confront a coworker about substance use."],
          barriers: ["Reputation Risk: Fear of damaging professional standing; concerns about background checks or stigma.", "Time Constraints: Inability to step away from demanding jobs; need for flexible scheduling.", "Denial and Self-Reliance: Belief they can manage the problem alone; fear of losing control or independence."]
        },
        fullStudyHtml: `<p class="persona-study-name"><strong>James</strong><br>Professional Under Pressure</p>
          <h4>Audience</h4>
          <p>Men aged 25–40 in white‑collar jobs or entrepreneurial roles. They often use alcohol or stimulants to cope with stress. They are image-conscious and worry about reputational risk. They may have high incomes and private insurance.</p>
          <h4>What They Are Feeling</h4>
          <ul>
            <li>Stress and burnout: high performance culture leads to anxiety and reliance on substances to maintain productivity.</li>
            <li>Fear of exposure: they worry that entering treatment will damage their career or professional reputation <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li>Desire for discretion: they want confidentiality and flexible scheduling.</li>
          </ul>
          <h4>Questions They Are Asking</h4>
          <ul>
            <li>“<strong>Is sober living confidential?</strong>” — emphasis on privacy. They want to know who will have access to their information.</li>
            <li>“<strong>Will this affect my job?</strong>” and “<strong>Can I continue working?</strong>” — they look for programs with flexible schedules (evenings, telehealth) and protective policies.</li>
            <li>“<strong>What is the success rate?</strong>” and “<strong>How do you handle relapse?</strong>” — they seek assurance and evidence-based approaches.</li>
            <li>“<strong>Can my partner/family be involved?</strong>” — some want supportive involvement without embarrassing exposure.</li>
          </ul>
          <h4>They are Influenced by:</h4>
          <ul>
            <li><strong>Spouse/Partner:</strong> Often handles healthcare decisions and may threaten separation if behavior continues; women make up to 90 % of healthcare decisions and 59 % make healthcare decisions for others.</li>
            <li><strong>Trusted Colleagues or Mentors:</strong> Workplace peers who have addressed their own addictions or mental‑health struggles can encourage them to seek help.</li>
            <li><strong>Primary Care Physicians/Therapists:</strong> They may refer to treatment; confidentiality from these professionals provides reassurance.</li>
            <li><strong>Employee Assistance Programs (EAPs):</strong> EAP counsellors may be the first point of contact for discreet help.</li>
          </ul>
          <h4>Influencer Feelings &amp; Questions</h4>
          <ul>
            <li><strong>Partners:</strong> Concerned about financial stability and emotional health; they ask <strong>“Will the program respect our privacy?,” “How can I support him without harming my own well-being?,”</strong> and <strong>“What happens if he relapses?”</strong> <a href="https://alcoholtreatment.niaaa.nih.gov/how-to-find-alcohol-treatment/how-to-search-what-to-ask/step-2-ask-10-recommended-questions">alcoholtreatment.niaaa.nih.gov</a>.</li>
            <li><strong>Colleagues/Mentors:</strong> Want to protect his career; they ask about confidentiality and time commitment. They may also search <strong>“how to confront a coworker about substance use.”</strong></li>
          </ul>
          <h4>Barriers to Treatment</h4>
          <ul>
            <li><strong>Reputation Risk:</strong> Fear of damaging professional standing; concerns about background checks or stigma <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Societal%20and%20Cultural%20Pressures">neworigins.org</a>.</li>
            <li><strong>Time Constraints:</strong> Inability to step away from demanding jobs; need for flexible scheduling. <a href="https://neworigins.org/challenges-men-face-substance-abuse-recovery/#:~:text=Work%20and%20Financial%20Responsibilities">neworigins.org</a>.</li>
            <li><strong>Denial and Self-Reliance:</strong> Belief they can manage the problem alone; fear of losing control or independence.</li>
          </ul>`
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
