import { systemConfig } from "./system-config.js";
import { clinicianCore } from "../config/clinician-core.js";
import { varettoConfig } from "../config/varetto.js";

const config = varettoConfig;
const app = document.querySelector("#app");
const dictationDialog = document.querySelector("#dictation-notice");
let activeRecognition = null;
let startedAt = new Date().toISOString();
let submissionId = createId();
let warningsAcknowledged = false;

function createId() {
  return crypto.randomUUID ? crypto.randomUUID() : `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

const esc = (value = "") => String(value)
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;")
  .replaceAll("'", "&#039;");

function choiceHTML(name, options, type = "radio", required = false) {
  return `<div class="inline-options">${options.map(option => `
    <label><input type="${type}" name="${esc(name)}" value="${esc(option.value)}" ${required ? "required aria-required=\"true\"" : ""}> ${esc(option.label)}</label>
  `).join("")}</div>`;
}

function narrativeField(id, label, help = "", required = false) {
  return `<div class="field">
    <label for="${id}" class="${required ? "required" : ""}">${esc(label)}</label>
    ${help ? `<p class="help">${esc(help)}</p>` : ""}
    <textarea id="${id}" name="${id}" data-narrative ${required ? "required aria-required=\"true\"" : ""}></textarea>
    <div class="textarea-tools">
      <button type="button" class="button secondary small dictate" data-target="${id}">Use microphone</button>
      <span class="dictation-state" id="${id}-dictation"></span>
    </div>
  </div>`;
}

function renderArchetypes() {
  return config.archetypes.map(item => `
    <article class="archetype" data-archetype="${item.id}">
      <h3>${esc(item.title)}</h3>
      <p class="situation">${esc(item.situation)}</p>
      <div class="archetype-controls">
        <div class="control-group">
          <span class="required">Clinical relationship</span>
          ${choiceHTML(`archetype.${item.id}.relationship`, clinicianCore.relationshipOptions, "radio", true)}
        </div>
        <div class="control-group">
          <span class="required">Future caseload / service mix</span>
          ${choiceHTML(`archetype.${item.id}.caseload`, clinicianCore.caseloadOptions, "radio", true)}
        </div>
        <div class="control-group">
          <span class="required">Website priority</span>
          ${choiceHTML(`archetype.${item.id}.market`, [
            { value: "yes", label: "Actively reach" },
            { value: "no", label: "Not a priority" }
          ], "radio", true)}
        </div>
      </div>
      <div class="field" style="margin-top:14px">
        <label for="note-${item.id}">Anything important about this situation?</label>
        <textarea id="note-${item.id}" name="archetype.${item.id}.note" rows="2" data-narrative></textarea>
        <div class="textarea-tools"><button type="button" class="button secondary small dictate" data-target="note-${item.id}">Use microphone</button><span class="dictation-state" id="note-${item.id}-dictation"></span></div>
      </div>
    </article>
  `).join("");
}

function renderOwnerQuestions() {
  return (config.ownerQuestions || []).map(([id, label]) => narrativeField(id, label, "", true)).join("");
}

function renderPatternQuestions(patternId) {
  return clinicianCore.patternQuestionGroups.map(group => `
    <div class="pattern-question-group">
      <p class="pattern-group-label">${esc(group.label)}</p>
      <p class="help">${esc(group.help)}</p>
      ${group.questions.map(([key, label]) => narrativeField(`${patternId}-${key}`, label, "", ["turningPoint", "preclinicalLanguage", "trustNeed", "realQuestion"].includes(key))).join("")}
    </div>
  `).join("");
}

function render() {
  app.innerHTML = `
    <section class="form-intro">
      <p class="eyebrow">${esc(config.clientLabel)} · Clinician Discovery</p>
      <h1>${esc(config.intro.title)}</h1>
      <p class="lede">${esc(config.intro.lead)}</p>
      <p>${esc(config.intro.purpose)}</p>
      <div class="meta-strip">
        <span class="meta-pill">About ${esc(systemConfig.estimatedMinutes)} minutes</span>
        <span class="meta-pill">Typing or microphone dictation</span>
        <span class="meta-pill">Draft saves in this browser</span>
      </div>
      <div class="notice">
        <strong>Please keep examples anonymous.</strong>
        <p>Describe recurring patterns and situations, but do not include names, contact information, dates of birth, exact dates, unusual combinations of details, or anything else that could identify an individual.</p>
        <p>Your response is intended for oobCREATIVE and authorized Varetto project participants for audience, service, and website strategy. It is not a patient record or clinical assessment.</p>
      </div>
      ${!systemConfig.submissionEndpoint ? `<div class="runtime-banner"><strong>Setup note:</strong> central data storage is not connected yet. This build is for review and testing. Final submission will be enabled after the private Bluehost endpoint is configured.</div>` : ""}
    </section>

    <div class="progress-wrap">
      <div class="progress-row"><span>Your progress</span><span id="progress-label">0%</span></div>
      <div class="progress-track"><div class="progress-bar" id="progress-bar"></div></div>
    </div>

    <form id="discovery-form" novalidate>
      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">1 · You and the practice</p><h2>A little context first.</h2><p>This helps keep the source material attributable without asking the questionnaire to make assumptions from a title or credential.</p></div>
        <div class="field"><label class="required" for="respondentName">Your name</label><input id="respondentName" name="respondent.name" type="text" required></div>
        <div class="field"><label for="respondentEmail">Email</label><input id="respondentEmail" name="respondent.email" type="email"></div>
        <div class="field"><label class="required" for="role">Role / title</label><input id="role" name="respondent.role" type="text" required></div>
        <div class="field"><label class="required" for="perspective">Perspective for this response</label><select id="perspective" name="respondent.perspective" required><option value="">Choose one</option><option value="clinician">Clinician / clinical leader</option><option value="owner">Practice owner / business leader</option><option value="both">Both clinical and business leadership</option></select><p class="help">This determines which questions you see. It also keeps clinical fit and business priority from being treated as the same judgment.</p></div>
        ${narrativeField("practicePurpose", "In your own words, what do you want Varetto's clinical practice to exist to do?", "", true)}
        ${narrativeField("idealInquiry", "What kind of inquiry would make you think, “Yes—this is exactly someone we should be talking with”?", "Describe the person or situation, not just a diagnosis.", true)}
        ${narrativeField("wrongFitInquiry", "What kind of inquiry may sound relevant on paper but is actually a poor fit for Varetto?", "", true)}
      </section>

      <section class="form-section perspective-section hidden" data-section data-perspectives="owner,both">
        <div class="section-copy"><p class="eyebrow">2 · Business direction</p><h2>What should Varetto build toward?</h2><p>These questions are about business priority and responsible growth. They intentionally remain separate from any one clinician’s personal caseload preference.</p></div>
        ${renderOwnerQuestions()}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">Patient situations</p><h2>Which of these people belong in Varetto’s work?</h2><p>These are working hypotheses, not conclusions or service promises. Classify every situation independently. Clinical fit, desired service mix, and website priority are separate judgments; disagreement between them is useful.</p></div>
        <div class="archetype-list">${renderArchetypes()}</div>
        ${narrativeField("missingSituations", "Who do you work with regularly—or want to work with—who is not represented above?", "Add as many missing situations as you need.")}
      </section>

      <section class="form-section perspective-section hidden" data-section data-perspectives="clinician,both">
        <div class="section-copy"><p class="eyebrow">Clinical fit</p><h2>Where does fit become more than capability?</h2><p>I’m interested in work that creates real engagement for you and the patient—not simply what falls within scope. Honest limits improve the strategy; they are not a judgment about a patient’s worthiness of care.</p></div>
        ${narrativeField("strongestWork", "Where do you believe you do your strongest clinical work? What makes you say that?", "", true)}
        ${narrativeField("energy", "Which kinds of patients, situations, or therapeutic problems tend to create energy and engagement for you?")}
        ${narrativeField("progress", "Where do you repeatedly see meaningful progress? What patterns have you noticed?")}
        ${narrativeField("trust", "With whom do you seem especially able to establish trust or honesty?")}
        ${narrativeField("strain", "Which work tends to create strain, frustration, weak engagement, or a poorer fit—even if it is technically within your scope?", "", true)}
        ${narrativeField("wantMore", "What would you genuinely like more of in your future caseload?")}
        ${narrativeField("wantLess", "What would you like less of in your future caseload?")}
        ${narrativeField("boundaries", "What clinical, ethical, scope, availability, or personal-interest boundaries should I understand before turning any of this into audience strategy?", "", true)}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">4 · Patient patterns</p><h2>Tell me about people, not cases.</h2><p>Add a pattern when the person’s situation, motivation, resistance, trust needs, or desired change is meaningfully different. Use generalized experience only—no identifying details.</p></div>
        <div id="patterns"></div>
        <button type="button" id="add-pattern" class="button secondary">Add another patient pattern</button>
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">5 · Practice reality</p><h2>What needs to be true in the real practice?</h2><p>This information validates and constrains later strategy. It should not automatically become the website's lead message.</p></div>
        ${narrativeField("launchServices", "What clinical services are actually expected to be available at launch?", "", true)}
        ${narrativeField("credentials", "Which credentials, licenses, specialties, or professional experience should be understood when evaluating what Varetto can responsibly claim?")}
        ${narrativeField("approaches", "What treatment approaches, philosophies, or modalities materially shape the work?", "Include only what matters for understanding fit—not every technique you can use.")}
        ${narrativeField("recoveryPosition", "How should I understand the relationship among abstinence, harm reduction, self-directed recovery, and Varetto's clinical philosophy?")}
        ${narrativeField("availability", "What practical realities matter: states served, telehealth/in-person, scheduling, capacity, payer model, ages, formats, or other limitations?", "", true)}
        ${narrativeField("referralBoundary", "When Varetto is not the right place, what kinds of needs should clearly be referred elsewhere?", "", true)}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">6 · Last look</p><h2>What have I not asked that would change how I understand your patients or your best work?</h2></div>
        ${narrativeField("finalThoughts", "Anything else I should understand before I begin identifying audience/persona hypotheses?")}
      </section>

      <div id="validation-output" class="notice hidden"></div>
      <div class="privacy-confirmation">
        <label><input type="checkbox" id="privacy-acknowledgment" name="privacyAcknowledgment" value="yes" required> I have kept patient examples anonymous and understand how this response will be used.</label>
      </div>
      <div class="form-actions">
        <div>
          <button type="button" id="save-backup" class="button secondary">Download response backup</button>
          <button type="button" id="clear-draft" class="text-button">Clear saved draft</button>
          <p class="backup-warning">Downloaded backups are unencrypted. Store or share them carefully.</p>
          <div id="status" class="status" role="status"></div>
        </div>
        <button type="submit" id="submit-button" class="button" ${!systemConfig.submissionEndpoint ? "disabled" : ""}>Submit discovery</button>
      </div>
    </form>
  `;

  wireEvents();
  restoreDraft();
  if (!document.querySelector(".pattern-card")) addPattern();
  updatePerspective();
  updateProgress();
}

function addPattern(existing = {}) {
  const container = document.querySelector("#patterns");
  const index = container.children.length;
  const id = `pattern-${Date.now()}-${index}`;
  const card = document.createElement("article");
  card.className = "pattern-card";
  card.dataset.patternId = id;
  card.innerHTML = `
    <button type="button" class="button secondary small remove-pattern">Remove</button>
    <p class="eyebrow">Patient pattern ${index + 1}</p>
    ${narrativeField(`${id}-label`, "Give this person/situation a short working name.", "", true)}
    ${renderPatternQuestions(id)}
  `;
  container.append(card);
  card.querySelector(".remove-pattern").addEventListener("click", () => {
    card.remove(); renumberPatterns(); autosave(); updateProgress();
  });
  wireDictation(card);
  if (Object.keys(existing).length) {
    Object.entries(existing).forEach(([key, value]) => {
      const el = [...card.querySelectorAll("textarea")].find(node => node.id.endsWith(`-${key}`));
      if (el) el.value = value;
    });
  }
}

function renumberPatterns() {
  document.querySelectorAll(".pattern-card").forEach((card, i) => {
    const eyebrow = card.querySelector(".eyebrow");
    if (eyebrow) eyebrow.textContent = `Patient pattern ${i + 1}`;
  });
}

function wireEvents() {
  const form = document.querySelector("#discovery-form");
  const changed = () => { warningsAcknowledged = false; autosave(); updatePerspective(); updateProgress(); };
  form.addEventListener("input", changed);
  form.addEventListener("change", changed);
  form.addEventListener("submit", submitForm);
  document.querySelector("#add-pattern").addEventListener("click", () => { addPattern(); autosave(); updateProgress(); });
  document.querySelector("#save-backup").addEventListener("click", () => downloadJson(buildSubmission(), "draft"));
  document.querySelector("#clear-draft").addEventListener("click", clearDraft);
  wireDictation(document);
}

function updatePerspective() {
  const perspective = document.querySelector("#perspective")?.value || "";
  document.querySelectorAll("[data-perspectives]").forEach(section => {
    const visible = section.dataset.perspectives.split(",").includes(perspective);
    section.classList.toggle("hidden", !visible);
    section.querySelectorAll("[required]").forEach(field => field.disabled = !visible);
  });
}

function wireDictation(scope) {
  scope.querySelectorAll(".dictate").forEach(button => {
    if (button.dataset.wired) return;
    button.dataset.wired = "true";
    button.addEventListener("click", () => startDictation(button.dataset.target));
  });
}

async function startDictation(targetId) {
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  const target = document.getElementById(targetId);
  const state = document.getElementById(`${targetId}-dictation`);
  if (!Recognition) {
    if (state) state.textContent = "Speech recognition is not available in this browser. Typing still works.";
    return;
  }
  if (!localStorage.getItem(systemConfig.dictationNoticeKey)) {
    dictationDialog.showModal();
    const result = await new Promise(resolve => {
      dictationDialog.addEventListener("close", () => resolve(dictationDialog.returnValue), { once: true });
    });
    if (result !== "continue") return;
    localStorage.setItem(systemConfig.dictationNoticeKey, "accepted");
  }
  if (activeRecognition) activeRecognition.stop();
  const recognition = new Recognition();
  recognition.lang = "en-US";
  recognition.continuous = true;
  recognition.interimResults = true;
  let finalTranscript = "";
  const original = target.value.trim();
  activeRecognition = recognition;
  if (state) state.textContent = "Listening…";
  recognition.onresult = event => {
    let interim = "";
    for (let i = event.resultIndex; i < event.results.length; i++) {
      const text = event.results[i][0].transcript;
      if (event.results[i].isFinal) finalTranscript += text + " "; else interim += text;
    }
    target.value = [original, finalTranscript.trim(), interim.trim()].filter(Boolean).join(" ");
    target.dispatchEvent(new Event("input", { bubbles: true }));
  };
  recognition.onerror = event => { if (state) state.textContent = `Microphone stopped: ${event.error}.`; };
  recognition.onend = () => { activeRecognition = null; if (state) state.textContent = "Dictation added. Edit anything you want."; };
  recognition.start();
}

function collectNamedFields() {
  const data = {};
  document.querySelectorAll("#discovery-form [name]").forEach(field => {
    if ((field.type === "radio" || field.type === "checkbox") && !field.checked) return;
    data[field.name] = field.value;
  });
  return data;
}

function collectArchetypes(named) {
  return config.archetypes.map(item => ({
    id: item.id,
    title: item.title,
    situation: item.situation,
    relationship: named[`archetype.${item.id}.relationship`] || "",
    caseload: named[`archetype.${item.id}.caseload`] || "",
    websitePriority: named[`archetype.${item.id}.market`] || "",
    note: named[`archetype.${item.id}.note`] || ""
  }));
}

function collectPatterns() {
  return [...document.querySelectorAll(".pattern-card")].map(card => {
    const result = {};
    card.querySelectorAll("textarea").forEach(textarea => {
      const suffix = textarea.id.substring(card.dataset.patternId.length + 1);
      result[suffix] = textarea.value.trim();
    });
    return result;
  }).filter(pattern => Object.values(pattern).some(Boolean));
}

function buildSubmission() {
  const named = collectNamedFields();
  const narrative = {};
  Object.entries(named).forEach(([key, value]) => {
    if (!key.startsWith("archetype.") && !key.startsWith("respondent.") && key !== "privacyAcknowledgment") narrative[key] = value;
  });
  return {
    submissionId,
    system: systemConfig.system,
    discoveryType: systemConfig.discoveryType,
    systemVersion: systemConfig.version,
    questionnaireVersion: config.questionnaireVersion,
    client: { id: config.clientId, label: config.clientLabel },
    respondent: {
      name: named["respondent.name"] || "",
      email: named["respondent.email"] || "",
      role: named["respondent.role"] || "",
      perspective: named["respondent.perspective"] || ""
    },
    timing: { startedAt, generatedAt: new Date().toISOString() },
    archetypes: collectArchetypes(named),
    patientPatterns: collectPatterns(),
    narrative,
    sourceIntegrity: {
      responseType: "clinician-self-report",
      patientIdentifyingInformationRequested: false,
      interpretationIncluded: false,
      respondentHypothesisIncluded: true,
      evidenceModel: "reported-observation-and-informed-hypothesis"
    }
  };
}

function validate(submission) {
  const errors = [];
  const warnings = [];
  const visibleRequired = [...document.querySelectorAll("#discovery-form [required]:not([disabled]):not([type=radio])")];
  const missing = visibleRequired.filter(field => field.type === "checkbox" ? !field.checked : !field.value.trim());
  if (missing.length) errors.push(`Please complete the ${missing.length} required ${missing.length === 1 ? "response" : "responses"} marked with an asterisk.`);
  const email = document.querySelector("#respondentEmail");
  if (email?.value && !email.validity.valid) errors.push("Please enter a valid email address or leave the email field blank.");
  if (submission.archetypes.some(item => !item.relationship || !item.caseload || !item.websitePriority)) {
    errors.push("Please classify clinical fit, future service mix, and website priority for every patient situation. Choose “Depends / needs more context” when that is the honest answer.");
  }
  if (!submission.patientPatterns.length) errors.push("Please complete at least one patient pattern.");
  submission.archetypes.forEach(item => {
    if (item.websitePriority === "yes" && item.relationship !== "strong-fit") warnings.push(`“${item.title}” is marked as a website priority but not as a strong clinical fit. That may be intentional; confirm the distinction before submitting.`);
  });
  if (submission.archetypes.every(a => a.websitePriority !== "yes")) warnings.push("No patient situations are marked as website priorities. That may be accurate; confirm before submitting.");
  return { errors, warnings };
}

function showValidation({ errors, warnings }) {
  const box = document.querySelector("#validation-output");
  if (!errors.length && !warnings.length) { box.classList.add("hidden"); box.innerHTML = ""; return; }
  box.classList.remove("hidden");
  box.innerHTML = `${errors.length ? `<strong>Please correct:</strong><ul class="warning-list">${errors.map(x => `<li>${esc(x)}</li>`).join("")}</ul>` : ""}${warnings.length ? `<strong>Please review:</strong><ul class="warning-list">${warnings.map(x => `<li>${esc(x)}</li>`).join("")}</ul>${!errors.length && !warningsAcknowledged ? `<button type="button" id="confirm-warnings" class="button secondary small">Submit with these distinctions</button>` : ""}` : ""}`;
  document.querySelector("#confirm-warnings")?.addEventListener("click", () => {
    warningsAcknowledged = true;
    document.querySelector("#discovery-form").requestSubmit();
  });
  box.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function submitForm(event) {
  event.preventDefault();
  const submission = buildSubmission();
  const validation = validate(submission);
  showValidation(validation);
  if (validation.errors.length || (validation.warnings.length && !warningsAcknowledged)) return;
  if (!systemConfig.submissionEndpoint) { setStatus("Central submission is not connected yet. Download a response backup for testing.", "error"); return; }
  autosave();
  const submitButton = document.querySelector("#submit-button");
  submitButton.disabled = true;
  setStatus("Submitting…");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), systemConfig.submissionTimeoutMs);
  try {
    const response = await fetch(systemConfig.submissionEndpoint, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(submission), signal: controller.signal });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.requestId ? `Submission failed. Reference ${result.requestId}.` : `Submission failed (${response.status}).`);
    localStorage.removeItem(systemConfig.storageKey);
    setStatus(result.duplicate ? "This response had already been received. No duplicate was created." : "Submitted successfully. Thank you.", "success");
    submitButton.textContent = "Submitted";
  } catch (error) {
    submitButton.disabled = false;
    const message = error.name === "AbortError" ? "The connection timed out." : error.message;
    setStatus(`${message} Your draft remains saved. Select Submit discovery to retry.`, "error");
  } finally {
    clearTimeout(timeout);
  }
}

function autosave() {
  const draft = { submissionId, startedAt, fields: collectNamedFields(), patterns: collectPatterns(), savedAt: new Date().toISOString() };
  try {
    localStorage.setItem(systemConfig.storageKey, JSON.stringify(draft));
    setStatus("Draft saved in this browser.");
  } catch {
    setStatus("This browser could not save the draft. Download a backup before leaving this page.", "error");
  }
}

function restoreDraft() {
  const raw = localStorage.getItem(systemConfig.storageKey);
  if (!raw) return;
  try {
    const draft = JSON.parse(raw);
    const savedAt = Date.parse(draft.savedAt || "");
    if (!savedAt || Date.now() - savedAt > systemConfig.draftRetentionDays * 86400000) {
      localStorage.removeItem(systemConfig.storageKey);
      setStatus("An expired browser draft was removed.");
      return;
    }
    if (draft.submissionId) submissionId = draft.submissionId;
    if (draft.startedAt) startedAt = draft.startedAt;
    Object.entries(draft.fields || {}).forEach(([name, value]) => {
      document.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach(el => {
        if (el.type === "radio" || el.type === "checkbox") el.checked = el.value === value; else el.value = value;
      });
    });
    (draft.patterns || []).forEach(pattern => addPattern(pattern));
    setStatus("Draft restored from this browser.");
  } catch { localStorage.removeItem(systemConfig.storageKey); }
}

function clearDraft() {
  if (!window.confirm("Clear the saved draft from this browser? This cannot be undone unless you downloaded a backup.")) return;
  localStorage.removeItem(systemConfig.storageKey);
  window.location.reload();
}

function updateProgress() {
  const fields = [...document.querySelectorAll("#discovery-form [required]:not([disabled]):not([type=radio])")];
  const radioNames = [...new Set([...document.querySelectorAll("#discovery-form input[type=radio]")].map(x => x.name))];
  const textDone = fields.filter(field => field.type === "checkbox" ? field.checked : field.value.trim()).length;
  const radioDone = radioNames.filter(name => document.querySelector(`[name="${CSS.escape(name)}"]:checked`)).length;
  const total = fields.length + radioNames.length;
  const percent = total ? Math.round(((textDone + radioDone) / total) * 100) : 0;
  document.querySelector("#progress-bar").style.width = `${percent}%`;
  document.querySelector("#progress-label").textContent = `${percent}%`;
}

function downloadJson(payload, label) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${config.clientId}-clinician-discovery-${label}-${new Date().toISOString().slice(0,10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
}

function setStatus(message, type = "") {
  const status = document.querySelector("#status");
  if (!status) return;
  status.textContent = message;
  status.className = `status ${type}`.trim();
}

render();
