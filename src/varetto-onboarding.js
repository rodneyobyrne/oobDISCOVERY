function applyVarettoOnboarding() {
  const intro = document.querySelector('.form-intro');
  if (!intro) return false;

  const eyebrow = intro.querySelector('.eyebrow');
  const title = intro.querySelector('h1');
  const lede = intro.querySelector('.lede');
  const purpose = lede?.nextElementSibling;
  const voice = intro.querySelector('.voice-guidance');
  const notice = intro.querySelector('.notice');

  if (eyebrow) eyebrow.textContent = 'Varetto Recovery · Audience Discovery';
  if (title) title.textContent = 'Help us better understand the people Varetto serves.';
  if (lede) {
    lede.textContent = 'The more we understand about the people you work with, what they are navigating, what they need, what they are asking, and what gets in the way, the better Varetto can connect people with useful information and support.';
  }
  if (purpose) {
    purpose.innerHTML = '<strong>Think of this as a structured meet and greet with your audience.</strong> Share the patterns you notice, the questions you hear, the concerns that shape decisions, and the language people actually use. You do not need perfect answers. Your perspective will be considered alongside the rest of the team so recurring patterns can become a clearer and more useful picture of the audiences Varetto serves.';
  }

  if (voice) {
    const heading = voice.querySelector('strong');
    const copy = voice.querySelector('p');
    if (heading) heading.textContent = 'Share what you actually notice.';
    if (copy) {
      copy.textContent = 'Use your own words. Specific observations, uncertainty, disagreement, and nuance are useful here. If your experience does not match one of the starting personas, say so. If you are unsure, say that too. The goal is not to make the existing research look correct. The goal is to make our understanding more accurate. The microphone can make this faster. Speak naturally, and review anything pasted from another writing or AI tool before submitting it.';
    }
  }

  if (notice) {
    const heading = notice.querySelector('strong');
    const paragraphs = notice.querySelectorAll('p');
    if (heading) heading.textContent = 'Describe patterns, not individual people.';
    if (paragraphs[0]) {
      paragraphs[0].textContent = 'We are looking for recurring experiences, questions, motivations, concerns, and situations. Do not include names, contact information, dates of birth, exact dates, unusual combinations of details, or anything else that could identify a patient or other individual.';
    }
    if (paragraphs[1]) {
      paragraphs[1].textContent = 'When possible, distinguish direct client language, repeated observation, collateral information, and clinical inference. Unsure and unanswered questions are acceptable.';
    }
  }

  return true;
}

if (!applyVarettoOnboarding()) {
  window.addEventListener('DOMContentLoaded', applyVarettoOnboarding, { once: true });
}
