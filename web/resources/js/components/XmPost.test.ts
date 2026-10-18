// @vitest-environment jsdom
// XmPost (lot fil-ecrans, P7-05) : port de x_render_post — en-tête (avatar, pseudo, étiquette aux couleurs du
// prototype, temps relatif), publication anonyme (RG-02), corps, image, sondage, j'aime optimiste (annulé si l'API
// refuse, un envoi à la fois), commentaires vers le détail, partage par le lien neutre /p/{ulid} (Q31 b), menu
// Enregistrer · Signaler (modale de modération chargée à la demande) · Masquer (avec « Annuler ») · Supprimer (confirmée
// par <XmModal type="delete">), commentaire depuis la carte.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { defineComponent, h, nextTick, type PropType } from 'vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json, makePoll, makePost } from '@/test/feed';
import type { Post } from '@/feed/types';
import XmPost from './XmPost.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

const events: Array<[string, unknown[]]> = [];
const Page = defineComponent({
    inheritAttrs: false,
    props: { post: { type: Object as PropType<Post>, required: true }, detail: { type: Boolean, default: false } },
    setup: (props) => () => h(XmPost, {
        post: props.post,
        detail: props.detail,
        'onUpdate:post': (...args: unknown[]) => events.push(['update:post', args]),
        onRemoved: (...args: unknown[]) => events.push(['removed', args]),
    }),
});

const mount = async (post: Post, detail = false) => {
    events.length = 0;
    await mountInertia(Page, { pageProps: { post, detail } });
    return document.querySelector('article') as HTMLElement;
};

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));
/** Bootstrap Modal : ouverture terminée, focus piégé. */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

