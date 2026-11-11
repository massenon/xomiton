<script setup lang="ts">
// Section « Profil » de /parametres (P8-09, prototype settings.php `action=profile`) : nom complet (facultatif, jamais
// affiché ; vide = null, Q81), pseudonyme, e-mail (lecture seule), téléphone, département puis commune (liste en
// cascade), présentation. Enregistré par PATCH me/identity ; le pseudonyme affiché par le gabarit (store session) suit
// sans recharger.
import { computed, reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useSessionStore } from '@/stores/session';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { Identity, Limits, ZoneOption } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ identity: Identity; zones: ZoneOption[]; limits: Limits }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const session = useSessionStore();
const { saving, errors, run } = useSection();

const form = reactive({
    name: props.identity.name ?? '',
    pseudo: props.identity.pseudo,
    phone: props.identity.phone ?? '',
    zone_id: props.identity.zone_id !== null ? String(props.identity.zone_id) : '',
    commune_id: props.identity.commune_id !== null ? String(props.identity.commune_id) : '',
    bio: props.identity.bio ?? '',
});

const none = computed(() => ({ '': t('buttons.choose') }));
const zoneOptions = computed(() => ({ ...none.value, ...Object.fromEntries(props.zones.map((zone) => [String(zone.id), zone.name])) }));
const communeOptions = computed(() => {
    const zone = props.zones.find((item) => String(item.id) === form.zone_id);
    return { ...none.value, ...Object.fromEntries((zone?.communes ?? []).map((commune) => [String(commune.id), commune.name])) };
});

watch(() => form.zone_id, () => {
    if (!(form.commune_id in communeOptions.value)) form.commune_id = '';
});

async function submit() {
    await run(async () => {
        const saved = await api.updateIdentity({
            name: form.name.trim() === '' ? null : form.name,
            pseudo: form.pseudo,
            phone: form.phone.trim() === '' ? null : form.phone,
            zone_id: form.zone_id === '' ? null : Number(form.zone_id),
            commune_id: form.commune_id === '' ? null : Number(form.commune_id),
            bio: form.bio,
        });
        form.name = saved.name ?? '';
        form.pseudo = saved.pseudo;
        form.phone = saved.phone ?? '';
        form.bio = saved.bio ?? '';
        if (session.user) session.user = { ...session.user, pseudo: saved.pseudo };
    }, t('profile.settings.toasts.profile_saved'));
}
</script>

<template>
    <section id="profil" class="card" aria-labelledby="profil-title">
        <div class="card-header border-0 pb-0"><h2 id="profil-title" class="h6 mb-0">{{ t('profile.settings.sections.profile') }}</h2></div>
        <div class="card-body">
            <form novalidate @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-md-6"><XmField v-model="form.name" name="name" :label="t('profile.settings.profile.name')" :hint="t('profile.settings.profile.name_help')" :error="errors.name" autocomplete="name" :maxlength="limits.name_max" optional /></div>
                    <div class="col-md-6"><XmField v-model="form.pseudo" name="pseudo" :label="t('profile.settings.profile.pseudo')" :hint="t('profile.settings.profile.pseudo_help')" :error="errors.pseudo" autocomplete="username" required /></div>
                    <div class="col-md-6"><XmField :model-value="identity.email" name="email" type="email" :label="t('profile.settings.profile.email')" :hint="t('profile.settings.profile.email_help')" disabled /></div>
                    <div class="col-md-6"><XmField v-model="form.phone" name="phone" type="tel" :label="t('profile.settings.profile.phone')" :hint="t('profile.settings.profile.phone_help')" :placeholder="t('profile.settings.profile.phone_placeholder')" :error="errors.phone" autocomplete="tel" inputmode="tel" optional /></div>
                    <div class="col-md-6"><XmField v-model="form.zone_id" name="zone_id" type="select" :label="t('profile.settings.profile.zone')" :options="zoneOptions" :error="errors.zone_id" optional /></div>
                    <div class="col-md-6"><XmField v-model="form.commune_id" name="commune_id" type="select" :label="t('profile.settings.profile.commune')" :options="communeOptions" :error="errors.commune_id" optional /></div>
                    <div class="col-12"><XmField v-model="form.bio" name="bio" type="textarea" :rows="2" :label="t('profile.settings.profile.bio', { max: limits.bio_max })" :maxlength="limits.bio_max" :error="errors.bio" optional /></div>
                </div>
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.profile.submit') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
