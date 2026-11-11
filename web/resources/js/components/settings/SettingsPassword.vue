<script setup lang="ts">
// Section « Mot de passe » de /parametres (P8-09) : mot de passe actuel vérifié par le serveur, nouveau mot de passe avec
// la jauge <PasswordMeter> (règles de PasswordStrength), confirmation. PUT me/password ferme les autres appareils et
// dépose l'e-mail `mot-de-passe-modifie` ; la section émet `changed` (nombre d'accès fermés) pour rafraîchir la liste
// des appareils. Les champs sont vidés après succès.
import { reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';
import PasswordMeter from './PasswordMeter.vue';

defineProps<{ min: number }>();
const emit = defineEmits<{ changed: [revoked: number] }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const toast = useToast();
const { saving, errors, run } = useSection();

const form = reactive({ current_password: '', password: '', password_confirmation: '' });

async function submit() {
    let revoked = 0;
    const ok = await run(async () => {
        revoked = (await api.changePassword({ ...form })).revoked;
    }, t('profile.settings.toasts.password_changed'));
    if (!ok) return;
    form.current_password = '';
    form.password = '';
    form.password_confirmation = '';
    if (revoked > 0) toast.info(t('profile.settings.toasts.sessions_revoked'));
    emit('changed', revoked);
}
</script>

<template>
    <section id="motdepasse" class="card" aria-labelledby="motdepasse-title">
        <div class="card-header border-0 pb-0"><h2 id="motdepasse-title" class="h6 mb-0">{{ t('profile.settings.sections.password') }}</h2></div>
        <div class="card-body">
            <form novalidate @submit.prevent="submit">
                <div class="row g-3">
                    <div class="col-12"><XmField v-model="form.current_password" name="current_password" type="password" :label="t('profile.settings.password.current')" :error="errors.current_password" autocomplete="current-password" required /></div>
                    <div class="col-md-6">
                        <XmField v-model="form.password" name="password" type="password" :label="t('profile.settings.password.new')" :error="errors.password" autocomplete="new-password" aria-describedby="pwMeter-rules" required />
                        <PasswordMeter id="pwMeter" :password="form.password" />
                    </div>
                    <div class="col-md-6"><XmField v-model="form.password_confirmation" name="password_confirmation" type="password" :label="t('profile.settings.password.confirm')" :error="errors.password_confirmation" autocomplete="new-password" required /></div>
                </div>
                <p class="form-text mt-3">{{ t('profile.settings.password.rules', { min }) }} {{ t('profile.settings.password.revoke_note') }}</p>
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.password.submit') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
