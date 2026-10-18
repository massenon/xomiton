// « Suivre la page » / « Ne plus suivre » (lot groupes-pages, P8-03 ; prototype pages.php l.8-13) : geste idempotent
// (`POST · DELETE pages/{ulid}/follow`), compteur repris du serveur, un geste à la fois par page, toasts de
// lang/fr/community.php ; 403 / 404 / réseau en toast (429, 419, 5xx déjà annoncés par useApi).
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { communityApi } from './api';
import type { OrganizationPage } from './types';

export function usePageFollow(onUpdated: (page: OrganizationPage) => void) {
    const { t } = useI18n();
    const toast = useToast();
    const api = communityApi(useApi());
    const busy = ref<string | null>(null);

    async function toggle(page: OrganizationPage) {
        if (busy.value) return;
        busy.value = page.id;
        const on = !page.following;
        try {
            const state = await api.follow(page.id, on);
            onUpdated({ ...page, following: state.following, followers_count: state.followers_count });
            toast.show(state.following ? t('community.pages.toasts.followed') : t('community.pages.toasts.unfollowed'), 'success');
        } catch (cause) {
            if (!(cause instanceof ApiError && (cause.status === 429 || cause.status === 419 || cause.status >= 500))) {
                toast.show(cause instanceof ApiError ? cause.message : t('microcopy.error.generic'), 'warning');
            }
        } finally {
            busy.value = null;
        }
    }

    return { busy, toggle };
}
