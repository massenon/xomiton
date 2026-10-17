// Jauge de solidité du mot de passe (LOGIN §4, Q30) : port de prototype_social/assets/js/pswmeter.js sur les règles
// Xomiton. MÊMES RÈGLES que App\Domain\Auth\PasswordStrength (serveur) : 10 caractères minimum, puis majuscule,
// minuscule et chiffre pour « Fort », un symbole en plus pour « Excellent ». La jauge est indicative : le bouton
// d'envoi reste actif, le serveur tranche. Couleur jamais seule : 4 segments + texte + liste des règles cochées.
//
// Balisage attendu (texte fourni par Blade via lang/fr/auth.php, jamais codé ici) :
//   <input id="password" type="password" data-password-meter="#pwMeter">
//   <div id="pwMeter" class="xm-pw-meter" data-labels='{"too_short":"Trop court",…}' aria-live="polite">
//     <div class="xm-pw-meter__segments"><span class="xm-pw-meter__segment"></span> ×4</div>
//     <p class="xm-pw-meter__label" data-password-label></p>
//     <ul class="xm-pw-meter__rules"><li data-rule="length">…</li><li data-rule="uppercase">…</li> …</ul>
//   </div>

export const MIN_LENGTH = 10;
export const SEGMENTS = 4;

/** Clé de niveau → nombre de segments allumés et variante Bootstrap (text porte toujours l'information). */
export const LEVELS = {
  too_short: { segments: 0, tone: 'danger' },
  weak: { segments: 1, tone: 'danger' },
  medium: { segments: 2, tone: 'warning' },
  strong: { segments: 3, tone: 'success' },
  excellent: { segments: 4, tone: 'success' },
};

const LETTER_OR_DIGIT = /[\p{L}\p{N}\s]/u;

/** Règles cochées en direct (même ordre que PasswordStrength::checks()). */
export function checks(password) {
  const value = String(password || '');
  return {
    length: [...value].length >= MIN_LENGTH,
    uppercase: /\p{Lu}/u.test(value),
    lowercase: /\p{Ll}/u.test(value),
    digit: /\d/.test(value),
    symbol: [...value].some((ch) => !LETTER_OR_DIGIT.test(ch)),
  };
}

/** Niveau : 'too_short' | 'weak' | 'medium' | 'strong' | 'excellent' (PasswordStrength::score()). */
export function score(password) {
  const c = checks(password);
  if (!c.length) return 'too_short';
  const kinds = Number(c.lowercase) + Number(c.uppercase) + Number(c.digit);
  if (kinds === 3) return c.symbol ? 'excellent' : 'strong';
  if (kinds === 2) return 'medium';
  return 'weak';
}

export function isAcceptable(level) {
  return LEVELS[level].segments >= LEVELS.strong.segments;
}

const readLabels = (meter) => {
  try {
    return JSON.parse(meter.dataset.labels || '{}');
  } catch {
    return {};
  }
};

/** Met à jour une jauge pour un mot de passe donné ; renvoie le niveau. */
export function render(meter, password) {
  const level = score(password);
  const { segments, tone } = LEVELS[level];
  const labels = readLabels(meter);
  const empty = String(password || '').length === 0;

  meter.dataset.level = level;
  meter.querySelectorAll('.xm-pw-meter__segment').forEach((seg, index) => {
    const on = !empty && index < segments;
    seg.classList.toggle('is-on', on);
    ['danger', 'warning', 'success'].forEach((t) => seg.classList.toggle(`is-${t}`, on && t === tone));
  });

  const label = meter.querySelector('[data-password-label]');
  if (label) label.textContent = empty ? (labels.empty || '') : (labels[level] || level);

  const results = checks(password);
  meter.querySelectorAll('[data-rule]').forEach((item) => {
    const ok = !!results[item.dataset.rule];
    item.classList.toggle('is-ok', ok);
    item.setAttribute('data-checked', ok ? 'true' : 'false');
    const icon = item.querySelector('[data-rule-icon]');
    if (icon) icon.className = `bi ${ok ? 'bi-check-circle-fill' : 'bi-circle'} me-1`;
  });

  meter.dispatchEvent(new CustomEvent('xm:password-score', { bubbles: true, detail: { level, segments, checks: results } }));
  return level;
}

/** Relie chaque champ `[data-password-meter]` à sa jauge. Idempotent. */
export function init(root = document) {
  root.querySelectorAll('input[data-password-meter]').forEach((input) => {
    if (input._xmMeter) return;
    const meter = root.querySelector(input.dataset.passwordMeter);
    if (!meter) return;
    input._xmMeter = meter;
    const update = () => render(meter, input.value);
    input.addEventListener('input', update);
    input.addEventListener('change', update);
    update();
  });
}

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

if (typeof window !== 'undefined') {
  window.XM = window.XM || {};
  window.XM.passwordMeter = { score, checks, render, init, MIN_LENGTH };
  ready(() => init());
}

export default init;
