// Formulaire /signaler (P5-04, Q11 a, signaler-et-sos.md) : amélioration progressive du formulaire Blade, qui s'envoie
// tel quel sans JavaScript (POST + @csrf, StoreReportRequest). Le composant Alpine xmReport n'ajoute que :
//  - le pas à pas « Étape n sur N » (une étape visible, aria-current="step" tenu à jour, focus sur le titre d'étape),
//    validé étape par étape avec les mêmes règles que le serveur (étapes et champs reçus de ReportSteps) ;
//  - la cascade département → commune depuis GET /api/v1/zones (repli : communes du balisage serveur) ;
//  - la position facultative (navigator.geolocation, jamais demandée sans clic) ;
//  - le brouillon local à chaque étape (IndexedDB `xomiton-drafts`, sans le contact, les preuves ni la position),
//    jamais repris sans le demander (appareil partagé), expiré après `draftDays` jours (xomiton.alerts.draft_days),
//    effacé par la page de confirmation et par « Retour » vers l'accueil à la première étape ;
//  - les liens des résumés d'erreurs : étape du champ rouverte puis focus sur le champ, message sous chaque champ
//    en erreur côté client (aria-describedby) ;
//  - l'état hors ligne : message et événement annulable `xm:outbox-submit` sur le formulaire [data-outbox], que la
//    boîte d'envoi commune (P5-10, ADR-26) interceptera ; ce module ne met rien en file d'attente lui-même.
// Aucun texte ici : tout vient de Blade (lang/fr) par `opts.texts`.
import { openDB } from 'idb';

export const DRAFT_DB = 'xomiton-drafts';
export const DRAFT_STORE = 'drafts';
export const DRAFT_KEY = 'signaler';
export const DRAFT_TTL_DAYS = 7;
const DAY_MS = 86400000;

/** Champs gardés dans le brouillon : ni contact (téléphone, e-mail), ni preuves, ni position, ni clé d'idempotence. */
export const DRAFT_FIELDS = ['type', 'category', 'description', 'occurred_on', 'needs', 'zone_id', 'commune_id', 'contactable', 'preferred_channel', 'anonymous'];

/** Type d'un fichier dont le navigateur ne donne pas le type, d'après son extension (types de MediaUploader). */
const EXTENSION_TYPES = {
  jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', pdf: 'application/pdf',
  mp3: 'audio/mpeg', m4a: 'audio/x-m4a', mp4: 'audio/mp4', ogg: 'audio/ogg', oga: 'audio/ogg', opus: 'audio/ogg',
};

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/u;

export function isOffline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false;
}

/** Longueur en caractères (comme mb_strlen côté serveur), pas en unités UTF-16. */
export function charLength(value = '') {
  return [...String(value)].length;
}

/** Numéro béninois, mêmes règles que App\Rules\PhoneBj::normalize(). */
export function isPhoneBj(value = '') {
  let digits = String(value).trim().replace(/[\s.\-()]/gu, '');
  if (!/^\+?\d+$/u.test(digits)) return false;
  digits = digits.replace(/^\+/u, '');
  for (const prefix of ['00229', '229']) {
    if (digits.startsWith(prefix) && [8, 10].includes(digits.length - prefix.length)) {
      digits = digits.slice(prefix.length);
      break;
    }
  }
  if (digits.length === 8) digits = `01${digits}`;
  return digits.length === 10 && digits.startsWith('01');
}

export function isPhoneOrEmail(value = '') {
  const text = String(value).trim();
  return text !== '' && (EMAIL.test(text) || isPhoneBj(text));
}

/** Valeurs du formulaire (pas de x-model : le balisage serveur et old() restent la source). */
export function readValues(form) {
  const data = new FormData(form);
  const text = (name) => {
    const value = data.get(name);
    return typeof value === 'string' ? value.trim() : '';
  };
  const anonymous = data.getAll('anonymous').filter((v) => typeof v === 'string');
  const input = form.querySelector('input[type="file"]');
  const files = input && input.files ? Array.from(input.files) : [];

  return {
    type: text('type'),
    category: text('category'),
    description: text('description'),
    occurred_on: text('occurred_on'),
    needs: data.getAll('needs[]').filter((v) => typeof v === 'string'),
    zone_id: text('zone_id'),
    commune_id: text('commune_id'),
    lat: text('lat'),
    lng: text('lng'),
    accuracy: text('accuracy'),
    contactable: text('contactable') === '1',
    contact: text('contact'),
    preferred_channel: text('preferred_channel'),
    // Case « ne pas lier à mon compte » précédée d'un champ caché à 0 : la dernière valeur l'emporte, comme en PHP.
    anonymous: anonymous.length === 0 ? true : anonymous[anonymous.length - 1] !== '0',
    files: files.map((file) => ({ name: file.name, size: file.size, type: file.type })),
  };
}

