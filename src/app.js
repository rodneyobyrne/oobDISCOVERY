import { systemConfig } from "./system-config.js?v=0.4.0";
import { clinicianCore } from "../config/clinician-core.js?v=0.4.0";
import { varettoConfig } from "../config/varetto.js?v=0.4.0";

const config = varettoConfig;
const app = document.querySelector("#app");
const dictationDialog = document.querySelector("#dictation-notice");
const patternCache = new Map();
let patternSequence = 1;
let patternOrder = ["audience-pattern-1"];
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
  const numberClass = String(questionNumber).length > 2 ? "question-number question-number-wide" : "question-number";
  return `<div class="field"${questionAttribute}>
    <label for="${esc(id)}">${questionNumber ? `<span class="${numberClass}">${esc(questionNumber)}</span>` : ""}${esc(label)}</label>
    ${help ? `<p class="help">${esc(help)}</p>` : ""}
    <textarea id="${esc(id)}" name="${esc(id)}" maxlength="10000" data-narrative></textarea>
    <div class="textarea-tools">
      <button type="button" class="button secondary small dictate" data-target="${esc(id)}" aria-controls="${esc(id)}-dictation">Use microphone</button>
      <button type="button" class="button small dictate-stop hidden" data-target="${esc(id)}">Stop now</button>
      <span class="dictation-state" id="${esc(id)}-dictation" role="status" aria-live="polite"></span>
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

function radioList(prefix, options, className = "choice-list") {
  return `<div class="${esc(className)}">${options.map(option => `
    <label>
      <input type="radio" name="${esc(prefix)}" value="${esc(option.id)}">
      <span>${esc(option.label)}</span>
    </label>
  `).join("")}</div>`;
}

function nextPatternId() {
  patternSequence += 1;
  return `audience-pattern-${patternSequence}`;
}

function renderPatternQuestion(patternId, question, index) {
  const questionNumber = `P${index + 1}.${question.number}`;
  const questionKey = `${patternId}-${question.key}`;
  if (question.type === "evidence") {
    return `<fieldset class="field evidence-field" data-question="${esc(questionKey)}">
      <legend><span class="question-number question-number-wide">${esc(questionNumber)}</span>${esc(question.label)}</legend>
      <p class="help">${esc(question.help)}</p>
      <p class="subquestion">Relationship to the intended practice</p>
      ${radioList(`pattern.${patternId}.basis`, config.audienceBasisOptions, "choice-list compact")}
      <p class="subquestion">Sources informing this description</p>
      ${checkboxList(`pattern.${patternId}.evidence`, config.evidenceSourceOptions, "choice-list compact")}
    </fieldset>`;
  }
  if (question.type === "conditions") {
    return `<fieldset class="field condition-field" data-question="${esc(questionKey)}">
      <legend><span class="question-number question-number-wide">${esc(questionNumber)}</span>${esc(question.label)}</legend>
      <p class="help">${esc(question.help)}</p>
      ${checkboxList(`pattern.${patternId}.conditions`, config.conditionOptions, "choice-list compact")}
    </fieldset>`;
  }
  return narrativeField(
    `${patternId}-${question.key}`,
    question.label,
    question.help || "",
    questionNumber,
    questionKey
  ).replace("data-narrative", `data-narrative data-pattern-key="${esc(question.key)}"`);
}

function renderPatternCard(patternId, index) {
  return `<article class="pattern-card" data-audience-id="${esc(patternId)}">
    <div class="pattern-heading">
      <div>
        <p class="eyebrow">Candidate audience pattern ${index + 1}</p>
        <h3>Describe one recognizable audience.</h3>
      </div>
      ${patternOrder.length > 1 ? `<button type="button" class="text-button remove-pattern" data-remove-pattern="${esc(patternId)}">Remove this pattern</button>` : ""}
    </div>
    <div class="field" data-question="${esc(patternId)}-working-label">
      <label for="${esc(patternId)}-workingLabel"><span class="question-number question-number-wide">P${index + 1}.0</span>Give this pattern a short, neutral working label.</label>
      <p class="help">Name the central tension or help-seeking position—not a fictional person, diagnosis, occupation, or demographic stereotype. Example: “Protecting competence while privately questioning control.”</p>
      <input id="${esc(patternId)}-workingLabel" name="${esc(patternId)}.workingLabel" type="text" maxlength="240" data-pattern-key="workingLabel">
    </div>
    <div class="pattern-questions">
      ${clinicianCore.patternQuestions.map(question => renderPatternQuestion(patternId, question, index)).join("")}
    </div>
  </article>`;
}

function render() {
  app.innerHTML = `
    <section class="form-intro">
      <p class="eyebrow">${esc(config.clientLabel)} · Clinical audience mapper</p>
      <h1>${esc(config.intro.title)}</h1>
      <p class="lede">${esc(config.intro.lead)}</p>
      <p>${esc(config.intro.purpose)}</p>
      <div class="meta-strip">
        <span class="meta-pill">${esc(systemConfig.estimatedMinutes)}</span>
        <span class="meta-pill">Most questions are optional</span>
        <span class="meta-pill">Draft saves in this browser</span>
      </div>
      <div class="notice">
        <strong>${esc(config.intro.boundary)}</strong>
        <p>Describe recurring patterns and situations, but do not include names, contact information, dates of birth, exact dates, unusual combinations of details, or anything else that could identify an individual.</p>
        <p>When possible, distinguish direct client language, repeated observation, collateral information, and clinical inference. “Unsure” and unanswered questions are acceptable.</p>
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
          <p>These fields distinguish clinician responses. They are optional.</p>
        </div>
        <div class="field"><label for="respondentName">Name</label><input id="respondentName" name="respondent.name" type="text" autocomplete="name"></div>
        <div class="field"><label for="respondentEmail">Email</label><input id="respondentEmail" name="respondent.email" type="email" autocomplete="email"></div>
      </section>

      <section class="form-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">1 · Practice reality</p>
          <h2>Define the service and population boundaries.</h2>
          <p>Start with what will actually be available. This prevents a compelling audience pattern from being mistaken for a service, specialty, or level of care Varetto cannot provide.</p>
        </div>
        <fieldset class="field" data-question="1">
          <legend><span class="question-number">1</span>Which therapy services will Varetto offer when the website launches?</legend>
          ${checkboxList("service", config.serviceOptions)}
        </fieldset>
        <fieldset class="field" data-question="2">
          <legend><span class="question-number">2</span>Which care or recovery contexts can these services support?</legend>
          ${checkboxList("recipient", config.recipientOptions)}
        </fieldset>
        ${narrativeField("currentPopulation", "Which populations are already represented in Varetto’s clinical experience?", "Describe recurring groups or patterns rather than individual cases. Note what is well established versus occasional.", 3, "3")}
        ${narrativeField("intendedPopulation", "Which populations does Varetto intentionally want to serve more?", "This may be aspirational. Identify it as a future-practice direction rather than presenting it as existing clinical evidence.", 4, "4")}
        ${narrativeField("practicalLimits", "What clinical, practical, or access boundaries should constrain the audience strategy?", "Consider age, location, licensure, in-person or virtual availability, payment, scheduling, stability, acuity, level of care, and referral thresholds.", 5, "5")}
        ${narrativeField("honestPromise", "What can Varetto honestly promise about the experience of care?", "Describe process, stance, safety, collaboration, and therapeutic approach—not guaranteed outcomes or universal fit.", 6, "6")}
      </section>

      <section class="form-section" id="audience-detail-section" data-section>
        <div class="section-copy">
          <p class="eyebrow">2 · Candidate audiences</p>
          <h2>Describe one audience pattern at a time.</h2>
          <p>Begin with the primary audience. Add another only when its motivation, decision process, language, trust requirement, or service pathway is meaningfully different. A different diagnosis, occupation, age, or recovery stage may be context for the same underlying archetype.</p>
        </div>
        <div id="audience-patterns"></div>
        <div class="pattern-actions">
          <button type="button" class="button secondary" id="add-pattern">Add another audience pattern</button>
          <p class="help" id="pattern-count"></p>
        </div>
      </section>

      <div id="validation-output" class="notice hidden"></div>
      <div class="privacy-confirmation">
        <label><input type="checkbox" id="privacy-acknowledgment" name="privacyAcknowledgment" value="yes"> I have kept all examples anonymous and understand that this is an audience-mapping response—not a clinical record or diagnostic assessment.</label>
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

  syncAudiencePanels();
  wireEvents();
  restoreDraft();
  updateProgress();
}

