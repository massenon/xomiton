// @vitest-environment jsdom
// Formulaire /signaler (P5-04) : lecture du formulaire, validation par étape (mêmes règles que StoreReportRequest),
// cascade département → commune (API puis repli balisage), stepper aria-current, récapitulatif, brouillon local
// (fraîcheur, injection d'un faux IndexedDB), composant xmReport (Entrée = étape suivante, hors ligne = événement pour la
// boîte d'envoi de P5-10, sans envoi) et xmReportSent (brouillon effacé, copie du code).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  applyDraft, checkFiles, clampStep, clearServerError, communesFor, createDraftStore, draftOf, fileType, fillCommunes,
  firstInvalidStep, formatDay, isFresh, isPhoneOrEmail, markFields, readValues, sanitizeDraft, summarize, syncStepper,
  validateStep, xmReport, xmReportSent, zonesFromApi, zonesFromSelect,
} from './signaler.js';

const STEPS = [
  { key: 'type', label: 'Type', fields: ['type', 'category'] },
  { key: 'facts', label: 'Les faits', fields: ['description', 'occurred_on'] },
  { key: 'needs', label: 'Besoins et lieu', fields: ['needs', 'zone_id', 'commune_id', 'lat', 'lng', 'accuracy'] },
  { key: 'evidence', label: 'Preuves', fields: ['attachments'] },
  { key: 'contact', label: 'Contact', fields: ['anonymous', 'contactable', 'contact', 'preferred_channel'] },
  { key: 'review', label: 'Vérification', fields: ['idempotency_key'] },
];

const TEXTS = {
  errors: {
    type: 'type requis', category: 'catégorie requise', description: 'récit requis', description_min: 'récit trop court',
    description_max: 'récit trop long', date_future: 'date future', needs: 'besoin requis', zone: 'département requis',
    contact: 'contact requis', contact_invalid: 'contact invalide', channel: 'canal requis',
    files_too_many: ':max fichiers au plus', file_too_large: '« :name » dépasse :max Mo', file_type: '« :name » refusé',
  },
  stepper: { done: 'terminée', current: 'en cours', todo: 'à venir' },
  commune_placeholder: 'Choisir…',
  files_one: ':count fichier joint', files_many: ':count fichiers joints',
  empty: 'Non renseigné', no_files: 'Aucune preuve', position_yes: 'Position jointe', position_no: 'Sans position',
  anonymous_yes: 'Envoi anonyme', anonymous_no: 'Recontact possible : :contact', channel: 'Canal : :channel',
  edit_step: 'Modifier l’étape « :step »', draft_restored: 'Brouillon repris.', draft_cleared: 'Brouillon effacé.',
  position_help: 'Facultatif', position_attached: 'Position jointe : :lat, :lng (± :accuracy m)',
};

const LIMITS = { description_min: 20, description_max: 4000, attachments_max: 2, max_mb: { images: 5, documents: 20, audio: 20 } };
const CFG = { steps: STEPS, limits: LIMITS, today: '2026-10-18', texts: TEXTS };
const STORY = 'Mon voisin me menace depuis trois semaines.';

const page = () => `
  <ol class="xm-stepper">${STEPS.map((s, i) => `<li class="xm-stepper__item${i === 0 ? ' is-current' : ''}"${i === 0 ? ' aria-current="step"' : ''}><span class="xm-stepper__dot">${i + 1}</span><span>${s.label}<span class="visually-hidden"> — x</span></span></li>`).join('')}</ol>
  <form data-outbox="signalement">
    ${STEPS.map((s, i) => `<h2 id="etape-${i + 1}-titre" tabindex="-1">${s.label}</h2>`).join('')}
    <input type="hidden" name="idempotency_key" value="k">
    <input type="radio" name="type" value="report"><input type="radio" name="type" value="need">
    <select name="category"><option value="">Choisir…</option><option value="physique">Violence physique</option></select>
    <textarea name="description"></textarea>
    <input type="date" name="occurred_on">
    <input type="checkbox" name="needs[]" value="hebergement"><input type="checkbox" name="needs[]" value="soutien_juridique">
    <select name="zone_id"><option value="">Choisir…</option><option value="1">Littoral</option><option value="2">Atlantique</option></select>
    <select name="commune_id"><option value="">Choisir…</option>
      <optgroup label="Littoral" data-zone="1"><option value="10">Cotonou</option></optgroup>
      <optgroup label="Atlantique" data-zone="2"><option value="20">Abomey-Calavi</option><option value="21">Ouidah</option></optgroup>
    </select>
    <input type="hidden" name="lat" value=""><input type="hidden" name="lng" value=""><input type="hidden" name="accuracy" value="">
    <input type="file" name="attachments[]" multiple>
    <input type="hidden" name="anonymous" value="0"><input type="checkbox" name="anonymous" value="1" checked>
    <input type="radio" name="contactable" value="0" checked><input type="radio" name="contactable" value="1">
    <input type="text" name="contact">
    <input type="radio" name="preferred_channel" value="sms"><input type="radio" name="preferred_channel" value="call">
  </form>`;

