<script setup lang="ts">
// Page `/pages/creer` (lot groupes-pages, P8-03 ; prototype create-page.php), page Inertia `Community/PageCreate` dans
// le gabarit membre (col-md-8 col-lg-6 + colonne droite) : nom (3–80), catégorie (liste du prototype), description
// (≤ 400), site web (facultatif, ajout), mention « vérifiée par l'équipe de modération ». Envoi `POST pages` : la page
// naît **en attente d'approbation** (P12-05) ; succès → toast puis sa fiche (bandeau « en attente ») ; 422 → erreurs par
// champ et résumé focalisé.
// Props : PagesPageController::create (title, categories, limits).
import { computed, nextTick, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { communityApi } from '@/community/api';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmRightColumn from '@/components/XmRightColumn.vue';

const props = defineProps<{
    title: string;
    categories: string[];
    limits: { name_min: number; name_max: number; description_max: number };
}>();

const { t } = useI18n();
const toast = useToast();
const api = communityApi(useApi());
const form = reactive({ name: '', category: props.categories[0] ?? '', description: '', website: '' });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);

const categories = computed(() => Object.fromEntries(props.categories.map((name) => [name, name])));
const back = route('pages.index', undefined, '/pages');

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    const name = form.name.trim();
    if (name.length < props.limits.name_min) found.name = t('community.pages.create.name_min', { min: props.limits.name_min });
    else if (name.length > props.limits.name_max) found.name = t('feed.create.text_max', { max: props.limits.name_max });
    if (!props.categories.includes(form.category)) found.category = t('community.pages.create.category_invalid');
    if (form.description.trim().length > props.limits.description_max) found.description = t('feed.create.text_max', { max: props.limits.description_max });
    return found;
}

async function focusError() {
    await nextTick();
    document.querySelector<HTMLElement>('#pageCreateForm .xm-error-summary, #pageCreateForm [aria-invalid="true"]')?.focus();
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
        const page = await api.createPage({
            name: form.name.trim(),
            category: form.category,
            description: form.description.trim() || null,
            website: form.website.trim() || null,
        });
        toast.show(t('community.pages.toasts.created', { name: page.name }), 'success');
        router.visit(page.url);
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
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <div class="card">
            <div class="card-header border-0 pb-0"><h1 class="h5 mb-0">{{ t('community.pages.create.title') }}</h1></div>
            <div class="card-body">
                <form id="pageCreateForm" novalidate @submit.prevent="onSubmit">
                    <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
                    <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
                    <XmField v-model="form.name" name="name" :label="t('community.pages.create.name')" :placeholder="t('community.pages.create.name_placeholder')" :maxlength="limits.name_max" :error="errors.name" required :minlength="limits.name_min" />
                    <XmField v-model="form.category" name="category" type="select" :label="t('community.pages.create.category')" :options="categories" :error="errors.category" />
                    <XmField v-model="form.description" name="description" type="textarea" optional :rows="4" :label="t('community.pages.create.description')" :placeholder="t('community.pages.create.description_placeholder')" :maxlength="limits.description_max" :error="errors.description" />
                    <XmField v-model="form.website" name="website" type="url" optional :label="t('community.pages.create.website')" inputmode="url" autocomplete="url" :maxlength="190" placeholder="https://" :error="errors.website" />
                    <div class="alert alert-info small"><i class="bi bi-info-circle me-1" aria-hidden="true"></i>{{ t('community.pages.create.intro') }}</div>
                    <div class="d-flex gap-2 justify-content-end">
                        <Link class="btn btn-light" :href="back">{{ t('common.cancel') }}</Link>
                        <button class="btn btn-primary" type="submit" :disabled="busy" :aria-busy="busy ? 'true' : undefined">{{ t('community.pages.create.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <XmRightColumn />
</template>
