// @vitest-environment jsdom
// Écran plein XmMaintenance de l'espace membre (P13-08) : même contenu que la page publique (accès SOS · Signaler ·
// Urgences en tête, message, fin prévue en clair, compte à rebours, numéros d'urgence de GET /api/v1/status), au tu ;
// heures dans le fuseau du serveur (Bénin), dépassement de la reprise annoncée, annonces aria-live aux seuils,
// vérification de la réouverture puis rechargement.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { renderXm } from '@/test/helpers';
import XmMaintenance from './XmMaintenance.vue';

const NBSP = ' ';
const status = (active: boolean, extra: Record<string, unknown> = {}) => new Response(JSON.stringify({
    data: {
        status: active ? 'maintenance' : 'ok',
        maintenance: { active, message: active ? 'Mise à jour de sécurité.' : null, starts_at: null, ends_at: active ? '2026-11-02T15:30:00+00:00' : null, retry_after: active ? 600 : null, overdue: false },
        announcement: null,
        emergency_numbers: [{ label: 'Police secours', number: '117' }, { label: 'Ligne d’écoute', number: '+229 01 97 38 88 47' }],
        timezone: 'Africa/Porto-Novo',
        server_time: '2026-11-02T14:00:00+00:00',
        ...extra,
    },
}), { status: 200, headers: { 'Content-Type': 'application/json' } });
const failing = () => vi.fn(async () => new Response('erreur', { status: 500 }));
const flush = async () => { for (let i = 0; i < 8; i += 1) await Promise.resolve(); await nextTick(); };

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
});

