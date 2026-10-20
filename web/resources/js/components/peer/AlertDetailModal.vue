<script setup lang="ts">
// Modale « Alerte qui m'est routée » de l'espace pair (lot espace-pair, P9-03 ; prototype espace-pair.php l.80-98), chargée
// à la demande par la page /espace-pair : détail pour répondre (`GET pair/alerts/{ulid}` : récit, personne à contacter,
// canal préféré, lieu, position d'un SOS géolocalisé, besoins, historique) et gestes permis par le serveur (`can`) :
// accuser réception, prendre en charge, clore (confirmation avant : une alerte close ne se rouvre pas). Aucun geste de
// réattribution, de note interne ni d'export (réservés à l'administration). Récit et contact sont chiffrés au repos et
// servis `no-store` : jamais gardés en dehors de cette modale. Props : alertId. Émet `changed` (liste à rafraîchir), `hidden`.
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { timeAgo } from '@/feed/format';
import { peerApi } from '@/peer/api';
import type { AlertDetail } from '@/peer/types';
import XmAlert from '@/components/XmAlert.vue';
import XmBadge from '@/components/XmBadge.vue';
import XmButton from '@/components/XmButton.vue';
import XmModal from '@/components/XmModal.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = defineProps<{ alertId: string }>();
const emit = defineEmits<{ changed: []; hidden: [] }>();

const { t } = useI18n();
const toast = useToast();
const api = peerApi(useApi());
const detail = ref<AlertDetail | null>(null);
const loading = ref(true);
const failure = ref('');
const busy = ref(false);
const confirming = ref(false);

const priorityColor = computed(() => (detail.value?.priority === 'critique' ? 'danger' : detail.value?.priority === 'haute' ? 'warning' : 'secondary'));
const closed = computed(() => detail.value?.status === 'clos');
const canTake = computed(() => Boolean(detail.value?.can.take) && detail.value?.status !== 'en_cours' && detail.value?.can.statuses.includes('en_cours'));

async function load() {
    loading.value = true;
    failure.value = '';
    try {
        detail.value = await api.alert(props.alertId);
    } catch (cause) {
        failure.value = cause instanceof ApiError && cause.status === 0 ? t('peer.alerts.offline') : t('peer.alerts.load_failed');
    } finally {
        loading.value = false;
    }
}

async function run(action: () => Promise<AlertDetail>, done: string) {
    if (busy.value) return;
    busy.value = true;
    failure.value = '';
    try {
        detail.value = await action();
        confirming.value = false;
        toast.show(done, 'success');
        emit('changed');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) failure.value = t('peer.alerts.offline');
        else failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
    } finally {
        busy.value = false;
    }
}

const acknowledge = () => run(() => api.acknowledge(props.alertId), t('peer.alerts.acknowledged'));
const take = () => run(() => api.changeStatus(props.alertId, 'en_cours'), t('peer.alerts.taken'));
const close = () => run(() => api.changeStatus(props.alertId, 'clos'), t('peer.alerts.closed'));

onMounted(load);
</script>

