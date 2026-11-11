// Tableau de bord /admin (P10-04) : graphiques Chart.js chargés à la demande (XM.admin.load('chart'), ADR-18) et son
// d'arrivée d'un SOS. Monté par admin.js pour tout élément [data-xm-screen="dashboard"] ; rien n'est chargé ailleurs.
// Les données viennent du serveur (<script type="application/json" id="xmDashboardCharts">) : les effectifs de 1 à 4
// sont déjà `null` (SmallCellMask) et le graphique ne les trace pas ; leur libellé le dit (« données masquées »).
export const SOUND_KEY = 'xm-admin-sos-sound';
const PALETTE = ['--xm-chart-1', '--xm-chart-2', '--xm-chart-3', '--xm-chart-4', '--xm-chart-5', '--xm-chart-6'];
// Repli si un jeton --xm-chart-* est absent (feuille de style non chargée) : mêmes valeurs que resources/css/xomiton.css.
const FALLBACK = ['#0433C3', '#6F2C91', '#C2255C', '#B84B00', '#0B7A4B', '#8E88A0'];

const store = {
  get() { try { return window.localStorage.getItem(SOUND_KEY); } catch (e) { return null; } },
  set(value) { try { window.localStorage.setItem(SOUND_KEY, value); } catch (e) { /* stockage indisponible */ } },
};

export function colors(doc = document) {
  const style = doc.defaultView.getComputedStyle(doc.documentElement);
  return PALETTE.map((name, i) => style.getPropertyValue(name).trim() || FALLBACK[i]);
}

/**
 * Couleur du jeton avec une opacité (0 à 1), quel que soit son format : #rgb, #rrggbb, rgb() / rgba() convertis en
 * rgba() ; tout autre format (hsl(), oklch(), nom…) passe par color-mix(), que le canvas comprend.
 */
export function withAlpha(color, alpha) {
  const value = String(color).trim();
  const hex = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(value);
  if (hex) {
    const digits = hex[1].length === 3 ? hex[1].split('').map((c) => c + c).join('') : hex[1];
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(digits.slice(i, i + 2), 16));
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }
  const rgb = /^rgba?\(\s*(\d{1,3})[\s,]+(\d{1,3})[\s,]+(\d{1,3})\s*(?:[,/]\s*[\d.]+%?\s*)?\)$/i.exec(value);
  if (rgb) return `rgba(${rgb[1]}, ${rgb[2]}, ${rgb[3]}, ${alpha})`;
  return `color-mix(in srgb, ${value} ${Math.round(alpha * 100)}%, transparent)`;
}

/** Configuration Chart.js d'un graphique serveur ({ key, type, labels, datasets }). */
export function chartConfig(chart, palette, maskedLabel) {
  const labels = chart.type === 'doughnut'
    ? chart.labels.map((label, i) => (chart.datasets[0].data[i] === null ? `${label} (${maskedLabel})` : label))
    : chart.labels;
  const integers = { beginAtZero: true, ticks: { precision: 0 } };
  const base = { responsive: true, maintainAspectRatio: false };

  if (chart.type === 'line') {
    return {
      type: 'line',
      data: { labels, datasets: chart.datasets.map((d) => ({ label: d.label, data: d.data, borderColor: palette[0], backgroundColor: withAlpha(palette[0], 0.12), fill: true, tension: 0.3, spanGaps: false })) },
      options: { ...base, scales: { y: integers }, plugins: { legend: { display: false } } },
    };
  }

  if (chart.type === 'doughnut') {
    return {
      type: 'doughnut',
      data: { labels, datasets: [{ data: chart.datasets[0].data, backgroundColor: chart.datasets[0].data.map((_, i) => palette[i % palette.length]) }] },
      options: base,
    };
  }

  const stacked = chart.datasets.length > 1;
  const sexPalette = [palette[1], palette[0], palette[3]];

  return {
    type: 'bar',
    data: { labels, datasets: chart.datasets.map((d, i) => ({ label: d.label, data: d.data, backgroundColor: stacked ? sexPalette[i % sexPalette.length] : palette[chart.key === 'population' ? 2 : 0] })) },
    options: stacked
      ? { ...base, scales: { x: { stacked: true }, y: { stacked: true, ...integers } } }
      : { ...base, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: integers } },
  };
}

