<script setup lang="ts">
// Section « Confidentialité » de /parametres (P8-09, prototype settings.php `action=privacy`) : qui voit mon profil,
// afficher ma zone, apparaître dans « Qui suivre », consentement au partage GPS lors d'un SOS, consentement aux données
// sensibles (aussi simple à retirer qu'à donner : le retirer efface identité de genre et orientation). PATCH me/privacy.
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { Privacy } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ privacy: Privacy; visibilities: { value: string; label: string }[] }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const { saving, errors, run } = useSection();

const form = reactive<Privacy>({ ...props.privacy });
const options = computed(() => Object.fromEntries(props.visibilities.map((item) => [item.value, item.label])));

async function submit() {
    await run(async () => {
        Object.assign(form, await api.updatePrivacy({ ...form }));
    }, t('profile.settings.toasts.saved'));
}
</script>

<template>
    <section id="confidentialite" class="card" aria-labelledby="confidentialite-title">
        <div class="card-header border-0 pb-0"><h2 id="confidentialite-title" class="h6 mb-0">{{ t('profile.settings.sections.privacy') }}</h2></div>
        <div class="card-body">
            <form novalidate @submit.prevent="submit">
                <XmField v-model="form.visibility" name="visibility" type="select" :label="t('profile.settings.privacy.visibility')" :options="options" :error="errors.visibility" />
                <XmField v-model="form.show_zone" name="show_zone" type="switch" :label="t('profile.settings.privacy.show_zone')" :error="errors.show_zone" />
                <XmField v-model="form.searchable" name="searchable" type="switch" :label="t('profile.settings.privacy.searchable')" :hint="t('profile.settings.privacy.searchable_help')" :error="errors.searchable" />
                <XmField v-model="form.sos_share_gps" name="sos_share_gps" type="switch" :label="t('profile.settings.privacy.sos_gps')" :hint="t('profile.settings.privacy.sos_gps_help')" :error="errors.sos_share_gps" />
                <XmField v-model="form.sensitive_consent" name="sensitive_consent" type="switch" :label="t('profile.settings.privacy.sensitive_consent')" :hint="t('profile.settings.privacy.sensitive_consent_help')" :error="errors.sensitive_consent" />
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.save') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