const form = () => document.querySelector('form');
const check = (name, value) => { form().querySelector(`[name="${name}"][value="${value}"]`).checked = true; };
const fill = (name, value) => { form().querySelector(`[name="${name}"]`).value = value; };

function memoryStore(initial = null) {
  let value = initial;
  return {
    load: vi.fn(async () => value),
    save: vi.fn(async (draft) => { value = { ...draft, saved_at: Date.now() }; return true; }),
    clear: vi.fn(async () => { value = null; return true; }),
    peek: () => value,
  };
}

function mount(opts = {}) {
  const component = xmReport({ steps: STEPS, limits: LIMITS, today: '2026-10-18', texts: TEXTS, ...opts });
  component.$refs = { form: form(), commune: form().querySelector('[name="commune_id"]'), progress: document.body, clientErrors: { focus: vi.fn() } };
  component.$nextTick = (fn) => fn();
  return component;
}

describe('signaler — fonctions pures', () => {
  beforeEach(() => { document.body.innerHTML = page(); });

  it('lit le formulaire comme la charge utile de POST /signalements', () => {
    check('type', 'need');
    fill('description', `  ${STORY}  `);
    form().querySelector('[name="needs[]"][value="hebergement"]').checked = true;
    fill('zone_id', '2');
    form().querySelector('[name="anonymous"][type="checkbox"]').checked = false;

    expect(readValues(form())).toMatchObject({
      type: 'need', description: STORY, needs: ['hebergement'], zone_id: '2', contactable: false, anonymous: false, files: [],
    });
  });

  it('valide chaque étape avec les règles du serveur', () => {
    const base = readValues(form());
    expect(validateStep('type', base, CFG)).toEqual({ type: 'type requis' });
    expect(validateStep('type', { ...base, type: 'report' }, CFG)).toEqual({ category: 'catégorie requise' });
    expect(validateStep('type', { ...base, type: 'need' }, CFG)).toEqual({});
    expect(validateStep('facts', { ...base, description: 'Trop court' }, CFG)).toEqual({ description: 'récit trop court' });
    expect(validateStep('facts', { ...base, description: STORY, occurred_on: '2026-10-19' }, CFG)).toEqual({ occurred_on: 'date future' });
    expect(validateStep('facts', { ...base, description: '😀'.repeat(19) }, CFG)).toEqual({ description: 'récit trop court' });
    expect(validateStep('needs', { ...base, type: 'need' }, CFG)).toEqual({ needs: 'besoin requis', zone_id: 'département requis' });
    expect(validateStep('needs', { ...base, type: 'report', zone_id: '1' }, CFG)).toEqual({});
    expect(validateStep('contact', { ...base, contactable: true, contact: 'pas un numéro' }, CFG)).toEqual({ contact: 'contact invalide', preferred_channel: 'canal requis' });
    expect(validateStep('contact', { ...base, contactable: true, contact: '+229 01 97 38 88 47', preferred_channel: 'sms' }, CFG)).toEqual({});
    expect(validateStep('contact', { ...base, contactable: false, contact: '' }, CFG)).toEqual({});
  });

  it('reconnaît un numéro béninois ou une adresse e-mail (PhoneBj)', () => {
    expect(isPhoneOrEmail('97 38 88 47')).toBe(true);
    expect(isPhoneOrEmail('0022901973888 47')).toBe(true);
    expect(isPhoneOrEmail('nom@exemple.bj')).toBe(true);
    expect(isPhoneOrEmail('+33 6 12 34 56 78')).toBe(false);
    expect(isPhoneOrEmail('')).toBe(false);
  });

  it('borne les preuves en nombre, en type et en taille par collection', () => {
    const mb = 1024 * 1024;
    expect(checkFiles([{ name: 'a.jpg', type: 'image/jpeg', size: mb }], LIMITS, TEXTS.errors)).toBe('');
    expect(checkFiles([{ name: 'a.jpg', type: 'image/jpeg', size: 6 * mb }], LIMITS, TEXTS.errors)).toBe('« a.jpg » dépasse 5 Mo');
    expect(checkFiles([{ name: 'b.exe', type: 'application/x-msdownload', size: 10 }], LIMITS, TEXTS.errors)).toBe('« b.exe » refusé');
    expect(checkFiles([1, 2, 3].map((i) => ({ name: `${i}.pdf`, type: 'application/pdf', size: 1 })), LIMITS, TEXTS.errors)).toBe('2 fichiers au plus');
  });

  it('n’accepte que les types du serveur (limits.accept), type vide compris', () => {
    const limits = { ...LIMITS, accept: 'image/jpeg,image/png,image/webp,application/pdf,audio/mpeg,audio/mp4,audio/x-m4a,audio/ogg' };
    expect(checkFiles([{ name: 'a.gif', type: 'image/gif', size: 10 }], limits, TEXTS.errors)).toBe('« a.gif » refusé');
    expect(checkFiles([{ name: 'a.wav', type: 'audio/wav', size: 10 }], limits, TEXTS.errors)).toBe('« a.wav » refusé');
    expect(checkFiles([{ name: 'note', type: '', size: 10 }], limits, TEXTS.errors)).toBe('« note » refusé');
    expect(checkFiles([{ name: 'a.heic', type: '', size: 10 }], limits, TEXTS.errors)).toBe('« a.heic » refusé');
    // Type vide (certains navigateurs mobiles) : repli sur l'extension, bornée à la même liste.
    expect(fileType({ name: 'vocal.M4A', type: '' })).toBe('audio/x-m4a');
    expect(checkFiles([{ name: 'vocal.m4a', type: '', size: 10 }, { name: 'b.png', type: 'image/png', size: 10 }], limits, TEXTS.errors)).toBe('');
    expect(checkFiles([{ name: 'vocal.m4a', type: '', size: 21 * 1024 * 1024 }], limits, TEXTS.errors)).toBe('« vocal.m4a » dépasse 20 Mo');
  });

  it('trouve la première étape en erreur et borne les numéros d’étape', () => {
    const values = { ...readValues(form()), type: 'report', category: 'physique', description: STORY };
    expect(firstInvalidStep(STEPS, values, CFG)).toEqual({ step: 3, errors: { zone_id: 'département requis' } });
    expect(firstInvalidStep(STEPS, { ...values, zone_id: '1' }, CFG).step).toBe(0);
    expect(clampStep(9, 6)).toBe(6);
    expect(clampStep('x', 6)).toBe(1);
  });

  it('remplit les communes du département depuis l’API ou le balisage', () => {
    const select = form().querySelector('[name="commune_id"]');
    const fromMarkup = zonesFromSelect(select);
    expect(communesFor(fromMarkup, '2').map((c) => c.name)).toEqual(['Abomey-Calavi', 'Ouidah']);

    const fromApi = zonesFromApi({ data: [{ id: 2, name: 'Atlantique', communes: [{ id: 21, name: 'Ouidah' }] }] });
    expect(fillCommunes(select, communesFor(fromApi, 2), 'Choisir…', '21')).toBe('21');
    expect(Array.from(select.options).map((o) => o.textContent)).toEqual(['Choisir…', 'Ouidah']);
    expect(fillCommunes(select, communesFor(fromApi, 1), 'Choisir…', '21')).toBe('');
    expect(zonesFromApi({ error: 'x' })).toBeNull();
  });

  it('tient le stepper à jour (aria-current="step", état annoncé)', () => {
    const list = document.querySelector('.xm-stepper');
    syncStepper(list, 3, TEXTS.stepper);
    const items = list.querySelectorAll('li');

    expect(items[2].getAttribute('aria-current')).toBe('step');
    expect(items[0].hasAttribute('aria-current')).toBe(false);
    expect(items[0].classList.contains('is-done')).toBe(true);
    expect(items[0].querySelector('.bi-check-lg')).not.toBeNull();
    expect(items[2].querySelector('.visually-hidden').textContent).toBe(' — en cours');
    expect(items[5].querySelector('.visually-hidden').textContent).toBe(' — à venir');
  });

  it('marque puis démarque les champs en erreur', () => {
    markFields(form(), { contact: 'x', needs: 'y' });
    expect(form().querySelector('[name="contact"]').getAttribute('aria-invalid')).toBe('true');
    expect(form().querySelectorAll('[name="needs[]"].is-invalid')).toHaveLength(2);
    markFields(form(), {});
    expect(form().querySelectorAll('.is-invalid')).toHaveLength(0);
  });

  it('place le message client sous le champ, lié par aria-describedby, et remplace le message serveur', () => {
    document.body.innerHTML = `<form>
      <div class="xm-field"><input id="f-contact" name="contact" aria-describedby="f-contact-hint f-contact-error" aria-invalid="true" class="is-invalid">
        <div id="f-contact-hint">aide</div><div id="f-contact-error">message serveur</div></div>
      <div class="xm-field"><fieldset id="f-needs" tabindex="-1"><legend>Besoins</legend><input type="checkbox" name="needs[]" value="a"></fieldset></div>
    </form>`;
    const f = document.querySelector('form');
    markFields(f, { contact: 'contact invalide', needs: 'besoin requis' });

    const input = f.querySelector('#f-contact');
    expect(f.querySelector('#f-contact-error')).toBeNull();
    expect(input.getAttribute('aria-describedby')).toBe('f-contact-hint f-contact-client-error');
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(f.querySelector('#f-contact-client-error').textContent).toBe('contact invalide');
    expect(f.querySelector('#f-contact-client-error').closest('.xm-field')).toBe(input.closest('.xm-field'));
    const group = f.querySelector('#f-needs');
    expect(group.getAttribute('aria-describedby')).toBe('f-needs-client-error');
    expect(group.getAttribute('aria-invalid')).toBe('true');
    expect(group.lastElementChild.id).toBe('f-needs-client-error');

    markFields(f, {});
    expect(f.querySelectorAll('[data-xm-client-error], .is-invalid, [aria-invalid]')).toHaveLength(0);
    expect(input.getAttribute('aria-describedby')).toBe('f-contact-hint');
    expect(group.hasAttribute('aria-describedby')).toBe(false);

    f.querySelector('.xm-field').insertAdjacentHTML('beforeend', '<div id="f-contact-error">encore</div>');
    input.setAttribute('aria-describedby', 'f-contact-hint f-contact-error');
    clearServerError(f, 'contact');
    expect(f.querySelector('#f-contact-error')).toBeNull();
    expect(input.getAttribute('aria-describedby')).toBe('f-contact-hint');
    document.body.innerHTML = page();
  });

  it('résume les réponses avec l’étape à rouvrir', () => {
    const rows = summarize({
      ...readValues(form()), type: 'need', description: STORY, occurred_on: '2026-10-01', needs: ['hebergement'], zone_id: '2', commune_id: '21',
      contactable: true, contact: '97 38 88 47', preferred_channel: 'call',
    }, {
      ...CFG,
      labels: { type: 'Type', place: 'Lieu' },
      choices: { type: { need: 'Exprimer un besoin' }, needs: { hebergement: 'Hébergement' }, zones: { 2: 'Atlantique' }, communes: { 21: 'Ouidah' }, preferred_channel: { call: 'Appel' } },
    });
    const byKey = Object.fromEntries(rows.map((r) => [r.key, r]));

    expect(byKey.type).toMatchObject({ step: 1, label: 'Type', value: 'Exprimer un besoin', edit: 'Modifier l’étape « Type »' });
    expect(byKey.category.value).toBe('Non renseigné');
    expect(byKey.occurred_on.value).toBe(formatDay('2026-10-01'));
    expect(byKey.place).toMatchObject({ step: 3, value: 'Ouidah, Atlantique' });
    expect(byKey.attachments).toMatchObject({ step: 4, value: 'Aucune preuve' });
    expect(byKey.contact).toMatchObject({ step: 5, value: 'Recontact possible : 97 38 88 47 · Canal : Appel' });
  });

  it('garde un brouillon sans contact, preuve ni position, frais 7 jours', () => {
    const draft = draftOf({ ...readValues(form()), lat: '6.3', description: STORY, contactable: true, contact: '97 38 88 47', preferred_channel: 'sms' }, 2);
    expect(draft).toMatchObject({ step: 2, description: STORY, contactable: true, preferred_channel: 'sms' });
    expect(draft).not.toHaveProperty('contact');
    expect(draft).not.toHaveProperty('lat');
    expect(draft).not.toHaveProperty('files');
    expect(JSON.stringify(draft)).not.toContain('97 38 88 47');
    expect(sanitizeDraft({ ...draft, saved_at: 1 })).toBeNull();
    expect(sanitizeDraft({ ...draft, contact: 'a@b.bj', saved_at: 1 })).toEqual(draft);

    const now = Date.UTC(2026, 9, 18);
    expect(isFresh({ saved_at: now - 6 * 86400000 }, now)).toBe(true);
    expect(isFresh({ saved_at: now - 8 * 86400000 }, now)).toBe(false);
    expect(isFresh(null, now)).toBe(false);
  });

  it('remet un brouillon dans le formulaire', () => {
    // Brouillon d'une version antérieure qui gardait le contact : il n'est jamais remis.
    applyDraft(form(), { type: 'need', description: STORY, needs: ['soutien_juridique'], zone_id: '2', contactable: true, contact: 'a@b.bj', preferred_channel: 'sms', anonymous: false });
    const values = readValues(form());

    expect(values).toMatchObject({ type: 'need', description: STORY, needs: ['soutien_juridique'], zone_id: '2', contactable: true, contact: '', preferred_channel: 'sms', anonymous: false });
    expect(form().querySelector('[name="idempotency_key"]').value).toBe('k');
  });

  it('enveloppe IndexedDB et renvoie null si le stockage est indisponible', async () => {
    const db = { get: vi.fn(async () => ({ step: 2 })), put: vi.fn(async () => undefined), delete: vi.fn(async () => undefined) };
    const store = createDraftStore(async () => db);
    expect(await store.load()).toEqual({ step: 2 });
    expect(await store.save({ step: 3 }, 42)).toBe(true);
    expect(db.put).toHaveBeenCalledWith('drafts', { step: 3, saved_at: 42 }, 'signaler');
    expect(await store.clear()).toBe(true);

    const broken = createDraftStore(async () => { throw new Error('blocked'); });
    expect(await broken.load()).toBeNull();
    expect(await broken.save({})).toBe(false);
  });
});

