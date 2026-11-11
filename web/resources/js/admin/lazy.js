// Bibliothèques lourdes de l'administration chargées à la demande (P10-02, ADR-18, PERFORMANCE.md §4) : jamais dans
// le paquet admin.js. Un écran les demande dans @push('adminScripts') :
//   <script type="module" nonce="…">const Chart = await XM.admin.load('chart'); …</script>
// ou les annonce par data-xm-needs="chart quill" (préchargement au rendu de la page). Chaque bibliothèque n'est
// téléchargée qu'une fois ; Chart.js reçoit les réglages communs (police Montserrat, légende en bas, §4.1). La feuille de
// style de Quill, Choices et flatpickr arrive dans le même morceau que la bibliothèque, jamais dans admin.css.
const loaders = {
  chart: () => import('chart.js/auto').then((m) => {
    const Chart = m.default;
    Chart.defaults.font.family = 'Montserrat, Arial, sans-serif';
    Chart.defaults.plugins.legend.position = 'bottom';
    window.Chart = Chart;
    return Chart;
  }),
  quill: () => Promise.all([import('quill'), import('../../css/app/admin-quill.css')]).then(([m]) => {
    window.Quill = m.default;
    return m.default;
  }),
  choices: () => Promise.all([import('choices.js'), import('choices.js/public/assets/styles/choices.min.css')]).then(([m]) => {
    window.Choices = m.default;
    return m.default;
  }),
  flatpickr: () => Promise.all([import('flatpickr'), import('flatpickr/dist/l10n/fr.js'), import('flatpickr/dist/flatpickr.min.css')]).then(([m, l10n]) => {
    const flatpickr = m.default;
    flatpickr.localize(l10n.French || (l10n.default && l10n.default.fr));
    window.flatpickr = flatpickr;
    return flatpickr;
  }),
};

export const LIBRARIES = Object.keys(loaders);

const cache = new Map();

export function load(name) {
  if (!Object.prototype.hasOwnProperty.call(loaders, name)) return Promise.reject(new Error(`Bibliothèque inconnue : ${name}`));
  if (!cache.has(name)) {
    cache.set(name, loaders[name]().catch((error) => {
      cache.delete(name);
      throw error;
    }));
  }
  return cache.get(name);
}

export function preload(root = document) {
  const names = new Set();
  root.querySelectorAll('[data-xm-needs]').forEach((el) => {
    (el.dataset.xmNeeds || '').split(/\s+/).filter(Boolean).forEach((n) => names.add(n));
  });
  names.forEach((n) => { load(n).catch(() => {}); });
  return [...names];
}
