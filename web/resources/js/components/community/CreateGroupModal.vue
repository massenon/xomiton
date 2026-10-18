<script setup lang="ts">
// Modale « Créer un groupe » (lot groupes-pages, P8-02 ; prototype groups.php l.111-127), chargée à la demande par la
// page /groupes (aucun import statique depuis app.ts) : nom (3–80, obligatoire), description (≤ 300), confidentialité
// (Privé par défaut, comme le prototype ; aide sous le champ), zone (National ou un département). Envoi `POST groups` ;
// 422 → erreurs par champ, résumé focalisé ; succès → émet `created` (la page ouvre le groupe).
// Props : options (create de GroupsPageController::index). Émet `created`, `hidden`.
import { computed, nextTick, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { communityApi } from '@/community/api';
import type { Group, GroupCreateOptions } from '@/community/types';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = defineProps<{ options: GroupCreateOptions }>();
const emit = defineEmits<{ created: [group: Group]; hidden: [] }>();

const { t } = useI18n();
const api = communityApi(useApi());
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const form = reactive({ name: '', description: '', privacy: props.options.privacies[0]?.value ?? 'prive', zone_id: '' });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);

const privacies = computed(() => Object.fromEntries(props.options.privacies.map((option) => [option.value, option.label])));
// Clés non numériques (`z<id>`) : un objet JS range les clés entières avant les autres, l'ordre des départements serait perdu.
const zones = computed(() => ({ '': t('community.groups.create.zone_national'), ...Object.fromEntries(props.options.zones.map((zone) => [`z${zone.id}`, zone.name])) }));
const privacyHint = computed(() => (form.privacy === 'public' ? t('community.groups.create.privacy_public_help') : t('community.groups.create.privacy_private_help')));
const dirty = computed(() => form.name.trim() !== '' || form.description.trim() !== '');

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    const name = form.name.trim();
    if (name.length < props.options.limits.name_min) found.name = t('community.groups.create.name_min', { min: props.options.limits.name_min });
    else if (name.length > props.options.limits.name_max) found.name = t('feed.create.text_max', { max: props.options.limits.name_max });
    if (form.description.trim().length > props.options.limits.description_max) found.description = t('feed.create.text_max', { max: props.options.limits.description_max });
    return found;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#modalCreateGroup .xm-error-summary, #modalCreateGroup [aria-invalid="true"]')?.focus();
}

async function onSubmit() {
    if (busy.value) return;
    failure.value = '';
    errors.value = validate();
    if (Object.keys(errors.value).length > 0) {
        await focusError();
        return;
    }
    busy.value = true;
    try {
        const group = await api.createGroup({
            name: form.name.trim(),
            description: form.description.trim() || null,
            privacy: form.privacy,
            zone_id: form.zone_id === '' ? null : Number(form.zone_id.slice(1)),
        });
        emit('created', group);
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
            if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            await focusError();
        } else {
            failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
        }
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <XmModal ref="modal" id="modalCreateGroup" :title="t('community.groups.create.title')" type="form" form :open="true" :action="t('community.groups.create.submit')" :busy="busy" :dirty="dirty" @submit="onSubmit" @hidden="emit('hidden')">
        <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
        <XmField v-model="form.name" name="name" :label="t('community.groups.create.name')" :placeholder="t('community.groups.create.name_placeholder')" :maxlength="options.limits.name_max" :error="errors.name" required :minlength="options.limits.name_min" />
        <XmField v-model="form.description" name="description" type="textarea" optional :rows="3" :label="t('community.groups.create.description')" :placeholder="t('community.groups.create.description_placeholder')" :maxlength="options.limits.description_max" :error="errors.description" />
        <div class="row g-3">
            <div class="col-sm-6">
                <XmField v-model="form.privacy" name="privacy" type="select" :label="t('community.groups.create.privacy')" :options="privacies" :hint="privacyHint" :error="errors.privacy" />
            </div>
            <div class="col-sm-6">
                <XmField v-model="form.zone_id" name="zone_id" type="select" :label="t('community.groups.create.zone')" :options="zones" :error="errors.zone_id" />
            </div>
        </div>
    </XmModal>
</template>