/** Types MIME acceptés comme preuves (ReportFormOptions::limits()['accept'], les mêmes que le serveur). */
export function acceptedTypes(limits = {}) {
  return String(limits.accept || '').split(',').map((type) => type.trim().toLowerCase()).filter(Boolean);
}

/** Type d'un fichier : celui du navigateur, sinon celui de son extension, sinon vide (refusé). */
export function fileType(file = {}) {
  const type = String(file.type || '').trim().toLowerCase();
  if (type !== '') return type;
  const extension = String(file.name || '').split('.').pop().toLowerCase();
  return EXTENSION_TYPES[extension] || '';
}

/** Collection MediaUploader d'un fichier (AlertAttachment::kindForMime), null s'il n'est pas accepté comme preuve. */
export function evidenceKind(type = '') {
  if (type.startsWith('image/')) return 'images';
  if (type.startsWith('audio/')) return 'audio';
  if (type === 'application/pdf') return 'documents';
  return null;
}

export function checkFiles(files = [], limits = {}, texts = {}) {
  const max = Number(limits.attachments_max) || 0;
  if (max > 0 && files.length > max) return String(texts.files_too_many || '').replace(':max', String(max));
  const accepted = acceptedTypes(limits);
  for (const file of files) {
    const type = fileType(file);
    // Liste du serveur (jpeg, png, webp, pdf, mpeg, mp4, x-m4a, ogg) : un image/gif ou un audio/wav serait refusé au
    // serveur et ferait rejoindre toutes les preuves ; repli sur les collections si la liste manque.
    const kind = accepted.length === 0 || accepted.includes(type) ? evidenceKind(type) : null;
    if (kind === null) return String(texts.file_type || '').replace(':name', file.name);
    const mb = Number((limits.max_mb || {})[kind]) || 0;
    if (mb > 0 && file.size > mb * 1024 * 1024) {
      return String(texts.file_too_large || '').replace(':name', file.name).replace(':max', String(mb));
    }
  }
  return '';
}

/**
 * Erreurs d'une étape (clé de ReportSteps), mêmes règles que StoreReportRequest ; le serveur reste l'autorité.
 * @returns {Record<string, string>} champ → message
 */
export function validateStep(key, values, cfg = {}) {
  const texts = (cfg.texts || {}).errors || {};
  const limits = cfg.limits || {};
  const errors = {};

  switch (key) {
    case 'type':
      if (!['report', 'need'].includes(values.type)) errors.type = texts.type;
      if (values.type === 'report' && !values.category) errors.category = texts.category;
      break;
    case 'facts': {
      const length = charLength(values.description);
      if (length === 0) errors.description = texts.description;
      else if (length < (Number(limits.description_min) || 0)) errors.description = texts.description_min;
      else if (limits.description_max && length > Number(limits.description_max)) errors.description = texts.description_max;
      if (values.occurred_on && cfg.today && values.occurred_on > cfg.today) errors.occurred_on = texts.date_future;
      break;
    }
    case 'needs':
      if (values.type === 'need' && values.needs.length === 0) errors.needs = texts.needs;
      if (!values.zone_id) errors.zone_id = texts.zone;
      break;
    case 'evidence': {
      const message = checkFiles(values.files, limits, texts);
      if (message) errors.attachments = message;
      break;
    }
    case 'contact':
      if (values.contactable) {
        if (!values.contact) errors.contact = texts.contact;
        else if (!isPhoneOrEmail(values.contact)) errors.contact = texts.contact_invalid;
        if (!values.preferred_channel) errors.preferred_channel = texts.channel;
      }
      break;
    default:
      break;
  }

  return errors;
}

