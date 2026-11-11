// Bouton « Partager » (prototype blog-details.php l.66-71), en amélioration progressive : `[data-xm-share]` est rendu
// `hidden` côté serveur et n'apparaît qu'avec JavaScript. Partage natif (navigator.share) si disponible, sinon copie
// du lien (navigator.clipboard) avec un toast (XM.toast) ; annulation du partage natif ignorée. Textes et adresse lus
// dans les attributs data-share-* (lang/fr/pages/actualites.php), jamais en dur. Générique : réutilisable par d'autres fiches.

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

/**
 * Partage ou copie l'adresse du bouton.
 * @param {HTMLElement} button
 * @param {{ navigator?: Navigator, toast?: (message: string, type?: string) => void }} [env]
 * @returns {Promise<'shared'|'copied'|'failed'|'cancelled'>}
 */
export const shareFrom = async (button, env = {}) => {
  const nav = env.navigator ?? globalThis.navigator;
  const toast = env.toast ?? ((message, type) => globalThis.XM?.toast?.(message, type));
  const url = button.dataset.shareUrl || globalThis.location?.href || '';
  const title = button.dataset.shareTitle || globalThis.document?.title || '';

  if (nav && typeof nav.share === 'function') {
    try {
      await nav.share({ title, url });
      return 'shared';
    } catch (error) {
      if (error && error.name === 'AbortError') return 'cancelled';
      // Échec du partage natif (permission, contexte) : on retombe sur la copie du lien.
    }
  }

  try {
    if (!nav?.clipboard) throw new Error('clipboard');
    await nav.clipboard.writeText(url);
    toast(button.dataset.shareCopied || '', 'success');
    return 'copied';
  } catch {
    toast(button.dataset.shareFailed || '', 'warning');
    return 'failed';
  }
};

/** Révèle et branche les boutons de partage de `root`. */
export const initShare = (root = document) => {
  root.querySelectorAll('[data-xm-share]').forEach((button) => {
    if (button.dataset.shareReady === '1') return;
    button.dataset.shareReady = '1';
    button.hidden = false;
    button.addEventListener('click', () => { shareFrom(button); });
  });
};

if (typeof document !== 'undefined') ready(() => initShare());
