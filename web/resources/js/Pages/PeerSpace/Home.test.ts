// @vitest-environment jsdom
// Page /espace-pair (lot espace-pair, P9-03 ; prototype espace-pair.php) : bienvenue avec état du réseau et file, 4 KPI,
// raccourcis, tâches, alertes routées vers moi (résumé), accuser réception, détail en modale (gestes selon `can`, clôture
// confirmée), interrogation toutes les 10 s seulement si le compte est de garde (jamais onglet caché ou réseau coupé).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { makeAlert, makeAlertDetail } from '@/test/peer';
import { router } from '@inertiajs/vue3';
import { useUiStore } from '@/stores/ui';
import Home from './Home.vue';

// Les visites partielles « de garde » partent en XHR : neutralisées (jsdom n'a pas de serveur), vérifiées à part.
beforeEach(() => {
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
});

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.useRealTimers();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

const ID = '01JAAAAAAAAAAAAAAAAAAAAAA1';

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'Espace pair',
    kpi: { collected: 12, this_month: 3, in_progress: 5, closed: 4 },
    tasks: [{ key: 'alerts_to_take', count: 2 }, { key: 'cases_to_contact', count: 1 }],
    alerts: { open: 2, new: 1, items: [makeAlert(), makeAlert({ id: '01JAAAAAAAAAAAAAAAAAAAAAA2', type_label: 'Besoin', priority: 'normale', priority_label: 'Normale', status: 'pris_en_charge', status_label: 'Pris en charge', acknowledged: true, place: null })] },
    on_duty: false,
    poll_seconds: 0,
    ...overrides,
});

