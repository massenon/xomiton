// Vote « Cet article vous a-t-il aidé·e ? » du centre d'aide (P4-05, prototype help-details.php l.37-38), en
// amélioration progressive : sans JavaScript, le formulaire `form[data-xm-faq-feedback]` part en POST et la page revient
// sur #avis avec le remerciement. Avec JavaScript : envoi par fetch (`Accept: application/json`, jeton CSRF du
// formulaire), remerciement du serveur à la place des boutons, puis focus sur ce remerciement (tabindex="-1" : le bouton
// masqué ne laisse pas le focus retomber sur <body>, WCAG 2.4.3) ; un seul canal d'annonce, le focus (ni role="status"
// ni toast en succès). En échec : boutons rendus, focus rendu au bouton choisi, toast d'avertissement (session expirée :
// texte 419 public). Textes lus dans les attributs data-* (lang/fr/pages/aide.php), jamais en dur. Navigateur sans
// `SubmitEvent.submitter` : envoi classique, rien n'est perdu.

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

/**
 * Envoie le vote du formulaire.
 * @param {HTMLFormElement} form
 * @param {boolean} helpful
 * @param {{ fetch?: typeof fetch, toast?: (message: string, type?: string) => void }} [env]
 * @returns {Promise<'sent'|'failed'>}
 */
export const sendFeedback = async (form, helpful, env = {}) => {
  const fetchFn = env.fetch ?? globalThis.fetch;
  const toast = env.toast ?? ((message, type) => globalThis.XM?.toast?.(message, type));
  const body = new FormData(form);
  body.set('helpful', helpful ? '1' : '0');

  try {
    const response = await fetchFn(form.action, {
      method: 'POST',
      body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    if (!response.ok) {
      toast((response.status === 419 ? form.dataset.feedbackExpired : form.dataset.feedbackFailed) || '', 'warning');
      return 'failed';
    }

    const data = await response.json();
    const message = typeof data?.message === 'string' ? data.message : '';
    const actions = form.querySelector('[data-feedback-actions]');
    const done = form.querySelector('[data-feedback-done]');
    if (done) {
      done.textContent = message;
      if (!done.hasAttribute('tabindex')) done.setAttribute('tabindex', '-1');
    }
    if (actions) actions.hidden = true;
    done?.focus();
    return 'sent';
  } catch {
    toast(form.dataset.feedbackFailed || '', 'warning');
    return 'failed';
  }
};

/** Branche les formulaires de vote de `root`. */
export const initFeedback = (root = document, env = {}) => {
  root.querySelectorAll('form[data-xm-faq-feedback]').forEach((form) => {
    if (form.dataset.feedbackReady === '1') return;
    form.dataset.feedbackReady = '1';
    form.addEventListener('submit', (event) => {
      const button = event.submitter;
      if (!button || button.name !== 'helpful') return;
      event.preventDefault();
      const buttons = form.querySelectorAll('button[name="helpful"]');
      buttons.forEach((b) => { b.disabled = true; });
      sendFeedback(form, button.value === '1', env).then((result) => {
        buttons.forEach((b) => { b.disabled = false; });
        if (result === 'failed') button.focus();
      });
    });
  });
};

if (typeof document !== 'undefined') ready(() => initFeedback());