function collectCurrentPatterns() {
  return [...document.querySelectorAll(".pattern-card")].map(card => {
    const result = {
      audienceId: card.dataset.audienceId,
      audienceBasis: "",
      evidenceSources: [],
      clinicalContext: []
    };
    card.querySelectorAll("[data-pattern-key]").forEach(field => {
      result[field.dataset.patternKey] = field.value.trim();
    });
    const basis = card.querySelector(`input[name="pattern.${CSS.escape(card.dataset.audienceId)}.basis"]:checked`);
    const basisOption = config.audienceBasisOptions.find(item => item.id === basis?.value);
    result.audienceBasis = basisOption?.label || "";
    card.querySelectorAll(`input[name^="pattern.${CSS.escape(card.dataset.audienceId)}.evidence."]:checked`).forEach(input => {
      const option = config.evidenceSourceOptions.find(item => item.id === input.value);
      result.evidenceSources.push(option?.label || input.value);
    });
    card.querySelectorAll(`input[name^="pattern."][name*=".conditions."]:checked`).forEach(input => {
      const option = config.conditionOptions.find(item => item.id === input.value);
      result.clinicalContext.push(option?.label || input.value);
    });
    return result;
  });
}

function cacheCurrentPatterns() {
  collectCurrentPatterns().forEach(pattern => patternCache.set(pattern.audienceId, pattern));
}