<template>
    <XmModal id="modalPeerAlert" :title="t('peer.alerts.modal_title')" :open="true" size="lg" @hidden="emit('hidden')">
        <XmSkeleton v-if="loading" :lines="4" />
        <template v-else-if="detail">
            <div class="d-flex flex-wrap gap-2 mb-3">
                <XmBadge color="neutral">{{ detail.type_label }}</XmBadge>
                <XmBadge :color="priorityColor">{{ detail.priority_label }}</XmBadge>
                <XmBadge color="primary" soft>{{ detail.status_label }}</XmBadge>
                <XmBadge v-if="detail.cancelled" color="secondary">{{ t('peer.alerts.cancelled') }}</XmBadge>
                <XmBadge v-if="detail.safe" color="success">{{ t('peer.alerts.safe') }}</XmBadge>
            </div>
            <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
            <dl class="row small mb-3">
                <dt class="col-sm-4">{{ t('peer.alerts.fields.received') }}</dt>
                <dd class="col-sm-8"><time :datetime="detail.created_at ?? undefined">{{ timeAgo(detail.created_at, t) }}</time></dd>
                <dt class="col-sm-4">{{ t('peer.alerts.fields.place') }}</dt>
                <dd class="col-sm-8">{{ detail.place || t('peer.alerts.unknown_zone') }}</dd>
                <template v-if="detail.category_label">
                    <dt class="col-sm-4">{{ t('peer.alerts.fields.category') }}</dt>
                    <dd class="col-sm-8">{{ detail.category_label }}</dd>
                </template>
                <template v-if="detail.needs.length > 0">
                    <dt class="col-sm-4">{{ t('peer.alerts.fields.needs') }}</dt>
                    <dd class="col-sm-8">{{ detail.needs.map((need) => need.label).join(', ') }}</dd>
                </template>
                <dt class="col-sm-4">{{ t('peer.alerts.fields.author') }}</dt>
                <dd class="col-sm-8">{{ detail.anonymous ? t('peer.alerts.anonymous') : (detail.author || t('peer.alerts.anonymous')) }}</dd>
                <template v-if="detail.contact">
                    <dt class="col-sm-4">{{ t('peer.alerts.fields.contact') }}</dt>
                    <dd class="col-sm-8">{{ detail.contact }}<template v-if="detail.preferred_channel_label"> · {{ detail.preferred_channel_label }}</template></dd>
                </template>
                <template v-if="detail.position">
                    <dt class="col-sm-4">{{ t('peer.alerts.fields.position') }}</dt>
                    <dd class="col-sm-8">{{ detail.position.lat }}, {{ detail.position.lng }}<template v-if="detail.position.accuracy"> · {{ t('peer.alerts.accuracy', { meters: Math.round(detail.position.accuracy) }) }}</template></dd>
                </template>
            </dl>
            <div v-if="detail.description" class="mb-3">
                <h3 class="h6">{{ t('peer.alerts.fields.description') }}</h3>
                <p class="mb-0 text-break" style="white-space: pre-line">{{ detail.description }}</p>
            </div>
            <div v-if="detail.history.length > 0">
                <h3 class="h6">{{ t('peer.alerts.fields.history') }}</h3>
                <ol class="small list-unstyled mb-0">
                    <li v-for="(step, index) in detail.history" :key="index">{{ step.status_label }}<template v-if="step.at"> · <time :datetime="step.at">{{ timeAgo(step.at, t) }}</time></template></li>
                </ol>
            </div>
            <XmAlert v-if="confirming" type="warning" class="small mt-3 mb-0">
                <p class="mb-2">{{ t('peer.alerts.close_confirm') }}</p>
                <span class="d-flex gap-2">
                    <XmButton size="sm" variant="danger" :loading="busy" @click="close">{{ t('peer.alerts.close_yes') }}</XmButton>
                    <XmButton size="sm" variant="light" :disabled="busy" @click="confirming = false">{{ t('common.cancel') }}</XmButton>
                </span>
            </XmAlert>
        </template>
        <XmAlert v-else type="danger" class="small mb-0">{{ failure }}</XmAlert>
        <template #footer>
            <template v-if="detail && !closed">
                <XmButton v-if="detail.can.acknowledge" variant="primary" icon="check2" :loading="busy" @click="acknowledge">{{ t('peer.alerts.acknowledge') }}</XmButton>
                <XmButton v-if="canTake" variant="outline" icon="hand-index-thumb" :disabled="busy" @click="take">{{ t('peer.alerts.take') }}</XmButton>
                <XmButton v-if="detail.can.close && !confirming" variant="danger-soft" icon="x-circle" :disabled="busy" @click="confirming = true">{{ t('peer.alerts.close') }}</XmButton>
            </template>
        </template>
    </XmModal>
</template>
