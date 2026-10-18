// @vitest-environment jsdom
// XmSosButton (P6-04) : interaction de la fabrique xmSos (public/sos-button.js) branchée sur Vue — maintien 2 s puis
// décompte 5 s annulable, Entrée puis confirmation, mode managed (resolve), Échap, focus piégé dans le panneau.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick } from 'vue';
import { renderXm } from '@/test/helpers';
import { useSessionStore } from '@/stores/session';
import XmSosButton, { type SosResult } from './XmSosButton.vue';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

const tick = async (ms: number) => {
    vi.advanceTimersByTime(ms);
    await nextTick();
};

describe('XmSosButton', () => {
    it('démonstration (managed false) : maintien 2 s → décompte 5 s → évènement action sans charge', async () => {
        vi.useFakeTimers();
        const sent: unknown[] = [];
        renderXm({ render: () => h(XmSosButton, { fixed: false, managed: false, onAction: (detail?: unknown) => sent.push(detail ?? 'sent') }) });
        const button = screen.getByRole('button', { name: 'SOS' });
        expect(button.getAttribute('aria-describedby')).toBe('xmSosHelp');
        await fireEvent.pointerDown(button);
        await nextTick();
        expect(button.getAttribute('data-holding')).toBe('true');
        await tick(2000);
        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('alertdialog', { hidden: true }).textContent).toContain('Envoi de ton alerte dans 5');
        expect(document.activeElement?.hasAttribute('data-sos-title')).toBe(true);
        await tick(5000);
        expect(sent).toEqual(['sent']);
        expect(screen.getByText('Ton alerte est partie.', { selector: 'h2' })).toBeTruthy();
    });

    it('Entrée → confirmation ; « Annuler l’alerte » n’envoie rien', async () => {
        vi.useFakeTimers();
        const sent: unknown[] = [];
        renderXm({ render: () => h(XmSosButton, { fixed: false, managed: false, confirm: 'Sans position.', onAction: () => sent.push(1) }) });
        const button = screen.getByRole('button', { name: 'SOS' });
        await fireEvent.keyDown(button, { key: 'Enter' });
        await nextTick();
        expect(screen.getByText('Envoyer une alerte SOS ?')).toBeTruthy();
        expect(screen.getByText('Sans position.')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler l’alerte' }));
        await tick(6000);
        expect(sent).toEqual([]);
        expect(screen.getByText('Alerte annulée.')).toBeTruthy();
    });

    it('mode managed : l’hôte envoie et rend compte par resolve()', async () => {
        vi.useFakeTimers();
        useSessionStore().$patch({ sosHoldMs: 1000, sosCountdown: 2 });
        let resolve: ((r: SosResult) => void) | null = null;
        renderXm({ render: () => h(XmSosButton, { fixed: false, managed: true, hold: 1000, countdown: 2, onAction: (detail?: { resolve: (r: SosResult) => void }) => { resolve = detail?.resolve ?? null; } }) });
        await fireEvent.pointerDown(screen.getByRole('button', { name: 'SOS' }));
        await tick(1000);
        await tick(2000);
        expect(resolve).not.toBeNull();
        expect(screen.getAllByText('Envoi de ton alerte…').length).toBeGreaterThan(0);
        (resolve as unknown as (r: SosResult) => void)({ state: 'sent', title: 'Alerte reçue', text: 'Une personne va te rappeler.', call: { href: 'tel:117', label: 'Appeler le 117' } });
        await nextTick();
        expect(screen.getByRole('link', { name: 'Appeler le 117' }).getAttribute('href')).toBe('tel:117');
        expect(screen.getByRole('button', { name: 'Fermer', hidden: true })).toBeTruthy();
    });

    it('par défaut (managed) sans hôte : échec annoncé et lien vers /sos, jamais « alerte envoyée »', async () => {
        vi.useFakeTimers();
        renderXm({ render: () => h(XmSosButton, { hold: 1000, countdown: 1 }) });
        await fireEvent.pointerDown(screen.getByRole('button', { name: 'SOS' }));
        await tick(1000);
        await tick(1000);
        expect(screen.getByText('Ton alerte n’est pas partie', { selector: 'h2' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Ouvrir la page SOS', hidden: true }).getAttribute('href')).toBe('/sos');
        expect(screen.queryByText('Ton alerte est partie.', { selector: 'h2' })).toBeNull();
    });

    it('Échap annule ; Tab reste dans le panneau', async () => {
        vi.useFakeTimers();
        renderXm({ render: () => h(XmSosButton, { fixed: false }) });
        const button = screen.getByRole('button', { name: 'SOS' });
        await fireEvent.keyDown(button, { key: 'Enter' });
        await nextTick();
        const dialog = screen.getByRole('alertdialog', { hidden: true });
        const send = screen.getByRole('button', { name: 'Envoyer' });
        send.focus();
        await fireEvent.keyDown(dialog, { key: 'Tab' });
        expect(dialog.contains(document.activeElement)).toBe(true);
        await fireEvent.keyDown(window, { key: 'Escape' });
        await nextTick();
        expect(button.getAttribute('aria-expanded')).toBe('false');
    });
});
