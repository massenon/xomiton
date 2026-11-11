<script setup lang="ts">
// Modale « Parler à un humain » de la page /assistant (P8-11), chargée à la demande : demande « besoin » routée
// (`POST /api/v1/assistant/escalate`, EscalateToHuman : RG-04 depuis le département du compte, lue sous 24 h). Moyen de
// contact obligatoire, moyen préféré et message facultatifs, pseudonyme non transmis par défaut ; le contenu de la
// conversation n'est jamais copié, et une conversation enregistrée n'est reliée à la demande (marquée pour relecture)
// que si le pseudonyme est transmis : une demande anonyme reste sans lien avec le compte. Idempotency-Key gardée tant
// que la demande ne change pas (ADR-32 : un nouvel essai après une coupure ne crée pas de doublon).
// 422 → erreurs par champ (résumé focalisé) ; tout autre échec (hors connexion, quota 429, 5xx) → message, puis lien
// d'appel du premier numéro d'urgence et annuaire (chemin de crise : jamais un échec sans issue).
// Props : channels, messageMax, conversationId, offlineText, call, directoryUrl. Émet `sent`, `hidden`.
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { uuidV4 } from '@/messaging/uuid';
import type { EscalationResult } from '@/assistant/types';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{
    channels: Record<string, string>;
    messageMax: number;
    conversationId?: number | null;
    offlineText: string;
    call?: { label: string; url: string } | null;
    directoryUrl: string;
}>(), { conversationId: null, call: null });
const emit = defineEmits<{ sent: [result: EscalationResult]; hidden: [] }>();

const CONTACT_MAX = 190;
// Adresse gardée ici, pas dans assistant/types.ts : importer une valeur du morceau de la page lui ferait perdre son
// entrée dans le manifest de Vite (budget par page de scripts/check-budgets.mjs).
const ESCALATE_ENDPOINT = '/api/v1/assistant/escalate';

const { t } = useI18n();
const api = useApi();
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const form = reactive({ contact: '', preferred_channel: '', message: '', share_pseudo: false });
const errors = ref<Record<string, string>>({});
const failure = ref('');
// Échec autre qu'une erreur de saisie : la personne reçoit aussi le numéro d'urgence et l'annuaire.
const failureHelp = ref(false);
const busy = ref(false);
let attempt: { key: string; fingerprint: string } | null = null;

const dirty = computed(() => form.contact.trim() !== '' || form.message.trim() !== '');
// La conversation enregistrée n'accompagne qu'une demande signée du pseudonyme (sinon l'anonymat serait levé).
const linked = computed(() => props.conversationId !== null && form.share_pseudo);
const conversationNote = computed(() => {
    if (props.conversationId === null) return t('assistant.escalation.not_shared');
    return linked.value ? t('assistant.escalation.shared') : t('assistant.escalation.shared_needs_pseudo');
});
const channelOptions = computed(() => ({ '': t('assistant.escalation.channel_any'), ...props.channels }));

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    if (form.contact.trim() === '') found.contact = t('assistant.escalation.contact_required');
    else if (form.contact.trim().length > CONTACT_MAX) found.contact = t('assistant.escalation.too_long', { max: CONTACT_MAX });
    if (form.message.trim().length > props.messageMax) found.message = t('assistant.escalation.too_long', { max: props.messageMax });
    return found;
}

/** Même demande → même clé (rejeu sans doublon) ; demande modifiée → nouvelle clé. */
function keyFor(fingerprint: string): string {
    if (attempt === null || attempt.fingerprint !== fingerprint) attempt = { key: uuidV4(), fingerprint };
    return attempt.key;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#modalEscalation .xm-error-summary, #modalEscalation [aria-invalid="true"]')?.focus();
}

async function onSubmit() {
    if (busy.value) return;
    failure.value = '';
    failureHelp.value = false;
    errors.value = validate();
    if (Object.keys(errors.value).length > 0) {
        await focusError();
        return;
    }
    const payload = {
        contact: form.contact.trim(),
        preferred_channel: form.preferred_channel || null,
        message: form.message.trim() || null,
        anonymous: !form.share_pseudo,
        conversation_id: linked.value ? props.conversationId : null,
    };
    busy.value = true;
    try {
        const { data } = await api.post<{ data: EscalationResult }>(ESCALATE_ENDPOINT, payload, { idempotencyKey: keyFor(JSON.stringify(payload)), silent: true });
        emit('sent', data.data);
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
            if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            await focusError();
        } else if (cause instanceof ApiError && cause.status === 0) {
            failure.value = props.offlineText;
            failureHelp.value = true;
        } else {
            const message = cause instanceof ApiError && cause.message ? cause.message : t('microcopy.error.generic');
            failure.value = `${message} ${t('assistant.escalation.failed_help')}`;
            failureHelp.value = true;
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <XmModal ref="modal" id="modalEscalation" :title="t('assistant.escalation.title')" type="form" form :open="true" :action="t('assistant.escalation.submit')" :busy="busy" :dirty="dirty" @submit="onSubmit" @hidden="emit('hidden')">
        <p class="small">{{ t('assistant.escalation.intro') }}</p>
        <XmErrorSummary :messages="errors" :title="t('assistant.escalation.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small" data-xm-escalation-failure>
            <p class="mb-0">{{ failure }}</p>
            <div v-if="failureHelp" class="mt-2 d-flex flex-wrap gap-1">
                <a v-if="call" class="btn btn-sm btn-danger" :href="call.url">{{ call.label }}</a>
                <a class="btn btn-sm btn-primary-soft" :href="directoryUrl">{{ t('assistant.escalation.directory') }}</a>
            </div>
        </XmAlert>
        <!-- Téléphone ou e-mail : aucune saisie automatique typée (WCAG 1.3.5), le navigateur ne propose que ce qu'il connaît du champ. -->
        <XmField v-model="form.contact" name="contact" :label="t('assistant.escalation.contact')" :hint="t('assistant.escalation.contact_help')" :maxlength="CONTACT_MAX" :error="errors.contact" required />
        <XmField v-model="form.preferred_channel" name="preferred_channel" type="select" optional :label="t('assistant.escalation.channel')" :options="channelOptions" :error="errors.preferred_channel" />
        <XmField v-model="form.message" name="message" type="textarea" optional :rows="3" :label="t('assistant.escalation.message')" :hint="t('assistant.escalation.message_help', { max: messageMax })" :maxlength="messageMax" :error="errors.message" />
        <XmField v-model="form.share_pseudo" name="share_pseudo" type="switch" :label="t('assistant.escalation.share_pseudo')" :hint="t('assistant.escalation.share_pseudo_help')" :error="errors.anonymous" />
        <p class="small text-secondary mb-2" data-xm-conversation-note><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ conversationNote }}</p>
        <a class="small" :href="directoryUrl">{{ t('assistant.escalation.directory') }}</a>
    </XmModal>
</template>
