// @vitest-environment jsdom
// Formulaire /inscription en 3 étapes (décision client du 11/11/2026) : lecture du formulaire, validation de chaque étape
// (mêmes règles que App\Http\Requests\Support\RegistrationRules), messages sous les champs (aria-invalid, aria-describedby),
// stepper aria-current, composant xmRegister (« Suivant » bloqué si l'étape est invalide, « Précédent », liens du résumé).
import { beforeEach, describe, expect, it } from 'vitest';
import {
  clearField, firstInvalidStep, isPhoneBj, isPhoneInternational, markFields, readValues, syncStepper, validateStep, xmRegister,
} from './inscription.js';

const STEPS = [
  { key: 'basic', fields: ['first_name', 'last_name', 'pseudo', 'email', 'age'] },
  { key: 'profile', fields: ['pronouns', 'country_code', 'city', 'phone', 'zone_id', 'commune_id', 'member_type'] },
  { key: 'details', fields: ['gender_identity', 'gender_identity_other', 'orientation', 'orientation_other', 'sensitive_data', 'ally_role', 'ally_role_other', 'password', 'password_confirmation', 'cgu', 'privacy'] },
];

const CFG = {
  minAge: 18,
  benin: 'BJ',
  texts: {
    errors: {
      first_name_required: 'prénom requis', last_name_required: 'nom requis', name_format: 'nom invalide', email_required: 'e-mail requis',
      email_format: 'e-mail invalide', pseudo_required: 'pseudo requis', age_required: 'âge requis', age_number: 'âge en chiffres',
      age_min: 'Tu dois avoir au moins :min_age ans', pronouns_required: 'pronoms requis', country_required: 'pays requis',
      phone_format: 'téléphone invalide', member_type_required: 'type requis', gender_identity_required: 'genre requis',
      orientation_required: 'orientation requise', other_required: 'précision requise', sensitive_data_required: 'consentement requis',
      ally_role_required: 'statut requis', password_required: 'mot de passe requis', password_mismatch: 'mots de passe différents',
      cgu_required: 'CGU requises', privacy_required: 'confidentialité requise',
    },
    stepper: { done: 'terminée', current: 'en cours', todo: 'à venir' },
    step_of: 'Étape :current sur :total',
  },
};

const MEMBER = {
  first_name: 'Awa', last_name: 'Sossou', pseudo: 'Awa_S', email: 'awa@exemple.bj', age: '24', pronouns: 'elle', country_code: 'BJ', phone: '',
  member_type: 'membre', gender_identity: 'femme_cis', orientation: 'lesbienne', sensitive_data: true, password: 'Xomiton2026!',
  password_confirmation: 'Xomiton2026!', cgu: true, privacy: true,
};