export async function mountCharts(doc = document, load = (name) => window.XM.admin.load(name)) {
  const source = doc.getElementById('xmDashboardCharts');
  if (!source) return [];
  let payload;
  try { payload = JSON.parse(source.textContent || '{}'); } catch (e) { return []; }
  const charts = Array.isArray(payload.charts) ? payload.charts : [];
  if (charts.length === 0) return [];
  const Chart = await load('chart');
  const palette = colors(doc);

  return charts.map((chart) => {
    const canvas = doc.getElementById(`xmChart-${chart.key}`);
    return canvas ? new Chart(canvas, chartConfig(chart, palette, payload.maskedLabel || 'données masquées')) : null;
  }).filter(Boolean);
}

/** Deux brèves tonalités ; AudioContext créé au clic sur le bouton (les navigateurs refusent un son sans geste). */
export function beep(win = window) {
  const Ctx = win.AudioContext || win.webkitAudioContext;
  if (!Ctx) return false;
  try {
    win.__xmAudio = win.__xmAudio || new Ctx();
    const ctx = win.__xmAudio;
    if (ctx.state === 'suspended') ctx.resume();
    [0, 0.28].forEach((offset, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.frequency.value = i === 0 ? 880 : 660;
      gain.gain.setValueAtTime(0.0001, ctx.currentTime + offset);
      gain.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime + offset + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + offset + 0.22);
      osc.connect(gain).connect(ctx.destination);
      osc.start(ctx.currentTime + offset);
      osc.stop(ctx.currentTime + offset + 0.25);
    });
    return true;
  } catch (e) {
    return false;
  }
}

export function soundEnabled() { return store.get() === 'on'; }

export function syncSoundButton(doc = document) {
  const button = doc.querySelector('[data-xm-sos-sound]');
  if (!button) return;
  const on = soundEnabled();
  button.setAttribute('aria-pressed', on ? 'true' : 'false');
  const label = button.querySelector('[data-xm-sos-sound-label]');
  if (label) label.textContent = (on ? label.dataset.on : label.dataset.off) || label.textContent;
  const icon = button.querySelector('[data-xm-sos-sound-icon]');
  if (icon) icon.className = `bi ${on ? 'bi-volume-up-fill' : 'bi-volume-mute'}`;
}

/**
 * Branche le bouton de son et l'écoute des nouveaux SOS ; renvoie la fonction qui retire tout (navigation sans
 * rechargement : les écouteurs de `document` / `window` survivraient sinon à l'écran et se cumuleraient au retour).
 */
export function bindSound(win = window) {
  const doc = win.document;
  if (doc.documentElement.dataset.xmSosSoundBound === '1') return () => {};
  doc.documentElement.dataset.xmSosSoundBound = '1';
  const onClick = (event) => {
    const button = event.target.closest && event.target.closest('[data-xm-sos-sound]');
    if (!button) return;
    const next = soundEnabled() ? 'off' : 'on';
    store.set(next);
    if (next === 'on') beep(win);
    syncSoundButton(doc);
  };
  doc.addEventListener('click', onClick);
  // Livewire : un SOS nouveau est apparu depuis le dernier sondage (évènement `xm-sos-new` de Dashboard\Index::refresh()).
  const onNew = () => { if (soundEnabled()) beep(win); };
  win.addEventListener('xm-sos-new', onNew);
  // Le bandeau apparaît et disparaît avec les SOS : le bouton reprend son état après chaque réponse de Livewire.
  let offHook = null;
  let cancelled = false;
  const afterCommit = () => {
    if (cancelled || !win.Livewire || typeof win.Livewire.hook !== 'function') return;
    const off = win.Livewire.hook('commit', ({ succeed }) => succeed(() => setTimeout(() => syncSoundButton(doc), 0)));
    offHook = typeof off === 'function' ? off : null;
  };
  if (win.Livewire) afterCommit();
  else doc.addEventListener('livewire:init', afterCommit, { once: true });

  return () => {
    cancelled = true;
    doc.removeEventListener('click', onClick);
    doc.removeEventListener('livewire:init', afterCommit);
    win.removeEventListener('xm-sos-new', onNew);
    if (offHook) offHook();
    delete doc.documentElement.dataset.xmSosSoundBound;
  };
}

/** Monte l'écran ; renvoie le démontage (écouteurs retirés, graphiques détruits) appelé sur `livewire:navigating`. */
export default async function init() {
  const unbind = bindSound();
  syncSoundButton();
  let charts = [];
  let gone = false;
  const teardown = () => {
    gone = true;
    unbind();
    charts.forEach((chart) => { try { chart.destroy(); } catch (e) { /* canevas déjà retiré */ } });
    charts = [];
  };
  const mounted = await mountCharts();
  if (gone) mounted.forEach((chart) => { try { chart.destroy(); } catch (e) { /* idem */ } });
  else charts = mounted;
  return teardown;
}
