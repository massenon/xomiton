// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { chartConfig, colors, withAlpha, mountCharts, soundEnabled, syncSoundButton, bindSound, SOUND_KEY } from './dashboard.js';

const palette = ['#1', '#2', '#3', '#4', '#5', '#6'];

describe('tableau de bord admin : graphiques et son (P10-04)', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    delete document.documentElement.dataset.xmSosSoundBound;
    window.localStorage.clear();
  });

  it('construit la couleur de remplissage quel que soit le format du jeton', () => {
    expect(withAlpha('#0433C3', 0.12)).toBe('rgba(4, 51, 195, 0.12)');
    expect(withAlpha('#03c', 0.12)).toBe('rgba(0, 51, 204, 0.12)');
    expect(withAlpha('rgb(4 51 195)', 0.12)).toBe('rgba(4, 51, 195, 0.12)');
    expect(withAlpha('rgba(4, 51, 195, 0.5)', 0.12)).toBe('rgba(4, 51, 195, 0.12)');
    expect(withAlpha('hsl(226 96% 39%)', 0.12)).toBe('color-mix(in srgb, hsl(226 96% 39%) 12%, transparent)');
    const line = chartConfig({ key: 'month', type: 'line', labels: ['a'], datasets: [{ label: 'Cas', data: [7] }] }, ['rgb(1, 2, 3)'], 'x');
    expect(line.data.datasets[0].backgroundColor).toBe('rgba(1, 2, 3, 0.12)');
  });

  it('ne trace pas une cellule masquée et le dit dans la légende d’un anneau', () => {
    const doughnut = chartConfig({ key: 'violence', type: 'doughnut', labels: ['Physique', 'Sexuelle'], datasets: [{ label: 'Cas', data: [12, null] }] }, palette, 'données masquées');
    expect(doughnut.type).toBe('doughnut');
    expect(doughnut.data.labels).toEqual(['Physique', 'Sexuelle (données masquées)']);
    expect(doughnut.data.datasets[0].data).toEqual([12, null]);

    const line = chartConfig({ key: 'month', type: 'line', labels: ['a', 'b'], datasets: [{ label: 'Cas', data: [null, 7] }] }, palette, 'x');
    expect(line.data.datasets[0].data).toEqual([null, 7]);
    expect(line.data.datasets[0].spanGaps).toBe(false);
    expect(line.options.plugins.legend.display).toBe(false);
  });

  it('empile sexe et tranche d’âge et trace les départements en barres horizontales', () => {
    const stacked = chartConfig({ key: 'sex_age', type: 'bar', labels: ['10-14'], datasets: [{ label: 'Femme', data: [5] }, { label: 'Homme', data: [null] }] }, palette, 'x');
    expect(stacked.options.scales.x.stacked).toBe(true);
    expect(stacked.options.scales.y.stacked).toBe(true);
    expect(stacked.data.datasets).toHaveLength(2);

    const bars = chartConfig({ key: 'zone', type: 'bar', labels: ['Littoral'], datasets: [{ label: 'Cas', data: [9] }] }, palette, 'x');
    expect(bars.options.indexAxis).toBe('y');
  });

  it('lit les jetons --xm-chart-* et retombe sur la palette de la charte', () => {
    document.documentElement.style.setProperty('--xm-chart-1', '#123456');
    const result = colors(document);
    expect(result[0]).toBe('#123456');
    expect(result).toHaveLength(6);
    expect(result[1]).toBe('#6F2C91');
  });

  it('charge Chart.js à la demande et monte un graphique par canevas présent', async () => {
    document.body.innerHTML = `<script type="application/json" id="xmDashboardCharts">${JSON.stringify({
      maskedLabel: 'données masquées',
      charts: [
        { key: 'zone', type: 'bar', labels: ['A'], datasets: [{ label: 'Cas', data: [null] }], masked: true },
        { key: 'absent', type: 'bar', labels: [], datasets: [{ label: 'Cas', data: [] }], masked: false },
      ],
    })}</script><canvas id="xmChart-zone"></canvas>`;
    const instances = [];
    const Chart = vi.fn(function Chart(canvas, config) { instances.push({ canvas, config }); });
    const load = vi.fn().mockResolvedValue(Chart);

    const mounted = await mountCharts(document, load);

    expect(load).toHaveBeenCalledWith('chart');
    expect(mounted).toHaveLength(1);
    expect(instances[0].canvas.id).toBe('xmChart-zone');
    expect(instances[0].config.data.datasets[0].data).toEqual([null]);
  });

  it('ne charge rien sans données ou avec des données illisibles', async () => {
    const load = vi.fn();
    expect(await mountCharts(document, load)).toEqual([]);
    document.body.innerHTML = '<script type="application/json" id="xmDashboardCharts">{pas du json</script>';
    expect(await mountCharts(document, load)).toEqual([]);
    expect(load).not.toHaveBeenCalled();
  });

  it('active le son au clic, le mémorise, et le rejoue à l’arrivée d’un SOS seulement s’il est actif', () => {
    document.body.innerHTML = '<button data-xm-sos-sound aria-pressed="false"><i data-xm-sos-sound-icon class="bi"></i><span data-xm-sos-sound-label data-on="Son activé" data-off="Son désactivé">Son désactivé</span></button>';
    const oscillators = [];
    const ctx = {
      state: 'running', currentTime: 0, destination: {}, resume: vi.fn(),
      createOscillator: () => { const o = { frequency: {}, connect: (g) => g, start: vi.fn(), stop: vi.fn() }; oscillators.push(o); return o; },
      createGain: () => ({ gain: { setValueAtTime: vi.fn(), exponentialRampToValueAtTime: vi.fn() }, connect: () => ({}) }),
    };
    window.AudioContext = function AudioContext() { return ctx; };
    bindSound(window);

    window.dispatchEvent(new CustomEvent('xm-sos-new'));
    expect(oscillators).toHaveLength(0);

    document.querySelector('[data-xm-sos-sound]').click();
    expect(soundEnabled()).toBe(true);
    expect(window.localStorage.getItem(SOUND_KEY)).toBe('on');
    expect(document.querySelector('[data-xm-sos-sound]').getAttribute('aria-pressed')).toBe('true');
    expect(document.querySelector('[data-xm-sos-sound-label]').textContent).toBe('Son activé');
    const afterClick = oscillators.length;
    expect(afterClick).toBeGreaterThan(0);

    window.dispatchEvent(new CustomEvent('xm-sos-new'));
    expect(oscillators.length).toBeGreaterThan(afterClick);

    document.querySelector('[data-xm-sos-sound]').click();
    expect(soundEnabled()).toBe(false);
    syncSoundButton(document);
    expect(document.querySelector('[data-xm-sos-sound]').getAttribute('aria-pressed')).toBe('false');
  });
});