describe('validateStep', () => {
  it('étape 1 : champs requis, forme du nom et de l’e-mail, âge numérique ≥ âge minimal lu dans la configuration', () => {
    expect(validateStep('basic', MEMBER, CFG)).toEqual({});
    expect(Object.keys(validateStep('basic', { ...MEMBER, first_name: '', email: '', age: '' }, CFG))).toEqual(['first_name', 'email', 'age']);
    expect(validateStep('basic', { ...MEMBER, last_name: '123' }, CFG)).toEqual({ last_name: 'nom invalide' });
    expect(validateStep('basic', { ...MEMBER, email: 'pasunemail' }, CFG)).toEqual({ email: 'e-mail invalide' });
    expect(validateStep('basic', { ...MEMBER, age: 'vingt' }, CFG)).toEqual({ age: 'âge en chiffres' });
    expect(validateStep('basic', { ...MEMBER, age: '17' }, CFG)).toEqual({ age: 'Tu dois avoir au moins 18 ans' });
    expect(validateStep('basic', { ...MEMBER, age: '17' }, { ...CFG, minAge: 21 })).toEqual({ age: 'Tu dois avoir au moins 21 ans' });
  });

  it('étape 2 : pronoms, pays, type requis ; téléphone selon le pays', () => {
    expect(validateStep('profile', MEMBER, CFG)).toEqual({});
    expect(Object.keys(validateStep('profile', { ...MEMBER, pronouns: '', country_code: '', member_type: '' }, CFG))).toEqual(['pronouns', 'country_code', 'member_type']);
    expect(validateStep('profile', { ...MEMBER, phone: '97 38 88 47' }, CFG)).toEqual({});
    expect(validateStep('profile', { ...MEMBER, phone: '123' }, CFG)).toEqual({ phone: 'téléphone invalide' });
    expect(validateStep('profile', { ...MEMBER, country_code: 'FR', phone: '+33 6 12 34 56 78' }, CFG)).toEqual({});
    expect(validateStep('profile', { ...MEMBER, country_code: 'FR', phone: '0612345678' }, CFG)).toEqual({ phone: 'téléphone invalide' });
  });

  it('étape 3 : membre → identité, orientation, consentement ; « autre » → précision ; allié·e → statut ; sécurité', () => {
    expect(validateStep('details', MEMBER, CFG)).toEqual({});
    expect(Object.keys(validateStep('details', { ...MEMBER, gender_identity: '', orientation: '', sensitive_data: false }, CFG))).toEqual(['gender_identity', 'orientation', 'sensitive_data']);
    expect(Object.keys(validateStep('details', { ...MEMBER, gender_identity: 'autre', orientation: 'autre' }, CFG))).toEqual(['gender_identity_other', 'orientation_other']);
    const ally = { ...MEMBER, member_type: 'allie', gender_identity: '', orientation: '', sensitive_data: false, ally_role: '' };
    expect(validateStep('details', ally, CFG)).toEqual({ ally_role: 'statut requis' });
    expect(validateStep('details', { ...ally, ally_role: 'autre' }, CFG)).toEqual({ ally_role_other: 'précision requise' });
    expect(validateStep('details', { ...ally, ally_role: 'avocat' }, CFG)).toEqual({});
    expect(validateStep('details', { ...MEMBER, password_confirmation: 'autre' }, CFG)).toEqual({ password_confirmation: 'mots de passe différents' });
    expect(Object.keys(validateStep('details', { ...MEMBER, cgu: false, privacy: false, password: '' }, CFG))).toEqual(['password', 'cgu', 'privacy']);
  });

  it('firstInvalidStep rend la première étape fautive, 0 si tout est valide', () => {
    expect(firstInvalidStep(STEPS, MEMBER, CFG)).toEqual({ step: 0, errors: {} });
    expect(firstInvalidStep(STEPS, { ...MEMBER, country_code: '', cgu: false }, CFG).step).toBe(2);
    expect(firstInvalidStep(STEPS, { ...MEMBER, age: '3' }, CFG).step).toBe(1);
  });

  it('formats de téléphone : béninois (PhoneBj) et international (« + » puis 8 à 15 chiffres)', () => {
    expect(isPhoneBj('+229 01 97 38 88 47')).toBe(true);
    expect(isPhoneBj('0197388847')).toBe(true);
    expect(isPhoneBj('12345')).toBe(false);
    expect(isPhoneInternational('+33 6 12 34 56 78')).toBe(true);
    expect(isPhoneInternational('+1234567')).toBe(false);
    expect(isPhoneInternational('0612345678')).toBe(false);
  });
});

function formHtml() {
  document.body.innerHTML = `
    <div id="root">
      <div class="xm-auth-progress"><ol class="xm-stepper">
        ${[1, 2, 3].map((n) => `<li class="xm-stepper__item${n === 1 ? ' is-current' : ''}"><span class="xm-stepper__dot">${n}</span><span>Étape<span class="visually-hidden"> — x</span></span></li>`).join('')}
      </ol></div>
      <div class="alert" tabindex="-1" id="clientErrors"></div>
      <form id="form">
        <section class="xm-register__step" id="etape-1"><h2 id="etape-1-titre" tabindex="-1">1</h2>
          <div class="xm-field"><input id="f-first_name" name="first_name" value=" Awa "></div>
          <div class="xm-field"><input id="f-age" name="age" value="12" aria-describedby="f-age-hint"></div>
        </section>
        <section class="xm-register__step" id="etape-2"><h2 id="etape-2-titre" tabindex="-1">2</h2>
          <div class="xm-field"><fieldset id="f-member_type"><input type="radio" name="member_type" value="membre"><input type="radio" name="member_type" value="allie" checked></fieldset></div>
          <div class="xm-field"><select id="f-country_code" name="country_code"><option value="BJ" selected>Bénin</option><option value="FR">France</option></select></div>
          <select id="f-zone_id" name="zone_id"><option value="">—</option><option value="1">Littoral</option></select>
          <select id="f-commune_id" name="commune_id"><option value="">—</option></select>
        </section>
        <section class="xm-register__step" id="etape-3"><h2 id="etape-3-titre" tabindex="-1">3</h2>
          <div class="xm-field"><input type="checkbox" id="f-cgu" name="cgu" checked></div>
          <input type="password" name="password" value=" secret ">
        </section>
      </form>
    </div>`;
  return document.getElementById('form');
}

