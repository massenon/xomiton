<script setup lang="ts">
// Modale « Signaler cette publication » / « Signaler ce commentaire » (lot fil-ecrans, P7-05), chargée à la demande
// (feed/lazy.ts) depuis le menu d'une carte ou d'un commentaire : motif (props `report_reasons` de la page, fournies
// par feed/reportReasons.ts = ContentReportReason::selectable() ; « Autre » seul sans elles), précisions facultatives
// (500 car., ReportContentRequest), envoi `POST posts/{ulid}/report` ou `comments/{id}/report` (RG-16 : masquage
// automatique au seuil, jamais annoncé ; 200 si déjà signalé). Renvoi vers `/signaler?post={ulid}` pour une personne en danger (alerte, P5-04).
// target : { post: ulid } | { comment: id } | (P8-05, forums) { topic: ulid } | { reply: id } ; émet `reported`, `hidden`.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { route } from '@/app/routes';
import { useToast } from '@/app/useToast';
import { feedApi, type ReportTarget } from '@/feed/api';
import { FALLBACK_REASON, injectReportReasons } from '@/feed/reportReasons';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const DETAILS_MAX = 500;

const props = defineProps<{ target: ReportTarget }>();
const emit = defineEmits<{ reported: []; hidden: [] }>();

const { t } = useI18n();
const toast = useToast();
const api = feedApi(useApi());
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const reason = ref<string>('');
const details = ref('');
const busy = ref(false);
const errors = ref<Record<string, string>>({});
const reasons = injectReportReasons();

const title = computed(() => {
    if ('post' in props.target) return t('feed.report.title');
    if ('topic' in props.target) return t('community.forums.detail.report_topic');
    if ('reply' in props.target) return t('community.forums.detail.report_reply');
    return t('feed.report.comment_title');
});
const options = computed(() => (reasons.length > 0
    ? Object.fromEntries(reasons.map((option) => [option.value, option.label]))
    : { [FALLBACK_REASON]: t(`enums/feed.report_reason.${FALLBACK_REASON}`) }));
const alertHref = computed(() => {
    const base = route('signaler', undefined, '/signaler');
    return 'post' in props.target ? `${base}?post=${encodeURIComponent(props.target.post)}` : base;
});

async function submit() {
    errors.value = {};
    if (!reason.value) {
        errors.value = { reason: t('feed.report.reason_required') };
        return;
    }
    busy.value = true;
    try {
        const { created } = await api.report(props.target, reason.value, details.value.trim() || null);
        toast.show(created ? t('feed.toasts.reported') : t('feed.report.already'), 'success');
        emit('reported');
        modal.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) errors.value = fieldErrors(cause);
        else if (cause instanceof ApiError && cause.status === 0) errors.value = { reason: t('microcopy.error.unreachable') };
        else if (cause instanceof ApiError && cause.status < 500) errors.value = { reason: cause.message };
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <XmModal ref="modal" id="modalReportContent" :title="title" type="form" form :open="true" :action="t('feed.report.submit')" :busy="busy" @submit="submit" @hidden="emit('hidden')">
        <p class="small text-secondary">{{ t('feed.report.intro') }}</p>
        <XmField v-model="reason" name="reason" type="radio-group" :label="t('feed.report.reason')" :options="options" :error="errors.reason" />
        <XmField v-model="details" name="details" type="textarea" :rows="2" :maxlength="DETAILS_MAX" optional :label="t('feed.report.details')" :placeholder="t('feed.report.details_placeholder')" :error="errors.details" />
        <div class="alert alert-warning small mb-0">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ t('feed.report.danger') }}
            <a class="alert-link" :href="alertHref">{{ t('feed.report.danger_link') }}</a>
        </div>
    </XmModal>
</template>
