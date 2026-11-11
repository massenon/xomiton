// Textes des modules JS de l'administration : lus dans <script type="application/json" id="xmAdminI18n"> posé par le
// gabarit (lang/fr/admin.php), jamais écrits en dur ici ; repli neutre si le bloc manque (tests, page sans gabarit).
const FALLBACK = {
  close: 'Fermer',
  confirmTitle: 'Confirmer',
  confirm: 'Confirmer',
  cancel: 'Annuler',
  prompt: ':word',
  promptLabel: '',
  mismatch: '',
  pageAnnounce: 'Page : :title',
  navReload: 'Rechargement de la page…',
};

let cache = null;

export function adminI18n(doc = document) {
  if (cache && doc === document) return cache;
  let parsed = {};
  try {
    const el = doc.getElementById('xmAdminI18n');
    parsed = el ? JSON.parse(el.textContent || '{}') : {};
  } catch (e) {
    parsed = {};
  }
  const value = { ...FALLBACK, ...parsed };
  if (doc === document) cache = value;
  return value;
}

export function resetAdminI18n() {
  cache = null;
}
