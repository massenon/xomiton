// @vitest-environment jsdom
// Page /parametres (lot parametres-notifications, P8-09 ; prototype settings.php) : sections et ancres du prototype,
// mot de passe (jauge, PUT me/password, erreurs de champ, appareils relus), préférences de notification (sécurité et
// SOS verrouillées, jamais envoyées), titre d'onglet neutre appliqué tout de suite (store session), thème, langue
// limitée à config('xomiton.locales'), appareils connectés, suppression du compte (mot SUPPRIMER + mot de passe).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { settingsProps } from '@/test/settings';
import { useSessionStore } from '@/stores/session';
import Settings from './Settings.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.title = '';
});

const mount = (overrides: Record<string, unknown> = {}) => mountInertia(Settings, { url: '/parametres', pageProps: settingsProps(overrides) });

describe('Page /parametres — structure', () => {
    it('affiche le menu des sections avec les ancres du prototype, sans sortie rapide', async () => {
        fakeFetch(() => json({ data: {} }));
        await mount();

        const nav = screen.getByRole('navigation', { name: 'Sections des paramètres' });
        const anchors = [...nav.querySelectorAll('a')].map((a) => a.getAttribute('href'));
        expect(anchors).toEqual(['#profil', '#motdepasse', '#confidentialite', '#notifications', '#langue', '#apparence', '#securite', '#appareils', '#newsletter', '#donnees', '#supprimer']);
        anchors.forEach((href) => expect(document.querySelector(href as string)).not.toBeNull());
        expect(screen.getByRole('heading', { level: 1, name: 'Paramètres' })).toBeTruthy();
        expect(screen.queryByText(/sortie rapide/i)).toBeNull();
        expect((document.querySelector('#f-email') as HTMLInputElement).disabled).toBe(true);
    });
});

describe('Page /parametres — mot de passe', () => {
    it('montre la jauge, affiche l’erreur du serveur près du champ, puis change le mot de passe et relit les appareils', async () => {
        let attempt = 0;
        const { calls } = fakeFetch((call) => {
            if (call.url === '/api/v1/me/password') {
                attempt += 1;
                return attempt === 1
                    ? json({ message: 'Données invalides.', errors: { current_password: ['Le mot de passe actuel est incorrect.'] } }, 422)
                    : json({ data: { changed: true, revoked: 1 } });
            }
            return json({ data: [(settingsProps().sessions as unknown[])[0]] });
        });
        await mount();

        await fireEvent.update((document.querySelector('#f-current_password') as HTMLInputElement), 'faux');
        await fireEvent.update(screen.getByLabelText('Nouveau mot de passe'), 'Nouveau-Mot2passe');
        await fireEvent.update(screen.getByLabelText('Confirmation'), 'Nouveau-Mot2passe');
        expect(document.querySelector('#pwMeter')?.getAttribute('data-level')).toBe('excellent');
        expect(screen.getByLabelText('Nouveau mot de passe').getAttribute('aria-describedby')).toContain('pwMeter-rules');

        await fireEvent.click(screen.getByRole('button', { name: 'Changer le mot de passe' }));
        await waitFor(() => expect(screen.getByText('Le mot de passe actuel est incorrect.')).toBeTruthy());
        expect(calls[0]).toMatchObject({ method: 'PUT', body: { current_password: 'faux', password: 'Nouveau-Mot2passe', password_confirmation: 'Nouveau-Mot2passe' } });

        await fireEvent.update((document.querySelector('#f-current_password') as HTMLInputElement), 'password');
        await fireEvent.click(screen.getByRole('button', { name: 'Changer le mot de passe' }));
        await waitFor(() => expect(calls.some((call) => call.url === '/api/v1/me/sessions' && call.method === 'GET')).toBe(true));
        expect(((document.querySelector('#f-current_password') as HTMLInputElement) as HTMLInputElement).value).toBe('');
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Déconnecter Pixel 7' })).toBeNull());
    });
});

