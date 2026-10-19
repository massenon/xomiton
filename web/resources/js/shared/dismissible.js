// Bandeaux refermables mémorisés pour la session de l'onglet (P13-08, annonce de maintenance) : un élément
// `[data-xm-dismiss-key]` fermé (évènement Bootstrap `closed.bs.alert`, ou clic sur son `[data-bs-dismiss]`) n'est plus
// affiché pendant la session (sessionStorage `xm-dismissed:<clé>`) ; même clé que l'espace Vue (MemberLayout.vue).
// Stockage indisponible (navigation privée stricte) : le bandeau réapparaît simplement au chargement suivant.
export const DISMISS_PREFIX = 'xm-dismissed:';

const safeStorage = (storage) => ({
  get: (key) => { try { return storage ? storage.getItem(key) : null; } catch { return null; } },
  set: (key) => { try { if (storage) storage.setItem(key, '1'); } catch { /* stockage refusé : sans effet */ } },
});

export function bindDismissible(root = document, storage = (typeof window !== 'undefined' ? window.sessionStorage : null)) {
  const store = safeStorage(storage);
  root.querySelectorAll('[data-xm-dismiss-key]').forEach((el) => {
    const key = DISMISS_PREFIX + el.getAttribute('data-xm-dismiss-key');
    if (store.get(key) === '1') {
      (el.closest('[data-xm-maintenance-notice]') || el).remove();
      return;
    }
    const remember = () => store.set(key);
    el.addEventListener('closed.bs.alert', remember);
    el.querySelectorAll('[data-bs-dismiss]').forEach((button) => button.addEventListener('click', remember));
  });
}