describe('Page /espace-pair', () => {
    it('affiche le bonjour, l’état du réseau, la file, les 4 KPI et les raccourcis', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        expect(screen.getByRole('heading', { level: 1, name: 'Bonjour Awa_S' })).toBeTruthy();
        expect(document.querySelector('[data-peer-online]')?.textContent).toBe('En ligne');
        expect(document.querySelector('[data-peer-queue]')?.textContent).toContain('Aucun élément en attente de synchronisation');
        const kpi = screen.getByRole('region', { name: 'Chiffres de tes dossiers' });
        expect(within(kpi).getByText('Dossiers collectés').closest('.card')?.textContent).toContain('12');
        expect(within(kpi).getByText('Ce mois-ci').closest('.card')?.textContent).toContain('3');
        expect(within(kpi).getByText('En cours').closest('.card')?.textContent).toContain('5');
        expect(within(kpi).getByText('Clos').closest('.card')?.textContent).toContain('4');
        const shortcuts = screen.getByRole('navigation', { name: 'Raccourcis' });
        expect([...shortcuts.querySelectorAll('a')].map((a) => [a.textContent?.trim(), a.getAttribute('href')])).toEqual([
            ['Nouvelle collecte', '/collecte'], ['Mes dossiers', '/espace-pair/dossiers'], ['Signalement', '/signaler'],
            ['Annuaire', '/annuaire'], ['Outils CCC', '/ccc'], ['Ressources', '/ressources'],
        ]);
        useUiStore().setOnline(false);
        await waitFor(() => expect(document.querySelector('[data-peer-online]')?.textContent).toBe('Hors ligne'));
    });

    it('liste les tâches du jour avec leurs liens', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        const tasks = screen.getByRole('region', { name: 'Mes tâches du jour' });
        const links = [...tasks.querySelectorAll('a')].map((a) => [a.textContent?.trim(), a.getAttribute('href')]);
        expect(links).toEqual([
            ['2 alertes nouvelles à traiter', '#alertes'],
            ['1 dossier ouvert à contacter', '/espace-pair/dossiers'],
            ['Préparer la prochaine séance\u00a0: boîtes à images', '/ccc'],
        ]);
    });

    it('montre les alertes routées en résumé (type · lieu, priorité, statut), sans récit ni contact', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        const list = document.querySelector('[data-peer-alerts]') as HTMLElement;
        const rows = list.querySelectorAll('li[data-peer-alert]');
        expect(rows).toHaveLength(2);
        expect(rows[0].textContent).toContain('SOS · Abomey');
        expect(rows[0].textContent).toContain('Critique');
        expect(rows[0].textContent).toContain('Nouveau');
        expect(rows[0].textContent).toContain('Reçue il y a 5 min');
        expect(within(rows[0] as HTMLElement).getByRole('button', { name: 'Accuser réception' })).toBeTruthy();
        expect(rows[1].textContent).toContain('Besoin · zone inconnue');
        expect(within(rows[1] as HTMLElement).queryByRole('button', { name: 'Accuser réception' })).toBeNull();
        expect(document.querySelector('[data-peer-alerts-count]')?.textContent).toContain('2');
        expect(screen.getByText(/La liste se met à jour quand tu ouvres la page/)).toBeTruthy();
        expect(screen.getByText(/1 nouvelle/)).toBeTruthy();
    });

    it('état vide : aucune alerte pour le moment', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 0, new: 0, items: [] }, tasks: [] }) });

        expect(screen.getByText('Aucune alerte pour le moment.')).toBeTruthy();
    });

    it('accuse réception d’un geste puis relit la liste', async () => {
        const { calls } = fakeFetch((call) => {
            if (call.method === 'POST') return json({ data: makeAlertDetail({ acknowledged: true }) });
            return json({ data: [makeAlert({ acknowledged: true, status: 'pris_en_charge', status_label: 'Pris en charge' })], meta: { new: 0, state: 'open' } });
        });
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        const row = document.querySelector(`[data-peer-alert="${ID}"]`) as HTMLElement;
        await fireEvent.click(within(row).getByRole('button', { name: 'Accuser réception' }));

        await waitFor(() => expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([
            `POST /api/v1/pair/alerts/${ID}/acknowledge`,
            'GET /api/v1/pair/alerts?state=open&per_page=5',
        ]));
        await waitFor(() => expect(document.querySelector(`[data-peer-alert="${ID}"]`)?.textContent).toContain('Pris en charge'));
        expect(within(document.querySelector(`[data-peer-alert="${ID}"]`) as HTMLElement).queryByRole('button', { name: 'Accuser réception' })).toBeNull();
        expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Réception accusée. La personne est prévenue que tu t’en occupes.');
    });

    it('ouvre le détail en modale : récit, contact, position ; prend en charge puis clôt après confirmation', async () => {
        const state = { detail: makeAlertDetail() };
        const { calls } = fakeFetch((call) => {
            if (call.method === 'GET' && call.url === `/api/v1/pair/alerts/${ID}`) return json({ data: state.detail });
            if (call.method === 'POST' && call.url.endsWith('/status')) {
                const status = (call.body as { status: string }).status;
                state.detail = makeAlertDetail({ status, status_label: status === 'clos' ? 'Clos' : 'En cours', acknowledged: true, can: { acknowledge: false, take: status !== 'clos', close: status !== 'clos', statuses: status === 'clos' ? [] : ['clos'] } });
                return json({ data: state.detail });
            }
            return json({ data: [], meta: { new: 0, state: 'open' } });
        });
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        const row = document.querySelector(`[data-peer-alert="${ID}"]`) as HTMLElement;
        await fireEvent.click(within(row).getByRole('button', { name: /Détail de l’alerte SOS, Abomey/ }));

        const dialog = await screen.findByRole('dialog', { name: 'Alerte qui m’est routée' }, { timeout: 3000 });
        expect(await within(dialog).findByText('Je suis enfermée chez moi.')).toBeTruthy();
        expect(dialog.textContent).toContain('+22901000000');
        expect(dialog.textContent).toContain('7.18, 1.99');
        expect(dialog.textContent).toContain('précision 20 m');
        expect(dialog.textContent).toContain('Mise en sécurité');
        expect(within(dialog).getByRole('button', { name: 'Accuser réception' })).toBeTruthy();

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Prendre en charge' }));
        await waitFor(() => expect(calls.some((call) => call.method === 'POST' && call.url === `/api/v1/pair/alerts/${ID}/status` && (call.body as { status: string }).status === 'en_cours')).toBe(true));
        // Le bouton en cours d'envoi s'appelle « Envoi… » : attendre le retour du serveur (Clore de nouveau actif).
        await waitFor(() => {
            expect(within(dialog).queryByRole('button', { name: 'Accuser réception' })).toBeNull();
            expect((within(dialog).getByRole('button', { name: 'Clore' }) as HTMLButtonElement).disabled).toBe(false);
        });
        expect(dialog.textContent).toContain('En cours');

        await fireEvent.click(within(dialog).getByRole('button', { name: 'Clore' }));
        expect(await within(dialog).findByText(/Une alerte close ne se rouvre pas/)).toBeTruthy();
        expect(calls.filter((call) => call.method === 'POST' && (call.body as { status?: string })?.status === 'clos')).toHaveLength(0);
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Oui, clore' }));
        await waitFor(() => expect(calls.filter((call) => call.method === 'POST' && (call.body as { status?: string })?.status === 'clos')).toHaveLength(1));
        await waitFor(() => expect(within(dialog).queryByRole('button', { name: 'Clore' })).toBeNull());
        expect(within(dialog).queryByRole('button', { name: 'Prendre en charge' })).toBeNull();
        await new Promise((resolve) => setTimeout(resolve, 50));
    });

    describe('interrogation des alertes', () => {
        const listBody = () => json({ data: [makeAlert({ id: '01JAAAAAAAAAAAAAAAAAAAAAA9', type_label: 'Signalement' })], meta: { new: 1, state: 'open', total: 1 } });

        it('de garde : toutes les 10 secondes, la liste se met à jour', async () => {
            vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
            const { calls } = fakeFetch(() => listBody());
            await mountInertia(Home, { url: '/espace-pair', pageProps: base({ on_duty: true, poll_seconds: 10 }) });
            expect(screen.getByText(/Tu es de garde : la liste se met à jour toutes les 10 secondes/)).toBeTruthy();

            expect(calls).toHaveLength(0);
            vi.advanceTimersByTime(10_000);
            await waitFor(() => expect(calls).toHaveLength(1));
            expect(calls[0].url).toBe('/api/v1/pair/alerts?state=open&per_page=5');
            await waitFor(() => expect(document.querySelector('[data-peer-alerts]')?.textContent).toContain('Signalement · Abomey'));
            vi.advanceTimersByTime(10_000);
            await waitFor(() => expect(calls).toHaveLength(2));
        });

        it('pas de garde : aucune interrogation, seulement « Actualiser »', async () => {
            vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
            const { calls } = fakeFetch(() => listBody());
            await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

            vi.advanceTimersByTime(60_000);
            expect(calls).toHaveLength(0);
            await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
            await waitFor(() => expect(calls).toHaveLength(1));
        });

        it('de garde mais réseau coupé ou onglet caché : l’intervalle passe sans requête', async () => {
            vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
            const { calls } = fakeFetch(() => listBody());
            await mountInertia(Home, { url: '/espace-pair', pageProps: base({ on_duty: true, poll_seconds: 10 }) });

            useUiStore().setOnline(false);
            vi.advanceTimersByTime(10_000);
            useUiStore().setOnline(true);
            const hidden = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden');
            vi.advanceTimersByTime(10_000);
            expect(calls).toHaveLength(0);
            hidden.mockReturnValue('visible');
            vi.advanceTimersByTime(10_000);
            await waitFor(() => expect(calls).toHaveLength(1));
        });

        it('un échec d’interrogation garde la liste et prévient discrètement', async () => {
            fakeFetch(() => Promise.reject(new TypeError('Failed to fetch')));
            await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

            await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
            expect(await screen.findByText(/Impossible de mettre la liste à jour pour l’instant/)).toBeTruthy();
            expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(2);
        });
    });
});

