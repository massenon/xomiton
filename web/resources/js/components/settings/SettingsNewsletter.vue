<script setup lang="ts">
// Section « Newsletter » de /parametres (P8-09, P6-06 ; Q70 : sans fréquence, faute de colonne) : abonnement du compte
// et centres d'intérêt. PATCH me/newsletter ; e-mail du compte non confirmé → double opt-in (« confirme depuis
// l'e-mail »), annoncé à l'écran et par un toast.
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { Newsletter } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ newsletter: Newsletter }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const toast = useToast();
const { saving, errors, run } = useSection();

const form = reactive({ subscribed: props.newsletter.subscribed || props.newsletter.pending_confirmation, interests: [...props.newsletter.interests] });
const pending = ref(props.newsletter.pending_confirmation);
const options = computed(() => Object.fromEntries(props.newsletter.available_interests.map((item) => [item.slug, item.label])));

async function submit() {
    await run(async () => {
        const saved = await api.updateNewsletter({ subscribed: form.subscribed, interests: form.interests });
        pending.value = saved.pending_confirmation;
        form.interests = [...saved.interests];
        toast.success(t(saved.pending_confirmation ? 'profile.settings.toasts.newsletter_pending' : 'profile.settings.toasts.saved'));
    });
}
</script>

<template>
    <section id="newsletter" class="card" aria-labelledby="newsletter-title">
        <div class="card-header border-0 pb-0"><h2 id="newsletter-title" class="h6 mb-0">{{ t('profile.settings.newsletter.title') }}</h2></div>
        <div class="card-body">
            <p class="small text-secondary">{{ t('profile.settings.newsletter.intro') }}</p>
            <div v-if="pending" class="alert alert-info small" role="status"><i class="bi bi-envelope me-1" aria-hidden="true"></i>{{ t('profile.settings.newsletter.pending') }}</div>
            <form novalidate @submit.prevent="submit">
                <XmField v-model="form.subscribed" name="subscribed" type="switch" :label="t('profile.settings.newsletter.subscribed')" :error="errors.subscribed" />
                <XmField v-if="newsletter.available_interests.length" v-model="form.interests" name="interests" type="checkbox-group" :label="t('profile.settings.newsletter.interests')" :hint="t('profile.settings.newsletter.interests_hint')" :options="options" :error="errors.interests" optional />
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.save') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
