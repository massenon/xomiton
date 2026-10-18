<script setup lang="ts">
// Bandeau « Confirme ton e-mail » du gabarit membre Vue (P6-03, Q12 c) : même HTML que auth/partials/verify-banner
// (x-alert warning), affiché tant que `auth.user.email_verified` est faux. « Saisir le code » ouvre l'écran du code ;
// « Renvoyer le code » est un formulaire natif (POST otp.resend, purpose=signup, limiteur `otp`) : la réponse redirige
// vers l'écran Blade du code, hors Inertia, d'où le jeton CSRF des props partagées (`shell.csrf_token`).
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { useSessionStore } from '@/stores/session';
import XmAlert from './XmAlert.vue';

const { t } = useI18n();
const session = useSessionStore();
const visible = computed(() => session.user !== null && session.user.email_verified === false);
const codeHref = computed(() => route('otp', { purpose: 'signup' }, '/code-de-verification?purpose=signup'));
const resendAction = computed(() => route('otp.resend', undefined, '/code-de-verification/renvoyer'));
</script>

<template>
    <XmAlert v-if="visible" type="warning" icon="envelope-exclamation" :title="t('auth.verify_banner.title')" class="xm-verify-banner">
        <p class="mb-2">{{ t('auth.verify_banner.text') }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-sm btn-primary" :href="codeHref"><i class="bi bi-123 me-1" aria-hidden="true"></i>{{ t('auth.verify_banner.enter_code') }}</a>
            <form method="post" :action="resendAction" class="d-inline">
                <input type="hidden" name="_token" :value="session.shell?.csrf_token ?? ''">
                <input type="hidden" name="purpose" value="signup">
                <button type="submit" class="btn btn-sm btn-light border"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>{{ t('auth.verify_banner.resend') }}</button>
            </form>
        </div>
    </XmAlert>
</template>