/** Première étape (1..n) en erreur sur l'ensemble du formulaire, 0 si tout est valide. */
export function firstInvalidStep(steps, values, cfg) {
  for (let i = 0; i < steps.length; i += 1) {
    const errors = validateStep(steps[i].key, values, cfg);
    if (Object.keys(errors).length > 0) return { step: i + 1, errors };
  }
  return { step: 0, errors: {} };
}

export function clampStep(step, total) {
  const n = Number(step) || 1;
  return Math.min(Math.max(1, Math.trunc(n)), Math.max(1, total));
}

/** Étape (1..n) qui porte un champ, d'après la configuration de ReportSteps. */
export function stepOf(steps, field) {
  const index = steps.findIndex((step) => (step.fields || []).includes(field));
  return index === -1 ? steps.length : index + 1;
}

/* ---------- Département → commune ---------- */

/** Départements et communes du balisage serveur (<optgroup data-zone>), repli sans réseau. */
export function zonesFromSelect(select) {
  return Array.from(select.querySelectorAll('optgroup[data-zone]')).map((group) => ({
    id: Number(group.dataset.zone),
    name: group.label,
    communes: Array.from(group.querySelectorAll('option')).map((option) => ({ id: Number(option.value), name: option.textContent })),
  }));
}

/** Réponse de GET /api/v1/zones ({ data: [{ id, name, communes: [{ id, name }] }] }), null si inattendue. */
export function zonesFromApi(json) {
  if (!json || !Array.isArray(json.data)) return null;
  return json.data.map((zone) => ({
    id: Number(zone.id),
    name: String(zone.name),
    communes: (Array.isArray(zone.communes) ? zone.communes : []).map((c) => ({ id: Number(c.id), name: String(c.name) })),
  }));
}

export function communesFor(zones, zoneId) {
  const zone = zones.find((z) => String(z.id) === String(zoneId));
  return zone ? zone.communes : [];
}

/** Remplace les options de la commune ; garde la sélection si elle appartient au département. */
export function fillCommunes(select, communes, placeholder, selected = '') {
  const doc = select.ownerDocument;
  const first = doc.createElement('option');
  first.value = '';
  first.textContent = placeholder;
  const options = [first];
  for (const commune of communes) {
    const option = doc.createElement('option');
    option.value = String(commune.id);
    option.textContent = commune.name;
    option.selected = String(commune.id) === String(selected);
    options.push(option);
  }
  select.replaceChildren(...options);
  return select.value;
}

/* ---------- Stepper (<x-stepper>) ---------- */

export function syncStepper(list, current, texts = {}) {
  if (!list) return;
  list.querySelectorAll('.xm-stepper__item').forEach((item, index) => {
    const n = index + 1;
    const done = n < current;
    const isCurrent = n === current;
    item.classList.toggle('is-done', done);
    item.classList.toggle('is-current', isCurrent);
    if (isCurrent) item.setAttribute('aria-current', 'step');
    else item.removeAttribute('aria-current');
    const dot = item.querySelector('.xm-stepper__dot');
    if (dot) {
      if (done) {
        const icon = dot.ownerDocument.createElement('i');
        icon.className = 'bi bi-check-lg';
        dot.replaceChildren(icon);
      } else {
        dot.textContent = String(n);
      }
    }
    const state = item.querySelector('.visually-hidden');
    if (state) state.textContent = ` — ${done ? texts.done : isCurrent ? texts.current : texts.todo}`;
  });
}

/** Ajoute (ou retire) un identifiant de la liste aria-describedby d'un élément. */
export function describedBy(el, id, add) {
  const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/u).filter((token) => token !== '' && token !== id);
  if (add) ids.push(id);
  if (ids.length > 0) el.setAttribute('aria-describedby', ids.join(' '));
  else el.removeAttribute('aria-describedby');
}

/**
 * Marque les champs en erreur (classe Bootstrap, aria-invalid) et place sous chacun son message (`#f-{champ}-client-error`,
 * lié par aria-describedby au champ ou au groupe `#f-{champ}`, comme <x-field>) ; retire marques et messages précédents.
 */
