// Repli de préchargement (< 1 Ko gzip, sans dépendance) pour les navigateurs sans « Speculation Rules » (Safari, Firefox) :
// au survol (souris) ou au toucher d'un lien de la liste blanche de partials/speculation-rules.blade.php, la page est
// demandée en basse priorité ; la réponse (Cache-Control: public, max-age) sert la navigation qui suit. Chrome / Edge
// n'exécutent rien ici : leurs règles natives s'en chargent. Rien avec « économiser les données » ni connexion 2G.
const glob = (list) => new RegExp('^(?:' + list.map((p) => p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*')).join('|') + ')$');

export function initPrefetch({ doc = document, loc = location, nav = navigator, get = (u) => fetch(u, { credentials: 'same-origin', priority: 'low' }), native = typeof HTMLScriptElement !== 'undefined' && !!HTMLScriptElement.supports && HTMLScriptElement.supports('speculationrules') } = {}) {
  const rules = doc.querySelector('script[type="speculationrules"]');
  const conn = nav.connection || {};
  if (!rules || native || conn.saveData || /2g/.test(conn.effectiveType || '')) return false;
  try {
    const parts = JSON.parse(rules.textContent).prefetch[0].where.and;
    const allow = glob(parts[0].href_matches);
    const d = parts.find((p) => p.not && p.not.href_matches);
    const deny = d ? glob(d.not.href_matches) : /$^/;
    const seen = new Set();
    let timer = 0;
    const warm = (e) => {
      const a = e.target.closest && e.target.closest('a[href]');
      if (!a || a.origin !== loc.origin || a.rel.includes('nofollow') || a.hasAttribute('download') || a.hasAttribute('data-no-prefetch') || (a.target && a.target !== '_self')) return;
      const url = a.pathname + a.search;
      if (seen.has(url) || !allow.test(a.pathname) || deny.test(a.pathname) || a.pathname === loc.pathname) return;
      seen.add(url);
      Promise.resolve(get(url)).catch(() => seen.delete(url));
    };
    doc.addEventListener('mouseover', (e) => { clearTimeout(timer); timer = setTimeout(() => warm(e), 100); }, { passive: true });
    doc.addEventListener('touchstart', warm, { passive: true });
    return true;
  } catch (e) {
    return false; // règles illisibles : aucun préchargement
  }
}
