// « Charger plus » sans rechargement (prototype assets/js/xomiton.js l.188-210), en amélioration progressive : le lien
// `[data-xm-more] a` reste un lien serveur classique (?n=…#liste, rel=nofollow). Générique (thématiques P4-02,
// ressources P4-03) : la liste est #liste, ses éléments dans `[data-xm-items]` (ou #liste lui-même). Le focus passe sur
// le premier lien ajouté ; tout échec retombe sur la navigation classique. Un clic pendant le chargement (lien
// aria-busy) est ignoré : un seul fetch, focus juste. Aucune donnée injectée hors du HTML serveur.

const itemsOf = (root) => (root.matches('[data-xm-items]') ? root : root.querySelector('[data-xm-items]')) || root;

/**
 * Remplace la liste et le bloc « Charger plus » de `doc` par ceux du HTML reçu.
 * @returns {boolean} faux si la réponse n'a pas de #liste (l'appelant navigue alors normalement).
 */
export const applyNextPage = (doc, html, href) => {
  const list = doc.getElementById('liste');
  const next = new DOMParser().parseFromString(html, 'text/html');
  const nextList = next.getElementById('liste');
  if (!list || !nextList) return false;

  const items = itemsOf(list);
  const before = items.children.length;
  items.innerHTML = itemsOf(nextList).innerHTML;

  const oldMore = doc.querySelector('[data-xm-more]');
  const newMore = next.querySelector('[data-xm-more]');
  if (oldMore) {
    if (newMore) oldMore.replaceWith(doc.importNode(newMore, true));
    else oldMore.remove();
  }

  doc.defaultView?.history.replaceState(null, '', href.split('#')[0]);
  const focusable = items.children[before]?.querySelector('a, button');
  focusable?.focus({ preventScroll: true });
  return true;
};

export const onLoadMoreClick = async (event) => {
  const link = event.target instanceof Element ? event.target.closest('[data-xm-more] a') : null;
  if (!link || event.defaultPrevented || event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const doc = link.ownerDocument;
  if (!doc.getElementById('liste')) return;

  event.preventDefault();
  if (link.getAttribute('aria-busy') === 'true') return;
  link.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(link.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
    if (!response.ok || !applyNextPage(doc, await response.text(), link.href)) doc.defaultView.location.href = link.href;
  } catch {
    doc.defaultView.location.href = link.href;
  }
};

document.addEventListener('click', onLoadMoreClick);