describe('Page /parametres — profil, confirmation, confidentialité', () => {
    it('laisse le nom facultatif : un compte sans nom enregistre son profil avec name null', async () => {
        const { calls } = fakeFetch(() => json({ data: { name: null, pseudo: 'Awa_S', email: 'awa@exemple.bj', phone: null, bio: 'Bonjour', zone_id: 4, commune_id: null } }));
        await mount({ identity: { name: null, pseudo: 'Awa_S', email: 'awa@exemple.bj', phone: null, bio: 'Bonjour', zone_id: 4, commune_id: null } });

        const name = document.querySelector('#f-name') as HTMLInputElement;
        expect(name.required).toBe(false);
        expect(document.querySelector('label[for="f-name"]')?.textContent).toContain('(facultatif)');
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le profil' }));
        await waitFor(() => expect(calls[0]).toMatchObject({ url: '/api/v1/me/identity', method: 'PATCH', body: { name: null, pseudo: 'Awa_S' } }));
    });

    it('affiche l’erreur de confirmation sous le champ Confirmation (aria-invalid)', async () => {
        fakeFetch(() => json({ message: 'Données invalides.', errors: { password_confirmation: ['La confirmation ne correspond pas.'] } }, 422));
        await mount();

        await fireEvent.update((document.querySelector('#f-current_password') as HTMLInputElement), 'password');
        await fireEvent.update(screen.getByLabelText('Nouveau mot de passe'), 'Nouveau-Mot2passe');
        await fireEvent.update(screen.getByLabelText('Confirmation'), 'Autre-Mot2passe');
        await fireEvent.click(screen.getByRole('button', { name: 'Changer le mot de passe' }));
        await waitFor(() => expect(screen.getByLabelText('Confirmation').getAttribute('aria-invalid')).toBe('true'));
        expect(screen.getByLabelText('Nouveau mot de passe').getAttribute('aria-invalid')).not.toBe('true');
    });

    it('retire le consentement aux données sensibles depuis « Confidentialité »', async () => {
        const privacy = { visibility: 'community', show_zone: true, searchable: true, sos_share_gps: false, sensitive_consent: true };
        const { calls } = fakeFetch(() => json({ data: { ...privacy, sensitive_consent: false } }));
        await mount({ privacy });

        const section = document.querySelector('#confidentialite') as HTMLElement;
        const consent = screen.getByRole('switch', { name: /Conserver mes données sensibles/ }) as HTMLInputElement;
        expect(consent.checked).toBe(true);
        expect(section.textContent).toContain('ces informations sont effacées de ton profil');
        await fireEvent.click(consent);
        await fireEvent.click(section.querySelector('button[type="submit"]') as HTMLButtonElement);
        await waitFor(() => expect(calls[0]).toMatchObject({ url: '/api/v1/me/privacy', method: 'PATCH', body: { sensitive_consent: false } }));
    });
});

describe('Page /parametres — notifications et sécurité', () => {
    it('verrouille sécurité et SOS, et n’envoie que les catégories modifiables', async () => {
        const { calls } = fakeFetch(() => json({ data: {} }));
        await mount();

        const locked = screen.getByRole('switch', { name: /^Push \(navigateur et mobile\)\s: SOS$/u }) as HTMLInputElement;
        expect(locked.disabled).toBe(true);
        expect(locked.checked).toBe(true);
        expect(screen.getAllByText('Toujours actif')).toHaveLength(2);

        await fireEvent.click(screen.getByRole('switch', { name: /^E-mail\s: Messages$/u }));
        await fireEvent.update(screen.getByLabelText('Résumé d’activité'), 'daily');
        await fireEvent.click(document.querySelector('#notifications button[type="submit"]') as HTMLButtonElement);

        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]).toMatchObject({ url: '/api/v1/me/settings', method: 'PATCH' });
        const body = calls[0].body as { notification_digest: string; notification_prefs: Record<string, { email: boolean }> };
        expect(body.notification_digest).toBe('daily');
        expect(Object.keys(body.notification_prefs)).toEqual(['messages', 'evenements']);
        expect(body.notification_prefs.messages.email).toBe(true);
    });

    it('applique le titre d’onglet neutre dès l’enregistrement (store session)', async () => {
        const { calls } = fakeFetch(() => json({ data: { discreet: false, mask_previews: false, pin_enabled: false, neutral_tab_title: true } }));
        await mount();
        const session = useSessionStore();
        expect(session.neutralTitle).toBe(false);

        await fireEvent.click(screen.getByRole('switch', { name: /Titre d’onglet neutre/ }));
        await fireEvent.click(document.querySelector('#securite button[type="submit"]') as HTMLButtonElement);

        await waitFor(() => expect(session.neutralTitle).toBe(true));
        expect(calls[0]).toMatchObject({ url: '/api/v1/me/security', method: 'PATCH', body: { neutral_tab_title: true } });
        expect(document.title).toBe('Notes');
        expect(session.documentTitle('Paramètres')).toBe('Notes');
        expect(session.user?.settings.neutral_tab_title).toBe(true);
    });
});

