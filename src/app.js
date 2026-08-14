import { systemConfig } from "./system-config.js";
import { clinicianCore } from "../config/clinician-core.js";
import { varettoConfig } from "../config/varetto.js";

const config = varettoConfig;
const app = document.querySelector("#app");
const dictationDialog = document.querySelector("#dictation-notice");
let activeRecognition = null;
let startedAt = new Date().toISOString();

const esc = (value = "") => String(value)
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;")
  .replaceAll("'", "&#039;");

function choiceHTML(name, options, type = "radio") {
  return `<div class="inline-options">${options.map(option => `
    <label><input type="${type}" name="${esc(name)}" value="${esc(option.value)}"> ${esc(option.label)}</label>
  `).join("")}</div>`;
}

function narrativeField(id, label, help = "") {
  return `<div class="field">
    <label for="${id}">${esc(label)}</label>
    ${help ? `<p class="help">${esc(help)}</p>` : ""}
    <textarea id="${id}" name="${id}" data-narrative></textarea>
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
          <span>Clinical relationship</span>
          ${choiceHTML(`archetype.${item.id}.relationship`, clinicianCore.relationshipOptions)}
        </div>
        <div class="control-group">
          <span>Future caseload</span>
          ${choiceHTML(`archetype.${item.id}.caseload`, clinicianCore.caseloadOptions)}
        </div>
        <div class="control-group">
          <span>Website priority</span>
          ${choiceHTML(`archetype.${item.id}.market`, [
            { value: "yes", label: "Actively reach" },
            { value: "no", label: "Not a priority" }
          ])}
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
        <p>Describe patterns and situations, but do not include patient names, contact information, dates of birth, or other details that could identify an individual.</p>
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
        <div class="field"><label for="role">Role / title</label><input id="role" name="respondent.role" type="text"></div>
        ${narrativeField("practicePurpose", "In your own words, what do you want Varetto's clinical practice to exist to do?")}
        ${narrativeField("idealInquiry", "What kind of inquiry would make you think, “Yes—this is exactly someone we should be talking with”?", "Describe the person or situation, not just a diagnosis.")}
        ${narrativeField("wrongFitInquiry", "What kind of inquiry may sound relevant on paper but is actually a poor fit for Varetto?")}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">2 · Recognizable patient situations</p><h2>Which of these people do you recognize in your work?</h2><p>These are working hypotheses, not conclusions or service promises. There is no target number. Classify each independently and tell me where the language or assumption is wrong.</p></div>
        <div class="archetype-list">${renderArchetypes()}</div>
        ${narrativeField("missingSituations", "Who do you work with regularly—or want to work with—who is not represented above?", "Add as many missing situations as you need.")}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">3 · Your strongest work</p><h2>Where does fit become more than capability?</h2><p>I’m interested in the work that creates real engagement for you and the patient—not simply what falls within scope.</p></div>
        ${narrativeField("strongestWork", "Where do you believe you do your strongest clinical work? What makes you say that?")}
        ${narrativeField("energy", "Which kinds of patients, situations, or therapeutic problems tend to create energy and engagement for you?")}
        ${narrativeField("progress", "Where do you repeatedly see meaningful progress? What patterns have you noticed?")}
        ${narrativeField("trust", "With whom do you seem especially able to establish trust or honesty?")}
        ${narrativeField("strain", "Which work tends to create strain, frustration, weak engagement, or a poorer fit—even if it is technically within your scope?")}
        ${narrativeField("wantMore", "What would you genuinely like more of in your future caseload?")}
        ${narrativeField("wantLess", "What would you like less of in your future caseload?")}
        ${narrativeField("boundaries", "What clinical, ethical, scope, availability, or personal-interest boundaries should I understand before turning any of this into audience strategy?")}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">4 · Patient patterns</p><h2>Tell me about people, not cases.</h2><p>Add a pattern when the person’s situation, motivation, resistance, trust needs, or desired change is meaningfully different. Use generalized experience only—no identifying details.</p></div>
        <div id="patterns"></div>
        <button type="button" id="add-pattern" class="button secondary">Add another patient pattern</button>
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">5 · Practice reality</p><h2>What needs to be true in the real practice?</h2><p>This information validates and constrains later strategy. It should not automatically become the website's lead message.</p></div>
        ${narrativeField("launchServices", "What clinical services are actually expected to be available at launch?")}
        ${narrativeField("credentials", "Which credentials, licenses, specialties, or professional experience should be understood when evaluating what Varetto can responsibly claim?")}
        ${narrativeField("approaches", "What treatment approaches, philosophies, or modalities materially shape the work?", "Include only what matters for understanding fit—not every technique you can use.")}
        ${narrativeField("recoveryPosition", "How should I understand the relationship among abstinence, harm reduction, self-directed recovery, and Varetto's clinical philosophy?")}
        ${narrativeField("availability", "What practical realities matter: states served, telehealth/in-person, scheduling, capacity, payer model, ages, formats, or other limitations?")}
        ${narrativeField("referralBoundary", "When Varetto is not the right place, what kinds of needs should clearly be referred elsewhere?")}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy"><p class="eyebrow">6 · Last look</p><h2>What have I not asked that would change how I understand your patients or your best work?</h2></div>
        ${narrativeField("finalThoughts", "Anything else I should understand before I begin identifying audience/persona hypotheses?")}
      </section>

      <div id="validation-output" class="notice hidden"></div>
      <div class="form-actions">
        <div>
          <button type="button" id="save-backup" class="button secondary">Download response backup</button>
          <div id="status" class="status" role="status"></div>
        </div>
        <button type="submit" id="submit-button" class="button" ${!systemConfig.submissionEndpoint ? "disabled" : ""}>Submit discovery</button>
      </div>
    </form>
  `;

  wireEvents();
  restoreDraft();
  if (!document.querySelector(".pattern-card")) addPattern();
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
    ${narrativeField(`${id}-label`, "Give this person/situation a short working name.")}
    ${clinicianCore.patternQuestions.map(([key, label]) => narrativeField(`${id}-${key}`, label)).join("")}
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
  form.addEventListener("input", () => { autosave(); updateProgress(); });
  form.addEventListener("change", () => { autosave(); updateProgress(); });
  form.addEventListener("submit", submitForm);
  document.querySelector("#add-pattern").addEventListener("click", () => { addPattern(); autosave(); updateProgress(); });
  document.querySelector("#save-backup").addEventListener("click", () => downloadJson(buildSubmission(), "draft"));
  wireDictation(document);
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
    if (!key.startsWith("archetype.") && !key.startsWith("respondent.")) narrative[key] = value;
  });
  return {
    submissionId: crypto.randomUUID ? crypto.randomUUID() : `local-${Date.now()}`,
    system: systemConfig.system,
    discoveryType: systemConfig.discoveryType,
    systemVersion: systemConfig.version,
    questionnaireVersion: config.questionnaireVersion,
    client: { id: config.clientId, label: config.clientLabel },
    respondent: {
      name: named["respondent.name"] || "",
      email: named["respondent.email"] || "",
      role: named["respondent.role"] || ""
    },
    timing: { startedAt, generatedAt: new Date().toISOString() },
    archetypes: collectArchetypes(named),
    patientPatterns: collectPatterns(),
    narrative,
    sourceIntegrity: {
      responseType: "clinician-self-report",
      patientIdentifyingInformationRequested: false,
      interpretationIncluded: false
    }
  };
}

