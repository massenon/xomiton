// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { chartConfig, fillTable, formatValue, openChart, readPayload } from './indicators.js';

const payload = { code: 'IND-01', label: 'Cas enregistrés', unit: 'nb', target: 80, labels: ['07/2026', '08/2026', '09/2026'], values: [12, null, 30], maskedLabel: 'données masquées', valueLabel: 'Valeur', targetLabel: 'Cible' };

describe('écran Indicateurs : graphique à la demande (P10-06)', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <button data-xm-ind-chart='${JSON.stringify(payload)}'>Graphique</button>
      <p data-xm-ind-chart-title></p><canvas id="xmIndChartCanvas"></canvas>
      <table><thead><tr><th></th><th data-xm-ind-chart-value></th></tr></thead><tbody data-xm-ind-chart-body></tbody></table>`;
  });

  it('lit la charge utile du bouton et refuse un JSON invalide', () => {
    expect(readPayload(document.querySelector('button')).code).toBe('IND-01');
    const broken = document.createElement('button');
    broken.setAttribute('data-xm-ind-chart', '{pas du json');
    expect(readPayload(broken)).toBeNull();
  });

  it('formate un nombre et un pourcentage à la française', () => {
    expect(formatValue(null, 'nb')).toBeNull();
    expect(formatValue(33.5, '%')).toContain('%');
    expect(formatValue(1200, 'nb')).toMatch(/1\s?200/);
  });

  it('remplit le tableau de données accessible, « données masquées » pour un mois sans valeur', () => {
    fillTable(document, payload);
    const rows = document.querySelectorAll('[data-xm-ind-chart-body] tr');
    expect(rows).toHaveLength(3);
    expect(rows[1].textContent).toContain('données masquées');
    expect(rows[2].textContent).toContain('30');
    expect(document.querySelector('[data-xm-ind-chart-title]').textContent).toBe('IND-01 — Cas enregistrés');
  });

  it('ajoute la cible comme seconde courbe et ne trace pas une valeur masquée', () => {
    const config = chartConfig(payload, ['#1', '#2']);
    expect(config.data.datasets).toHaveLength(2);
    expect(config.data.datasets[0].data).toEqual([12, null, 30]);
    expect(config.data.datasets[0].spanGaps).toBe(false);
    expect(config.data.datasets[1].data).toEqual([80, 80, 80]);
  });

  it('charge Chart.js à la demande, au clic seulement', async () => {
    const instance = { destroy: vi.fn() };
    const Chart = vi.fn(() => instance);
    const load = vi.fn(() => Promise.resolve(Chart));
    expect(load).not.toHaveBeenCalled();
    await openChart(document.querySelector('button'), document, load);
    expect(load).toHaveBeenCalledWith('chart');
    expect(Chart).toHaveBeenCalledTimes(1);
    await openChart(document.querySelector('button'), document, load);
    expect(instance.destroy).toHaveBeenCalledTimes(1);
  });
});
