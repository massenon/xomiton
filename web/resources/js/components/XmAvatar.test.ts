// @vitest-environment jsdom
// XmAvatar : sans image (src absent, nul ou vide), initiales neutres dans un role="img" nommé, jamais de <img> ni de
// requête réseau ; avec une image téléversée, <img> borné par ses dimensions. Aucune photo par défaut (rendu mobile membre).
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import XmAvatar from './XmAvatar.vue';

afterEach(cleanup);

describe('XmAvatar', () => {
    it.each([[undefined], [null], ['']])('retombe sur les initiales quand src vaut %j', (src) => {
        const { container } = renderXm(XmAvatar, { props: { name: 'Awa Dossou', src: src as string | null | undefined } });
        expect(container.querySelector('img')).toBeNull();
        const initials = container.querySelector('.avatar-img.xm-avatar');
        expect(initials?.textContent).toBe('AD');
        expect(initials?.getAttribute('role')).toBe('img');
        expect(initials?.getAttribute('aria-label')).toContain('Awa Dossou');
    });

    it('limite les initiales à deux lettres et gère un pseudonyme seul', () => {
        expect(renderXm(XmAvatar, { props: { name: 'awa_s' } }).container.textContent).toBe('A');
        cleanup();
        expect(renderXm(XmAvatar, { props: { name: 'Jean Marie Paul' } }).container.textContent).toBe('JM');
    });

    it('affiche l’image téléversée avec ses dimensions et son texte alternatif', () => {
        const { container } = renderXm(XmAvatar, { props: { name: 'Awa', src: '/storage/avatars/awa.png', size: 'sm' } });
        const img = container.querySelector('img');
        expect(img?.getAttribute('src')).toBe('/storage/avatars/awa.png');
        expect([img?.getAttribute('width'), img?.getAttribute('height')]).toEqual(['40', '40']);
        expect(img?.getAttribute('alt')).toContain('Awa');
    });
});
