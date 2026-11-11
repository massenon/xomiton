// Formulaire /inscription en 3 étapes obligatoires (décision client du 11/11/2026) : amélioration progressive du formulaire
// Blade, qui s'envoie tel quel sans JavaScript (POST + @csrf, App\Http\Requests\Auth\RegisterRequest) avec les trois étapes
// visibles à la suite. Le composant Alpine xmRegister n'ajoute que :
//  - le pas à pas « Étape n sur 3 » (une étape visible, aria-current="step" tenu à jour, focus sur le titre de l'étape),
//    chaque étape étant validée avant « Suivant » avec les mêmes règles que le serveur (étapes et champs reçus de
//    RegistrationSteps) ; « Précédent » toujours présent (à l'étape 1 il ramène à la connexion) ;
//  - un résumé d'erreurs focalisable (liens vers les champs) et un message sous chaque champ en erreur (aria-invalid,
//    aria-describedby) ; les liens d'un résumé (serveur ou client) rouvrent l'étape du champ visé ;
//  - les blocs conditionnels (membre ou allié·e, précision « autre », zone → commune du pays choisi).
// Les données restent dans le formulaire d'une étape à l'autre. Aucun texte ici : tout vient de Blade (lang/fr) par `opts.texts`.

const NAME = /^[\p{L}\p{M}][\p{L}\p{M} '’.-]*$/u;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/u;

/** Valeurs du formulaire : textes rognés, cases en booléens, groupe radio en valeur choisie ('' si aucune). */
export function readValues(form) {
  const values = {};
  form.querySelectorAll('input[name], select[name], textarea[name]').forEach((el) => {
    if (el.type === 'radio') {
      if (!(el.name in values)) values[el.name] = '';
      if (el.checked) values[el.name] = el.value;
    } else if (el.type === 'checkbox') {
      values[el.name] = el.checked;
    } else if (el.name !== '_token') {
      values[el.name] = el.type === 'password' ? el.value : String(el.value ?? '').trim();
    }
  });
  return values;
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

/** Numéro international, mêmes règles que App\Rules\RegistrationPhone : « + » puis 8 à 15 chiffres. */
export function isPhoneInternational(value = '') {
  return /^\+[1-9]\d{7,14}$/u.test(String(value).trim().replace(/[\s.\-()]/gu, ''));
}

const fill = (text = '', replacements = {}) => Object.entries(replacements).reduce((out, [key, value]) => out.replace(`:${key}`, String(value)), String(text));

/**
 * Erreurs d'une étape : { champ: message }, mêmes règles que App\Http\Requests\Support\RegistrationRules (le serveur
 * revalide tout à l'envoi). `cfg` : { minAge, benin, texts: { errors } }.
 */
export function validateStep(key, values, cfg) {
  const t = (cfg.texts && cfg.texts.errors) || {};
  const errors = {};
  const need = (field, message) => {
    const value = values[field];
    if (value === undefined || value === null || value === false || String(value).trim() === '') errors[field] = message;
  };

  if (key === 'basic') {
    need('first_name', t.first_name_required);
    need('last_name', t.last_name_required);
    need('pseudo', t.pseudo_required);
    need('email', t.email_required);
    need('age', t.age_required);
    if (!errors.first_name && !NAME.test(values.first_name)) errors.first_name = t.name_format;
    if (!errors.last_name && !NAME.test(values.last_name)) errors.last_name = t.name_format;
    if (!errors.email && !EMAIL.test(values.email)) errors.email = t.email_format;
    if (!errors.age) {
      if (!/^\d+$/u.test(String(values.age))) errors.age = t.age_number;
      else if (Number(values.age) < Number(cfg.minAge)) errors.age = fill(t.age_min, { min_age: cfg.minAge });
    }
  }

  if (key === 'profile') {
    need('pronouns', t.pronouns_required);
    need('country_code', t.country_required);
    need('member_type', t.member_type_required);
    const phone = String(values.phone || '').trim();
    if (phone !== '') {
      const ok = values.country_code === cfg.benin ? isPhoneBj(phone) : isPhoneInternational(phone);
      if (!ok) errors.phone = t.phone_format;
    }
  }

  if (key === 'details') {
    if (values.member_type === 'membre') {
      need('gender_identity', t.gender_identity_required);
      need('orientation', t.orientation_required);
      if (values.gender_identity === 'autre') need('gender_identity_other', t.other_required);
      if (values.orientation === 'autre') need('orientation_other', t.other_required);
      if (!values.sensitive_data) errors.sensitive_data = t.sensitive_data_required;
    } else if (values.member_type === 'allie') {
      need('ally_role', t.ally_role_required);
      if (values.ally_role === 'autre') need('ally_role_other', t.other_required);
    }
    need('password', t.password_required);
    if (!errors.password && values.password !== values.password_confirmation) errors.password_confirmation = t.password_mismatch;
    if (!values.cgu) errors.cgu = t.cgu_required;
    if (!values.privacy) errors.privacy = t.privacy_required;
  }

  return errors;
}

/** Première étape invalide (numéro à partir de 1, 0 si tout est valide) et ses erreurs. */
export function firstInvalidStep(steps, values, cfg) {
  for (let i = 0; i < steps.length; i += 1) {
    const errors = validateStep(steps[i].key, values, cfg);
    if (Object.keys(errors).length > 0) return { step: i + 1, errors };
  }
  return { step: 0, errors: {} };
}

function fieldElement(form, field) {
  return form.ownerDocument.getElementById(`f-${field}`);
}

/** Retire l'erreur (serveur ou client) d'un champ : message, aria-invalid, is-invalid, référence dans aria-describedby. */
export function clearField(form, field) {
  const el = fieldElement(form, field);
  const message = form.ownerDocument.getElementById(`f-${field}-error`);
  if (message) message.remove();
  if (!el) return;
  const controls = el.matches('input, select, textarea') ? [el] : Array.from(el.querySelectorAll('input, select, textarea'));
  [el, ...controls].forEach((node) => {
    node.removeAttribute('aria-invalid');
    node.classList.remove('is-invalid');
    const describedBy = (node.getAttribute('aria-describedby') || '').split(/\s+/u).filter((id) => id && id !== `f-${field}-error`);
    if (describedBy.length > 0) node.setAttribute('aria-describedby', describedBy.join(' '));
    else node.removeAttribute('aria-describedby');
  });
}

/** Marque les champs de `errors` : message sous le champ (aria-describedby) et aria-invalid. */
export function markFields(form, errors) {
  Object.entries(errors).forEach(([field, message]) => {
    const el = fieldElement(form, field);
    if (!el) return;
    const doc = form.ownerDocument;
    const wrapper = el.closest('.xm-field') || el.parentElement;
    const note = doc.createElement('div');
    note.id = `f-${field}-error`;
    note.className = 'invalid-feedback d-block';
    const icon = doc.createElement('i');
    icon.className = 'bi bi-exclamation-circle me-1';
    icon.setAttribute('aria-hidden', 'true');
    note.append(icon, doc.createTextNode(message));
    wrapper.append(note);
    const controls = el.matches('input, select, textarea') ? [el] : Array.from(el.querySelectorAll('input, select, textarea'));
    [el, ...controls].forEach((node) => {
      node.setAttribute('aria-invalid', 'true');
      if (node.matches('input, select, textarea')) node.classList.add('is-invalid');
      const describedBy = (node.getAttribute('aria-describedby') || '').split(/\s+/u).filter(Boolean);
      if (!describedBy.includes(note.id)) describedBy.push(note.id);
      node.setAttribute('aria-describedby', describedBy.join(' '));
    });
  });
}

const clamp = (n, total) => Math.min(Math.max(Number(n) || 1, 1), Math.max(total, 1));

/** Met à jour le stepper (étapes terminées, étape courante, texte caché d'état). */
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

export function xmRegister(opts = {}) {
  const steps = Array.isArray(opts.steps) ? opts.steps : [];
  const texts = opts.texts || {};
  const communes = opts.communes || {};
  const zones = opts.zones || {};
  const zoneCountries = Array.isArray(opts.zoneCountries) ? opts.zoneCountries : [];

  return {
    step: clamp(opts.initial, steps.length),
    total: steps.length,
    counter: '',
    errorList: [],
    sending: false,
    memberType: '',
    isBenin: true,
    hasZones: true,
    genderIdentity: '',
    orientation: '',
    allyRole: '',

    init() {
      const form = this.$refs.form;
      form.noValidate = true;
      this.refresh();
      this.refreshZones();
      this.refreshCommunes();
      this.syncProgress();
      // Retour par l'historique (cache de page) : le bouton d'envoi redevient actif.
      window.addEventListener('pageshow', () => { this.sending = false; });
    },

    cfg() {
      return { minAge: opts.minAge, benin: opts.benin, texts };
    },

    isStep(n) {
      return this.step === n;
    },

    isLast() {
      return this.step === this.total;
    },

    /** Relit les champs qui commandent les blocs conditionnels. */
    refresh() {
      const values = readValues(this.$refs.form);
      this.memberType = values.member_type || '';
      this.isBenin = values.country_code === opts.benin;
      this.hasZones = zoneCountries.includes(values.country_code);
      this.genderIdentity = values.gender_identity || '';
      this.orientation = values.orientation || '';
      this.allyRole = values.ally_role || '';
      return values;
    },

    onChange(event) {
      const name = event && event.target ? event.target.name : '';
      this.refresh();
      if (name === 'country_code') {
        this.refreshZones();
        this.refreshCommunes();
      }
      if (name === 'zone_id') this.refreshCommunes();
    },

    /** Zones du pays choisi (le balisage serveur les liste toutes, par pays, pour le cas sans JavaScript). */
    refreshZones() {
      const select = this.$refs.zone;
      const country = this.$refs.form.querySelector('[name="country_code"]');
      if (!select || !country) return;
      const doc = select.ownerDocument;
      const previous = select.value;
      const list = zones[country.value] || [];
      const options = [];
      const placeholder = doc.createElement('option');
      placeholder.value = '';
      placeholder.textContent = texts.zone_placeholder || '';
      options.push(placeholder);
      list.forEach((zone) => {
        const option = doc.createElement('option');
        option.value = String(zone.id);
        option.textContent = zone.name;
        option.selected = String(zone.id) === previous;
        options.push(option);
      });
      select.replaceChildren(...options);
    },

    /** Communes de la zone choisie (le balisage serveur les liste toutes, par département, pour le cas sans JavaScript). */
    refreshCommunes() {
      const select = this.$refs.commune;
      const zone = this.$refs.form.querySelector('[name="zone_id"]');
      if (!select || !zone) return;
      const doc = select.ownerDocument;
      const previous = select.value;
      const list = communes[zone.value] || [];
      const options = [];
      const placeholder = doc.createElement('option');
      placeholder.value = '';
      placeholder.textContent = zone.value === '' ? (texts.commune_first || '') : (texts.commune_placeholder || '');
      options.push(placeholder);
      list.forEach((commune) => {
        const option = doc.createElement('option');
        option.value = String(commune.id);
        option.textContent = commune.name;
        option.selected = String(commune.id) === previous;
        options.push(option);
      });
      select.replaceChildren(...options);
      select.disabled = zone.value === '';
    },

    validate(n) {
      const step = steps[n - 1];
      const form = this.$refs.form;
      const errors = step ? validateStep(step.key, readValues(form), this.cfg()) : {};
      this.showErrors(errors, step ? step.fields : []);
      return Object.keys(errors).length === 0;
    },

    showErrors(errors, fields = []) {
      const form = this.$refs.form;
      fields.forEach((field) => clearField(form, field));
      Object.keys(errors).forEach((field) => clearField(form, field));
      this.errorList = Object.entries(errors).map(([field, message]) => ({ field, message, target: `f-${field}` }));
      markFields(form, errors);
      if (this.errorList.length > 0) this.$nextTick(() => this.$refs.clientErrors && this.$refs.clientErrors.focus());
    },

    next() {
      if (!this.validate(this.step)) return false;
      this.goTo(this.step + 1);
      return true;
    },

    back() {
      this.showErrors({}, (steps[this.step - 1] || {}).fields || []);
      this.goTo(this.step - 1);
    },

    /** « Précédent » : étape précédente ; à l'étape 1 le lien ramène à la connexion. */
    previous(event) {
      if (this.step <= 1) return;
      event.preventDefault();
      this.back();
    },

    goTo(n, focus = null) {
      this.step = clamp(n, this.total);
      this.syncProgress();
      this.$nextTick(() => {
        const el = focus || this.$refs.form.ownerDocument.getElementById(`etape-${this.step}-titre`);
        if (el) el.focus();
      });
    },

    /** Lien d'un résumé d'erreurs (serveur ou client) : rouvre l'étape qui porte le champ visé, puis le focalise. */
    jump(event) {
      const link = event && event.target && event.target.closest ? event.target.closest('a[href^="#"]') : null;
      if (!link) return false;
      const el = this.$refs.form.ownerDocument.getElementById(decodeURIComponent(link.getAttribute('href').slice(1)));
      const section = el && this.$refs.form.contains(el) ? el.closest('.xm-register__step') : null;
      const match = section ? /^etape-(\d+)$/u.exec(section.id) : null;
      if (!match) return false;
      event.preventDefault();
      this.goTo(Number(match[1]), el);
      return true;
    },

    syncProgress() {
      const list = this.$refs.progress ? this.$refs.progress.querySelector('.xm-stepper') : null;
      syncStepper(list, this.step, texts.stepper || {});
      this.counter = fill(texts.step_of || '', { current: this.step, total: this.total });
    },

    submit(event) {
      const form = this.$refs.form;
      if (this.step < this.total) {
        // Entrée dans un champ : on avance d'une étape, jamais d'envoi avant la dernière.
        event.preventDefault();
        this.next();
        return;
      }
      const { step, errors } = firstInvalidStep(steps, readValues(form), this.cfg());
      if (step > 0) {
        event.preventDefault();
        this.goTo(step);
        this.showErrors(errors, steps[step - 1].fields);
        return;
      }
      if (this.sending) {
        event.preventDefault();
        return;
      }
      this.sending = true;
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmRegister', xmRegister);
  });
}
