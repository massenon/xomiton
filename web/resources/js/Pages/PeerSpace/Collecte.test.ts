// @vitest-environment jsdom
// Page /collecte (lot espace-pair, P9-04 ; prototype collecte.php) : fiche remplie avec les listes et les règles de la
// page (sans réseau), cascade département → commune, validation par les règles actives (résumé d'erreurs focalisé),
// envoi par la boîte d'envoi commune sur un vrai IndexedDB factice : hors ligne la fiche est mise en file UNE fois (double
// clic compris, file visible), au retour du réseau elle part une seule fois avec sa clé d'idempotence = client_uuid et le
// code attribué par le serveur est annoncé ; brouillon sans les notes ; refus du serveur affiché.
import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { openDB } from 'idb';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { activeRules, collecteForm, collecteOptions, makeCase, TODAY } from '@/test/peer';
import * as core from '@/shared/outbox.js';
import { DRAFT_STORE, setDraftOpener, useDraftsStore } from '@/stores/drafts';
import { claim, recordFailures, resetRejections } from '@/peer/rejections';
import { useOutboxStore } from '@/stores/outbox';
import { useUiStore } from '@/stores/ui';
import Collecte from './Collecte.vue';

let online = true;
let box: ReturnType<typeof core.createOutbox>;

beforeEach(() => {
    globalThis.indexedDB = new IDBFactory();
    const name = `xomiton-drafts-${Math.random().toString(36).slice(2)}`;
    setDraftOpener(() => openDB(name, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } }));
    online = true;
    resetRejections();
    // Relecture des derniers dossiers (visite partielle XHR) : neutralisée, jsdom n'a pas de serveur.
    vi.spyOn(router, 'reload').mockImplementation(() => undefined);
});

afterEach(() => {
    box?.stop();
    setDraftOpener(null);
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    vi.useRealTimers();
});

const props = (overrides: Record<string, unknown> = {}) => ({
    title: 'Collecte de données',
    form: collecteForm(),
    options: collecteOptions(),
    rules: activeRules(),
    today: TODAY,
    recent: [makeCase()],
    ...overrides,
});

/** Page montée avec la VRAIE boîte d'envoi (createOutbox) sur le faux IndexedDB, réseau pilotable. */
async function mount(pageProps: Record<string, unknown> = {}) {
    const mounted = await mountInertia(Collecte, { url: '/collecte', pageProps: props(pageProps) });
    const store = useOutboxStore(mounted.pinia);
    await store.start({
        account: 'compte',
        ttlDays: 7,
        storage: null,
        target: window,
        importer: async () => {
            box = core.createOutbox({ account: () => 'compte', online: () => online, cookies: () => 'XSRF-TOKEN=abc', locks: null, storage: null, target: window });
            return [{ ...core, getOutbox: () => box }, { mountOutboxNotices: () => undefined }] as never;
        },
    });
    return { ...mounted, store };
}

async function fill(overrides: Record<string, string> = {}) {
    const values: Record<string, string> = { 'Type de dossier': 'VBG', 'Type de violence': '3', 'Département': '4', 'Commune': '41', 'Population': '1', 'Sexe': 'femme', 'Tranche d’âge': '18-24', ...overrides };
    for (const [label, value] of Object.entries(values)) {
        await fireEvent.update(screen.getByLabelText(new RegExp(`^${label}`)), value);
    }
}