describe('Page /parametres — apparence, langue, appareils, suppression', () => {
    it('applique le thème sur l’appareil et l’enregistre sur le compte', async () => {
        const { calls } = fakeFetch(() => json({ data: {} }));
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: /Sombre/ }));
        expect(document.documentElement.getAttribute('data-bs-theme')).toBe('dark');
        expect(window.localStorage.getItem('theme')).toBe('dark');
        expect(screen.getByRole('button', { name: /Sombre/ }).getAttribute('aria-pressed')).toBe('true');
        await waitFor(() => expect(calls[0]).toMatchObject({ url: '/api/v1/me/settings', method: 'PATCH', body: { theme: 'dark' } }));
    });

    it('ne propose que les langues complètes, les autres « bientôt »', async () => {
        fakeFetch(() => json({ data: {} }));
        await mount();

        const select = screen.getByLabelText('Langue de l’interface') as HTMLSelectElement;
        const options = [...select.options].map((o) => [o.value, o.disabled, o.textContent?.trim()]);
        expect(options).toEqual([['fr', false, 'Français'], ['fon', true, 'Fɔngbe (bientôt)'], ['yo', true, 'Yorùbá (bientôt)'], ['en', true, 'English (bientôt)']]);
    });

    it('déconnecte un appareil, puis tous les autres', async () => {
        const { calls } = fakeFetch(() => json({ data: { revoked: 1 } }));
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Déconnecter Pixel 7' }));
        await waitFor(() => expect(screen.queryByRole('button', { name: 'Déconnecter Pixel 7' })).toBeNull());
        expect(calls[0]).toMatchObject({ url: '/api/v1/me/sessions/token-9', method: 'DELETE' });
        expect(screen.getByText('Aucun autre appareil connecté.')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Retirer l’appareil Pixel 7' }));
        await waitFor(() => expect(screen.getByText('Retiré')).toBeTruthy());
        expect(calls[1]).toMatchObject({ url: '/api/v1/me/devices/7d9f0c1e-2b3a-4c5d-8e9f-0a1b2c3d4e5f', method: 'DELETE' });
    });

    it('exige le mot SUPPRIMER avant tout appel, puis le mot de passe', async () => {
        const { calls } = fakeFetch(() => json({ message: 'Données invalides.', errors: { password: ['Le mot de passe actuel est incorrect.'] } }, 422));
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: /Supprimer mon compte/ }));
        expect(screen.getByText('Tape SUPPRIMER pour confirmer.', { selector: '.invalid-feedback' })).toBeTruthy();
        expect(calls).toHaveLength(0);

        await fireEvent.update(screen.getByLabelText('Tape SUPPRIMER pour confirmer'), 'SUPPRIMER');
        await fireEvent.update(document.querySelector('#f-delete_password') as HTMLInputElement, 'faux');
        await fireEvent.click(screen.getByRole('button', { name: /Supprimer mon compte/ }));
        await waitFor(() => expect(calls[0]).toMatchObject({ url: '/api/v1/me', method: 'DELETE', body: { password: 'faux' } }));
        await waitFor(() => expect(document.querySelector('#f-delete_password-error')?.textContent).toContain('incorrect'));
    });

    it('explique à un compte de l’administration qu’il ne se supprime pas d’ici', async () => {
        fakeFetch(() => json({ data: {} }));
        await mount({ can: { delete_account: false } });

        expect(screen.getByText(/ne se supprime pas depuis cette page/)).toBeTruthy();
        expect(screen.queryByLabelText('Tape SUPPRIMER pour confirmer')).toBeNull();
    });
});
