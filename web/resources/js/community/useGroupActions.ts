// Gestes sur un groupe depuis les écrans Vue (lot groupes-pages, P8-02) : rejoindre (public → membre, privé →
// demande), annuler sa demande, quitter (confirmation par la page), ouvrir la discussion du groupe (conversation P8-01,
// visite Inertia vers /messages?c=). Un seul geste à la fois par groupe (`busy`) ; toasts de lang/fr/community.php ;
// erreurs : 422 / 403 / 404 / réseau en toast (429, 419 et 5xx sont déjà annoncés par useApi).
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { communityApi } from './api';
import type { Group } from './types';

const ANNOUNCED_BY_USE_API = new Set([419, 429]);

export function useGroupActions(onUpdated: (group: Group) => void) {
    const { t } = useI18n();
    const toast = useToast();
    const api = communityApi(useApi());
    const busy = ref<string | null>(null);

    function report(cause: unknown) {
        if (cause instanceof ApiError) {
            if (ANNOUNCED_BY_USE_API.has(cause.status) || cause.status >= 500) return;
            toast.show(cause.message, 'warning');
            return;
        }
        toast.show(t('microcopy.error.generic'), 'danger');
    }

    async function run(group: Group, action: () => Promise<Group>, message: (updated: Group) => string): Promise<Group | null> {
        if (busy.value) return null;
        busy.value = group.id;
        try {
            const updated = await action();
            onUpdated(updated);
            toast.show(message(updated), 'success');
            return updated;
        } catch (cause) {
            report(cause);
            return null;
        } finally {
            busy.value = null;
        }
    }

    const join = (group: Group) => run(group, () => api.join(group.id), (updated) => (updated.membership?.status === 'demande'
        ? t('community.groups.toasts.requested')
        : t('community.groups.toasts.joined')));

    const cancelRequest = (group: Group) => run(group, () => api.leave(group.id), () => t('community.groups.toasts.request_cancelled'));

    const leave = (group: Group) => run(group, () => api.leave(group.id), () => t('community.groups.toasts.left'));

    async function discuss(group: Group) {
        if (busy.value) return;
        busy.value = group.id;
        try {
            const { url } = await api.discussion(group.id);
            router.visit(url);
        } catch (cause) {
            report(cause);
        } finally {
            busy.value = null;
        }
    }

    return { busy, join, cancelRequest, leave, discuss, report };
}
