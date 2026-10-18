// Liste de publications par curseur (lot fil-ecrans, P7-03, P7-06) pour /fil et /fil/enregistres : première page
// reçue en props Inertia (même forme que l'API), suite chargée à la demande (« Charger plus ») par `GET feed` /
// `feed/saved` (?cursor=, ?tag=), sans doublon ; nombre de publications ajoutées annoncé (région polite) ; retrait
// (supprimée, retirée des enregistrées), mise à jour d'une carte et ajout en tête (publication créée). Une réponse
// arrivée après un changement de liste (filtre : `reset()`) est ignorée : jamais d'ajout ni de curseur d'un autre filtre.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { feedApi } from './api';
import type { CursorPage, Post } from './types';

export function useFeedList(kind: 'feed' | 'saved', initial: CursorPage<Post>, tag: () => string | null = () => null) {
    const { t } = useI18n();
    const toast = useToast();
    const api = feedApi(useApi());

    const items = ref<Post[]>([...initial.data]);
    const cursor = ref<string | null>(initial.meta.next_cursor ?? null);
    const loading = ref(false);
    const pages = ref(1);
    const announce = ref('');
    /** Génération de la liste : incrémentée par reset(), une réponse d'une génération passée est écartée. */
    let generation = 0;

    const hasMore = computed(() => cursor.value !== null);
    const finished = computed(() => !hasMore.value && pages.value > 1);

    function reset(page: CursorPage<Post>) {
        generation += 1;
        loading.value = false;
        items.value = [...page.data];
        cursor.value = page.meta.next_cursor ?? null;
        pages.value = 1;
        announce.value = '';
    }

    async function loadMore() {
        if (loading.value || cursor.value === null) return;
        loading.value = true;
        const current = generation;
        try {
            const page = await api.list(kind, { cursor: cursor.value, tag: tag() });
            if (current !== generation) return;
            const known = new Set(items.value.map((post) => post.id));
            const fresh = page.data.filter((post) => !known.has(post.id));
            items.value = [...items.value, ...fresh];
            cursor.value = page.meta.next_cursor ?? null;
            pages.value += 1;
            announce.value = t('feed.list.loaded', { count: fresh.length }, fresh.length);
        } catch (cause) {
            if (current === generation && cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
        } finally {
            if (current === generation) loading.value = false;
        }
    }

    const remove = (id: string) => { items.value = items.value.filter((post) => post.id !== id); };
    const replace = (post: Post) => { items.value = items.value.map((item) => (item.id === post.id ? post : item)); };
    const prepend = (post: Post) => { items.value = [post, ...items.value.filter((item) => item.id !== post.id)]; };

    return { items, cursor, loading, pages, announce, hasMore, finished, reset, loadMore, remove, replace, prepend };
}
