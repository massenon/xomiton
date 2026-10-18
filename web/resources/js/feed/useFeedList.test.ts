// @vitest-environment jsdom
// Liste du fil par curseur (lot fil-ecrans, P7-03) : une réponse de « Charger plus » arrivée après un changement de
// filtre (reset() par la visite partielle Inertia) est ignorée — ni publications ni curseur de l'ancien filtre.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { renderXm } from '@/test/helpers';
import { fakeFetch, json, makePost } from '@/test/feed';
import { useFeedList } from './useFeedList';

afterEach(() => {
    vi.unstubAllGlobals();
});

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('useFeedList', () => {
    it('ignore la suite d’un filtre quitté pendant le chargement', async () => {
        let release: (response: Response) => void = () => undefined;
        const { calls } = fakeFetch(() => new Promise<Response>((resolve) => { release = resolve; }));
        let list: ReturnType<typeof useFeedList> | null = null;
        let tag: string | null = null;
        renderXm(defineComponent({
            setup() {
                list = useFeedList('feed', { data: [makePost({ id: 'A1' })], meta: { next_cursor: 'cA' } }, () => tag);
                return () => h('div');
            },
        }));
        const feed = list as unknown as ReturnType<typeof useFeedList>;

        const pending = feed.loadMore();
        expect(calls[0].url).toBe('/api/v1/feed?cursor=cA');
        tag = 'question';
        feed.reset({ data: [makePost({ id: 'Q1', tag: 'question' })], meta: { next_cursor: null } });
        release(json({ data: [makePost({ id: 'A2' })], meta: { next_cursor: 'cA2' } }));
        await pending;
        await tick();

        expect(feed.items.value.map((post) => post.id)).toEqual(['Q1']);
        expect(feed.cursor.value).toBeNull();
        expect(feed.loading.value).toBe(false);
        expect(feed.announce.value).toBe('');
    });
});
