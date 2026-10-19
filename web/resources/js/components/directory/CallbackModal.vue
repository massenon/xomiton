<script setup lang="ts">
// Modale « Demander à être rappelé·e » de l'annuaire (lot annuaire-membre, P8-12 ; prototype annuaire.php l.122-140),
// chargée à la demande par la page /annuaire : rappel par la personne choisie, transmis comme un « besoin » à elle
// seule (RequestCallback, P5-02 : sans RG-04). Moyen de contact obligatoire (téléphone ou e-mail), message facultatif
// (`limits.message_max`), « Ne pas transmettre mon pseudo » coché par défaut. Envoi `POST directory/{id}/callback` avec
// une Idempotency-Key gardée tant que la demande ne change pas : un nouvel essai après une coupure ne crée pas de
// doublon (ADR-32). 422 → erreurs par champ (résumé focalisé) ou refus métier (`contact_unavailable`) ; réseau absent →
// invitation à réessayer ou à appeler. Succès → émet `sent` (code de suivi), la page ferme et annonce.
// L'envoi est fourni par la page (`send`, directory/api.ts) : la modale n'importe rien du morceau de la page, qui reste
// une entrée à part dans le manifest (mesure des budgets, scripts/check-budgets.mjs).
// Props : contact, limits, send. Émet `sent`, `hidden`.
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors } from '@/app/useApi';
import type { CallbackPayload, CallbackResult, DirectoryContact, Limits } from '@/directory/types';
import { uuidV4 } from '@/messaging/uuid';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = defineProps<{
    contact: DirectoryContact;
    limits: Limits;
    send: (payload: CallbackPayload, idempotencyKey: string) => Promise<CallbackResult>;
}>();
const emit = defineEmits<{ sent: [result: CallbackResult, contact: DirectoryContact]; hidden: [] }>();

const { t } = useI18n();
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const form = reactive({ contact: '', message: '', anonymous: true });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);
let attempt: { key: string; fingerprint: string } | null = null;

const dirty = computed(() => form.contact.trim() !== '' || form.message.trim() !== '');

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    if (form.contact.trim() === '') found.contact = t('directory.callback.contact_required');
    else if (form.contact.trim().length > props.limits.contact_max) found.contact = t('directory.callback.message_max', { max: props.limits.contact_max });
    if (form.message.trim().length > props.limits.message_max) found.message = t('directory.callback.message_max', { max: props.limits.message_max });
    return found;
}

/** Même demande → même clé (rejeu sans doublon) ; demande modifiée → nouvelle clé. */
function keyFor(fingerprint: string): string {
    if (attempt === null || attempt.fingerprint !== fingerprint) attempt = { key: uuidV4(), fingerprint };
    return attempt.key;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#modalCallback .xm-error-summary, #modalCallback [aria-invalid="true"]')?.focus();
}

async function onSubmit() {
    if (busy.value) return;
    failure.value = '';
    errors.value = validate();
    if (Object.keys(errors.value).length > 0) {
        await focusError();
        return;
    }
    const payload = { contact: form.contact.trim(), message: form.message.trim() || null, anonymous: form.anonymous };
    busy.value = true;
    try {
        const result = await props.send(payload, keyFor(JSON.stringify([props.contact.id, payload])));
        emit('sent', result, props.contact);
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
            if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            await focusError();
        } else if (cause instanceof ApiError && cause.status === 0) {
            failure.value = t('directory.callback.offline');
        } else {
            failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <XmModal ref="modal" id="modalCallback" :title="t('alerts.callback.title')" type="form" form :open="true" :action="t('directory.callback.submit')" :busy="busy" :dirty="dirty" @submit="onSubmit" @hidden="emit('hidden')">
        <p class="small">
            {{ t('directory.callback.who', { name: contact.name, role: contact.role_label }) }}
            {{ t('directory.callback.only_them') }}
        </p>
        <XmErrorSummary :messages="errors" :title="t('directory.callback.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
        <XmField v-model="form.contact" name="contact" :label="t('alerts.callback.contact')" :hint="t('directory.callback.contact_help')" :placeholder="t('directory.callback.placeholder')" autocomplete="tel" :maxlength="limits.contact_max" :error="errors.contact" required />
        <XmField v-model="form.message" name="message" type="textarea" optional :rows="3" :label="t('alerts.callback.message')" :hint="t('alerts.callback.message_help', { max: limits.message_max })" :placeholder="t('directory.callback.message_placeholder')" :maxlength="limits.message_max" :error="errors.message" />
        <XmField v-model="form.anonymous" name="anonymous" type="switch" :label="t('directory.callback.anonymous')" :hint="t('directory.callback.anonymous_help')" :error="errors.anonymous" />
    </XmModal>
</template>