describe('formulaire', () => {
  beforeEach(() => { document.body.innerHTML = ''; });

  it('readValues : textes rognés (sauf mot de passe), cases en booléens, groupe radio en valeur choisie', () => {
    const values = readValues(formHtml());
    expect(values).toMatchObject({ first_name: 'Awa', age: '12', member_type: 'allie', country_code: 'BJ', cgu: true, password: ' secret ' });
  });

  it('markFields puis clearField : message sous le champ, aria-invalid et aria-describedby tenus à jour', () => {
    const form = formHtml();
    markFields(form, { age: 'Tu dois avoir au moins 18 ans' });
    const input = document.getElementById('f-age');
    expect(input.getAttribute('aria-invalid')).toBe('true');
    expect(input.classList.contains('is-invalid')).toBe(true);
    expect(input.getAttribute('aria-describedby')).toBe('f-age-hint f-age-error');
    expect(document.getElementById('f-age-error').textContent).toContain('au moins 18 ans');

    markFields(form, { member_type: 'type requis' });
    expect(document.getElementById('f-member_type').getAttribute('aria-invalid')).toBe('true');

    clearField(form, 'age');
    expect(document.getElementById('f-age-error')).toBeNull();
    expect(input.hasAttribute('aria-invalid')).toBe(false);
    expect(input.getAttribute('aria-describedby')).toBe('f-age-hint');
  });

  it('syncStepper : étapes terminées, étape courante (aria-current) et texte d’état', () => {
    formHtml();
    const list = document.querySelector('.xm-stepper');
    syncStepper(list, 2, CFG.texts.stepper);
    const items = list.querySelectorAll('.xm-stepper__item');
    expect(items[0].classList.contains('is-done')).toBe(true);
    expect(items[1].getAttribute('aria-current')).toBe('step');
    expect(items[2].hasAttribute('aria-current')).toBe(false);
    expect(items[1].querySelector('.visually-hidden').textContent).toContain('en cours');
  });
});

describe('xmRegister', () => {
  function component(values = {}) {
    const form = formHtml();
    Object.entries(values).forEach(([name, value]) => { form.elements[name].value = value; });
    const data = xmRegister({ ...CFG, initial: 1, steps: STEPS, communes: { 1: [{ id: 5, name: 'Cotonou' }] }, texts: CFG.texts });
    data.$refs = { form, progress: document.querySelector('.xm-auth-progress'), clientErrors: document.getElementById('clientErrors'), commune: document.getElementById('f-commune_id') };
    data.$nextTick = (fn) => fn();
    data.init();
    return data;
  }

  it('reste à l’étape 1 tant qu’elle est invalide, liste les erreurs et marque les champs ; avance quand tout est valide', () => {
    const c = component({ age: '12' });
    expect(c.counter).toBe('Étape 1 sur 3');
    expect(c.next()).toBe(false);
    expect(c.step).toBe(1);
    expect(c.errorList.map((e) => e.field)).toEqual(['last_name', 'pseudo', 'email', 'age']);
    expect(document.getElementById('f-age').getAttribute('aria-invalid')).toBe('true');

    document.getElementById('f-age').value = '24';
    ['last_name', 'pseudo', 'email'].forEach((n) => {
      const el = document.createElement('input');
      el.name = n; el.id = `f-${n}`; el.value = { email: 'awa@exemple.bj', last_name: 'Sossou', pseudo: 'Awa_S' }[n];
      document.querySelector('#etape-1 .xm-field').append(el);
    });
    expect(c.next()).toBe(true);
    expect(c.step).toBe(2);
    expect(c.counter).toBe('Étape 2 sur 3');
    expect(document.activeElement.id).toBe('etape-2-titre');
  });

  it('« Précédent » revient d’une étape ; à l’étape 1 il laisse le lien (retour à la connexion)', () => {
    const c = component();
    let prevented = false;
    c.previous({ preventDefault: () => { prevented = true; } });
    expect(prevented).toBe(false);
    c.goTo(3);
    expect(c.isLast()).toBe(true);
    c.previous({ preventDefault: () => { prevented = true; } });
    expect(prevented).toBe(true);
    expect(c.step).toBe(2);
  });

  it('conditions : bloc membre ou allié·e, pays → département et commune au Bénin seulement, communes du département', () => {
    const c = component();
    expect(c.memberType).toBe('allie');
    expect(c.isBenin).toBe(true);
    document.getElementById('f-country_code').value = 'FR';
    c.onChange({ target: { name: 'country_code' } });
    expect(c.isBenin).toBe(false);

    const zone = document.getElementById('f-zone_id');
    zone.value = '1';
    c.onChange({ target: { name: 'zone_id' } });
    expect([...document.getElementById('f-commune_id').options].map((o) => o.textContent)).toEqual(['', 'Cotonou']);
  });

  it('un lien du résumé d’erreurs rouvre l’étape du champ visé puis le focalise', () => {
    const c = component();
    const link = document.createElement('a');
    link.setAttribute('href', '#f-cgu');
    document.body.append(link);
    let prevented = false;
    expect(c.jump({ target: link, preventDefault: () => { prevented = true; } })).toBe(true);
    expect(prevented).toBe(true);
    expect(c.step).toBe(3);
    expect(document.activeElement.id).toBe('f-cgu');
  });

  it('l’envoi final est bloqué (et l’étape fautive rouverte) si une étape est invalide ; Entrée avant la dernière avance sans envoyer', () => {
    const c = component();
    let prevented = 0;
    const event = { preventDefault: () => { prevented += 1; } };
    c.submit(event);
    expect(prevented).toBe(1);
    expect(c.step).toBe(1);

    c.step = 3;
    c.submit(event);
    expect(prevented).toBe(2);
    expect(c.step).toBe(1);
    expect(c.sending).toBe(false);
  });
});
