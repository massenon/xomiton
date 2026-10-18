// @vitest-environment jsdom
// XmChart (P6-04) : Chart.js chargé à la demande, tableau de données accessible, petits effectifs masqués.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, screen, waitFor } from '@testing-library/vue';
import { h } from 'vue';
import { renderXm } from '@/test/helpers';

const created: Array<{ type: string; data: { datasets: Array<{ data: Array<number | null> }> } }> = [];
vi.mock('chart.js/auto', () => ({
    default: class {
        constructor(_canvas: unknown, config: { type: string; data: { datasets: Array<{ data: Array<number | null> }> } }) { created.push(config); }
        destroy() {}
        update() {}
    },
}));

afterEach(() => {
    cleanup();
    created.length = 0;
});

describe('XmChart', async () => {
    const XmChart = (await import('./XmChart.vue')).default;

    it('canevas nommé, tableau accessible, effectifs < 5 masqués et retirés du tracé', async () => {
        renderXm({ render: () => h(XmChart, { label: 'Signalements par mois', labels: ['Janv.', 'Févr.'], datasets: [{ label: 'Signalements', data: [12, 3] }] }) });
        expect(screen.getByRole('img', { name: 'Signalements par mois' }).tagName).toBe('CANVAS');
        const table = screen.getByRole('table');
        expect(table.className).toBe('visually-hidden');
        expect(table.querySelector('caption')?.textContent).toContain('Données du graphique');
        expect(screen.getByRole('cell', { name: '12' })).toBeTruthy();
        expect(screen.getByRole('cell', { name: 'données masquées' })).toBeTruthy();
        await waitFor(() => expect(created).toHaveLength(1));
        expect(created[0].type).toBe('bar');
        expect(created[0].data.datasets[0].data).toEqual([12, null]);
    });

    it('sans masquage quand maskBelow vaut null', async () => {
        renderXm({ render: () => h(XmChart, { type: 'line', label: 'Visites', labels: ['S1'], datasets: [{ label: 'Visites', data: [2] }], maskBelow: null }) });
        expect(screen.getByRole('cell', { name: '2' })).toBeTruthy();
        await waitFor(() => expect(created).toHaveLength(1));
        expect(created[0].data.datasets[0].data).toEqual([2]);
    });
});