export function markFields(form, errors = {}) {
  const doc = form.ownerDocument;
  form.querySelectorAll('[data-xm-client-invalid]').forEach((el) => {
    el.classList.remove('is-invalid');
    el.removeAttribute('aria-invalid');
    el.removeAttribute('data-xm-client-invalid');
  });
  form.querySelectorAll('[data-xm-client-error]').forEach((note) => {
    const owner = doc.getElementById(note.dataset.for || '');
    if (owner) describedBy(owner, note.id, false);
    note.remove();
  });
  for (const [field, message] of Object.entries(errors)) {
    form.querySelectorAll(`[name="${field}"], [name="${field}[]"]`).forEach((el) => {
      if (el.type === 'hidden') return;
      el.classList.add('is-invalid');
      el.setAttribute('aria-invalid', 'true');
      el.setAttribute('data-xm-client-invalid', '');
    });
    const target = doc.getElementById(`f-${field}`);
    if (!message || !target || !form.contains(target)) continue;
    // Le message serveur du même champ (`#f-{champ}-error`) est périmé : le message client le remplace.
    clearServerError(form, field);
    const note = doc.createElement('div');
    note.id = `f-${field}-client-error`;
    note.className = 'invalid-feedback d-block';
    note.dataset.xmClientError = '';
    note.dataset.for = target.id;
    const icon = doc.createElement('i');
    icon.className = 'bi bi-exclamation-circle me-1';
    icon.setAttribute('aria-hidden', 'true');
    note.append(icon, doc.createTextNode(String(message)));
    if (target.tagName === 'FIELDSET') {
      target.append(note);
      target.setAttribute('aria-invalid', 'true');
      target.setAttribute('data-xm-client-invalid', '');
    } else {
      (target.closest('.xm-field') || target.parentElement || form).append(note);
    }
    describedBy(target, note.id, true);
  }
}

/** Retire le message serveur d'un champ (`#f-{champ}-error`) et ses marques, une fois l'étape revalidée. */
export function clearServerError(form, field) {
  const doc = form.ownerDocument;
  const id = `f-${field}-error`;
  const note = doc.getElementById(id);
  if (note && form.contains(note)) note.remove();
  const target = doc.getElementById(`f-${field}`);
  if (target && form.contains(target)) describedBy(target, id, false);
  const marked = [target, ...form.querySelectorAll(`[name="${field}"], [name="${field}[]"]`)];
  marked.forEach((el) => {
    if (!el || !form.contains(el) || el.hasAttribute('data-xm-client-invalid')) return;
    el.classList.remove('is-invalid');
    el.removeAttribute('aria-invalid');
  });
}

/* ---------- Récapitulatif ---------- */

export function formatDay(value = '') {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/u.exec(value);
  return match ? `${match[3]}/${match[2]}/${match[1]}` : '';
}

export function excerpt(value = '', max = 280) {
  const chars = [...String(value)];
  return chars.length > max ? `${chars.slice(0, max).join('').trimEnd()}…` : chars.join('');
}

export function filesLabel(count, texts = {}) {
  return String(count > 1 ? texts.files_many : texts.files_one).replace(':count', String(count));
}

/** Lignes du récapitulatif : libellé, valeur lisible, étape à rouvrir et nom accessible du bouton « Modifier ». */
export function summarize(values, cfg = {}) {
  const labels = cfg.labels || {};
  const choices = cfg.choices || {};
  const texts = cfg.texts || {};
  const steps = cfg.steps || [];
  const empty = texts.empty || '—';
  const pick = (map, key) => (map && key !== '' && map[key] !== undefined ? String(map[key]) : '');
  const channel = pick(choices.preferred_channel, values.preferred_channel);
  const contact = values.contactable
    ? [String(texts.anonymous_no || '').replace(':contact', values.contact), channel ? String(texts.channel || '').replace(':channel', channel) : ''].filter(Boolean).join(' · ')
    : texts.anonymous_yes;

  const rows = [
    ['type', 'type', pick(choices.type, values.type)],
    ['category', 'category', pick(choices.category, values.category)],
    ['description', 'description', excerpt(values.description)],
    ['occurred_on', 'occurred_on', formatDay(values.occurred_on)],
    ['needs', 'needs', values.needs.map((need) => pick(choices.needs, need)).filter(Boolean).join(' · ')],
    ['place', 'zone_id', [pick(choices.communes, values.commune_id), pick(choices.zones, values.zone_id)].filter(Boolean).join(', ')],
    ['position', 'lat', values.lat && values.lng ? texts.position_yes : texts.position_no],
    ['attachments', 'attachments', values.files.length > 0 ? filesLabel(values.files.length, texts) : texts.no_files],
    ['contact', 'contactable', contact],
  ];

  return rows.map(([key, field, value]) => {
    const step = stepOf(steps, field);
    const stepLabel = (steps[step - 1] || {}).label || '';
    return { key, step, label: labels[key] || key, value: value || empty, edit: String(texts.edit_step || '').replace(':step', stepLabel) };
  });
}

