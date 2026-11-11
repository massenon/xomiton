<script setup lang="ts">
// « Mes données » et « Supprimer mon compte » de /parametres (P8-09, P6-06) : export RGPD (POST me/export, fichier
// préparé en file, lien envoyé par e-mail) ; suppression (DELETE me : mot de passe + mot SUPPRIMER tapé, effacement
// différé de `deletion_days` jours, session fermée, retour à l'accueil). Comptes de l'administration : suppression
// impossible d'ici (403 côté serveur), texte d'explication à la place du formulaire.
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { route } from '@/app/routes';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { Limits } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ limits: Limits; canDelete: boolean }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const exporting = useSection();
const deleting = useSection();

const exported = ref(false);
const confirmWord = ref('');
const password = ref('');
const confirmError = ref<string | null>(null);

async function requestExport() {
    exported.value = await exporting.run(() => api.requestExport(), t('profile.settings.data.preparing', { hours: props.limits.export_hours }));
}

async function destroy() {
    confirmError.value = null;
    if (confirmWord.value.trim() !== t('profile.settings.delete.confirm_word')) {
        confirmError.value = t('profile.settings.delete.confirm_error');
        return;
    }
    const ok = await deleting.run(() => api.deleteAccount(password.value));
    if (ok) window.location.assign(route('home', undefined, '/'));
}
</script>

<template>
    <section id="donnees" class="card" aria-labelledby="donnees-title">
        <div class="card-header border-0 pb-0"><h2 id="donnees-title" class="h6 mb-0">{{ t('profile.settings.data.title') }}</h2></div>
        <div class="card-body">
            <p class="small text-secondary">{{ t('profile.settings.data.text') }}</p>
            <p v-if="exported" class="alert alert-info small" role="status">{{ t('profile.settings.data.preparing', { hours: limits.export_hours }) }}</p>
            <XmButton variant="primary-soft" icon="download" :loading="exporting.saving.value" :disabled="exported" @click="requestExport">{{ t('profile.settings.data.submit') }}</XmButton>
        </div>
    </section>

    <section id="supprimer" class="card border-danger" aria-labelledby="supprimer-title">
        <div class="card-header border-0 pb-0"><h2 id="supprimer-title" class="h6 mb-0 text-danger">{{ t('profile.settings.delete.title') }}</h2></div>
        <div class="card-body">
            <p class="small">{{ t('profile.settings.delete.text', { days: limits.deletion_days }) }}</p>
            <p v-if="!canDelete" class="alert alert-secondary small mb-0">{{ t('profile.settings.delete.admin_blocked') }}</p>
            <form v-else novalidate @submit.prevent="destroy">
                <div class="row g-2 align-items-start">
                    <div class="col-md-6"><XmField v-model="confirmWord" name="confirm_text" :label="t('profile.settings.delete.confirm_label')" :error="confirmError" autocomplete="off" required /></div>
                    <div class="col-md-6"><XmField v-model="password" name="delete_password" type="password" :label="t('profile.settings.password.current')" :hint="t('profile.settings.delete.password_help')" :error="deleting.errors.value.password" autocomplete="current-password" required /></div>
                </div>
                <div class="text-end"><XmButton type="submit" variant="danger" icon="trash" :loading="deleting.saving.value">{{ t('profile.settings.delete.submit') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
