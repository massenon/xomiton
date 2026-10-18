// @vitest-environment jsdom
// Messages flashés (P6-02) : toast montré une seule fois par identifiant serveur — jamais au retour dans l'historique
// (nouvel objet page restauré depuis history.state, ou onglet rechargé), statut aussi montré, type ramené à ToastType.
import { beforeEach, describe, expect, it } from 'vitest';
import { FLASH_SEEN_KEY, createFlashPresenter, toastType } from './flash';
import type { SharedProps } from './types';

const props = (toast: unknown, status: unknown = null) => ({ flash: { toast, status } }) as unknown as Partial<SharedProps>;

beforeEach(() => window.sessionStorage.clear());

describe('createFlashPresenter', () => {
    it('affiche le toast et le statut une fois ; une page restaurée avec le même flash ne le réaffiche pas', () => {
        const shown: string[] = [];
        const present = createFlashPresenter((message, type) => shown.push(`${type}:${message}`));
        present(props({ id: 'a1', message: 'Publié.', type: 'success' }, { id: 'a2', message: 'Profil enregistré.', type: 'info' }));
        // popstate : Inertia restaure un nouvel objet page portant les mêmes props.
        present(JSON.parse(JSON.stringify(props({ id: 'a1', message: 'Publié.', type: 'success' }, { id: 'a2', message: 'Profil enregistré.', type: 'info' }))));
        present(props({ id: 'b1', message: 'Supprimé.', type: 'danger' }));
        present(props(null));
        present(undefined);
        expect(shown).toEqual(['success:Publié.', 'info:Profil enregistré.', 'danger:Supprimé.']);
    });

    it('garde les identifiants vus dans sessionStorage : un onglet rechargé puis Retour ne réaffiche rien', () => {
        const first: string[] = [];
        createFlashPresenter((message) => first.push(message))(props({ id: 'r1', message: 'Envoyé.', type: 'success' }));
        expect(JSON.parse(window.sessionStorage.getItem(FLASH_SEEN_KEY) || '[]')).toEqual(['r1']);
        const second: string[] = [];
        createFlashPresenter((message) => second.push(message))(props({ id: 'r1', message: 'Envoyé.', type: 'success' }));
        expect(first).toEqual(['Envoyé.']);
        expect(second).toEqual([]);
    });

    it('type inconnu ramené à primary ; stockage bloqué toléré', () => {
        const shown: string[] = [];
        const blocked = { getItem: () => { throw new Error('bloqué'); }, setItem: () => { throw new Error('bloqué'); } };
        const present = createFlashPresenter((message, type) => shown.push(`${type}:${message}`), blocked);
        present(props({ id: 'x', message: 'Note.', type: 'light text-bg-danger' }));
        present(props({ id: 'x', message: 'Note.', type: 'info' }));
        expect(shown).toEqual(['primary:Note.']);
        expect(toastType('warning')).toBe('warning');
        expect(toastType(undefined)).toBe('primary');
    });
});