describe('alertes au-delà de la première page', () => {
    const ids = (from: number, count: number) => Array.from({ length: count }, (_, i) => `01JAAAAAAAAAAAAAAAAAAA${String(from + i).padStart(4, '0')}`);
    const page = (list: string[]) => list.map((id) => makeAlert({ id, type_label: `Alerte ${id.slice(-4)}` }));

    it('garde le total réel après « Actualiser » (12 ouvertes, 5 affichées) et propose « Voir plus »', async () => {
        const { calls } = fakeFetch(() => json({ data: page(ids(1, 5)), meta: { new: 3, state: 'open', total: 12, per_page: 5 } }));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 12, new: 3, items: page(ids(1, 5)) } }) });

        expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(5);
        expect(document.querySelector('[data-peer-alerts-count]')?.textContent).toContain('12');
        expect(screen.getByRole('button', { name: 'Voir plus (7 restantes)' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
        await waitFor(() => expect(calls.some((call) => call.url === '/api/v1/pair/alerts?state=open&per_page=5')).toBe(true));
        await waitFor(() => expect(document.querySelector('[data-peer-alerts-count]')?.textContent).toContain('12'));
        expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(5);
        expect(document.querySelector('[data-peer-alerts-shown]')?.textContent).toBe('5 alertes affichées sur 12.');
    });

    it('« Voir plus » charge la page suivante, sans doublon, jusqu’à tout afficher', async () => {
        const { calls } = fakeFetch((call) => {
            if (call.url.includes('page=2')) return json({ data: page(ids(6, 5)), meta: { new: 3, state: 'open', total: 12, per_page: 5 } });
            if (call.url.includes('page=3')) return json({ data: page(ids(11, 2)), meta: { new: 3, state: 'open', total: 12, per_page: 5 } });
            return json({ data: page(ids(1, 10)), meta: { new: 3, state: 'open', total: 12 } });
        });
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 12, new: 3, items: page(ids(1, 5)) } }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Voir plus (7 restantes)' }));
        await waitFor(() => expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(10));
        expect(calls[0].url).toBe('/api/v1/pair/alerts?state=open&per_page=5&page=2');
        expect(new Set([...document.querySelectorAll('li[data-peer-alert]')].map((li) => li.getAttribute('data-peer-alert'))).size).toBe(10);

        await fireEvent.click(await screen.findByRole('button', { name: 'Voir plus (2 restantes)' }));
        await waitFor(() => expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(12));
        expect(calls[1].url).toBe('/api/v1/pair/alerts?state=open&per_page=5&page=3');
        expect(screen.queryByRole('button', { name: /Voir plus/ })).toBeNull();
        expect(document.querySelector('[data-peer-alerts-count]')?.textContent).toContain('12');
    });

    it('actualise ce qui est déjà affiché en une requête (10 alertes chargées → per_page=10)', async () => {
        const { calls } = fakeFetch(() => json({ data: page(ids(1, 10)), meta: { new: 0, state: 'open', total: 12 } }));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 12, new: 0, items: page(ids(1, 10)) } }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0].url).toBe('/api/v1/pair/alerts?state=open&per_page=10');
        await waitFor(() => expect(document.querySelectorAll('li[data-peer-alert]')).toHaveLength(10));
    });
});

