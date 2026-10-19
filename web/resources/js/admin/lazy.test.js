// @vitest-environment jsdom
import { describe, it, expect, vi } from 'vitest';

vi.mock('chart.js/auto', () => {
  const Chart = function Chart() {};
  Chart.defaults = { font: {}, plugins: { legend: {} } };
  return { default: Chart };
});
vi.mock('quill', () => ({ default: function Quill() {} }));

const { load, preload, LIBRARIES } = await import('./lazy.js');

describe('bibliothèques de l’administration à la demande (P10-02, ADR-18)', () => {
  it('déclare Chart.js, Quill, Choices et flatpickr comme chargements différés', () => {
    expect(LIBRARIES).toEqual(['chart', 'quill', 'choices', 'flatpickr']);
  });

  it('charge Chart.js une seule fois avec les réglages communs (Montserrat, légende en bas)', async () => {
    const [a, b] = await Promise.all([load('chart'), load('chart')]);
    expect(a).toBe(b);
    expect(a.defaults.font.family).toBe('Montserrat, Arial, sans-serif');
    expect(a.defaults.plugins.legend.position).toBe('bottom');
    expect(window.Chart).toBe(a);
  });

  it('refuse une bibliothèque inconnue et précharge celles annoncées par data-xm-needs', async () => {
    await expect(load('leaflet')).rejects.toThrow('Bibliothèque inconnue');
    document.body.innerHTML = '<canvas data-xm-needs="chart"></canvas><div data-xm-needs="quill chart"></div>';
    expect(preload(document)).toEqual(['chart', 'quill']);
    await expect(load('quill')).resolves.toBeTypeOf('function');
  });
});
