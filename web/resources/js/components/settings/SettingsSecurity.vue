<script setup lang="ts">
// Section « Sécurité » de /parametres (P8-09, D8) : mode discret (active aussi aperçus masqués et onglet neutre, côté
// serveur), contenu sensible masqué dans les notifications, titre d'onglet neutre (« Notes ») appliqué tout de suite
// à l'onglet courant (store session) puis par le gabarit Inertia à chaque page. PATCH me/security. Pas de code PIN
// web (Q37 b : le code reste sur l'appareil mobile). *Sans* sortie rapide (D8).
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useSessionStore } from '@/stores/session';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { Security } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ security: Security; title: string }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const session = useSessionStore();
const { saving, errors, run } = useSection();

const form = reactive({ discreet: props.security.discreet, mask_previews: props.security.mask_previews, neutral_tab_title: props.security.neutral_tab_title });

async function submit() {
    await run(async () => {
        const saved = await api.updateSecurity({ ...form });
        Object.assign(form, { discreet: saved.discreet, mask_previews: saved.mask_previews, neutral_tab_title: saved.neutral_tab_title });
        session.neutralTitle = saved.neutral_tab_title;
        if (session.user) session.user = { ...session.user, settings: { ...session.user.settings, neutral_tab_title: saved.neutral_tab_title, mask_sensitive_notifications: saved.mask_previews } };
        document.title = session.documentTitle(props.title);
    }, t('profile.settings.toasts.saved'));
}
</script>

<template>
    <section id="securite" class="card" aria-labelledby="securite-title">
        <div class="card-header border-0 pb-0"><h2 id="securite-title" class="h6 mb-0">{{ t('profile.settings.sections.security') }}</h2></div>
        <div class="card-body">
            <form novalidate @submit.prevent="submit">
                <XmField v-model="form.discreet" name="discreet" type="switch" :label="t('profile.settings.security.discreet_mode')" :hint="t('profile.settings.security.discreet_mode_help')" :error="errors.discreet" />
                <XmField v-model="form.mask_previews" name="mask_previews" type="switch" :label="t('profile.settings.security.mask_notifications')" :hint="t('notifications.preferences.mask_sensitive_help')" :error="errors.mask_previews" />
                <XmField v-model="form.neutral_tab_title" name="neutral_tab_title" type="switch" :label="t('profile.settings.security.neutral_tab_title')" :hint="t('profile.settings.security.neutral_tab_title_help')" :error="errors.neutral_tab_title" />
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.save') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
