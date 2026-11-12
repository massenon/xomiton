<script setup lang="ts">
// Page « Boîte à idées » (P18-14, `/boite-a-idees`, ContributePageController::suggestion), chargée à la demande par
// app.ts : catégorie (fonctionnalité, contenu, sécurité, autre), message ≤ 1000 caractères, envoi anonyme possible
// (l'équipe ne voit pas le compte, sa réponse arrive quand même en notification). Envoi `POST /api/v1/suggestions`.
import { nextTick, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import XmField from '@/components/XmField.vue';

const props = defineProps<{
    title: string;
    options: { categories: Array<{ value: string; label: string }>; limits: { message_min: number; message_max: number } };
}>();

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const form = reactive({ category: props.options.categories[0]?.value ?? 'autre', message: '', is_anonymous: false });
const errors = ref<Record<string, string>>({});
const sending = ref(false);
const sent = ref(false);
const done = ref<HTMLElement | null>(null);

async function submit() {
    if (sending.value) return;
    sending.value = true;
    errors.value = {};
    try {
        await api.post('/api/v1/suggestions', { ...form }, { silent: true });
        sent.value = true;
        toast.show(t('community.suggestions.sent_toast'), 'success');
        await nextTick();
        done.value?.focus();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
            await nextTick();
            document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
        } else if (cause instanceof ApiError) {
            toast.show(cause.status === 0 ? t('microcopy.error.unreachable') : cause.message, 'warning');
        } else {
            toast.show(t('microcopy.error.generic'), 'warning');
        }
    } finally {
        sending.value = false;
    }
}

function again() {
    form.message = '';
    form.is_anonymous = false;
    sent.value = false;
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9" data-xm-suggestion>
        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-1">{{ t('community.suggestions.title') }}</h1>
                <p class="text-secondary">{{ t('community.suggestions.intro') }}</p>

                <div v-if="sent" ref="done" class="alert alert-success" role="status" tabindex="-1">
                    <p class="mb-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ t('community.suggestions.sent') }}</p>
                    <button class="btn btn-sm btn-outline-success" type="button" @click="again">{{ t('community.suggestions.again') }}</button>
                </div>

                <form v-else novalidate @submit.prevent="submit">
                    <XmField v-model="form.category" name="category" type="radio-group" :label="t('community.suggestions.fields.category')" :options="options.categories" :error="errors.category" />
                    <XmField v-model="form.message" name="message" type="textarea" :rows="5" :label="t('community.suggestions.fields.message')" :maxlength="options.limits.message_max" :error="errors.message" :hint="t('community.suggestions.hints.message', { min: options.limits.message_min, max: options.limits.message_max })" />
                    <XmField v-model="form.is_anonymous" name="is_anonymous" type="switch" :label="t('community.suggestions.fields.anonymous')" :hint="t('community.suggestions.hints.anonymous')" />
                    <button class="btn btn-primary" type="submit" :disabled="sending"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ t('community.suggestions.submit') }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
