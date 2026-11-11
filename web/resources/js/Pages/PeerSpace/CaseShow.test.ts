// @vitest-environment jsdom
// Page /espace-pair/dossiers/{code} (lot espace-pair, P9-08 ; prototype admin/suivi.php) : fiche de référence en cinq
// sections en lecture (fiche de l'administration ou champs de la collecte), frise de suivi la plus récente d'abord (retard
// en toutes lettres), ajout d'une étape (validations, Idempotency-Key gardée au nouvel essai, 422 par champ, hors ligne :
// rien d'enregistré), « Marquer comme réalisée », changement de statut du dossier, dossier verrouillé.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { makeCase, makeFollowup, makeSheet, TODAY } from '@/test/peer';
import { useUiStore } from '@/stores/ui';
import CaseShow from './CaseShow.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const outcomes = [{ value: 'planifie', label: 'Planifié' }, { value: 'realise', label: 'Réalisé' }, { value: 'reporte', label: 'Reporté' }, { value: 'annule', label: 'Annulé' }];

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'VBG-0042',
    record: makeCase({
        followups: [
            makeFollowup({ id: 1, date: '2026-10-02', step: 'Accueil', outcome: 'realise' }),
            makeFollowup({ id: 2, date: '2026-10-09', step: 'Appui juridique', outcome: 'planifie', outcome_label: 'Planifié', outcome_badge: 'info', next_on: '2026-10-12', overdue: true, comment: 'Rendez-vous avec la juriste.', author: 'Awa_S' }),
        ],
    }),
    sheet: makeSheet(),
    steps: ['Accueil', 'Écoute', 'Appui juridique', 'Clôture'],
    outcomes,
    statuses: [{ value: 'en_cours', label: 'En cours' }, { value: 'clos', label: 'Clos' }],
    limits: { comment_max: 1000 },
    today: TODAY,
    ...overrides,
});