/* ---------- Brouillon local (IndexedDB) ---------- */

export function isFresh(draft, now = Date.now(), days = DRAFT_TTL_DAYS) {
  if (!draft || typeof draft.saved_at !== 'number') return false;
  return draft.saved_at <= now + 60000 && now - draft.saved_at <= days * DAY_MS;
}

export function draftOf(values, step) {
  const draft = { step };
  for (const field of DRAFT_FIELDS) draft[field] = values[field];
  return draft;
}

/** Brouillon réduit aux champs autorisés (un brouillon d'une version antérieure pouvait garder le contact). */
export function sanitizeDraft(draft) {
  const clean = draftOf(draft || {}, (draft || {}).step);
  return Object.keys(draft || {}).some((key) => key !== 'saved_at' && !(key in clean)) ? clean : null;
}

/** Remet un brouillon dans le formulaire (la commune est remplie après la cascade, par le composant). */
export function applyDraft(form, draft) {
  const elements = (name) => Array.from(form.querySelectorAll(`[name="${name}"]`));
  const scalar = (name, value) => {
    for (const el of elements(name)) {
      if (el.type === 'hidden' || el.type === 'file') continue;
      if (el.type === 'radio') el.checked = el.value === String(value ?? '');
      else if (el.type === 'checkbox') el.checked = Boolean(value);
      else el.value = value ?? '';
    }
  };
  ['type', 'category', 'description', 'occurred_on', 'zone_id', 'preferred_channel'].forEach((name) => scalar(name, draft[name]));
  scalar('contactable', draft.contactable ? '1' : '0');
  scalar('anonymous', draft.anonymous !== false);
  const needs = Array.isArray(draft.needs) ? draft.needs.map(String) : [];
  for (const el of elements('needs[]')) el.checked = needs.includes(el.value);
}

export function createDraftStore(open = () => openDB(DRAFT_DB, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } })) {
  let connection = null;
  const db = async () => {
    if (!connection) connection = await open();
    return connection;
  };

  // Navigation privée, stockage bloqué ou plein : le formulaire fonctionne sans brouillon.
  return {
    async load() {
      try {
        return (await (await db()).get(DRAFT_STORE, DRAFT_KEY)) ?? null;
      } catch {
        return null;
      }
    },
    async save(draft, now = Date.now()) {
      try {
        await (await db()).put(DRAFT_STORE, { ...draft, saved_at: now }, DRAFT_KEY);
        return true;
      } catch {
        return false;
      }
    },
    async clear() {
      try {
        await (await db()).delete(DRAFT_STORE, DRAFT_KEY);
        return true;
      } catch {
        return false;
      }
    },
  };
}

/* ---------- Composants Alpine ---------- */

function round(value, digits) {
  const factor = 10 ** digits;
  return Math.round(value * factor) / factor;
}

export function positionLabel(position, pattern = '') {
  return String(pattern)
    .replace(':lat', String(position.lat))
    .replace(':lng', String(position.lng))
    .replace(':accuracy', String(position.accuracy));
}

