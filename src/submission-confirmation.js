(() => {
  const originalFetch = window.fetch.bind(window);

  function isSubmissionRequest(resource, options) {
    const url = typeof resource === "string" ? resource : resource?.url || "";
    const method = String(options?.method || (typeof resource !== "string" ? resource?.method : "GET") || "GET").toUpperCase();
    return method === "POST" && /\/discovery\/submit\/?(?:\?|$)/.test(url);
  }

  function renderConfirmation(result) {
    const submissionId = String(result?.submissionId || "").trim();
    if (!submissionId) return;

    const form = document.querySelector("#discovery-form");
    const actions = document.querySelector(".form-actions");
    if (!form || !actions) return;

    let panel = document.querySelector("#submission-confirmation");
    if (!panel) {
      panel = document.createElement("section");
      panel.id = "submission-confirmation";
      panel.className = "submission-confirmation";
      panel.setAttribute("aria-labelledby", "submission-confirmation-heading");
      panel.setAttribute("tabindex", "-1");
      actions.insertAdjacentElement("beforebegin", panel);
    }

    const duplicateCopy = result.duplicate
      ? "We already had this exact response, so no duplicate was created. Your original submission is still safely stored."
      : "Your response has been received and stored. The source response remains intact so you can see exactly what you shared.";
    const reviewUrl = `https://api.oobcreative.com/discovery/response/?submission_id=${encodeURIComponent(submissionId)}`;

    panel.innerHTML = `
      <div class="submission-confirmation-mark" aria-hidden="true">✓</div>
      <div class="submission-confirmation-copy">
        <p class="eyebrow">Response received</p>
        <h2 id="submission-confirmation-heading">Your response is in.</h2>
        <p>${duplicateCopy}</p>
        <p class="submission-confirmation-next">You can review your original answers and a few early observations without turning this single response into a finished persona or marketing conclusion.</p>
        <div class="submission-confirmation-actions">
          <a class="button" href="${reviewUrl}">Review my response</a>
          <button type="button" class="button secondary" data-download-submitted>Download a copy</button>
        </div>
      </div>
    `;

    const submitButton = document.querySelector("#submit-button");
    const clearDraft = document.querySelector("#clear-draft");
    const status = document.querySelector("#status");
    if (submitButton) submitButton.classList.add("hidden");
    if (clearDraft) clearDraft.classList.add("hidden");
    if (status) {
      status.textContent = "";
      status.className = "status hidden";
    }

    panel.querySelector("[data-download-submitted]")?.addEventListener("click", () => {
      document.querySelector("#save-backup")?.click();
    });

    window.setTimeout(() => {
      panel.focus({ preventScroll: true });
      panel.scrollIntoView({ behavior: "smooth", block: "center" });
    }, 80);
  }

  window.fetch = async function patchedFetch(resource, options) {
    const response = await originalFetch(resource, options);
    if (isSubmissionRequest(resource, options)) {
      try {
        const result = await response.clone().json();
        if (response.ok && result?.ok) window.setTimeout(() => renderConfirmation(result), 0);
      } catch {
        // The questionnaire's normal error handling remains authoritative.
      }
    }
    return response;
  };
})();