describe('XmPost', () => {
    it('rend l’en-tête, l’étiquette aux couleurs du prototype, le corps et les actions de la carte', async () => {
        const card = await mount(makePost({ tag: 'question', tag_label: 'Question', tag_color: 'warning' }));
        expect(screen.getByRole('heading', { level: 3, name: 'Awa_S' })).toBeTruthy();
        expect(card.querySelector('.badge')?.className).toContain('bg-warning bg-opacity-10 text-warning');
        expect(card.querySelector('time')?.textContent).toBe('il y a 5 min');
        expect(card.querySelector('.xm-post-body')?.textContent).toBe('Merci à toutes pour vos messages.\nÇa fait du bien.');
        expect(screen.getByRole('button', { name: /J’aime \(3\)/ }).getAttribute('aria-pressed')).toBe('false');
        expect(screen.getByRole('link', { name: /Commentaires \(1\)/ }).getAttribute('href')).toBe('http://localhost/fil/01JC0000000000000000000001#commentaires');
        expect(screen.getByLabelText('Ton commentaire')).toBeTruthy();
    });

    it('n’identifie jamais l’auteur·rice d’une publication anonyme, même pour elle-même', async () => {
        await mount(makePost({ is_mine: true, is_anonymous: true, author: { id: null, pseudo: 'Membre anonyme', avatar_url: '/assets/images/personnage-1.png', role: null, anonymous: true } }));
        const heading = screen.getByRole('heading', { level: 3 });
        expect(heading.textContent?.trim()).toBe('Membre anonyme');
        expect(heading.querySelector('a')).toBeNull();
        expect(screen.queryByRole('button', { name: 'Masquer' })).toBeNull();
    });

    it('aime de façon optimiste puis reprend le compteur du serveur', async () => {
        let release: (response: Response) => void = () => undefined;
        const { calls } = fakeFetch(() => new Promise<Response>((resolve) => { release = resolve; }));
        await mount(makePost());
        await fireEvent.click(screen.getByRole('button', { name: /J’aime/ }));
        const button = screen.getByRole('button', { name: /J’aime/ });
        expect(button.textContent).toContain('(4)');
        expect(button.getAttribute('aria-pressed')).toBe('true');
        expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/v1/posts/01JC0000000000000000000001/like' });
        release(json({ data: { liked: true, likes_count: 7, changed: true } }));
        await waitFor(() => expect(screen.getByRole('button', { name: /J’aime/ }).textContent).toContain('(7)'));
    });

    it('ignore un second clic sur « J’aime » tant que le premier envoi n’est pas revenu', async () => {
        let release: (response: Response) => void = () => undefined;
        const { calls } = fakeFetch(() => new Promise<Response>((resolve) => { release = resolve; }));
        await mount(makePost());
        await fireEvent.click(screen.getByRole('button', { name: /J’aime/ }));
        await fireEvent.click(screen.getByRole('button', { name: /J’aime/ }));
        expect(calls).toHaveLength(1);
        release(json({ data: { liked: true, likes_count: 4, changed: true } }));
        await waitFor(() => expect(screen.getByRole('button', { name: /J’aime/ }).textContent).toContain('(4)'));
        expect(screen.getByRole('button', { name: /J’aime/ }).getAttribute('aria-pressed')).toBe('true');
        await flush();
        await fireEvent.click(screen.getByRole('button', { name: /J’aime/ }));
        await waitFor(() => expect(calls.map((call) => call.method)).toEqual(['POST', 'DELETE']));
    });

    it('annule le j’aime si l’envoi échoue', async () => {
        fakeFetch(() => Promise.reject(new TypeError('Failed to fetch')));
        await mount(makePost({ liked: true, likes_count: 3 }));
        await fireEvent.click(screen.getByRole('button', { name: /J’aime/ }));
        await flush();
        await waitFor(() => expect(screen.getByRole('button', { name: /J’aime/ }).getAttribute('aria-pressed')).toBe('true'));
        expect(screen.getByRole('button', { name: /J’aime/ }).textContent).toContain('(3)');
    });

    it('partage le lien neutre /p/{ulid} vers WhatsApp, Telegram, Facebook, et le copie', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        await mount(makePost());
        const encoded = encodeURIComponent('http://localhost/p/01JC0000000000000000000001');
        expect(screen.getByRole('link', { name: /WhatsApp/ }).getAttribute('href')).toBe(`https://wa.me/?text=${encoded}`);
        expect(screen.getByRole('link', { name: /Telegram/ }).getAttribute('href')).toBe(`https://t.me/share/url?url=${encoded}`);
        expect(screen.getByRole('link', { name: /Facebook/ }).getAttribute('href')).toBe(`https://www.facebook.com/sharer/sharer.php?u=${encoded}`);
        await fireEvent.click(screen.getByRole('button', { name: 'Copier le lien' }));
        expect(writeText).toHaveBeenCalledWith('http://localhost/p/01JC0000000000000000000001');
    });

    it('enregistre, puis retire des enregistrées (émis pour la page /fil/enregistres)', async () => {
        const { calls } = fakeFetch((call) => json({ data: { saved: call.method === 'POST', changed: true } }));
        await mount(makePost());
        await fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));
        await waitFor(() => expect(screen.getByRole('button', { name: 'Retirer des enregistrés' })).toBeTruthy());
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer des enregistrés' }));
        await waitFor(() => expect(events.find(([name]) => name === 'removed')?.[1]).toEqual(['01JC0000000000000000000001', 'unsaved']));
        expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual([
            'POST /api/v1/posts/01JC0000000000000000000001/save',
            'DELETE /api/v1/posts/01JC0000000000000000000001/save',
        ]);
    });

    it('masque la publication du fil de la personne, avec « Annuler »', async () => {
        const { calls } = fakeFetch((call) => json({ data: { hidden: call.method === 'POST', changed: true } }));
        await mount(makePost());
        await fireEvent.click(screen.getByRole('button', { name: 'Masquer' }));
        expect(await screen.findByText('Publication masquée. Elle ne réapparaîtra pas dans ton fil.')).toBeTruthy();
        expect(document.querySelector('article')).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        await waitFor(() => expect(document.querySelector('article')).not.toBeNull());
        expect(calls.map((call) => call.method)).toEqual(['POST', 'DELETE']);
    });

    it('n’offre ni signalement ni masquage sur sa propre publication', async () => {
        await mount(makePost({ is_mine: true, can: { update: true, delete: true, report: false, comment: true } }));
        expect(screen.queryByRole('button', { name: 'Signaler cette publication' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Masquer' })).toBeNull();
        expect(screen.getByRole('button', { name: 'Supprimer' })).toBeTruthy();
    });

    it('supprime sa publication après confirmation dans la modale « Supprimer définitivement ? »', async () => {
        const confirm = vi.spyOn(window, 'confirm');
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mount(makePost({ is_mine: true, can: { update: true, delete: true, report: false, comment: true } }));
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));
        await shown();
        expect(document.getElementById('modalDeletePost-01JC0000000000000000000001Title')?.textContent).toMatch(/^Supprimer définitivement\s\?$/);
        expect(screen.getByText('Supprimer cette publication ? Ses commentaires seront aussi supprimés.')).toBeTruthy();
        expect(calls).toHaveLength(0);
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }));
        await waitFor(() => expect(events.find(([name]) => name === 'removed')?.[1]).toEqual(['01JC0000000000000000000001', 'deleted']));
        expect(calls[0]).toMatchObject({ method: 'DELETE', url: '/api/v1/posts/01JC0000000000000000000001' });
        expect(confirm).not.toHaveBeenCalled();
    });

    it('garde la publication si la suppression est annulée', async () => {
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mount(makePost({ is_mine: true, can: { update: true, delete: true, report: false, comment: true } }));
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));
        await shown();
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        await waitFor(() => expect(document.querySelector('[id^="modalDeletePost-"]')).toBeNull());
        expect(calls).toHaveLength(0);
        expect(events.find(([name]) => name === 'removed')).toBeUndefined();
    });

    it('ouvre la modale « Signaler cette publication » chargée à la demande', async () => {
        await mount(makePost());
        await fireEvent.click(screen.getByRole('button', { name: 'Signaler cette publication' }));
        await waitFor(() => expect(document.getElementById('modalReportContent')).not.toBeNull());
        await shown();
        expect(document.getElementById('modalReportContentTitle')?.textContent).toBe('Signaler cette publication');
    });

    it('commente depuis la carte : compteur repris du serveur, champ vidé', async () => {
        const { calls } = fakeFetch(() => json({ data: { id: 9, body: 'Courage à toi', parent_id: null }, meta: { comments_count: 2 } }, 201));
        await mount(makePost());
        const field = screen.getByLabelText('Ton commentaire') as HTMLTextAreaElement;
        await fireEvent.update(field, 'Courage à toi');
        await fireEvent.submit(field.form as HTMLFormElement);
        await waitFor(() => expect(screen.getByRole('link', { name: /Commentaires \(2\)/ })).toBeTruthy());
        expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/v1/posts/01JC0000000000000000000001/comments', body: { body: 'Courage à toi' } });
        expect(field.value).toBe('');
    });

    it('rend le sondage de la publication et l’image avec son texte alternatif', async () => {
        await mount(makePost({ tag: 'sondage', poll: makePoll(), media: [{ kind: 'image', url: 'http://localhost/media/x.webp', alt: null, width: 1600, height: 1200 }] }));
        await nextTick();
        expect(document.querySelector('.xm-poll')).not.toBeNull();
        expect(screen.getByRole('heading', { level: 4, name: 'Quel thème pour la causerie ?' })).toBeTruthy();
        const image = document.querySelector('img.card-img') as HTMLImageElement;
        expect(image.alt).toBe('Illustration de la publication de Awa_S');
        expect(image.getAttribute('width')).toBe('1600');
    });
});
