import { systemConfig } from "./system-config.js?v=0.6.0";
import { varettoConfig } from "../config/varetto.js?v=0.6.0";

const accountEndpoint = "https://api.oobcreative.com/discovery/account/claim/";
const params = new URLSearchParams(window.location.search);
const editingSubmissionId = params.get("edit") || "";
const originalFetch = window.fetch.bind(window);

function fieldMapFromPayload(payload) {
  const services = payload.services || {};
  const narrative = payload.narrative || {};
  const fields = {
    "respondent.name": payload.respondent?.name || "",
    "respondent.email": payload.respondent?.email || "",
    currentPopulation: services.currentPopulation || "",
    intendedPopulation: services.intendedPopulation || "",
    practicalLimits: services.boundaries || "",
    honestPromise: narrative.honestPromise || ""
  };

  for (const option of varettoConfig.serviceOptions || []) {
    if ((services.offered || []).includes(option.label)) fields[`service.${option.id}`] = option.id;
  }
  for (const option of varettoConfig.recipientOptions || []) {
    if ((services.recipients || []).includes(option.label)) fields[`recipient.${option.id}`] = option.id;
  }
  return fields;
}

function savedDraft() {
  try { return JSON.parse(localStorage.getItem(systemConfig.storageKey) || "null"); }
  catch { return null; }
}

async function accountSession() {
  try {
    const response = await originalFetch(`${accountEndpoint}?mode=session`, {
      method: "GET",
      credentials: "include",
      headers: { "Accept": "application/json" }
    });
    if (!response.ok) return { authenticated: false, user: null };
    return await response.json();
  } catch {
    return { authenticated: false, user: null };
  }
}

const session = await accountSession();
const priorDraft = savedDraft();
if (!editingSubmissionId && priorDraft?.editSubmissionId) {
  if (session.authenticated) {
    window.location.replace(`?edit=${encodeURIComponent(priorDraft.editSubmissionId)}`);
    throw new Error("Resuming saved edit.");
  }
  localStorage.removeItem(systemConfig.storageKey);
}

if (editingSubmissionId) {
  if (!session.authenticated) {
    window.location.replace(`https://api.oobcreative.com/discovery/results/?return=edit&submission_id=${encodeURIComponent(editingSubmissionId)}`);
    throw new Error("Discovery sign-in required.");
  }
  const response = await originalFetch(`${accountEndpoint}?account_submission=1&submission_id=${encodeURIComponent(editingSubmissionId)}`, {
    method: "GET",
    credentials: "include",
    headers: { "Accept": "application/json" }
  });
  if (!response.ok) {
    window.location.replace("https://api.oobcreative.com/discovery/results/");
    throw new Error("This response is not available to your account.");
  }
  const result = await response.json();
  const payload = result.payload;
  const draft = {
    submissionId: payload.submissionId,
    editSubmissionId: payload.submissionId,
    startedAt: payload.timing?.startedAt || new Date().toISOString(),
    fields: fieldMapFromPayload(payload),
    patterns: Array.isArray(payload.patientPatterns) ? payload.patientPatterns : [],
    savedAt: new Date().toISOString()
  };
  localStorage.setItem(systemConfig.storageKey, JSON.stringify(draft));
}

window.fetch = async (input, init = {}) => {
  const url = typeof input === "string" ? input : input?.url || "";
  const method = String(init.method || (typeof input !== "string" ? input.method : "GET") || "GET").toUpperCase();
  if (url !== systemConfig.submissionEndpoint || method !== "POST") return originalFetch(input, init);

  const response = await originalFetch(input, init);
  if (!response.ok || !session.authenticated) return response;

  let payload;
  try {
    payload = JSON.parse(String(init.body || "{}"));
  } catch {
    return response;
  }
  if (!payload?.submissionId) return response;

  const action = editingSubmissionId && payload.submissionId === editingSubmissionId ? "update" : "claim";
  const ownershipResponse = await originalFetch(`${accountEndpoint}?account_submission=1`, {
    method: "POST",
    credentials: "include",
    headers: { "Content-Type": "application/json", "Accept": "application/json" },
    body: JSON.stringify({ action, payload })
  });
  if (!ownershipResponse.ok) {
    let detail = "Your response was saved but could not be linked to your signed-in Discovery account.";
    try {
      const errorResult = await ownershipResponse.json();
      if (errorResult?.error) detail = errorResult.error;
    } catch {}
    throw new Error(`${detail} Return to your Discovery workspace and try again.`);
  }

  if (action === "update") {
    return new Response(JSON.stringify({ ok: true, submissionId: payload.submissionId, updated: true }), {
      status: 200,
      headers: { "Content-Type": "application/json" }
    });
  }
  return response;
};

await import("./app.js?v=0.6.0");
