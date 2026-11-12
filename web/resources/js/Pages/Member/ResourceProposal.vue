<script setup lang="ts">
// Page « Proposer une ressource » (P18-12, `/proposer-une-ressource`, ContributePageController::proposal), chargée à la
// demande par app.ts : titre, type (fiche, guide, document PDF, lien), résumé, catégorie facultative, lien `https` (exigé
// pour un lien) ou fichier PDF (exigé pour un document PDF), crédit facultatif. Envoi multipart `POST
// /api/v1/resource-proposals` ; la proposition n'est jamais publique : l'équipe la relit (notification de la décision).
// Erreurs par champ (422) sous chaque champ, résumé focalisé ; message de confirmation annoncé (role="status").
import { computed, nextTick, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import XmField from '@/components/XmField.vue';

interface Option { value: string | number; label: string }

const props = defineProps<{
    title: string;
    options: {
        kinds: Option[];
        categories: Option[];
        limits: { title_min: number; title_max: number; summary_min: number; summary_max: number; file_max_kb: number };
    };
}>();

const { t } = useI18n();
const api = useApi();
const toast = useToast();

const form = reactive({ title: '', kind: String(props.options.kinds[0]?.value ?? 'lien'), summary: '', category_id: '', external_url: '', credit_author: false });
const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const errors = ref<Record<string, string>>({});
const sending = ref(false);
const sent = ref(false);
const done = ref<HTMLElement | null>(null);

const needsFile = computed(() => form.kind === 'pdf');
const categoryOptions = computed(() => [{ value: '', label: t('community.proposals.no_category') }, ...props.options.categories]);
const maxMb = computed(() => Math.round(props.options.limits.file_max_kb / 1024));

function pick(event: Event) {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
}

async function submit() {
    if (sending.value) return;
    sending.value = true;
    errors.value = {};
    const body = new FormData();
    body.append('title', form.title);
    body.append('kind', form.kind);
    body.append('summary', form.summary);
    if (form.category_id !== '') body.append('category_id', String(form.category_id));
    if (!needsFile.value && form.external_url.trim() !== '') body.append('external_url', form.external_url.trim());
    if (needsFile.value && file.value) body.append('file', file.value);
    body.append('credit_author', form.credit_author ? '1' : '0');
    try {
        await api.post('/api/v1/resource-proposals', body, { silent: true });
        sent.value = true;
        toast.show(t('community.proposals.sent_toast'), 'success');
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
    Object.assign(form, { title: '', summary: '', category_id: '', external_url: '', credit_author: false });
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
    sent.value = false;
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9" data-xm-resource-proposal>
        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-1">{{ t('community.proposals.title') }}</h1>
                <p class="text-secondary">{{ t('community.proposals.intro') }}</p>

                <div v-if="sent" ref="done" class="alert alert-success" role="status" tabindex="-1">
                    <p class="mb-2"><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ t('community.proposals.sent') }}</p>
                    <button class="btn btn-sm btn-outline-success" type="button" @click="again">{{ t('community.proposals.again') }}</button>
                </div>

                <form v-else novalidate @submit.prevent="submit">
                    <XmField v-model="form.title" name="title" :label="t('community.proposals.fields.title')" :maxlength="options.limits.title_max" :error="errors.title" :hint="t('community.proposals.hints.title', { min: options.limits.title_min, max: options.limits.title_max })" />
                    <XmField v-model="form.kind" name="kind" type="radio-group" :label="t('community.proposals.fields.kind')" :options="options.kinds" :error="errors.kind" />
                    <XmField v-model="form.summary" name="summary" type="textarea" :rows="4" :label="t('community.proposals.fields.summary')" :maxlength="options.limits.summary_max" :error="errors.summary" :hint="t('community.proposals.hints.summary', { min: options.limits.summary_min, max: options.limits.summary_max })" />
                    <XmField v-model="form.category_id" name="category_id" type="select" optional :label="t('community.proposals.fields.category')" :options="categoryOptions" :error="errors.category_id" />
                    <div v-if="needsFile" class="mb-3 xm-field">
                        <label class="form-label" for="f-file">{{ t('community.proposals.fields.file') }}</label>
                        <input id="f-file" ref="fileInput" :class="['form-control', { 'is-invalid': errors.file }]" type="file" name="file" accept="application/pdf,.pdf" :aria-invalid="errors.file ? 'true' : undefined" aria-describedby="f-file-hint" @change="pick">
                        <div id="f-file-hint" class="form-text">{{ t('community.proposals.hints.file', { max: maxMb }) }}</div>
                        <div v-if="errors.file" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.file }}</div>
                    </div>
                    <XmField v-else v-model="form.external_url" name="external_url" type="url" :optional="form.kind !== 'lien'" inputmode="url" :label="t('community.proposals.fields.link')" :error="errors.external_url" :hint="t('community.proposals.hints.link')" />
                    <XmField v-model="form.credit_author" name="credit_author" type="switch" :label="t('community.proposals.fields.credit')" :hint="t('community.proposals.hints.credit')" />
                    <button class="btn btn-primary" type="submit" :disabled="sending"><i class="bi bi-send me-1" aria-hidden="true"></i>{{ t('community.proposals.submit') }}</button>
                </form>
            </div>
        </div>
    </div>
</template>