describe('XmMaintenance', () => {
    it('affiche les accès prioritaires, le message de la réponse 503, la fin prévue à l’heure du Bénin et les numéros d’urgence', async () => {
        const fetcher = vi.fn(async () => status(true));
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance', maintenance_message: 'Nous installons une mise à jour.', ends_at: '2026-11-02T15:30:00+00:00', timezone: 'Africa/Porto-Novo' }, fetcher, reload: vi.fn() } });
        await flush();

        const dialog = container.querySelector('[data-xm-maintenance-screen]') as HTMLElement;
        expect(dialog.getAttribute('role')).toBe('dialog');
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        const links = [...container.querySelectorAll('.xmm-help__actions a')].map((a) => [a.textContent?.trim(), a.getAttribute('href')]);
        expect(links).toEqual([['SOS', '/sos'], ['Signaler', '/signaler'], ['Urgences', '/urgences']]);
        expect(container.querySelector('h1')?.textContent).toBe('Une pause pour mieux t’accueillir.');
        expect(container.querySelector('.xmm-eyebrow')?.textContent).toBe('Maintenance en cours');
        expect(container.querySelector('.xmm-chip')).toBeNull();
        expect(container.querySelector('.xmm-lead')?.textContent).toBe('Mise à jour de sécurité.');
        // 15 h 30 UTC = 16 h 30 à Cotonou, quel que soit le fuseau de l'appareil : mêmes heures que la page publique.
        expect(container.querySelector('[data-xmm-ends]')?.textContent).toBe(`16${NBSP}h${NBSP}30`);
        expect(container.querySelector('.xmm-status__sub')?.textContent).toBe('lundi 2 novembre 2026');
        expect(container.querySelector('.xmm-status__text')?.textContent).toBe(`Reprise prévue le lundi 2 novembre 2026 à 16${NBSP}h${NBSP}30.`);
        const countdown = container.querySelector('[data-xmm-countdown]');
        expect(countdown?.getAttribute('aria-hidden')).toBe('true');
        expect(container.querySelector('[data-xmm-remaining]')?.textContent).toMatch(/^\d{2,}:\d{2}:\d{2}$/);
        expect([...container.querySelectorAll('.xmm-number')].map((a) => a.getAttribute('href'))).toEqual(['tel:117', 'tel:+2290197388847']);
        expect(container.querySelector('.xmm-note')?.textContent).toContain('ta session a été fermée');
        expect(container.querySelector('.xmm-art')).not.toBeNull();
        expect(container.querySelector('.xmm-logo svg[aria-label="Xomiton"]')).not.toBeNull();
        expect(container.querySelector('img')).toBeNull();
        expect(container.querySelector('.xmm-badge--alert')).toBeNull();
        expect(fetcher).toHaveBeenCalledWith('/api/v1/status', expect.objectContaining({ cache: 'no-store', credentials: 'omit' }));
    });

    it('nomme chaque numéro « Appeler … » sans aria-label : le nom contient le texte visible (WCAG 2.5.3)', async () => {
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance' }, fetcher: vi.fn(async () => status(true)), reload: vi.fn() } });
        await flush();

        const link = container.querySelector('.xmm-number') as HTMLAnchorElement;
        expect(link.hasAttribute('aria-label')).toBe(false);
        expect(link.querySelector('.xmm-vh')?.textContent).toBe('Appeler ');
        expect(link.textContent?.replace(/\s+/g, ' ').trim()).toBe('Appeler Police secours 117');
    });

    it('recharge la page quand GET status annonce la réouverture', async () => {
        vi.useFakeTimers();
        const reload = vi.fn();
        const fetcher = vi.fn(async () => status(false));
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance' }, fetcher, reload } });
        await flush();
        expect(container.querySelector('[data-xmm-announce]')?.textContent).toContain('de nouveau disponible');
        vi.advanceTimersByTime(1600);
        expect(reload).toHaveBeenCalledTimes(1);
    });

    it('reprise annoncée dépassée : « Bientôt », phrase dédiée, sans compte à rebours, annoncée une fois', async () => {
        vi.useFakeTimers({ now: new Date('2026-11-02T15:20:00+00:00') });
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance', ends_at: '2026-11-02T15:30:00+00:00', timezone: 'Africa/Porto-Novo' }, fetcher: failing(), reload: vi.fn() } });
        await flush();
        expect(container.querySelector('[data-xmm-countdown]')).not.toBeNull();

        vi.advanceTimersByTime(11 * 60 * 1000);
        await flush();

        expect(container.querySelector('.xmm')?.classList.contains('xmm-overdue')).toBe(true);
        expect(container.querySelector('[data-xmm-ends]')?.textContent).toBe('Bientôt');
        expect(container.querySelector('.xmm-status__sub')).toBeNull();
        expect(container.querySelector('[data-xmm-countdown]')).toBeNull();
        expect(container.querySelector('.xmm-status__text')?.textContent).toBe('La reprise prend un peu plus de temps que prévu. Merci de ta patience.');
        expect(container.querySelector('[data-xmm-announce]')?.textContent).toBe('La reprise prend un peu plus de temps que prévu. Merci de ta patience.');
    });

    it('dépassement signalé par le serveur sans heure de reprise (`overdue`)', async () => {
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance', ends_at: null, overdue: true }, fetcher: failing(), reload: vi.fn() } });
        await flush();
        expect(container.querySelector('.xmm-status__text')?.textContent).toContain('un peu plus de temps que prévu');
        expect(container.querySelector('[data-xmm-ends]')?.textContent).toBe('Bientôt');
    });

    it('n’annonce le temps restant qu’aux seuils 60, 30, 15 et 5 minutes', async () => {
        vi.useFakeTimers({ now: new Date('2026-11-02T14:00:00+00:00') });
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance', ends_at: '2026-11-02T15:12:00+00:00' }, fetcher: failing(), reload: vi.fn() } });
        await flush();
        const live = () => container.querySelector('[data-xmm-announce]')?.textContent ?? '';
        const said: string[] = [];
        for (let minute = 0; minute < 70; minute += 1) {
            vi.advanceTimersByTime(60 * 1000);
            await flush();
            if (live() && live() !== said[said.length - 1]) said.push(live());
        }
        expect(said).toEqual([
            'Reprise prévue dans 60 minutes.',
            'Reprise prévue dans 30 minutes.',
            'Reprise prévue dans 15 minutes.',
            'Reprise prévue dans 5 minutes.',
        ]);
    });

    it('sans heure de reprise : « Bientôt », phrase sans heure et pas de compte à rebours', async () => {
        const { container } = renderXm(XmMaintenance, { props: { payload: { code: 'maintenance', ends_at: null }, fetcher: failing(), reload: vi.fn() } });
        await flush();
        expect(container.querySelector('.xmm-status__big')?.textContent).toBe('Bientôt');
        expect(container.querySelector('[data-xmm-countdown]')).toBeNull();
        expect(container.querySelector('.xmm-status__text')?.textContent).toContain('Pas encore d’heure de reprise');
        expect(container.querySelector('.xmm-lead')?.textContent).toBe('Xomiton est en maintenance. Nous revenons dès que possible.');
    });
});
