<script setup lang="ts">
// « Signaler cette conversation » (P8-01, prototype messaging.php l.85-100) : motif (mêmes motifs que le fil,
// App\Enums\ContentReportReason), précisions facultatives (≤ 500), rappel « En danger ? » vers le SOS et le
// signalement anonyme. Transmis à la modération de façon confidentielle.
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XmModal from '@/components/XmModal.vue';
import { route } from '@/app/routes';

const props = withDefaults(defineProps<{
    open: boolean;
    reasons: Array<{ value: string; label: string }>;
    busy?: boolean;
    error?: string | null;
}>(), { busy: false, error: null });

const emit = defineEmits<{
    'update:open': [open: boolean];
    submit: [reason: string, details: string | null];
}>();

const { t } = useI18n();
const reason = ref('');
const details = ref('');
const sosHref = route('sos', undefined, '/sos');
const reportHref = route('signaler', undefined, '/signaler');

watch(() => props.open, (open) => {
    if (!open) return;
    reason.value = props.reasons[0]?.value ?? '';
    details.value = '';
});

function submit() {
    if (!reason.value) return;
    emit('submit', reason.value, details.value.trim() === '' ? null : details.value.trim());
}
</script>

<template>
    <XmModal id="modalReportChat" :open="open" type="form" form :title="t('community.messages.report.title')" :action="t('community.messages.report.submit')"
        :busy="busy" @update:open="emit('update:open', $event)" @submit="submit">
        <p class="small text-secondary">{{ t('community.messages.report.intro') }}</p>
        <label class="form-label" for="reportReason">{{ t('community.messages.report.reason') }}</label>
        <select id="reportReason" v-model="reason" class="form-select mb-3" required>
            <option v-for="item in reasons" :key="item.value" :value="item.value">{{ item.label }}</option>
        </select>
        <label class="form-label" for="reportNote">{{ t('community.messages.report.details') }}</label>
        <textarea id="reportNote" v-model="details" class="form-control" rows="3" maxlength="500"></textarea>
        <p v-if="error" class="small text-danger mt-2 mb-0" role="alert">{{ error }}</p>
        <div class="alert alert-warning small mt-3 mb-0">
            {{ t('community.messages.danger_hint') }}
            <a class="alert-link ms-1" :href="sosHref">{{ t('community.messages.report.sos') }}</a> ·
            <a class="alert-link" :href="reportHref">{{ t('community.messages.report.anonymous_report') }}</a>
        </div>
    </XmModal>
</template>
