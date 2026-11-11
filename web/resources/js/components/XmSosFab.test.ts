// @vitest-environment jsdom
// XmSosFab (P6-03) : équivalent Vue de <x-sos-fab> — XmSosButton fixe managé (64 px, z-index 1060) piloté par la
// fabrique xmSosFab de public/sos.js : endpoint, département et commune du profil (lus à chaque envoi), suivi
// /sos?alerte=, premier numéro d'urgence, textes de lang/fr ; résultat rendu dans le panneau ; arrêt au démontage ;
// module sos.js chargé dès le montage (hors du paquet app.ts) et, s'il manque, échec annoncé avec l'appel et /sos.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/vue';
import { nextTick } from 'vue';
import { i18n } from '@/app/i18n';
import { freshPinia, useFrench } from '@/test/helpers';
import { memberProps } from '@/test/inertia';
import { useSessionStore } from '@/stores/session';
import type { SosResult } from './XmSosButton.vue';
import XmSosFab from './XmSosFab.vue';

const { factory, onSend, destroy } = vi.hoisted(() => {
    const onSend = vi.fn();
    const destroy = vi.fn();
    return { onSend, destroy, factory: vi.fn((_opts: Record<string, unknown>) => ({ onSend, destroy })) };
});
vi.mock('@/public/sos.js', () => ({ xmSosFab: factory }));

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    factory.mockClear();
    onSend.mockReset();
    destroy.mockClear();
});

function mountFab() {
    const pinia = freshPinia();
    useFrench();
    const session = useSessionStore(pinia);
    session.hydrate(memberProps({ ui: { sos: { hold_ms: 1000, countdown_seconds: 1 } } }));
    return { session, ...render(XmSosFab, { global: { plugins: [pinia, i18n] } }) };
}

describe('XmSosFab', () => {
    it('configure la fabrique comme x-sos-fab dès le montage : endpoint, profil, suivi, numéro, textes', async () => {
        const { session } = mountFab();
        await vi.waitFor(() => expect(factory).toHaveBeenCalledTimes(1));
        const opts = factory.mock.calls[0][0] as unknown as Record<string, unknown> & { texts: Record<string, string> };
        expect(opts.endpoint).toBe('/api/v1/sos');
        expect(opts.followUrl).toBe('/sos?alerte=__ID__');
        expect(opts.pageUrl).toBe('/sos');
        expect(opts.number).toBe('117');
        expect([opts.zoneId, opts.communeId]).toEqual([4, 12]);
        expect(opts.texts.call).toBe('Appeler le :number');
        expect(opts.texts.offlineTitle).toBe('Tu es hors ligne');
        expect(opts.texts.sentText).toBe('Ouverture du suivi de ton alerte…');
        session.hydrate({ shell: { ...session.shell!, sos: { ...session.shell!.sos, zone_id: 9, commune_id: null } } });
        expect([opts.zoneId, opts.communeId]).toEqual([9, null]);

        const wrap = document.querySelector('[data-xm-sos-fab]') as HTMLElement;
        expect(wrap.getAttribute('data-outbox')).toBe('sos');
        expect(wrap.className).toBe('no-print');
        expect(wrap.querySelector('.xm-sos-wrap.xm-sos-wrap--fixed.xm-sos-fab > button#xmSosFab.xm-sos')).not.toBeNull();
    });

    it('envoie au terme du maintien et du décompte, sans position, puis affiche le résultat', async () => {
        vi.useFakeTimers();
        let resolve: ((result: SosResult) => void) | null = null;
        onSend.mockImplementation((detail: { resolve: (result: SosResult) => void }) => { resolve = detail.resolve; });
        mountFab();
        await vi.waitFor(() => expect(factory).toHaveBeenCalledTimes(1));
        const button = screen.getByRole('button', { name: 'SOS' });
        await fireEvent.keyDown(button, { key: 'Enter' });
        await nextTick();
        expect(screen.getByText('Ton alerte partira sans ta position GPS, vers la personne de l’annuaire la plus proche.')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer', hidden: true }));
        vi.advanceTimersByTime(1000);
        await nextTick();
        expect(onSend).toHaveBeenCalledTimes(1);
        (resolve as unknown as (r: SosResult) => void)({ state: 'queued', title: 'Tu es hors ligne', text: 'Ton alerte partira dès que le réseau revient.', call: { href: 'tel:117', label: 'Appeler le 117' } });
        await nextTick();
        expect(screen.getByText('Tu es hors ligne', { selector: 'h2' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Appeler le 117', hidden: true }).getAttribute('href')).toBe('tel:117');
    });

    it('arrête le client (nouveaux essais) au démontage', async () => {
        const { unmount } = mountFab();
        await vi.waitFor(() => expect(factory).toHaveBeenCalledTimes(1));
        unmount();
        expect(destroy).toHaveBeenCalledTimes(1);
    });

    it('module SOS indisponible : jamais « envoyée », appel du numéro et page /sos, nouvel essai à l’appui suivant', async () => {
        factory.mockImplementationOnce(() => { throw new Error('chunk'); });
        mountFab();
        await vi.waitFor(() => expect(factory).toHaveBeenCalledTimes(1));
        vi.useFakeTimers();
        const button = screen.getByRole('button', { name: 'SOS' });
        await fireEvent.keyDown(button, { key: 'Enter' });
        await nextTick();
        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer', hidden: true }));
        await vi.advanceTimersByTimeAsync(1000);
        await nextTick();
        expect(onSend).toHaveBeenCalledTimes(1);
        expect(factory).toHaveBeenCalledTimes(2);
    });

    it('module SOS toujours indisponible : échec annoncé avec l’appel du premier numéro et la page /sos', async () => {
        factory.mockImplementation(() => { throw new Error('chunk'); });
        mountFab();
        vi.useFakeTimers();
        const button = screen.getByRole('button', { name: 'SOS' });
        await fireEvent.keyDown(button, { key: 'Enter' });
        await nextTick();
        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer', hidden: true }));
        await vi.advanceTimersByTimeAsync(1000);
        await nextTick();
        expect(onSend).not.toHaveBeenCalled();
        expect(screen.getByText('Ton alerte n’est pas partie', { selector: 'h2' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Appeler le 117', hidden: true }).getAttribute('href')).toBe('tel:117');
        expect(screen.getByRole('link', { name: 'Ouvrir la page SOS', hidden: true }).getAttribute('href')).toBe('/sos');
        expect(screen.queryByText('Ton alerte est partie.', { selector: 'h2' })).toBeNull();
        factory.mockReset();
        factory.mockImplementation(() => ({ onSend, destroy }));
    });
});
