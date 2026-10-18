// @vitest-environment jsdom
// XmBell (P6-04) : compteur annoncé, filtres Non lus / Lues / Toutes, état vide, évènements read et read-all.
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h } from 'vue';
import { renderXm } from '@/test/helpers';
import XmBell from './XmBell.vue';

afterEach(() => cleanup());

const items = [
    { id: 'n1', text: 'Nouvelle réponse', time: 'il y a 5 min', href: '#fil', read: false },
    { id: 'n2', text: 'Invitation acceptée', href: '/groupes', read: true },
];

describe('XmBell', () => {
    it('compteur des non lues avec texte caché, filtres et état vide', async () => {
        renderXm({ render: () => h(XmBell, { items }) });
        const toggle = screen.getByRole('button', { name: 'Notifications' });
        expect(toggle.getAttribute('data-bs-toggle')).toBe('dropdown');
        expect(toggle.textContent).toContain('1');
        expect(toggle.textContent).toContain('notification non lue');
        expect(screen.getByText('Nouvelle réponse')).toBeTruthy();
        expect(screen.queryByText('Invitation acceptée')).toBeNull();
        await fireEvent.click(screen.getByRole('tab', { name: 'Lues' }));
        expect(screen.getByText('Invitation acceptée')).toBeTruthy();
        expect(screen.getByRole('tab', { name: 'Lues' }).getAttribute('aria-selected')).toBe('true');
        expect(screen.getByText('Aucune notification dans cette liste.').className).toContain('d-none');
    });

    it('émet read au clic d’une non lue et read-all', async () => {
        const events: string[] = [];
        renderXm({ render: () => h(XmBell, { items, onRead: (id: string) => events.push(id), 'onRead-all': () => events.push('all'), href: '/notifications' }) });
        await fireEvent.click(screen.getByText('Nouvelle réponse'));
        await fireEvent.click(screen.getByRole('button', { name: 'Tout marquer comme lu' }));
        expect(events).toEqual(['n1', 'all']);
        expect(screen.getByRole('link', { name: 'Voir toutes les notifications' }).getAttribute('href')).toBe('/notifications');
    });

    it('sans notification : badge masqué, état vide visible', () => {
        renderXm({ render: () => h(XmBell, { items: [] }) });
        expect(screen.getByRole('button', { name: 'Notifications' }).querySelector('.badge')?.className).toContain('d-none');
        expect(screen.getByText('Aucune notification dans cette liste.').className).not.toContain('d-none');
    });
});
