// « Je participe » / annuler depuis les écrans Vue (lot evenements, P8-04) : un seul geste à la fois par événement
// (`busy`), carte ou fiche mise à jour avec la réponse de l'API (qui livre alors l'adresse exacte et le lien), toasts de
// lang/fr/community.php (`community.events.toasts` ; « un rappel t'est envoyé la veille » seulement s'il peut encore
// partir, `reminder_available`) ; 422 (événement terminé), 403 / 404 et réseau en toast (419, 429 et 5xx sont déjà
// annoncés par useApi).
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { eventsApi } from './api';
import type { EventItem } from './types';

const ANNOUNCED_BY_USE_API = new Set([419, 429]);

export function useRsvp(onUpdated: (event: EventItem) => void) {
    const { t } = useI18n();
    const toast = useToast();
    const api = eventsApi(useApi());
    const busy = ref<string | null>(null);

    function report(cause: unknown) {
        if (cause instanceof ApiError) {
            if (ANNOUNCED_BY_USE_API.has(cause.status) || cause.status >= 500) return;
            toast.show(cause.status === 0 ? t('microcopy.error.unreachable') : cause.message, 'warning');
            return;
        }
        toast.show(t('microcopy.error.generic'), 'danger');
    }

    async function respond(event: EventItem, going: boolean, reminder = true): Promise<EventItem | null> {
        if (busy.value) return null;
        busy.value = event.id;
        try {
            const updated = await api.rsvp(event.id, going, reminder);
            onUpdated(updated);
            return updated;
        } catch (cause) {
            report(cause);
            return null;
        } finally {
            busy.value = null;
        }
    }

    async function toggle(event: EventItem): Promise<EventItem | null> {
        const going = event.rsvp === null;
        const updated = await respond(event, going);
        if (updated) toast.show(going ? t(updated.reminder_available && updated.rsvp?.reminder ? 'community.events.toasts.attending' : 'community.events.toasts.attending_no_reminder') : t('community.events.toasts.declined'), 'success');
        return updated;
    }

    async function setReminder(event: EventItem, reminder: boolean): Promise<EventItem | null> {
        const updated = await respond(event, true, reminder);
        if (updated) toast.show(reminder ? t('community.events.toasts.reminder_on') : t('community.events.toasts.reminder_off'), 'success');
        return updated;
    }

    return { busy, toggle, setReminder, report };
}