function populatePatternCard(card, existing = {}) {
  Object.entries(existing).forEach(([key, value]) => {
    if (key === "audienceBasis" && typeof value === "string") {
      card.querySelectorAll(`input[name="pattern.${CSS.escape(card.dataset.audienceId)}.basis"]`).forEach(input => {
        const option = config.audienceBasisOptions.find(item => item.id === input.value);
        input.checked = value === (option?.label || input.value);
      });
      return;
    }
    if (key === "evidenceSources" && Array.isArray(value)) {
      card.querySelectorAll(`input[name^="pattern.${CSS.escape(card.dataset.audienceId)}.evidence."]`).forEach(input => {
        const option = config.evidenceSourceOptions.find(item => item.id === input.value);
        input.checked = value.includes(option?.label || input.value);
      });
      return;
    }
    if (key === "clinicalContext" && Array.isArray(value)) {
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
    const restoredOrder = [];
    restoredPatterns.forEach(pattern => {
      if (pattern?.audienceId) {
        patternCache.set(pattern.audienceId, pattern);
        restoredOrder.push(pattern.audienceId);
        const sequence = Number(String(pattern.audienceId).split("-").pop());
        if (Number.isFinite(sequence)) patternSequence = Math.max(patternSequence, sequence);
      }
    });
    if (restoredOrder.length) patternOrder = restoredOrder.slice(0, config.maxAudiencePatterns);
  }
  const container = document.querySelector("#audience-patterns");
  container.innerHTML = patternOrder.map(renderPatternCard).join("");
  container.querySelectorAll(".pattern-card").forEach(card => populatePatternCard(card, patternCache.get(card.dataset.audienceId) || {}));
  const addButton = document.querySelector("#add-pattern");
  const atLimit = patternOrder.length >= config.maxAudiencePatterns;
  addButton.disabled = atLimit;
  addButton.classList.toggle("hidden", atLimit);
  document.querySelector("#pattern-count").textContent = atLimit
    ? `Maximum of ${config.maxAudiencePatterns} candidate patterns reached.`
    : `${patternOrder.length} of up to ${config.maxAudiencePatterns} candidate patterns.`;
  wireDictation(container);
  updateProgress();
}

function wireEvents() {
  const form = document.querySelector("#discovery-form");
  form.addEventListener("input", () => {
    scheduleAutosave();
    updateProgress();
  });
  form.addEventListener("change", () => {
    scheduleAutosave();
    updateProgress();
  });
  form.addEventListener("click", event => {
    const addButton = event.target.closest("#add-pattern");
    if (addButton && patternOrder.length < config.maxAudiencePatterns) {
      cacheCurrentPatterns();
      const id = nextPatternId();
      patternOrder.push(id);
      syncAudiencePanels();
      document.querySelector(`[data-audience-id="${CSS.escape(id)}"]`)?.scrollIntoView({ behavior: "smooth", block: "start" });
      scheduleAutosave();
      return;
    }
    const removeButton = event.target.closest("[data-remove-pattern]");
    if (removeButton && patternOrder.length > 1) {
      cacheCurrentPatterns();
      const id = removeButton.dataset.removePattern;
      patternOrder = patternOrder.filter(patternId => patternId !== id);
      patternCache.delete(id);
      syncAudiencePanels();
      scheduleAutosave();
    }
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
  scope.querySelectorAll(".dictate-stop").forEach(button => {
    if (button.dataset.wired) return;
    button.dataset.wired = "true";
    button.addEventListener("click", () => stopDictation(button.dataset.target));
  });
}

function setDictationControls(session, listening) {
  const stopHadFocus = document.activeElement === session.stopButton;
  session.startButton?.classList.toggle("hidden", listening);
  session.stopButton?.classList.toggle("hidden", !listening);
  if (listening) {
    session.stopButton.disabled = false;
    session.stopButton.focus();
  } else if (stopHadFocus) {
    session.startButton?.focus();
  }
}

function clearDictationTimers(session) {
  window.clearTimeout(session.silenceTimer);
  window.clearTimeout(session.stopFallbackTimer);
}

function finishDictation(session) {
  if (session.finished) return;
  session.finished = true;
  clearDictationTimers(session);
  setDictationControls(session, false);
  if (session.state) session.state.textContent = session.endMessage || "Dictation added. Edit anything you want.";
  if (activeRecognition === session) activeRecognition = null;
}

function requestDictationStop(session, message) {
  if (!session || session.stopping || session.finished) return;
  session.stopping = true;
  session.endMessage = message;
  window.clearTimeout(session.silenceTimer);
  if (session.state) session.state.textContent = "Stopping…";
  if (session.stopButton) session.stopButton.disabled = true;
  session.stopFallbackTimer = window.setTimeout(() => finishDictation(session), 1200);
  try {
    session.recognition.stop();
  } catch {
    finishDictation(session);
  }
}

function scheduleDictationStop(session, delay, message) {
  window.clearTimeout(session.silenceTimer);
  session.silenceTimer = window.setTimeout(() => requestDictationStop(session, message), delay);
}

function stopDictation(targetId) {
  if (!activeRecognition || activeRecognition.targetId !== targetId) return;
  requestDictationStop(activeRecognition, "Dictation stopped. Edit anything you want.");
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
  if (activeRecognition) requestDictationStop(activeRecognition, "Dictation stopped. Edit anything you want.");
  const recognition = new Recognition();
  recognition.lang = "en-US";
  recognition.continuous = true;
  recognition.interimResults = true;
  let finalTranscript = "";
  const original = target.value.trim();
  const session = {
    recognition,
    targetId,
    state,
    startButton: document.querySelector(`.dictate[data-target="${CSS.escape(targetId)}"]`),
    stopButton: document.querySelector(`.dictate-stop[data-target="${CSS.escape(targetId)}"]`),
    silenceTimer: null,
    stopFallbackTimer: null,
    endMessage: "",
    stopping: false,
    finished: false
  };
  activeRecognition = session;
  setDictationControls(session, true);
  if (state) state.textContent = "Listening… stops after about 2 seconds of silence.";
  scheduleDictationStop(session, systemConfig.dictationInitialTimeoutMs, "No speech was detected. Try again or type your response.");
  recognition.onresult = event => {
    if (session.stopping || session.finished) return;
    let interim = "";
    for (let i = event.resultIndex; i < event.results.length; i++) {
      const text = event.results[i][0].transcript;
      if (event.results[i].isFinal) finalTranscript += `${text} `; else interim += text;
    }
    target.value = [original, finalTranscript.trim(), interim.trim()].filter(Boolean).join(" ");
    target.dispatchEvent(new Event("input", { bubbles: true }));
    scheduleDictationStop(session, systemConfig.dictationSilenceTimeoutMs, "Dictation stopped after a short pause. Edit anything you want.");
  };
  recognition.onerror = event => {
    if (session.stopping) return;
    session.endMessage = event.error === "no-speech"
      ? "No speech was detected. Try again or type your response."
      : `Microphone stopped: ${event.error}.`;
  };
  recognition.onend = () => finishDictation(session);
  try {
    recognition.start();
  } catch {
    session.endMessage = "The microphone could not start. Try again or type your response.";
    finishDictation(session);
  }
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

function patternHasContent(pattern) {
  return Object.entries(pattern).some(([key, value]) => {
    if (key === "audienceId") return false;
    if (Array.isArray(value)) return value.length > 0;
    return typeof value === "string" && value.trim() !== "";
  });
}

function collectPatterns() {
  return collectCurrentPatterns().filter(patternHasContent);
}

function collectAudienceSummaries(patterns) {
  return patterns.map((pattern, index) => ({
    id: pattern.audienceId,
    title: pattern.workingLabel || `Audience pattern ${index + 1}`,
    situation: pattern.helpSeekingState || ""
  }));
}

function buildSubmission() {
  const named = collectNamedFields();
  const patterns = collectPatterns();
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
      currentPopulation: named.currentPopulation || "",
      intendedPopulation: named.intendedPopulation || "",
      boundaries: named.practicalLimits || ""
    },
    audiences: collectAudienceSummaries(patterns),
    patientPatterns: patterns,
    narrative: {
      honestPromise: named.honestPromise || ""
    },
    sourceIntegrity: {
      responseType: "clinical-audience-mapping-response",
      patientIdentifyingInformationRequested: false,
      interpretationIncluded: false,
      respondentHypothesisIncluded: true,
      archetypeGenerated: false,
      diagnosticAssessment: false,
      evidenceModel: "clinician-reported-observation-inference-and-intended-audience-hypothesis"
    }
  };
}

function validate() {
  const errors = [];
  const email = document.querySelector("#respondentEmail");
  if (email?.value && !email.validity.valid) errors.push("Enter a valid email address or leave the email field blank.");
  if (collectPatterns().length === 0) errors.push("Describe at least one candidate audience pattern before submitting.");
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
    patterns: collectCurrentPatterns(),
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
  link.download = `${config.clientId}-clinical-audience-map-${label}-${new Date().toISOString().slice(0, 10)}.json`;
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