describe('annonce aux lecteurs d’écran', () => {
    it('annonce les nouvelles alertes arrivées après une actualisation, pas l’état initial', async () => {
        fakeFetch(() => json({ data: [makeAlert(), makeAlert({ id: '01JAAAAAAAAAAAAAAAAAAAAAA3' })], meta: { new: 3, state: 'open', total: 4 } }));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 2, new: 1, items: [makeAlert()] } }) });

        const region = document.querySelector('[data-peer-announce]') as HTMLElement;
        expect(region.getAttribute('role')).toBe('status');
        expect(region.getAttribute('aria-live')).toBe('polite');
        expect(region.classList.contains('visually-hidden')).toBe(true);
        expect(region.textContent).toBe('');

        await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
        await waitFor(() => expect(region.textContent).toBe('2 nouvelles alertes viennent d’arriver.'));
    });

    it('n’annonce rien quand le nombre de nouvelles alertes ne grandit pas', async () => {
        fakeFetch(() => json({ data: [makeAlert()], meta: { new: 0, state: 'open', total: 1 } }));
        await mountInertia(Home, { url: '/espace-pair', pageProps: base({ alerts: { open: 2, new: 1, items: [makeAlert()] } }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
        await waitFor(() => expect(document.querySelector('[data-peer-alerts-count]')?.textContent).toContain('1'));
        expect(document.querySelector('[data-peer-announce]')?.textContent).toBe('');
    });
});

describe('état « de garde »', () => {
    it('relit on_duty et poll_seconds par une visite partielle à « Actualiser »', async () => {
        fakeFetch(() => json({ data: [], meta: { new: 0, state: 'open', total: 0 } }));
        const reload = vi.mocked(router.reload);
        await mountInertia(Home, { url: '/espace-pair', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: 'Actualiser' }));
        expect(reload).toHaveBeenCalledWith({ only: ['on_duty', 'poll_seconds'] });
    });
});