export function xmReport(opts = {}) {
  const store = opts.store || createDraftStore();
  const steps = Array.isArray(opts.steps) ? opts.steps : [];
  const texts = opts.texts || {};
  const navigate = opts.navigate || ((url) => window.location.assign(url));

  return {
    step: clampStep(opts.initial, steps.length),
    total: steps.length,
    errorList: [],
    moved: false,
    offline: false,
    sending: false,
    draftState: '',
    draftMessage: '',
    summary: [],
    type: '',
    contactable: false,
    position: null,
    positionText: texts.position_help || '',
    filesText: '',
    geo: typeof navigator !== 'undefined' && Boolean(navigator.geolocation),
    zones: [],

    init() {
      const form = this.$refs.form;
      form.noValidate = true;
      this.offline = isOffline();
      window.addEventListener('online', () => { this.offline = false; });
      window.addEventListener('offline', () => { this.offline = true; });
      if (this.$refs.commune) {
        this.zones = zonesFromSelect(this.$refs.commune);
        this.refreshCommunes();
        this.loadZones();
      }
      this.refresh();
      this.readPosition();
      this.syncProgress();
      if (this.step === this.total) this.summary = summarize(readValues(form), this.cfg());
      if (!opts.hasOld) {
        return store.load().then((draft) => {
          if (isFresh(draft, Date.now(), opts.draftDays || DRAFT_TTL_DAYS)) {
            this.draftState = 'found';
            const clean = sanitizeDraft(draft);
            if (clean) return store.save(clean, draft.saved_at);
          } else if (draft) {
            return store.clear();
          }
          return undefined;
        });
      }
      return Promise.resolve();
    },

    cfg() {
      return { steps, limits: opts.limits || {}, today: opts.today || '', texts, labels: opts.labels || {}, choices: opts.choices || {} };
    },

    isStep(n) {
      return this.step === n;
    },

    refresh() {
      const values = readValues(this.$refs.form);
      this.type = values.type;
      this.contactable = values.contactable;
      this.filesText = values.files.length > 0 ? filesLabel(values.files.length, texts) : '';
      return values;
    },

    onChange(event) {
      if (event && event.target && event.target.name === 'zone_id') this.refreshCommunes();
      this.refresh();
    },

    refreshCommunes(selected) {
      const select = this.$refs.commune;
      const zone = this.$refs.form.querySelector('[name="zone_id"]');
      if (!select || !zone) return;
      fillCommunes(select, communesFor(this.zones, zone.value), texts.commune_placeholder || '—', selected ?? select.value);
    },

    async loadZones() {
      if (!opts.zonesUrl || typeof fetch !== 'function') return;
      try {
        const response = await fetch(opts.zonesUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) return;
        const zones = zonesFromApi(await response.json());
        if (zones && zones.length > 0) {
          this.zones = zones;
          this.refreshCommunes();
        }
      } catch {
        // Hors ligne ou réseau en échec : les communes du balisage serveur restent utilisées.
      }
    },

    validate(n) {
      const step = steps[n - 1];
      const errors = step ? validateStep(step.key, readValues(this.$refs.form), this.cfg()) : {};
      // Messages serveur de l'étape (envoi refusé) : remplacés par l'état courant, le serveur revalide à l'envoi.
      if (step) (step.fields || []).forEach((field) => clearServerError(this.$refs.form, field));
      this.showErrors(errors);
      return Object.keys(errors).length === 0;
    },

    showErrors(errors) {
      this.errorList = Object.entries(errors).map(([field, message]) => ({ field, message, target: `f-${field}` }));
      markFields(this.$refs.form, errors);
      if (this.errorList.length > 0) this.$nextTick(() => this.$refs.clientErrors && this.$refs.clientErrors.focus());
    },

    next() {
      if (!this.validate(this.step)) return false;
      this.goTo(this.step + 1);
      // Brouillon à chaque étape franchie, avec l'étape où reprendre.
      this.persist();
      return true;
    },

    back() {
      this.showErrors({});
      this.goTo(this.step - 1);
    },

    goTo(n, focus = null) {
      const target = clampStep(n, this.total);
      this.moved = true;
      this.step = target;
      if (target === this.total) this.summary = summarize(readValues(this.$refs.form), this.cfg());
      this.syncProgress();
      this.$nextTick(() => {
        const el = focus || this.$refs.form.ownerDocument.getElementById(`etape-${target}-titre`);
        if (el) el.focus();
      });
    },

    /** Lien d'un résumé d'erreurs (serveur ou client) : rouvre l'étape qui porte le champ visé, puis le focalise. */
    jump(event) {
      const link = event && event.target && event.target.closest ? event.target.closest('a[href^="#"]') : null;
      if (!link) return false;
      const el = this.$refs.form.ownerDocument.getElementById(decodeURIComponent(link.getAttribute('href').slice(1)));
      const section = el && this.$refs.form.contains(el) ? el.closest('.xm-report__step') : null;
      const match = section ? /^etape-(\d+)$/u.exec(section.id) : null;
      if (!match) return false;
      event.preventDefault();
      this.goTo(Number(match[1]), el);
      return true;
    },

    /** « Retour » vers l'accueil à la première étape : la personne abandonne, le brouillon local est effacé. */
    leave(event) {
      const href = event && event.currentTarget ? event.currentTarget.href : '';
      if (!href) return Promise.resolve();
      event.preventDefault();
      return store.clear().then(() => navigate(href));
    },

    edit(n) {
      this.showErrors({});
      this.goTo(n);
    },

    syncProgress() {
      const list = this.$refs.progress ? this.$refs.progress.querySelector('.xm-stepper') : null;
      syncStepper(list, this.step, texts.stepper || {});
    },

    persist() {
      return store.save(draftOf(readValues(this.$refs.form), this.step));
    },

    submit(event) {
      const form = this.$refs.form;
      if (this.step < this.total) {
        // Entrée dans un champ : on avance d'une étape, jamais d'envoi sans récapitulatif.
        event.preventDefault();
        this.next();
        return;
      }
      const { step, errors } = firstInvalidStep(steps, readValues(form), this.cfg());
      if (step > 0) {
        event.preventDefault();
        this.goTo(step);
        this.showErrors(errors);
        return;
      }
      if (this.sending) {
        event.preventDefault();
        return;
      }
      this.persist();
      if (isOffline()) {
        event.preventDefault();
        this.offline = true;
        // P5-10 (ADR-26) : la boîte d'envoi commune écoute cet événement et rejoue POST /api/v1/signalements.
        form.dispatchEvent(new CustomEvent('xm:outbox-submit', { bubbles: true, cancelable: true, detail: { form, type: form.dataset.outbox || '' } }));
        return;
      }
      this.sending = true;
    },

    resumeDraft() {
      return store.load().then((draft) => {
        if (!draft) {
          this.draftState = '';
          return;
        }
        applyDraft(this.$refs.form, draft);
        this.refreshCommunes(draft.commune_id ?? '');
        this.refresh();
        this.draftState = '';
        this.draftMessage = texts.draft_restored || '';
        this.goTo(draft.step);
      });
    },

    clearDraft() {
      return store.clear().then(() => {
        this.draftState = '';
        this.draftMessage = texts.draft_cleared || '';
      });
    },

    readPosition() {
      const form = this.$refs.form;
      const lat = parseFloat(form.querySelector('[name="lat"]')?.value ?? '');
      const lng = parseFloat(form.querySelector('[name="lng"]')?.value ?? '');
      const accuracy = parseInt(form.querySelector('[name="accuracy"]')?.value ?? '', 10);
      if (Number.isFinite(lat) && Number.isFinite(lng)) this.setPosition({ lat, lng, accuracy: Number.isFinite(accuracy) ? accuracy : 0 });
    },

    setPosition(position) {
      const form = this.$refs.form;
      const set = (name, value) => {
        const input = form.querySelector(`[name="${name}"]`);
        if (input) input.value = value;
      };
      set('lat', position ? String(position.lat) : '');
      set('lng', position ? String(position.lng) : '');
      set('accuracy', position ? String(position.accuracy) : '');
      this.position = position;
      this.positionText = position ? positionLabel(position, texts.position_attached) : texts.position_help || '';
    },

    locate() {
      if (!this.geo) return;
      this.positionText = texts.position_locating || '';
      navigator.geolocation.getCurrentPosition(
        (result) => this.setPosition({
          lat: round(result.coords.latitude, 6),
          lng: round(result.coords.longitude, 6),
          accuracy: Math.min(65535, Math.max(0, Math.round(result.coords.accuracy || 0))),
        }),
        () => {
          this.setPosition(null);
          this.positionText = texts.position_unavailable || '';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 60000 },
      );
    },

    clearPosition() {
      this.setPosition(null);
    },
  };
}

/** Page de confirmation : efface le brouillon, copie et impression du code de suivi. */
export function xmReportSent(opts = {}) {
  const store = opts.store || createDraftStore();
  const texts = opts.texts || {};

  return {
    copied: '',
    canCopy: typeof navigator !== 'undefined' && Boolean(navigator.clipboard),
    init() {
      return store.clear();
    },
    async copy() {
      try {
        await navigator.clipboard.writeText(String(opts.code || ''));
        this.copied = texts.copied || '';
      } catch {
        this.copied = texts.copy_failed || '';
      }
    },
    print() {
      window.print();
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmReport', xmReport);
    window.Alpine.data('xmReportSent', xmReportSent);
  });
}