describe('Page /espace-pair/dossiers/{code}', () => {
    it('affiche l’en-tête et les cinq sections tirées de la collecte quand la fiche n’est pas remplie', async () => {
        fakeFetch(() => json({}));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        expect(screen.getByRole('heading', { level: 1, name: 'VBG-0042' })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Mes dossiers/ }).getAttribute('href')).toBe('/espace-pair/dossiers');
        expect(document.querySelector('[data-peer-status]')?.textContent).toBe('Ouvert');
        expect(screen.getByText(/La fiche de référence n’est pas encore remplie/)).toBeTruthy();
        const sheet = document.querySelector('[data-peer-sheet]') as HTMLElement;
        const titles = within(sheet).getAllByRole('heading', { level: 2 }).map((h) => h.textContent);
        expect(titles).toEqual(['1. Identification pseudonymisée', '2. Faits', '3. Services reçus', '4. Orientation', '5. Consentement']);
        expect(sheet.textContent).toContain('Abomey, Zou');
        expect(sheet.textContent).toContain('Accueil réalisé.');
        expect(sheet.textContent).toContain('01/10/2026');
        expect(sheet.textContent).toContain('Juriste');
        expect(sheet.textContent).toContain('Pas encore renseigné.');
    });

    it('affiche la fiche de référence quand elle existe : services, orientation, consentement', async () => {
        fakeFetch(() => json({}));
        const sheet = makeSheet({
            filled: true,
            reference: 'FR-2026-0007',
            identification: { pseudo: 'Awa', sex: 'Femme', age_range: '18-24', population: 'Femmes', zone: 'Zou', commune: 'Abomey' },
            services: { items: ['Écoute / accueil', 'Appui juridique'], note: 'Deux séances.' },
            orientation: { referral: 'Psychologue', contact: 'Clinique Espoir', date: '2026-10-05', reason: 'Soutien psychologique', urgency: 'haute' },
            consent: { given: true, mode: 'ecrit', sharing: false },
        });
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base({ sheet }) });

        expect(screen.queryByText(/n’est pas encore remplie/)).toBeNull();
        expect(screen.getByText('Fiche de référence FR-2026-0007')).toBeTruthy();
        const text = (document.querySelector('[data-peer-sheet]') as HTMLElement).textContent as string;
        for (const expected of ['Awa', 'Écoute / accueil', 'Deux séances.', 'Clinique Espoir', '05/10/2026', 'Haute', 'Écrit']) {
            expect(text).toContain(expected);
        }
        expect(text).not.toContain('signataire');
    });

    it('montre la frise : plus récente d’abord, résultat en toutes lettres, « En retard », prochain suivi', async () => {
        fakeFetch(() => json({}));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        const items = screen.getByRole('list', { name: 'Frise de suivi' }).querySelectorAll('li');
        expect(items).toHaveLength(2);
        expect(items[0].textContent).toContain('Appui juridique');
        expect(items[0].textContent).toContain('Planifié');
        expect(items[0].textContent).toContain('En retard');
        expect(items[0].textContent).toContain('Rendez-vous avec la juriste.');
        expect(items[0].textContent).toContain('12/10/2026');
        expect(items[1].textContent).toContain('Accueil');
        expect(within(items[0] as HTMLElement).getByRole('button', { name: 'Marquer comme réalisée' })).toBeTruthy();
        expect(within(items[1] as HTMLElement).queryByRole('button', { name: 'Marquer comme réalisée' })).toBeNull();
    });

    it('marque une étape comme réalisée', async () => {
        const { calls } = fakeFetch(() => json({ data: makeFollowup({ id: 2, date: '2026-10-09', step: 'Appui juridique', outcome: 'realise', next_on: '2026-10-12', overdue: false }) }));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: 'Marquer comme réalisée' }));
        await waitFor(() => expect(calls).toEqual([expect.objectContaining({ method: 'PATCH', url: '/api/v1/pair/cases/VBG-0042/followups/2', body: { outcome: 'realise' } })]));
        // Pendant l'envoi le bouton s'appelle « Envoi… » : attendre la frise mise à jour.
        await waitFor(() => expect(screen.queryByText('En retard')).toBeNull());
        expect(screen.queryByRole('button', { name: 'Marquer comme réalisée' })).toBeNull();
    });

    it('ajoute une étape : champs vérifiés avant l’envoi, Idempotency-Key = client_uuid, frise mise à jour', async () => {
        const { calls } = fakeFetch(() => json({ data: makeFollowup({ id: 9, date: '2026-10-20', step: 'Écoute', outcome: 'realise', comment: 'Première écoute.' }) }, 201));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        await fireEvent.update(screen.getByLabelText(/^Date/), '2026-10-25');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));
        expect(await screen.findByText('La date ne peut pas être postérieure à aujourd’hui.', { selector: '.invalid-feedback' })).toBeTruthy();
        expect(calls).toHaveLength(0);

        await fireEvent.update(screen.getByLabelText(/^Date/), '2026-10-20');
        await fireEvent.update(screen.getByLabelText(/^Prochaine date de suivi/), '2026-10-10');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));
        expect(await screen.findByText('La prochaine date ne peut pas précéder la date de l’étape.', { selector: '.invalid-feedback' })).toBeTruthy();
        expect(calls).toHaveLength(0);

        await fireEvent.update(screen.getByLabelText(/^Prochaine date de suivi/), '');
        await fireEvent.update(screen.getByLabelText(/^Étape/), 'Écoute');
        await fireEvent.update(screen.getByLabelText(/^Commentaire/), ' Première écoute. ');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));

        await waitFor(() => expect(calls).toHaveLength(1));
        const call = calls[0];
        expect(call.url).toBe('/api/v1/pair/cases/VBG-0042/followups');
        expect(call.body).toMatchObject({ date: '2026-10-20', step: 'Écoute', outcome: 'realise', comment: 'Première écoute.' });
        expect(call.headers['Idempotency-Key']).toBe((call.body as { client_uuid: string }).client_uuid);
        expect(await screen.findByText('Première écoute.')).toBeTruthy();
        expect(screen.getByRole('list', { name: 'Frise de suivi' }).querySelectorAll('li')).toHaveLength(3);
        expect((screen.getByLabelText(/^Commentaire/) as HTMLTextAreaElement).value).toBe('');
    });

    it('hors ligne : rien d’enregistré, saisie gardée, même clé au nouvel essai (pas de doublon)', async () => {
        let attempts = 0;
        const { calls } = fakeFetch(() => {
            attempts += 1;
            return attempts === 1 ? Promise.reject(new TypeError('Failed to fetch')) : json({ data: makeFollowup({ id: 9, step: 'Écoute' }) }, 201);
        });
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        await fireEvent.update(screen.getByLabelText(/^Étape/), 'Écoute');
        await fireEvent.update(screen.getByLabelText(/^Commentaire/), 'Écoute à distance');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));
        expect(await screen.findByText(/Tu es hors ligne : rien n’a été enregistré/)).toBeTruthy();
        expect((screen.getByLabelText(/^Commentaire/) as HTMLTextAreaElement).value).toBe('Écoute à distance');
        expect(screen.getByRole('list', { name: 'Frise de suivi' }).querySelectorAll('li')).toHaveLength(2);

        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));
        await waitFor(() => expect(calls).toHaveLength(2));
        expect(calls[1].headers['Idempotency-Key']).toBe(calls[0].headers['Idempotency-Key']);
        await waitFor(() => expect(screen.getByRole('list', { name: 'Frise de suivi' }).querySelectorAll('li')).toHaveLength(3));
    });

    it('affiche les erreurs 422 du serveur par champ', async () => {
        fakeFetch(() => json({ message: 'Les données sont invalides.', code: 'validation_failed', errors: { date: ['La date ne peut pas être dans le futur.'] } }, 422));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer l’étape' }));
        expect(await screen.findAllByText('La date ne peut pas être dans le futur.')).not.toHaveLength(0);
    });

    it('change le statut du dossier selon les transitions proposées par le serveur, puis relit la fiche', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        const { calls } = fakeFetch(() => json({ data: makeCase({ status: 'clos', status_label: 'Clos', status_badge: 'success' }) }));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });

        const select = screen.getByLabelText('Passer le dossier à') as HTMLSelectElement;
        expect([...select.options].map((o) => o.value)).toEqual(['', 'en_cours', 'clos']);
        expect((screen.getByRole('button', { name: 'Mettre à jour' }) as HTMLButtonElement).disabled).toBe(true);
        await fireEvent.update(select, 'clos');
        await fireEvent.click(screen.getByRole('button', { name: 'Mettre à jour' }));

        await waitFor(() => expect(calls).toEqual([expect.objectContaining({ method: 'PATCH', url: '/api/v1/pair/cases/VBG-0042', body: { status: 'clos' } })]));
        await waitFor(() => expect(document.querySelector('[data-peer-status]')?.textContent).toBe('Clos'));
        expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Statut du dossier mis à jour.');
        expect(reload).toHaveBeenCalledWith({ only: ['record', 'statuses', 'sheet'] });
    });

    it('après la relecture, les transitions et gestes suivent le nouveau statut (dossier clos : plus de formulaire)', async () => {
        const closed = makeCase({ status: 'clos', status_label: 'Clos', status_badge: 'success', can: { update: false, add_followup: false } });
        // La relecture serveur est simulée : mêmes props que le contrôleur renverrait pour un dossier clos.
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => {
            router.replace({ preserveState: true, preserveScroll: true, props: (current: Record<string, unknown>) => ({ ...current, record: closed, statuses: [] }) } as never);
        });
        fakeFetch(() => json({ data: closed }));
        await mountInertia(CaseShow, { url: '/espace-pair/dossiers/VBG-0042', pageProps: base() });
        expect(screen.getByRole('button', { name: 'Enregistrer l’étape' })).toBeTruthy();

        await fireEvent.update(screen.getByLabelText('Passer le dossier à'), 'clos');
        await fireEvent.click(screen.getByRole('button', { name: 'Mettre à jour' }));
        await waitFor(() => expect(reload).toHaveBeenCalled());

        await waitFor(() => expect(screen.queryByRole('button', { name: 'Enregistrer l’étape' })).toBeNull());
        expect(screen.queryByLabelText('Passer le dossier à')).toBeNull();
        expect(screen.getByText(/Ce dossier est archivé ou anonymisé/)).toBeTruthy();
    });

    it('dossier verrouillé (archivé ou anonymisé) : ni formulaire d’étape ni geste de modification', async () => {
        fakeFetch(() => json({}));
        await mountInertia(CaseShow, {
            url: '/espace-pair/dossiers/VBG-0042',
            pageProps: base({ record: makeCase({ anonymized: true, can: { update: false, add_followup: false }, followups: [makeFollowup({ outcome: 'planifie', outcome_label: 'Planifié' })] }), statuses: [] }),
        });

        expect(screen.getByText(/Ce dossier est archivé ou anonymisé : il ne reçoit plus de nouvelle étape/)).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Enregistrer l’étape' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Marquer comme réalisée' })).toBeNull();
        expect(screen.queryByLabelText('Passer le dossier à')).toBeNull();
        expect(screen.getByText('Anonymisé')).toBeTruthy();
    });
});
