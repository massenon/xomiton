// Écran Indicateurs /admin/indicateurs (P10-06) : graphique à la demande. Chart.js n'est chargé qu'au premier clic sur
// « Graphique » (XM.admin.load('chart'), ADR-18). Les données viennent de l'attribut data-xm-ind-chart du bouton (valeurs
// des instantanés mensuels ; `null` = mois sans instantané ou effectif masqué) ; le tableau de données accessible sous
// le graphique est rempli en même temps, avec « données masquées » pour une valeur absente. Tous les libellés viennent du
// serveur (lang/fr, attributs de l'élément) : aucun texte en dur ici.
const PALETTE = ['--xm-chart-1', '--xm-chart-2'];

export function readPayload(button) {
  try {
    const payload = JSON.parse(button.getAttribute('data-xm-ind-chart') || '{}');
    return Array.isArray(payload.labels) && Array.isArray(payload.values) ? payload : null;
  } catch (e) {
    return null;
  }
}

export function formatValue(value, unit, locale = 'fr-FR') {
  if (value === null || value === undefined) return null;
  const text = new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(value);
  return unit === '%' ? `${text} %` : text;
}

export function fillTable(doc, payload) {
  const body = doc.querySelector('[data-xm-ind-chart-body]');
  const head = doc.querySelector('[data-xm-ind-chart-value]');
  const title = doc.querySelector('[data-xm-ind-chart-title]');
  if (!body) return;
  if (head) head.textContent = payload.valueLabel || '';
  if (title) title.textContent = `${payload.code} — ${payload.label}`;
  body.textContent = '';
  payload.labels.forEach((label, i) => {
    const row = doc.createElement('tr');
    const month = doc.createElement('th');
    month.scope = 'row';
    month.className = 'fw-normal';
    month.textContent = label;
    const cell = doc.createElement('td');
    cell.className = 'text-end';
    const text = formatValue(payload.values[i], payload.unit);
    if (text === null) {
      cell.className += ' text-secondary fst-italic';
      cell.textContent = payload.maskedLabel || '';
    } else {
      cell.textContent = text;
    }
    row.append(month, cell);
    body.append(row);
  });
}

export function chartConfig(payload, palette) {
  const datasets = [{ label: payload.valueLabel || '', data: payload.values, borderColor: palette[0], backgroundColor: `${palette[0]}1F`, fill: true, tension: 0.3, spanGaps: false }];
  if (payload.target !== null && payload.target !== undefined) {
    datasets.push({ label: payload.targetLabel || '', data: payload.labels.map(() => payload.target), borderColor: palette[1], borderDash: [6, 4], pointRadius: 0, fill: false });
  }
  return { type: 'line', data: { labels: payload.labels, datasets }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } };
}

function colors(doc) {
  const style = doc.defaultView.getComputedStyle(doc.documentElement);
  return PALETTE.map((name, i) => style.getPropertyValue(name).trim() || ['#0433C3', '#B84B00'][i]);
}

let chart = null;

/** Détruit le graphique ouvert (navigation sans rechargement : le canevas disparaît avec la page). */
export function destroyChart() {
  if (chart) {
    try { chart.destroy(); } catch (e) { /* canevas déjà retiré */ }
    chart = null;
  }
}

export async function openChart(button, doc = document, load = (name) => window.XM.admin.load(name)) {
  const payload = readPayload(button);
  const canvas = doc.getElementById('xmIndChartCanvas');
  if (!payload || !canvas) return null;
  fillTable(doc, payload);
  const modalEl = doc.getElementById('xmIndChartModal');
  if (modalEl && doc.defaultView.bootstrap && doc.defaultView.bootstrap.Modal) doc.defaultView.bootstrap.Modal.getOrCreateInstance(modalEl).show();
  const Chart = await load('chart');
  if (!canvas.isConnected) return null; // page quittée pendant le chargement de Chart.js
  destroyChart();
  chart = new Chart(canvas, chartConfig(payload, colors(doc)));
  return chart;
}

/** Monte l'écran ; renvoie le démontage (écouteur retiré, graphique détruit). */
export default function init(doc = document) {
  if (doc.documentElement.dataset.xmIndChartBound === '1') return () => {};
  doc.documentElement.dataset.xmIndChartBound = '1';
  const onClick = (event) => {
    const button = event.target.closest && event.target.closest('[data-xm-ind-chart]');
    if (button) openChart(button, doc).catch(() => {});
  };
  doc.addEventListener('click', onClick);
  return () => {
    doc.removeEventListener('click', onClick);
    delete doc.documentElement.dataset.xmIndChartBound;
    destroyChart();
  };
}
