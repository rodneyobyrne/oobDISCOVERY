import { systemConfig } from "./system-config.js?v=0.2.1";
import { clinicianCore } from "../config/clinician-core.js?v=0.2.1";
import { varettoConfig } from "../config/varetto.js?v=0.2.1";

const config = varettoConfig;
const app = document.querySelector("#app");
const dictationDialog = document.querySelector("#dictation-notice");
const patternCache = new Map();
let activeRecognition = null;
let autosaveTimer = null;
let startedAt = new Date().toISOString();
let submissionId = createId();

function createId() {
  return crypto.randomUUID ? crypto.randomUUID() : `local-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

const esc = (value = "") => String(value)
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;")
  .replaceAll("'", "&#039;");

function narrativeField(id, label, help = "", questionNumber = "", questionKey = "") {
  const questionAttribute = questionKey ? ` data-question="${esc(questionKey)}"` : "";
  return `<div class="field"${questionAttribute}>
    <label for="${esc(id)}">${questionNumber ? `<span class="question-number">${esc(questionNumber)}</span>` : ""}${esc(label)}</label>
    ${help ? `<p class="help">${esc(help)}</p>` : ""}
    <textarea id="${esc(id)}" name="${esc(id)}" data-narrative></textarea>
    <div class="textarea-tools">
      <button type="button" class="button secondary small dictate" data-target="${esc(id)}">Use microphone</button>
      <span class="dictation-state" id="${esc(id)}-dictation"></span>
    </div>
  </div>`;
}

function checkboxList(prefix, options, className = "choice-list") {
  return `<div class="${esc(className)}">${options.map(option => `
    <label>
      <input type="checkbox" name="${esc(prefix)}.${esc(option.id)}" value="${esc(option.id)}">
      <span>${esc(option.label)}</span>
    </label>
  `).join("")}</div>`;
}

function renderAudienceChoices() {
  return config.audienceSituations.map(item => `
    <label class="situation-choice">
      <input type="checkbox" name="audience.${esc(item.id)}" value="${esc(item.id)}" data-audience-choice>
      <span class="situation-choice-copy">
        <strong>${esc(item.title)}</strong>
        <span>${esc(item.situation)}</span>
      </span>
    </label>
  `).join("");
}

function renderPatternQuestion(audienceId, question) {
  const questionKey = `${audienceId}-${question.number}`;
  if (question.key === "associatedConditions") {
    return `<fieldset class="field condition-field" data-question="${esc(questionKey)}">
      <legend><span class="question-number">${question.number}</span>${esc(question.label)}</legend>
      <p class="help">${esc(question.help)}</p>
      ${checkboxList(`pattern.${audienceId}.conditions`, config.conditionOptions, "choice-list compact")}
    </fieldset>`;
  }
  return narrativeField(
    `pattern-${audienceId}-${question.key}`,
    question.label,
    question.help || "",
    question.number,
    questionKey
  ).replace("data-narrative", `data-narrative data-pattern-key="${esc(question.key)}"`);
}

function renderPatternCard(item, index) {
  return `<article class="pattern-card" data-audience-id="${esc(item.id)}">
    <p class="eyebrow">Priority audience ${index + 1}</p>
    <h3>${esc(item.title)}</h3>
    <p class="pattern-situation">${esc(item.situation)}</p>
    <div class="pattern-questions">
      ${clinicianCore.patternQuestions.map(question => renderPatternQuestion(item.id, question)).join("")}
    </div>
  </article>`;
}

function render() {
  app.innerHTML = `
    <section class="form-intro">
      <p class="eyebrow">${esc(config.clientLabel)} · Therapy website discovery</p>
      <h1>${esc(config.intro.title)}</h1>
      <p class="lede">${esc(config.intro.lead)}</p>
      <p>${esc(config.intro.purpose)}</p>
      <div class="meta-strip">
        <span class="meta-pill">${esc(systemConfig.estimatedMinutes)} minutes</span>
        <span class="meta-pill">Every discovery question is optional</span>
        <span class="meta-pill">Draft saves in this browser</span>
      </div>
      <div class="notice">
        <strong>Please keep examples anonymous.</strong>
        <p>Describe recurring patterns and situations, but do not include names, contact information, dates of birth, exact dates, unusual combinations of details, or anything else that could identify an individual.</p>
        <p>This is website discovery—not a patient intake, clinical assessment, or request for patient records. “Unsure” and unanswered questions are both acceptable.</p>
      </div>
      ${!systemConfig.submissionEndpoint ? `<div class="runtime-banner"><strong>Setup note:</strong> central data storage is not connected. Download a response backup instead.</div>` : ""}
    </section>

    <div class="progress-wrap">
      <div class="progress-row"><span>Your progress</span><span id="progress-label">0 of 8 questions answered</span></div>
      <div class="progress-track"><div class="progress-bar" id="progress-bar"></div></div>
    </div>

    <form id="discovery-form" novalidate>
      <section class="form-section respondent-section">
        <div class="section-copy">
          <p class="eyebrow">Your response</p>
          <h2>Who is responding?</h2>
          <p>These fields help Rodney distinguish the two responses. They are optional.</p>
        </div>
        <div class="field"><label for="respondentName">Name</label><input id="respondentName" name="respondent.name" type="text" autocomplete="name"></div>
        <div class="field"><label for="respondentEmail">Email</label><input id="respondentEmail" name="respondent.email" type="email" autocomplete="email"></div>
      </section>

      <section class="form-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">1 · Services</p>
          <h2>What will Varetto Therapy offer?</h2>
          <p>Start with what will actually be available. We can refine terminology later.</p>
        </div>
        <fieldset class="field" data-question="1">
          <legend><span class="question-number">1</span>Which therapy services will Varetto offer when the website launches?</legend>
          ${checkboxList("service", config.serviceOptions)}
        </fieldset>
        <fieldset class="field" data-question="2">
          <legend><span class="question-number">2</span>Who can receive those services?</legend>
          ${checkboxList("recipient", config.recipientOptions)}
        </fieldset>
        ${narrativeField("practicalLimits", "What else should I understand about the services, availability, or limits?", "Add any service not listed. Consider age, location, licensure, in-person or virtual availability, payment, scheduling, stability, and level of care.", 3, "3")}
      </section>

      <section class="form-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">2 · Audience</p>
          <h2>Which people should the website understand especially well?</h2>
          <p>Choose up to three situations. Select the closest match even if the wording is not perfect; the next section gives you room to clarify it.</p>
        </div>
        <fieldset class="field" data-question="4">
          <legend><span class="question-number">4</span>Which situations best represent the people Varetto wants the website to reach?</legend>
          <p class="selection-count" id="selection-count">0 of 3 selected</p>
          <div class="situation-list">${renderAudienceChoices()}</div>
        </fieldset>
      </section>

      <section class="form-section hidden" id="audience-detail-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">3 · Recognizable patterns</p>
          <h2>What is life like for these people?</h2>
          <p>Answer what you know. Short phrases are enough. Your job is to share experience; Rodney will do the persona development and content analysis afterward.</p>
        </div>
        <div id="audience-patterns"></div>
      </section>

      <section class="form-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">4 · Website language</p>
          <h2>Help the website sound like it understands.</h2>
          <p>Think about the priority audiences you selected. Differences between them are useful; note those differences in your answer.</p>
        </div>
        ${narrativeField("searchLanguage", "What might this person type into Google or say when asking someone for help?", "Use words you have actually heard whenever possible.", 15, "15")}
        ${narrativeField("recognitionNeed", "What would they need to read to think, “They understand what this is like”?", "", 16, "16")}
        ${narrativeField("languageToAvoid", "What language might make them feel judged, misunderstood, or incorrectly labeled?", "", 17, "17")}
        ${narrativeField("honestPromise", "What can Varetto honestly promise about the experience of working with its therapists?", "Focus on the experience and approach—not a guaranteed outcome.", 18, "18")}
      </section>

      <div id="validation-output" class="notice hidden"></div>
      <div class="privacy-confirmation">
        <label><input type="checkbox" id="privacy-acknowledgment" name="privacyAcknowledgment" value="yes"> I have kept any patient examples anonymous and understand that this response will be used for Varetto’s service, audience, and website-content development.</label>
      </div>
      <div class="form-actions">
        <div>
          <button type="button" id="save-backup" class="button secondary">Download response backup</button>
          <button type="button" id="clear-draft" class="text-button">Clear saved draft</button>
          <p class="backup-warning">Downloaded backups are unencrypted. Store or share them carefully.</p>
          <div id="status" class="status" role="status"></div>
        </div>
        <button type="submit" id="submit-button" class="button" ${!systemConfig.submissionEndpoint ? "disabled" : ""}>Submit response</button>
      </div>
    </form>
  `;

  wireEvents();
  restoreDraft();
  syncAudiencePanels();
  updateProgress();
}

function selectedAudienceIds() {
  return [...document.querySelectorAll("[data-audience-choice]:checked")].map(input => input.value);
}

function collectCurrentPatterns() {
  return [...document.querySelectorAll(".pattern-card")].map(card => {
    const result = {
      audienceId: card.dataset.audienceId,
      associatedConditions: []
    };
    card.querySelectorAll("[data-pattern-key]").forEach(field => {
      result[field.dataset.patternKey] = field.value.trim();
    });
    card.querySelectorAll(`input[name^="pattern."][name*=".conditions."]:checked`).forEach(input => {
      const option = config.conditionOptions.find(item => item.id === input.value);
      result.associatedConditions.push(option?.label || input.value);
    });
    return result;
  });
}

function cacheCurrentPatterns() {
  collectCurrentPatterns().forEach(pattern => patternCache.set(pattern.audienceId, pattern));
}

function populatePatternCard(card, existing = {}) {
  Object.entries(existing).forEach(([key, value]) => {
    if (key === "associatedConditions" && Array.isArray(value)) {
      card.querySelectorAll(`input[name^="pattern."][name*=".conditions."]`).forEach(input => {
        const option = config.conditionOptions.find(item => item.id === input.value);
        input.checked = value.includes(option?.label || input.value);
      });
      return;
    }
    const field = card.querySelector(`[data-pattern-key="${CSS.escape(key)}"]`);
    if (field && typeof value === "string") field.value = value;
  });
}

function syncAudiencePanels(restoredPatterns = null) {
  cacheCurrentPatterns();
  if (Array.isArray(restoredPatterns)) {
    restoredPatterns.forEach(pattern => {
      if (pattern?.audienceId) patternCache.set(pattern.audienceId, pattern);
    });
  }
  const ids = selectedAudienceIds();
  const container = document.querySelector("#audience-patterns");
  const section = document.querySelector("#audience-detail-section");
  const selected = ids.map(id => config.audienceSituations.find(item => item.id === id)).filter(Boolean);
  container.innerHTML = selected.map(renderPatternCard).join("");
  container.querySelectorAll(".pattern-card").forEach(card => populatePatternCard(card, patternCache.get(card.dataset.audienceId) || {}));
  section.classList.toggle("hidden", selected.length === 0);
  document.querySelector("#selection-count").textContent = `${selected.length} of 3 selected`;
  wireDictation(container);
  updateProgress();
}

function wireEvents() {
  const form = document.querySelector("#discovery-form");
  form.addEventListener("input", () => {
    scheduleAutosave();
    updateProgress();
  });
  form.addEventListener("change", event => {
    if (event.target.matches("[data-audience-choice]")) {
      const ids = selectedAudienceIds();
      if (ids.length > 3) {
        event.target.checked = false;
        setStatus("Choose up to three priority audience situations. You can change your selections at any time.", "error");
        updateProgress();
        return;
      }
      syncAudiencePanels();
    }
    scheduleAutosave();
    updateProgress();
  });
  form.addEventListener("submit", submitForm);
  document.querySelector("#save-backup").addEventListener("click", () => downloadJson(buildSubmission(), "draft"));
  document.querySelector("#clear-draft").addEventListener("click", clearDraft);
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
      if (event.results[i].isFinal) finalTranscript += `${text} `; else interim += text;
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

function collectOptionLabels(prefix, options) {
  return options.filter(option => document.querySelector(`[name="${CSS.escape(`${prefix}.${option.id}`)}"]:checked`)).map(option => option.label);
}

function collectSelectedAudiences() {
  return selectedAudienceIds().map(id => config.audienceSituations.find(item => item.id === id)).filter(Boolean);
}

function collectPatterns() {
  const audienceMap = new Map(config.audienceSituations.map(item => [item.id, item]));
  return collectCurrentPatterns().map(pattern => {
    const audience = audienceMap.get(pattern.audienceId);
    return {
      audienceId: pattern.audienceId,
      title: audience?.title || "",
      situation: audience?.situation || "",
      helpSeekingMoment: pattern.helpSeekingMoment || "",
      outsideView: pattern.outsideView || "",
      privateExperience: pattern.privateExperience || "",
      repeatedPattern: pattern.repeatedPattern || "",
      temporaryFunction: pattern.temporaryFunction || "",
      desiredChange: pattern.desiredChange || "",
      contactHesitation: pattern.contactHesitation || "",
      serviceFit: pattern.serviceFit || "",
      associatedConditions: pattern.associatedConditions || [],
      referralBoundary: pattern.referralBoundary || ""
    };
  });
}

function buildSubmission() {
  const named = collectNamedFields();
  return {
    submissionId,
    system: systemConfig.system,
    discoveryType: systemConfig.discoveryType,
    systemVersion: systemConfig.version,
    questionnaireVersion: config.questionnaireVersion,
    client: { id: config.clientId, label: config.clientLabel },
    respondent: {
      name: named["respondent.name"] || "",
      email: named["respondent.email"] || ""
    },
    timing: { startedAt, generatedAt: new Date().toISOString() },
    services: {
      offered: collectOptionLabels("service", config.serviceOptions),
      recipients: collectOptionLabels("recipient", config.recipientOptions),
      boundaries: named.practicalLimits || ""
    },
    audiences: collectSelectedAudiences(),
    patientPatterns: collectPatterns(),
    narrative: {
      searchLanguage: named.searchLanguage || "",
      recognitionNeed: named.recognitionNeed || "",
      languageToAvoid: named.languageToAvoid || "",
      honestPromise: named.honestPromise || ""
    },
    sourceIntegrity: {
      responseType: "stakeholder-discovery-response",
      patientIdentifyingInformationRequested: false,
      interpretationIncluded: false,
      respondentHypothesisIncluded: true,
      evidenceModel: "stakeholder-observation-and-professional-judgment"
    }
  };
}

function validate() {
  const errors = [];
  const email = document.querySelector("#respondentEmail");
  if (email?.value && !email.validity.valid) errors.push("Enter a valid email address or leave the email field blank.");
  if (!document.querySelector("#privacy-acknowledgment").checked) errors.push("Confirm that patient examples have been kept anonymous before submitting.");
  return errors;
}

function showValidation(errors) {
  const box = document.querySelector("#validation-output");
  if (!errors.length) {
    box.classList.add("hidden");
    box.innerHTML = "";
    return;
  }
  box.classList.remove("hidden");
  box.innerHTML = `<strong>Please review:</strong><ul class="warning-list">${errors.map(error => `<li>${esc(error)}</li>`).join("")}</ul>`;
  box.scrollIntoView({ behavior: "smooth", block: "center" });
}

async function submitForm(event) {
  event.preventDefault();
  const errors = validate();
  showValidation(errors);
  if (errors.length) return;
  if (!systemConfig.submissionEndpoint) {
    setStatus("Central submission is not connected. Download a response backup instead.", "error");
    return;
  }
  const submission = buildSubmission();
  autosave();
  const submitButton = document.querySelector("#submit-button");
  submitButton.disabled = true;
  setStatus("Submitting…");
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), systemConfig.submissionTimeoutMs);
  try {
    const response = await fetch(systemConfig.submissionEndpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(submission),
      signal: controller.signal
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.requestId ? `Submission failed. Reference ${result.requestId}.` : `Submission failed (${response.status}).`);
    localStorage.removeItem(systemConfig.storageKey);
    setStatus(result.duplicate ? "This response had already been received. No duplicate was created." : "Submitted successfully. Thank you.", "success");
    submitButton.textContent = "Submitted";
  } catch (error) {
    submitButton.disabled = false;
    const message = error.name === "AbortError" ? "The connection timed out." : error.message;
    setStatus(`${message} Your draft remains saved. Select Submit response to retry.`, "error");
  } finally {
    clearTimeout(timeout);
  }
}

function scheduleAutosave() {
  window.clearTimeout(autosaveTimer);
  autosaveTimer = window.setTimeout(autosave, 350);
}

function autosave() {
  const draft = {
    submissionId,
    startedAt,
    fields: collectNamedFields(),
    patterns: collectPatterns(),
    savedAt: new Date().toISOString()
  };
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
      document.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach(field => {
        if (field.type === "radio" || field.type === "checkbox") field.checked = field.value === value;
        else field.value = value;
      });
    });
    syncAudiencePanels(draft.patterns || []);
    setStatus("Draft restored from this browser.");
  } catch {
    localStorage.removeItem(systemConfig.storageKey);
  }
}

function clearDraft() {
  if (!window.confirm("Clear the saved draft from this browser? This cannot be undone unless you downloaded a backup.")) return;
  localStorage.removeItem(systemConfig.storageKey);
  window.location.reload();
}

function questionIsAnswered(group) {
  const checked = group.querySelector("input[type=checkbox]:checked, input[type=radio]:checked");
  if (checked) return true;
  return [...group.querySelectorAll("textarea, input[type=text], input[type=email], select")].some(field => field.value.trim());
}

function updateProgress() {
  const questions = [...document.querySelectorAll("#discovery-form [data-question]")].filter(group => !group.closest(".hidden"));
  const answered = questions.filter(questionIsAnswered).length;
  const total = questions.length;
  const percent = total ? Math.round((answered / total) * 100) : 0;
  document.querySelector("#progress-bar").style.width = `${percent}%`;
  document.querySelector("#progress-label").textContent = `${answered} of ${total} ${total === 1 ? "question" : "questions"} answered`;
}

function downloadJson(payload, label) {
  const blob = new Blob([JSON.stringify(payload, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `${config.clientId}-therapy-discovery-${label}-${new Date().toISOString().slice(0, 10)}.json`;
  link.click();
  URL.revokeObjectURL(url);
}

function setStatus(message, type = "") {
  const status = document.querySelector("#status");
  if (!status) return;
  status.textContent = message;
  status.className = `status ${type}`.trim();
}

render();
