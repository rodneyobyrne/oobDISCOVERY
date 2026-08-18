const sessionEndpoint = "https://api.oobcreative.com/discovery/account/claim/?mode=session";
const resultsUrl = "https://api.oobcreative.com/discovery/results/";
const adminUrl = "https://api.oobcreative.com/discovery/results/invitations/";
const projectUrl = "https://api.oobcreative.com/discovery/project/";
const forgotUrl = "https://api.oobcreative.com/discovery/account/forgot/";

const signInPanel = document.querySelector("#sign-in-panel");
const accountPanel = document.querySelector("#account-panel");
const signInForm = document.querySelector("#discovery-login-form");
const loginStatus = document.querySelector("#login-status");
const accountSummary = document.querySelector("#account-summary");
const projectList = document.querySelector("#project-list");
const headerSignedOut = document.querySelector("#header-signed-out");
const headerSignedIn = document.querySelector("#header-signed-in");
const headerAccountName = document.querySelector("#header-account-name");
const headerAccountRole = document.querySelector("#header-account-role");
const headerAccountActions = document.querySelector("#header-account-actions");
const signOutButton = document.querySelector("#header-sign-out-button");

function esc(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function projectIntakeUrl(projectId) {
  if (projectId === "varetto") return "./varetto/";
  return null;
}

function projectDashboardUrl(projectId) {
  return `${projectUrl}?project_id=${encodeURIComponent(projectId)}`;
}

function setLoginStatus(message, type = "") {
  loginStatus.textContent = message;
  loginStatus.className = `login-status${type ? ` ${type}` : ""}`;
  loginStatus.hidden = !message;
}

function renderSignedOut() {
  signInPanel.hidden = false;
  accountPanel.hidden = true;
  headerSignedOut.hidden = false;
  headerSignedIn.hidden = true;
  signInForm.reset();
  accountSummary.innerHTML = "";
  projectList.innerHTML = "";
  headerAccountName.textContent = "";
  headerAccountRole.textContent = "";
  headerAccountActions.innerHTML = "";
}

function renderSignedIn(session) {
  const user = session.user;
  const projects = Array.isArray(session.projects) ? session.projects : [];
  signInPanel.hidden = true;
  accountPanel.hidden = false;
  headerSignedOut.hidden = true;
  headerSignedIn.hidden = false;

  headerAccountName.textContent = user.username;
  headerAccountRole.textContent = user.accountType;
  const actions = [`<a class="button" href="${resultsUrl}">${user.systemAdmin ? "Responses" : "Project responses"}</a>`];
  if (user.systemAdmin) actions.push(`<a class="button secondary" href="${adminUrl}">Projects, users &amp; access</a>`);
  headerAccountActions.innerHTML = actions.join("");

  accountSummary.innerHTML = `<p class="eyebrow">${esc(user.accountType)}</p><h1>Welcome, ${esc(user.username)}.</h1><p class="lede">Signed in as ${esc(user.email)}. Project membership determines which shared Discovery data is available to this account.</p>`;

  if (!projects.length) {
    projectList.innerHTML = `<article class="discovery-card muted-card"><div><p class="card-kicker">No active projects</p><h3>No project access is currently assigned.</h3><p>Ask a Full Admin if you expected to see a project here.</p></div></article>`;
    return;
  }

  projectList.innerHTML = projects.map(project => {
    const intakeUrl = projectIntakeUrl(project.id);
    const intakeAction = intakeUrl
      ? `<a class="button" href="${intakeUrl}">Open ${esc(project.name)} Discovery</a>`
      : `<span class="button disabled" aria-disabled="true">Intake not configured</span>`;
    return `<article class="discovery-card"><div><p class="card-kicker">${esc(project.businessType || "Project")}</p><h3>${esc(project.name)}</h3><p>Project ID: ${esc(project.id)}</p></div><div class="account-actions"><a class="button secondary" href="${projectDashboardUrl(project.id)}">Project dashboard</a>${intakeAction}</div></article>`;
  }).join("");
}

async function requestSession(options = {}) {
  const response = await fetch(sessionEndpoint, {
    credentials: "include",
    cache: "no-store",
    ...options,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.error || "Discovery account access is temporarily unavailable.");
  return payload;
}

async function loadSession() {
  try {
    const session = await requestSession();
    if (session.authenticated) renderSignedIn(session);
    else renderSignedOut();
  } catch {
    renderSignedOut();
    setLoginStatus("Discovery account access is temporarily unavailable. You can still use account recovery below.", "error");
  }
}

signInForm.addEventListener("submit", async event => {
  event.preventDefault();
  const submitButton = signInForm.querySelector("button[type='submit']");
  const identifier = signInForm.elements.identifier.value.trim();
  const password = signInForm.elements.password.value;
  setLoginStatus("Signing in…");
  submitButton.disabled = true;
  try {
    const session = await requestSession({
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "login", identifier, password }),
    });
    setLoginStatus("");
    renderSignedIn(session);
  } catch (error) {
    setLoginStatus(error.message, "error");
  } finally {
    submitButton.disabled = false;
  }
});

signOutButton.addEventListener("click", async () => {
  signOutButton.disabled = true;
  try {
    await requestSession({
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ action: "logout" }),
    });
  } catch {
    // The local homepage still returns to signed-out state even if the API response is interrupted.
  }
  setLoginStatus("");
  renderSignedOut();
  signOutButton.disabled = false;
});

document.querySelector("#forgot-link").href = forgotUrl;
loadSession();
