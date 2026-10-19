// @vitest-environment jsdom
// Mode maintenance dans l'espace membre (P13-08) : reconnaissance d'un 503 `maintenance` (en-tête X-Xm-Maintenance ou
// code), écran XmMaintenance chargé une seule fois à la demande (rechargement si le morceau manque), visite Inertia
// refusée interceptée au lieu de la modale d'erreur, appel d'API → évènement `xm:maintenance` sans toast, bandeau
// d'annonce du gabarit membre refermable pour la session.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { fireEvent } from '@testing-library/vue';
import { freshPinia, useFrench } from '@/test/helpers';
import { memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import MemberLayout from '@/layouts/MemberLayout.vue';
import { bindMaintenance, createMaintenanceScreen, isMaintenanceResponse, MAINTENANCE_EVENT, type MaintenancePayload } from './shellBoot';
import { ApiError, createApi } from './useApi';

const json = (status: number, body: unknown, headers: Record<string, string> = {}) =>
    new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });
const flush = async () => { for (let i = 0; i < 5; i += 1) await Promise.resolve(); await nextTick(); };

beforeEach(() => {
    freshPinia();
    useFrench();
});

afterEach(() => {
    unmountInertia();
    window.sessionStorage.clear();
    document.body.innerHTML = '';
});

describe('isMaintenanceResponse', () => {
    it('reconnaît un 503 par l’en-tête X-Xm-Maintenance ou le code, jamais un autre statut ni un 503 quelconque', () => {
        expect(isMaintenanceResponse(503, { 'x-xm-maintenance': '1' }, '<html>')).toBe(true);
        expect(isMaintenanceResponse(503, new Headers({ 'X-Xm-Maintenance': '1' }), null)).toBe(true);
        expect(isMaintenanceResponse(503, {}, { code: 'maintenance' })).toBe(true);
        expect(isMaintenanceResponse(503, {}, { code: 'server_error' })).toBe(false);
        expect(isMaintenanceResponse(500, { 'x-xm-maintenance': '1' }, { code: 'maintenance' })).toBe(false);
    });
});

describe('createMaintenanceScreen', () => {
    it('charge et monte l’écran une seule fois, avec la charge utile du premier 503', async () => {
        const load = vi.fn(async () => 'XmMaintenance');
        const mount = vi.fn();
        const show = createMaintenanceScreen(load, mount, vi.fn());
        await Promise.all([show({ ends_at: '2026-11-02T16:30:00+01:00' }), show({ ends_at: 'autre' })]);
        await show({});
        expect(load).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledTimes(1);
        expect(mount).toHaveBeenCalledWith('XmMaintenance', { ends_at: '2026-11-02T16:30:00+01:00' });
    });

    it('recharge la page si le morceau ne se charge pas (le serveur sert alors la page de maintenance)', async () => {
        const reload = vi.fn();
        const show = createMaintenanceScreen(async () => { throw new Error('chunk'); }, vi.fn(), reload);
        await expect(show({})).resolves.toBe(false);
        expect(reload).toHaveBeenCalledTimes(1);
    });
});

describe('bindMaintenance', () => {
    function fakeRouter() {
        let handler: ((event: never) => void) | null = null;
        const off = vi.fn();
        return { on: vi.fn((_type: 'invalid', cb: (event: never) => void) => { handler = cb; return off; }), fire: (event: unknown) => handler?.(event as never), off };
    }
    const invalid = (status: number, headers: Record<string, string>, data: unknown) => {
        const event = { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, detail: { response: { status, headers, data } } };
        return event;
    };

    it('intercepte la visite Inertia refusée par la maintenance, laisse passer les autres erreurs', () => {
        const router = fakeRouter();
        const show = vi.fn();
        bindMaintenance(window, router, show);

        const other = invalid(500, {}, '<html>');
        router.fire(other);
        expect(other.defaultPrevented).toBe(false);
        expect(show).not.toHaveBeenCalled();

        const maintenance = invalid(503, { 'x-xm-maintenance': '1' }, { code: 'maintenance', maintenance_message: 'Mise à jour.' });
        router.fire(maintenance);
        expect(maintenance.defaultPrevented).toBe(true);
        expect(show).toHaveBeenCalledWith({ code: 'maintenance', maintenance_message: 'Mise à jour.' });
    });

    it('ouvre l’écran sur l’évènement xm:maintenance et se désabonne', () => {
        const router = fakeRouter();
        const show = vi.fn();
        const off = bindMaintenance(window, router, show);
        window.dispatchEvent(new CustomEvent<MaintenancePayload>(MAINTENANCE_EVENT, { detail: { ends_at: null } }));
        expect(show).toHaveBeenCalledWith({ ends_at: null });
        off();
        window.dispatchEvent(new CustomEvent(MAINTENANCE_EVENT, { detail: {} }));
        expect(show).toHaveBeenCalledTimes(1);
        expect(router.off).toHaveBeenCalled();
    });
});

