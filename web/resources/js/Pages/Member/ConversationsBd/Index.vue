<script setup lang="ts">
// « Mes conversations en BD » (P20-07, P20-08, P20-09 ; `/mes-conversations-bd`, ConversationsBdPageController::index),
// chargée à la demande : créer une conversation (titre, résumé facultatif → `POST /api/v1/bd-conversations`, puis
// l'éditeur), liste de mes créations avec leur statut (brouillon privé, proposée, publiée, refusée avec le motif,
// retirée), et pour chacune : ouvrir (éditeur ou aperçu), PDF, page publique, retirer (proposée ou publiée), supprimer
// (brouillon, refusée, retirée). Les confirmations passent par window.confirm (lisible au lecteur d'écran, sans modale).
import { computed, nextTick, reactive, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import XmField from '@/components/XmField.vue';

interface Item {
    ulid: string;
    title: string;
    summary: string;
    status: string;
    status_label: string;
    editable: boolean;
    refusal_reason: string | null;
    panels_count: number;
    updated_at: string | null;
    public_url: string | null;
    editor_url: string;
    pdf_url: string;
}

const props = defineProps<{
    title: string;
    conversations: Item[];
    limits: { max: number; title_min: number; title_max: number; summary_max: number };
}>();

const { t, locale } = useI18n();
const api = useApi();
const toast = useToast();

const items = ref<Item[]>([...props.conversations]);
const form = reactive({ title: '', summary: '' });
const errors = ref<Record<string, string>>({});
const busy = ref(false);
const live = ref('');
const heading = ref<HTMLElement | null>(null);

const BADGES: Record<string, string> = { brouillon: 'secondary', proposee: 'info', publiee: 'success', refusee: 'danger', retiree: 'warning' };
const full = computed(() => items.value.length >= props.limits.max);

function date(value: string | null): string {
    if (!value) return '';
    try {
        return new Intl.DateTimeFormat(locale.value || 'fr', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
    } catch {
        return value;
    }
}

function failure(cause: unknown) {
    if (cause instanceof ApiError && cause.status === 422) {
        errors.value = fieldErrors(cause);
        if (Object.keys(errors.value).length === 0) toast.show(cause.message, 'warning');
    } else if (cause instanceof ApiError) {
        toast.show(cause.status === 0 ? t('microcopy.error.unreachable') : cause.message, 'warning');
    } else {
        toast.show(t('microcopy.error.generic'), 'warning');
    }
}

async function create() {
    if (busy.value) return;
    busy.value = true;
    errors.value = {};
    try {
        const response = await api.post<{ data: Item }>('/api/v1/bd-conversations', { title: form.title, summary: form.summary }, { silent: true });
        router.visit(response.data.data.editor_url);
    } catch (cause) {
        failure(cause);
        await nextTick();
        document.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
    } finally {
        busy.value = false;
    }
}

async function withdraw(item: Item) {
    if (!window.confirm(t('conversations_bd.member.confirm_withdraw', { title: item.title }))) return;
    try {
        const response = await api.post<{ data: Item }>(`/api/v1/bd-conversations/${item.ulid}/withdraw`, {}, { silent: true });
        items.value = items.value.map((row) => (row.ulid === item.ulid ? { ...row, ...response.data.data } : row));
        live.value = t('conversations_bd.member.withdrawn', { title: item.title });
    } catch (cause) {
        failure(cause);
    }
}

async function remove(item: Item) {
    if (!window.confirm(t('conversations_bd.member.confirm_delete', { title: item.title }))) return;
    try {
        await api.delete(`/api/v1/bd-conversations/${item.ulid}`, undefined, { silent: true });
        items.value = items.value.filter((row) => row.ulid !== item.ulid);
        live.value = t('conversations_bd.member.deleted', { title: item.title });
        heading.value?.focus();
    } catch (cause) {
        failure(cause);
    }
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4" data-xm-bd-mine>
        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-1">{{ t('conversations_bd.member.title') }}</h1>
                <p class="text-secondary">{{ t('conversations_bd.member.intro') }}</p>
                <p v-if="full" class="alert alert-info mb-0">{{ t('conversations_bd.member.full', { max: limits.max }) }}</p>
                <form v-else novalidate @submit.prevent="create">
                    <h2 class="h6">{{ t('conversations_bd.member.new_title') }}</h2>
                    <XmField v-model="form.title" name="title" :label="t('conversations_bd.editor.fields.title')" :maxlength="limits.title_max" :error="errors.title" :hint="t('conversations_bd.editor.hints.title', { min: limits.title_min, max: limits.title_max })" />
                    <XmField v-model="form.summary" name="summary" type="textarea" :rows="2" optional :label="t('conversations_bd.editor.fields.summary')" :maxlength="limits.summary_max" :error="errors.summary" :hint="t('conversations_bd.editor.hints.summary')" />
                    <button class="btn btn-primary xm-bde-touch" type="submit" :disabled="busy"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.create') }}</button>
                </form>
            </div>
        </div>

        <section class="card" aria-labelledby="bd-mine-list">
            <div class="card-body">
                <h2 id="bd-mine-list" ref="heading" class="h5" tabindex="-1">{{ t('conversations_bd.member.list_title') }}</h2>
                <p class="visually-hidden" role="status" aria-live="polite">{{ live }}</p>
                <p v-if="items.length === 0" class="text-secondary mb-0">{{ t('conversations_bd.member.empty') }}</p>
                <ul v-else class="list-unstyled vstack gap-3 mb-0">
                    <li v-for="item in items" :key="item.ulid" class="border rounded p-3" :data-status="item.status">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <h3 class="h6 mb-0 me-auto text-break">{{ item.title }}</h3>
                            <span :class="['badge', `text-bg-${BADGES[item.status] ?? 'secondary'}`]">{{ item.status_label }}</span>
                        </div>
                        <p class="small text-secondary mb-2">
                            {{ t('conversations_bd.member.meta', { count: item.panels_count, date: date(item.updated_at) }) }}
                            <span v-if="item.status === 'brouillon'"> · {{ t('conversations_bd.member.private') }}</span>
                        </p>
                        <p v-if="item.status === 'refusee' && item.refusal_reason" class="alert alert-warning small py-2 mb-2">
                            <strong>{{ t('conversations_bd.member.refusal') }}</strong> {{ item.refusal_reason }}
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <Link class="btn btn-sm btn-primary xm-bde-touch" :href="item.editor_url">
                                <i :class="['bi', item.editable ? 'bi-pencil' : 'bi-eye', 'me-1']" aria-hidden="true"></i>{{ item.editable ? t('conversations_bd.member.edit') : t('conversations_bd.member.view') }}<span class="visually-hidden"> {{ item.title }}</span>
                            </Link>
                            <a class="btn btn-sm btn-outline-primary xm-bde-touch" :href="item.pdf_url" download>
                                <i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.pdf') }}<span class="visually-hidden"> {{ item.title }}</span>
                            </a>
                            <a v-if="item.public_url" class="btn btn-sm btn-outline-primary xm-bde-touch" :href="item.public_url">
                                <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.public') }}<span class="visually-hidden"> {{ item.title }}</span>
                            </a>
                            <button v-if="item.status === 'proposee' || item.status === 'publiee'" type="button" class="btn btn-sm btn-outline-warning xm-bde-touch" @click="withdraw(item)">
                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.withdraw') }}<span class="visually-hidden"> {{ item.title }}</span>
                            </button>
                            <button v-if="item.editable" type="button" class="btn btn-sm btn-outline-danger xm-bde-touch" @click="remove(item)">
                                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.delete') }}<span class="visually-hidden"> {{ item.title }}</span>
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </section>
    </div>
</template>