function validate(submission) {
  const errors = [];
  const warnings = [];
  if (!submission.respondent.name.trim()) errors.push("Please add your name.");
  submission.archetypes.forEach(item => {
    if (item.websitePriority === "yes" && item.relationship !== "strong-fit") warnings.push(`“${item.title}” is marked as a website priority but not as a strong clinical fit. Please confirm that distinction is intentional.`);
  });
  if (submission.archetypes.filter(a => a.websitePriority === "yes").length === 0) warnings.push("No patient situations are marked as website priorities. That may be accurate; confirm before submitting.");
  return { errors, warnings };
}

function showValidation({ errors, warnings }) {
  const box = document.querySelector("#validation-output");
  if (!errors.length && !warnings.length) { box.classList.add("hidden"); box.innerHTML = ""; return; }
  box.classList.remove("hidden");
  box.innerHTML = `${errors.length ? `<strong>Please correct:</strong><ul class="warning-list">${errors.map(x => `<li>${esc(x)}</li>`).join("")}</ul>` : ""}${warnings.length ? `<strong>Please review:</strong><ul class="warning-list">${warnings.map(x => `<li>${esc(x)}</li>`).join("")}</ul>` : ""}`;
  box.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function submitForm(event) {
  event.preventDefault();
  const submission = buildSubmission();
  const validation = validate(submission);
  showValidation(validation);
  if (validation.errors.length || validation.warnings.length) return;
  if (!systemConfig.submissionEndpoint) { setStatus("Central submission is not connected yet. Download a response backup for testing.", "error"); return; }
  const submitButton = document.querySelector("#submit-button");
  submitButton.disabled = true;
  setStatus("Submitting…");
  try {
    const response = await fetch(systemConfig.submissionEndpoint, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify(submission) });
    if (!response.ok) throw new Error(`Submission failed (${response.status})`);
    localStorage.removeItem(systemConfig.storageKey);
    setStatus("Submitted successfully. Thank you.", "success");
    submitButton.textContent = "Submitted";
  } catch {
    submitButton.disabled = false;
    setStatus("Your response was not submitted. Your draft remains saved in this browser.", "error");
  }
}

function autosave() {
  const draft = { startedAt, fields: collectNamedFields(), patterns: collectPatterns(), savedAt: new Date().toISOString() };
  localStorage.setItem(systemConfig.storageKey, JSON.stringify(draft));
  setStatus("Draft saved in this browser.");
}

function restoreDraft() {
  const raw = localStorage.getItem(systemConfig.storageKey);
  if (!raw) return;
  try {
    const draft = JSON.parse(raw);
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

function updateProgress() {
  const fields = [...document.querySelectorAll("#discovery-form input[type=text], #discovery-form input[type=email], #discovery-form textarea")];
  const radioNames = [...new Set([...document.querySelectorAll("#discovery-form input[type=radio]")].map(x => x.name))];
  const textDone = fields.filter(f => f.value.trim()).length;
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
