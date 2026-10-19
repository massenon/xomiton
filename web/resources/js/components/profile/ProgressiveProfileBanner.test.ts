// @vitest-environment jsdom
// Bandeau du profil progressif (lot profil, P8-08) : un groupe à la fois, « Pourquoi on demande ça ? », consentement
// `sensitive_data` exigé (jamais pré-coché) avant d'envoyer une identité de genre, « Afficher sur mon profil » décoché
// par défaut (aucun champ sensible partagé sans choix explicite), « Je préfère ne pas le dire » sans consentement,
// « Passer ce groupe » gardé pour la session, groupe suivant donné par le serveur.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import { fakeFetch, json } from '@/test/feed';
import { makeProgressive } from '@/test/profile';
import ProgressiveProfileBanner from './ProgressiveProfileBanner.vue';

afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.sessionStorage.clear();
});

const serverProfile = (progressive: ReturnType<typeof makeProgressive>['state'], overrides: Record<string, unknown> = {}) => ({
    data: { profile: {
        pronouns: 'Elle', member_type: 'membre', ally_status: null, gender_identity: null, orientation: null, age_range: null,
        interests: [], shared_fields: [], completion: 57, progressive, ...overrides,
    } },
});

describe('ProgressiveProfileBanner', () => {
    it('exige le consentement avant d’envoyer une identité de genre, sans la partager par défaut', async () => {
        const next = makeProgressive('orientation', true).state;
        const { calls } = fakeFetch(() => json(serverProfile(next, { gender_identity: 'non_binaire' })));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('gender_identity') } });

        expect(screen.getByText('Question 4 sur 8')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: /Pourquoi on demande ça/ }));
        expect(screen.getByText(/Donnée sensible/)).toBeTruthy();

        await fireEvent.click(screen.getByLabelText('Non binaire'));
        const consent = screen.getByLabelText(/J’accepte que Xomiton conserve/) as HTMLInputElement;
        const share = screen.getByLabelText('Afficher sur mon profil') as HTMLInputElement;
        expect(consent.checked).toBe(false);
        expect(share.checked).toBe(false);

        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        expect(screen.getByRole('alert').textContent).toContain('accepte d’abord la conservation');
        expect(calls).toHaveLength(0);

        await fireEvent.click(consent);
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(screen.getByText('Question 5 sur 8')).toBeTruthy());
        expect(calls[0]).toMatchObject({ url: '/api/v1/me/profile', method: 'PATCH', body: { sensitive_consent: true, gender_identity: 'non_binaire', shared_fields: [] } });
        expect(screen.getByRole('heading', { name: /Complète ton profil/ })).toBeTruthy();
    });

    it('« Je préfère ne pas le dire » part sans consentement ; partager ajoute le champ à shared_fields', async () => {
        const after = makeProgressive('age', true).state;
        const { calls } = fakeFetch((call) => json(serverProfile(call.body && (call.body as Record<string, unknown>).orientation === 'prefer_not' ? makeProgressive('age', true).state : after)));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('orientation', true) } });

        await fireEvent.click(screen.getByLabelText('Queer'));
        await fireEvent.click(screen.getByLabelText('Afficher sur mon profil'));
        expect(screen.queryByLabelText(/J’accepte que Xomiton conserve/)).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]?.body).toEqual({ orientation: 'queer', shared_fields: ['orientation'] });
    });

    it('passe un groupe pour la session et envoie « Je préfère ne pas le dire » pour l’âge', async () => {
        const { calls } = fakeFetch(() => json(serverProfile(makeProgressive('location').state)));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('ally_status') } });

        await fireEvent.click(screen.getByRole('button', { name: 'Passer ce groupe' }));
        expect(JSON.parse(window.sessionStorage.getItem('xm_profile_skipped') ?? '[]')).toEqual(['ally_status']);
        expect(screen.getByText('Identité de genre')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Passer ce groupe' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Passer ce groupe' }));

        expect(screen.getByText('Question 6 sur 8')).toBeTruthy();
        await fireEvent.click(screen.getByLabelText('Je préfère ne pas le dire'));
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]?.body).toEqual({ age_range: 'prefer_not' });
    });

    it('annonce la fin quand tous les groupes sont traités', async () => {
        fakeFetch(() => json(serverProfile(makeProgressive(null).state)));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('interests') } });

        await fireEvent.click(screen.getByLabelText('Santé'));
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(screen.getByRole('heading', { name: /Merci, ton profil est à jour/ })).toBeTruthy());
    });

    it('groupes passés puis dernier groupe répondu : état de fin, jamais une carte vide', async () => {
        // Le serveur repropose « Ville » (passée pour la session) : rien d'autre à montrer pendant cette session.
        const onlyLocation = makeProgressive(null).state;
        onlyLocation.groups = onlyLocation.groups.map((group) => ({ ...group, answered: group.key !== 'location' }));
        Object.assign(onlyLocation, { next: 'location', done: false, answered: 7 });
        fakeFetch(() => json(serverProfile(onlyLocation)));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('location') } });

        await fireEvent.click(screen.getByRole('button', { name: 'Passer ce groupe' }));
        expect(screen.getByText('Question 8 sur 8')).toBeTruthy();
        await fireEvent.click(screen.getByLabelText('Santé'));
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));

        await waitFor(() => expect(screen.getByRole('heading', { name: /Merci, ton profil est à jour/ })).toBeTruthy());
        expect(screen.getByRole('button', { name: 'Fermer' })).toBeTruthy();
    });

    it('groupe Ville : « Afficher ma zone » explicite (valeur actuelle), envoyé avec le département', async () => {
        const { calls } = fakeFetch(() => json(serverProfile(makeProgressive('interests').state, { show_zone: false })));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('location') } });

        await fireEvent.update(screen.getByLabelText('Département'), '4');
        const showZone = screen.getByLabelText('Afficher ma zone sur mon profil') as HTMLInputElement;
        expect(showZone.checked).toBe(true);
        expect(screen.getByText(/ne voient que ton département/)).toBeTruthy();
        await fireEvent.click(showZone);
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));

        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]?.body).toEqual({ zone_id: 4, commune_id: null, show_zone: false });
    });

    it('« Je préfère ne pas le dire » aussi pour le type de membre et les centres d’intérêt', async () => {
        const { calls } = fakeFetch((call) => json(serverProfile(
            makeProgressive((call.body as { answered_groups?: string[] }).answered_groups?.[0] === 'member_type' ? 'ally_status' : null).state,
        )));
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('member_type') } });

        await fireEvent.click(screen.getByLabelText('Je préfère ne pas le dire'));
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]?.body).toEqual({ answered_groups: ['member_type'] });

        cleanup();
        renderXm(ProgressiveProfileBanner, { props: { progressive: makeProgressive('interests') } });
        await fireEvent.click(screen.getByLabelText('Je préfère ne pas le dire'));
        expect((screen.getByLabelText('Santé') as HTMLInputElement).disabled).toBe(true);
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer et continuer' }));
        await waitFor(() => expect(calls).toHaveLength(2));
        expect(calls[1]?.body).toEqual({ answered_groups: ['interests'] });
    });
});