describe('signaler — composant xmReport', () => {
  beforeEach(() => {
    document.body.innerHTML = page();
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, json: async () => ({ data: [{ id: 1, name: 'Littoral', communes: [{ id: 10, name: 'Cotonou' }] }] }) })));
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('bloque « Continuer » sur une étape invalide puis avance et enregistre le brouillon', async () => {
    const store = memoryStore();
    const c = mount({ store });
    await c.init();

    expect(form().noValidate).toBe(true);
    expect(c.next()).toBe(false);
    expect(c.step).toBe(1);
    expect(c.errorList).toEqual([{ field: 'type', message: 'type requis', target: 'f-type' }]);
    expect(c.$refs.clientErrors.focus).toHaveBeenCalled();

    check('type', 'need');
    expect(c.next()).toBe(true);
    expect(c.step).toBe(2);
    expect(c.errorList).toEqual([]);
    expect(store.save).toHaveBeenCalledWith(expect.objectContaining({ type: 'need', step: 2 }));
    expect(document.activeElement.id).toBe('etape-2-titre');
    expect(document.querySelectorAll('[aria-current="step"]')).toHaveLength(1);
    expect(document.querySelector('[aria-current="step"]').textContent).toContain('Les faits');
  });

  it('transforme Entrée avant la dernière étape en « Continuer » et renvoie à l’étape en erreur à l’envoi', async () => {
    const c = mount({ store: memoryStore() });
    await c.init();
    check('type', 'report');
    fill('category', 'physique');

    const early = new Event('submit', { cancelable: true });
    c.submit(early);
    expect(early.defaultPrevented).toBe(true);
    expect(c.step).toBe(2);

    c.goTo(6);
    const late = new Event('submit', { cancelable: true });
    c.submit(late);
    expect(late.defaultPrevented).toBe(true);
    expect(c.step).toBe(2);
    expect(c.errorList[0].field).toBe('description');
  });

  it('laisse partir le formulaire complet une seule fois', async () => {
    const c = mount({ store: memoryStore() });
    await c.init();
    check('type', 'report');
    fill('category', 'physique');
    fill('description', STORY);
    fill('zone_id', '1');
    c.goTo(6);

    const first = new Event('submit', { cancelable: true });
    c.submit(first);
    expect(first.defaultPrevented).toBe(false);
    expect(c.sending).toBe(true);

    const second = new Event('submit', { cancelable: true });
    c.submit(second);
    expect(second.defaultPrevented).toBe(true);
  });

  it('hors ligne : n’envoie pas, garde le brouillon et passe la main à la boîte d’envoi (P5-10)', async () => {
    const store = memoryStore();
    const c = mount({ store });
    await c.init();
    check('type', 'need');
    form().querySelector('[name="needs[]"][value="hebergement"]').checked = true;
    fill('description', STORY);
    fill('zone_id', '1');
    c.goTo(6);
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    const handoff = vi.fn();
    form().addEventListener('xm:outbox-submit', handoff);

    const event = new Event('submit', { cancelable: true });
    c.submit(event);

    expect(event.defaultPrevented).toBe(true);
    expect(c.offline).toBe(true);
    expect(c.sending).toBe(false);
    expect(handoff).toHaveBeenCalledTimes(1);
    expect(handoff.mock.calls[0][0].detail.type).toBe('signalement');
    expect(store.save).toHaveBeenCalled();
  });

  it('applique la cascade depuis GET /api/v1/zones et garde le repli du balisage', async () => {
    const c = mount({ store: memoryStore(), zonesUrl: '/api/v1/zones' });
    await c.init();
    await c.loadZones();
    fill('zone_id', '1');
    c.onChange({ target: form().querySelector('[name="zone_id"]') });

    expect(fetch).toHaveBeenCalledWith('/api/v1/zones', expect.objectContaining({ headers: { Accept: 'application/json' } }));
    expect(Array.from(c.$refs.commune.options).map((o) => o.textContent)).toEqual(['Choisir…', 'Cotonou']);

    fetch.mockRejectedValue(new Error('offline'));
    const offline = mount({ store: memoryStore(), zonesUrl: '/api/v1/zones' });
    document.body.innerHTML = page();
    offline.$refs.form = form();
    offline.$refs.commune = form().querySelector('[name="commune_id"]');
    await offline.init();
    await offline.loadZones();
    fill('zone_id', '2');
    offline.onChange({ target: form().querySelector('[name="zone_id"]') });
    expect(Array.from(offline.$refs.commune.options).map((o) => o.textContent)).toEqual(['Choisir…', 'Abomey-Calavi', 'Ouidah']);
  });

  it('propose le brouillon sans le reprendre d’office, puis le reprend ou l’efface', async () => {
    const store = memoryStore({ step: 3, type: 'need', description: STORY, needs: ['hebergement'], zone_id: '2', commune_id: '21', saved_at: Date.now() });
    const c = mount({ store });
    await c.init();

    expect(c.draftState).toBe('found');
    expect(form().querySelector('[name="description"]').value).toBe('');

    await c.resumeDraft();
    expect(c.step).toBe(3);
    expect(readValues(form())).toMatchObject({ type: 'need', description: STORY, zone_id: '2', commune_id: '21' });
    expect(c.draftMessage).toBe('Brouillon repris.');

    await c.clearDraft();
    expect(store.peek()).toBeNull();
    expect(c.draftMessage).toBe('Brouillon effacé.');
  });

  it('ne propose pas de brouillon après un envoi refusé (old()) et efface un brouillon expiré', async () => {
    const withOld = memoryStore({ step: 2, saved_at: Date.now() });
    const c = mount({ store: withOld, hasOld: true });
    await c.init();
    expect(c.draftState).toBe('');
    expect(withOld.load).not.toHaveBeenCalled();

    const expired = memoryStore({ step: 2, saved_at: Date.now() - 30 * 86400000 });
    const d = mount({ store: expired });
    await d.init();
    expect(d.draftState).toBe('');
    expect(expired.clear).toHaveBeenCalled();
  });

  it('retire le contact d’un brouillon d’une version antérieure, sans le proposer autrement', async () => {
    const store = memoryStore({ step: 5, type: 'need', contactable: true, contact: '97 38 88 47', saved_at: Date.now() });
    const c = mount({ store });
    await c.init();

    expect(c.draftState).toBe('found');
    expect(store.peek()).not.toHaveProperty('contact');
    expect(store.peek()).toMatchObject({ step: 5, type: 'need', contactable: true });
  });

  it('efface le brouillon par « Retour » vers l’accueil à la première étape, puis navigue', async () => {
    const store = memoryStore({ step: 2, type: 'need', saved_at: Date.now() });
    const navigate = vi.fn();
    const c = mount({ store, navigate });
    await c.init();
    const link = document.createElement('a');
    link.href = 'http://localhost/';
    const event = { currentTarget: link, preventDefault: vi.fn() };

    await c.leave(event);
    expect(event.preventDefault).toHaveBeenCalled();
    expect(store.clear).toHaveBeenCalled();
    expect(store.peek()).toBeNull();
    expect(navigate).toHaveBeenCalledWith('http://localhost/');
  });

  it('rouvre l’étape du champ visé par un lien du résumé d’erreurs, puis lui donne le focus', async () => {
    document.body.innerHTML = `<form>
      <section class="xm-report__step" id="etape-1"><h2 id="etape-1-titre" tabindex="-1">Type</h2></section>
      <section class="xm-report__step" id="etape-3"><h2 id="etape-3-titre" tabindex="-1">Lieu</h2>
        <select id="f-zone_id" name="zone_id"><option value="">Choisir…</option></select>
        <div id="f-position" tabindex="-1"></div></section>
    </form>
    <div class="alert"><a href="#f-zone_id">département requis</a><a href="#f-position">position</a><a href="#ailleurs">x</a></div>`;
    const c = mount({ store: memoryStore() });
    const [zone, position, other] = document.querySelectorAll('.alert a');
    const click = (target) => ({ target, preventDefault: vi.fn() });

    const first = click(zone);
    expect(c.jump(first)).toBe(true);
    expect(first.preventDefault).toHaveBeenCalled();
    expect(c.step).toBe(3);
    expect(document.activeElement.id).toBe('f-zone_id');

    c.goTo(1);
    expect(c.jump(click(position))).toBe(true);
    expect(c.step).toBe(3);
    expect(document.activeElement.id).toBe('f-position');

    const outside = click(other);
    expect(c.jump(outside)).toBe(false);
    expect(outside.preventDefault).not.toHaveBeenCalled();
    document.body.innerHTML = page();
  });

  it('joint puis retire la position (champs cachés lat, lng, accuracy)', async () => {
    const c = mount({ store: memoryStore() });
    await c.init();
    c.setPosition({ lat: 6.36, lng: 2.42, accuracy: 30 });

    expect(readValues(form())).toMatchObject({ lat: '6.36', lng: '2.42', accuracy: '30' });
    expect(c.positionText).toBe('Position jointe : 6.36, 2.42 (± 30 m)');

    c.clearPosition();
    expect(readValues(form())).toMatchObject({ lat: '', lng: '', accuracy: '' });
    expect(c.positionText).toBe('Facultatif');
  });
});

describe('signaler — composant xmReportSent', () => {
  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('efface le brouillon à l’affichage et copie le code de suivi', async () => {
    const store = memoryStore({ step: 6, saved_at: Date.now() });
    const writeText = vi.fn(async () => undefined);
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } });
    const c = xmReportSent({ store, code: 'SIG-A7K2M9P4QX', texts: { copied: 'Code copié.', copy_failed: 'Copie impossible.' } });

    await c.init();
    expect(store.clear).toHaveBeenCalled();

    await c.copy();
    expect(writeText).toHaveBeenCalledWith('SIG-A7K2M9P4QX');
    expect(c.copied).toBe('Code copié.');

    writeText.mockRejectedValueOnce(new Error('denied'));
    await c.copy();
    expect(c.copied).toBe('Copie impossible.');
  });
});