describe('useApi et la maintenance', () => {
    it('503 maintenance → onMaintenance sans toast, ApiError `maintenance` ; un autre 503 garde le toast', async () => {
        const toast = vi.fn();
        const onMaintenance = vi.fn();
        const body = { message: 'Plateforme en maintenance. Réessayer dans quelques minutes.', code: 'maintenance', retry_after: 600, ends_at: null, maintenance_message: 'Mise à jour.' };
        const fetch = vi.fn()
            .mockResolvedValueOnce(json(503, body, { 'Retry-After': '600', 'X-Xm-Maintenance': '1' }))
            .mockResolvedValueOnce(json(503, { message: 'Indisponible.' }));
        const api = createApi({ fetch: fetch as unknown as typeof globalThis.fetch, cookies: () => '', toast, onMaintenance });

        const error = await api.get('/api/v1/feed').catch((e: unknown) => e);
        expect(error).toBeInstanceOf(ApiError);
        expect((error as ApiError).code).toBe('maintenance');
        expect((error as ApiError).retryAfter).toBe(600);
        expect(onMaintenance).toHaveBeenCalledWith(body);
        expect(toast).not.toHaveBeenCalled();

        await api.get('/api/v1/feed').catch(() => undefined);
        expect(toast).toHaveBeenCalledWith('Indisponible.', 'danger');
        expect(onMaintenance).toHaveBeenCalledTimes(1);
    });

    it('par défaut, émet xm:maintenance sur window', async () => {
        const listener = vi.fn();
        window.addEventListener(MAINTENANCE_EVENT, listener);
        const fetch = vi.fn().mockResolvedValue(json(503, { code: 'maintenance', ends_at: '2026-11-02T16:30:00+01:00' }));
        await createApi({ fetch: fetch as unknown as typeof globalThis.fetch, cookies: () => '', toast: vi.fn() }).get('/api/v1/me').catch(() => undefined);
        window.removeEventListener(MAINTENANCE_EVENT, listener);
        expect(listener).toHaveBeenCalledTimes(1);
        expect((listener.mock.calls[0][0] as CustomEvent).detail).toEqual({ code: 'maintenance', ends_at: '2026-11-02T16:30:00+01:00' });
    });
});

describe('MemberLayout : annonce préalable', () => {
    const notice = { id: 3, key: 'maint-3-1793642400', title: 'Maintenance prévue', text: 'Une maintenance est prévue demain de 22 h à 23 h.', message: 'Mise à jour de la messagerie.', starts_at: '2026-11-02T22:00:00+01:00', ends_at: '2026-11-02T23:00:00+01:00' };
    const page = defineComponent({ layout: MemberLayout, render: () => h('div', { class: 'col-lg-9', 'data-page': '' }, 'Contenu') });

    it('affiche le bandeau refermable puis le garde fermé pour la session', async () => {
        await mountInertia(page, { props: memberProps({ maintenance_notice: notice }) });
        const banner = document.querySelector('[data-xm-maintenance-notice]') as HTMLElement;
        expect(banner?.textContent).toContain('Maintenance prévue');
        expect(banner?.textContent).toContain('Une maintenance est prévue demain de 22 h à 23 h.');
        expect(banner?.textContent).toContain('Mise à jour de la messagerie.');
        await fireEvent.click(banner.querySelector('.btn-close') as HTMLElement);
        await flush();
        expect(document.querySelector('[data-xm-maintenance-notice]')).toBeNull();
        expect(window.sessionStorage.getItem('xm-dismissed:maint-3-1793642400')).toBe('1');

        unmountInertia();
        await mountInertia(page, { props: memberProps({ maintenance_notice: notice }) });
        expect(document.querySelector('[data-xm-maintenance-notice]')).toBeNull();
    });

    it('n’affiche rien sans annonce', async () => {
        await mountInertia(page, { props: memberProps({ maintenance_notice: null }) });
        expect(document.querySelector('[data-xm-maintenance-notice]')).toBeNull();
    });
});