describe('Page /collecte', () => {
    it('affiche l’avertissement, le formulaire complet, la file visible et les derniers dossiers', async () => {
        fakeFetch(() => json({}));
        await mount();

        expect(screen.getByRole('heading', { level: 1, name: 'Collecte de données' })).toBeTruthy();
        expect(screen.getByText(/Jamais de nom ni de coordonnées/)).toBeTruthy();
        for (const label of ['Type de dossier', 'Date des faits', 'Type de violence', 'Département', 'Commune', 'Population', 'Sexe', 'Tranche d’âge', 'Orientation vers un service', 'Notes']) {
            expect(screen.getByLabelText(new RegExp(`^${label}`))).toBeTruthy();
        }
        expect((screen.getByLabelText(/^Date des faits/) as HTMLInputElement).max).toBe(TODAY);
        expect((screen.getByLabelText(/^Date des faits/) as HTMLInputElement).value).toBe(TODAY);
        expect(document.querySelector('[data-peer-queue]')?.textContent).toContain('Aucune fiche en attente');
        expect(screen.getByRole('link', { name: 'VBG-0042' }).getAttribute('href')).toBe('/espace-pair/dossiers/VBG-0042');
        const ages = [...(screen.getByLabelText(/^Tranche d’âge/) as HTMLSelectElement).options].map((o) => o.value);
        expect(ages).toEqual(['', '15-17', '18-24']);
    });

    it('fait suivre la commune du département choisi (cascade RG-08)', async () => {
        fakeFetch(() => json({}));
        await mount();

        const commune = screen.getByLabelText(/^Commune/) as HTMLSelectElement;
        expect([...commune.options].map((o) => o.text)).toEqual(['—']);
        await fireEvent.update(screen.getByLabelText(/^Département/), '4');
        expect([...commune.options].map((o) => o.text)).toEqual(['—', 'Abomey', 'Bohicon']);
        await fireEvent.update(commune, '42');
        await fireEvent.update(screen.getByLabelText(/^Département/), '8');
        expect(commune.value).toBe('');
        expect([...commune.options].map((o) => o.text)).toEqual(['—', 'Cotonou']);
    });

    it('refuse une fiche invalide sans rien mettre en file : résumé d’erreurs focalisé', async () => {
        fakeFetch(() => json({}));
        const { store } = await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));
        await waitFor(() => expect(document.activeElement).toBe(document.querySelector('.xm-error-summary')));
        expect(within(document.querySelector('.xm-error-summary') as HTMLElement).getAllByRole('link')).toHaveLength(6);
        expect(await store.entries('collecte')).toEqual([]);

        // Date future et notes trop courtes : règles actives du catalogue.
        await fill();
        await fireEvent.update(screen.getByLabelText(/^Date des faits/), '2026-10-25');
        await fireEvent.update(screen.getByLabelText(/^Notes/), 'trop court');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));
        expect(await screen.findByText('La date ne peut pas être postérieure à aujourd’hui.', { selector: '.invalid-feedback' })).toBeTruthy();
        expect(screen.getByText('Les notes contiennent au moins 20 caractères, ou restent vides.', { selector: '.invalid-feedback' })).toBeTruthy();
        expect(await store.entries('collecte')).toEqual([]);
    });

    it('hors ligne : met la fiche en file UNE fois (double clic compris), file visible, formulaire vidé', async () => {
        online = false;
        fakeFetch(() => json({}));
        const { store } = await mount();
        useUiStore().setOnline(false);

        await fill();
        await fireEvent.update(screen.getByLabelText(/^Notes/), 'Accueil réalisé, orientation vers une juriste.');
        const submit = screen.getByRole('button', { name: 'Enregistrer le dossier' });
        await Promise.all([fireEvent.click(submit), fireEvent.click(submit)]);

        await waitFor(async () => expect(await store.entries('collecte')).toHaveLength(1));
        const [entry] = await store.entries('collecte');
        const body = JSON.parse(String(entry.body));
        expect(entry.url).toBe('/api/v1/pair/cases');
        expect(entry.account).toBe('compte');
        expect(body).toMatchObject({ kind: 'VBG', zone_id: 4, commune_id: 41, population_id: 1, sex: 'femme', age_range: '18-24', violence_type_id: 3, notes: 'Accueil réalisé, orientation vers une juriste.' });
        expect(body.client_uuid).toBe(entry.idempotencyKey);
        await waitFor(() => expect(document.querySelector('[data-peer-queue]')?.textContent).toContain('1 fiche en attente'));
        expect(screen.getByText(/partira dès que le réseau revient, une seule fois/)).toBeTruthy();
        expect((screen.getByLabelText(/^Type de dossier/) as HTMLSelectElement).value).toBe('');
        expect((screen.getByLabelText(/^Notes/) as HTMLTextAreaElement).value).toBe('');
        expect(document.querySelector('[data-peer-online]')?.textContent).toBe('Hors ligne');
    });

    it('au retour du réseau : un seul envoi avec Idempotency-Key = client_uuid, code annoncé, rien au second passage', async () => {
        online = false;
        const { calls } = fakeFetch((call) => (call.url === '/sanctum/csrf-cookie'
            ? json({}, 204)
            : json({ data: makeCase({ code: 'VBG-0099' }), meta: { warnings: [{ code: 'duplicate_suspected', message: 'Un dossier ressemble beaucoup à celui-ci (VBG-0007).' }], duplicate: false } }, 201)));
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined as never);
        const { store } = await mount();
        await fill();
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));
        await waitFor(async () => expect(await store.entries('collecte')).toHaveLength(1));
        const [entry] = await store.entries('collecte');
        expect(calls).toHaveLength(0);

        online = true;
        await store.flush(true);
        await store.flush(true);

        const posts = calls.filter((call) => call.method === 'POST');
        expect(posts).toHaveLength(1);
        expect(posts[0].url).toBe('/api/v1/pair/cases');
        expect(posts[0].headers['Idempotency-Key']).toBe(entry.idempotencyKey);
        expect(posts[0].headers['X-Xm-Outbox-Account']).toBe('compte');
        expect((posts[0].body as { client_uuid: string }).client_uuid).toBe(entry.idempotencyKey);
        expect(await store.entries('collecte')).toEqual([]);
        expect(await screen.findByText('Dossier VBG-0099 enregistré.', { selector: '[data-peer-status] *' })).toBeTruthy();
        expect(screen.getByText('Un dossier ressemble beaucoup à celui-ci (VBG-0007).')).toBeTruthy();
        expect(reload).toHaveBeenCalledWith({ only: ['recent'] });
        await waitFor(() => expect(document.querySelector('[data-peer-queue]')?.textContent).toContain('Aucune fiche en attente'));
    });

    it('réseau là : part tout de suite par la boîte d’envoi', async () => {
        const { calls } = fakeFetch(() => json({ data: makeCase({ code: 'VDH-0003' }) }, 201));
        vi.spyOn(router, 'reload').mockImplementation(() => undefined as never);
        await mount();
        await fill({ 'Type de dossier': 'VDH' });
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));

        expect(await screen.findByText('Dossier VDH-0003 enregistré.', { selector: '[data-peer-status] *' })).toBeTruthy();
        expect(calls.filter((call) => call.method === 'POST')).toHaveLength(1);
    });

    it('affiche le refus du serveur (422) et garde la fiche : « Corriger et renvoyer » la remet dans le formulaire, notes comprises', async () => {
        const state = { refuse: true };
        const { calls } = fakeFetch((call) => (state.refuse
            ? json({ message: 'Les données sont invalides.', code: 'validation_failed', errors: { age_range: ['Choisis une tranche d’âge comprise entre 15 et 60 ans.'] } }, 422)
            : json({ data: { code: 'VBG-0050' }, meta: {} }, 201)));
        await mount();
        await fill({ 'Département': '8', 'Commune': '81' });
        await fireEvent.update(screen.getByLabelText(/^Notes/), 'Récit détaillé de la situation, long de plus de vingt caractères.');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));

        expect(await screen.findByText('Le dossier n’a pas été enregistré')).toBeTruthy();
        expect(screen.getByText('Choisis une tranche d’âge comprise entre 15 et 60 ans.')).toBeTruthy();
        // Le formulaire est vide (la fiche est partie), la fiche refusée est gardée.
        expect((screen.getByLabelText(/^Département/) as HTMLSelectElement).value).toBe('');

        await fireEvent.click(screen.getByRole('button', { name: 'Corriger et renvoyer' }));
        await waitFor(() => expect((screen.getByLabelText(/^Département/) as HTMLSelectElement).value).toBe('8'));
        expect((screen.getByLabelText(/^Commune/) as HTMLSelectElement).value).toBe('81');
        expect((screen.getByLabelText(/^Notes/) as HTMLTextAreaElement).value).toBe('Récit détaillé de la situation, long de plus de vingt caractères.');
        expect(screen.queryByText('Le dossier n’a pas été enregistré')).toBeNull();
        expect(screen.getAllByText('Choisis une tranche d’âge comprise entre 15 et 60 ans.').length).toBeGreaterThan(0);

        state.refuse = false;
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));
        expect(await screen.findByText('Dossier VBG-0050 enregistré.', { selector: '[data-peer-status] *' })).toBeTruthy();
        expect(calls.filter((call) => call.method === 'POST')).toHaveLength(2);
    });

    it('« Corriger et renvoyer » attend que la fiche en cours soit terminée ou effacée', async () => {
        fakeFetch(() => json({ message: 'Les données sont invalides.', code: 'validation_failed', errors: { sex: ['Valeur refusée.'] } }, 422));
        await mount();
        await fill();
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le dossier' }));
        await screen.findByText('Valeur refusée.');

        await fireEvent.update(screen.getByLabelText(/^Type de dossier/), 'VDH');
        expect((screen.getByRole('button', { name: 'Corriger et renvoyer' }) as HTMLButtonElement).disabled).toBe(true);
        expect(screen.getByText(/Termine ou efface la fiche en cours/)).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));
        expect(screen.queryByText('Le dossier n’a pas été enregistré')).toBeNull();
    });

    it('affiche au retour sur la page un refus déjà enregistré, sans bouton de correction si la fiche n’est plus connue', async () => {
        fakeFetch(() => json({}));
        recordFailures([{ id: 'oubliee', type: 'collecte', errors: { age_range: ['Tranche refusée.'] } }], 'générique');
        await mount();

        const alert = document.querySelector('[data-peer-refused]') as HTMLElement;
        expect(alert.textContent).toContain('Tranche refusée.');
        expect(alert.textContent).toContain('Cette fiche n’est plus sur l’appareil');
        expect(within(alert).queryByRole('button', { name: 'Corriger et renvoyer' })).toBeNull();
        await fireEvent.click(within(alert).getByRole('button', { name: 'Fermer' }));
        expect(document.querySelector('[data-peer-refused]')).toBeNull();
    });

    it('annonce au montage le résultat d’un passage tombé ailleurs, une seule fois', async () => {
        fakeFetch(() => json({}));
        const mounted = await mount();
        const detail = { sent: [{ id: 's1', type: 'collecte', status: 201, data: { code: 'VBG-0099' } }], failed: [], pending: 0 };
        mounted.store.lastFlush = detail;
        expect(await screen.findByText('Dossier VBG-0099 enregistré.', { selector: '[data-peer-status] *' })).toBeTruthy();
        expect(claim(mounted.store.lastFlush as object)).toBe(false);
    });

    it('garde un brouillon SANS les notes, le relit au retour et l’efface à la demande', async () => {
        fakeFetch(() => json({}));
        await mount();
        await new Promise((resolve) => setTimeout(resolve, 50)); // lecture du brouillon (IndexedDB) terminée
        await fill({ 'Département': '8', 'Commune': '81' });
        await fireEvent.update(screen.getByLabelText(/^Notes/), 'détail qui ne doit pas rester sur l’appareil');

        const drafts = useDraftsStore();
        await waitFor(async () => expect(await drafts.load('collecte')).not.toBeNull(), { timeout: 2000 });
        const draft = await drafts.load<Record<string, string>>('collecte');
        expect(draft).toMatchObject({ kind: 'VBG', zone_id: '8', commune_id: '81' });
        expect(draft).not.toHaveProperty('notes');
        expect(await screen.findByText(/Un brouillon \(sans les notes\)/)).toBeTruthy();

        unmountInertia();
        await mount();
        await waitFor(() => expect((screen.getByLabelText(/^Département/) as HTMLSelectElement).value).toBe('8'));
        expect((screen.getByLabelText(/^Notes/) as HTMLTextAreaElement).value).toBe('');

        await fireEvent.click(screen.getByRole('button', { name: 'Effacer le brouillon' }));
        await waitFor(async () => expect(await useDraftsStore().load('collecte')).toBeNull());
        expect((screen.getByLabelText(/^Département/) as HTMLSelectElement).value).toBe('');
    });
});
