// Gestes de « Qui suivre et invitations » (lot qui-suivre, P8-06 ; prototype who-to-follow.php l.14-46) : inviter,
// suivre / ne plus suivre, accepter, refuser, annuler. Un geste à la fois **par élément** (`busy` = clés des gestes en
// cours, ex. `invite:7`, `accept:3` : deux cartes avancent en parallèle ; accepter et refuser une même invitation
// s'excluent, portée `invitation:<id>`), toasts de
// lang/fr/community.php (`follow.toasts`), compteurs des onglets repris du serveur (`meta.counts`). Refus : 422 et 403
// avec le message de l'API en toast ; 404 (invitation annulée, tranchée ou bloquée entre-temps) → `gone` (l'écran
// retire l'élément) ; réseau coupé en toast ; 419, 429 et 5xx déjà annoncés par useApi.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { networkApi, type InvitationResult } from './api';
import type { Counts, FollowState, Invitation, NetworkPerson, Suggestion } from './types';

export type Outcome<T> = { ok: true; value: T } | { ok: false; gone: boolean };

export function useNetworkActions(onCounts: (counts: Counts) => void) {
    const { t } = useI18n();
    const toast = useToast();
    const api = networkApi(useApi());
    /** Portée de l'élément → clé du geste en cours. */
    const pending = ref<Record<string, string>>({});
    const busy = computed<string[]>(() => Object.values(pending.value));

    function report(cause: unknown): boolean {
        if (cause instanceof ApiError) {
            if (cause.status === 404) {
                toast.show(t('community.follow.errors.not_pending'), 'warning');
                return true;
            }
            if (cause.status === 419 || cause.status === 429 || cause.status >= 500) return false;
            toast.show(cause.status === 0 ? t('microcopy.error.unreachable') : cause.message, 'warning');
            return false;
        }
        toast.show(t('microcopy.error.generic'), 'warning');
        return false;
    }

    async function run<T>(key: string, action: () => Promise<T>, scope: string = key): Promise<Outcome<T>> {
        if (pending.value[scope]) return { ok: false, gone: false };
        pending.value = { ...pending.value, [scope]: key };
        try {
            return { ok: true, value: await action() };
        } catch (cause) {
            return { ok: false, gone: report(cause) };
        } finally {
            const rest = { ...pending.value };
            delete rest[scope];
            pending.value = rest;
        }
    }

    const counted = (result: InvitationResult): InvitationResult => {
        if (result.counts) onCounts(result.counts);
        return result;
    };

    async function invite(person: NetworkPerson): Promise<Outcome<InvitationResult>> {
        const outcome = await run(`invite:${person.id}`, async () => counted(await api.invite(person.id)));
        if (outcome.ok) {
            toast.show(outcome.value.created ? t('community.follow.toasts.invited', { name: person.pseudo }) : t('community.follow.toasts.already_invited', { name: person.pseudo }), 'success');
        }
        return outcome;
    }

    async function follow(person: Suggestion): Promise<Outcome<FollowState>> {
        const outcome = await run(`follow:${person.id}`, () => api.follow(person.id, !person.following));
        if (outcome.ok) {
            toast.show(outcome.value.following ? t('community.follow.toasts.followed', { name: person.pseudo }) : t('community.follow.toasts.unfollowed', { name: person.pseudo }), 'success');
        }
        return outcome;
    }

    async function accept(invitation: Invitation): Promise<Outcome<InvitationResult>> {
        const outcome = await run(`accept:${invitation.id}`, async () => counted(await api.accept(invitation.id)), `invitation:${invitation.id}`);
        if (outcome.ok) toast.show(t('community.follow.toasts.accepted'), 'success');
        return outcome;
    }

    async function refuse(invitation: Invitation): Promise<Outcome<InvitationResult>> {
        const outcome = await run(`refuse:${invitation.id}`, async () => counted(await api.refuse(invitation.id)), `invitation:${invitation.id}`);
        if (outcome.ok) toast.show(t('community.follow.toasts.declined'), 'success');
        return outcome;
    }

    async function cancel(invitation: Invitation): Promise<Outcome<void>> {
        const outcome = await run(`cancel:${invitation.id}`, () => api.cancel(invitation.id), `invitation:${invitation.id}`);
        if (outcome.ok) toast.show(t('community.follow.toasts.cancelled'), 'success');
        return outcome;
    }

    return { busy, invite, follow, accept, refuse, cancel };
}
