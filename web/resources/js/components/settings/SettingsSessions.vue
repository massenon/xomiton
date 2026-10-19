<script setup lang="ts">
// « Appareils connectés » de /parametres (P8-09, P2-06, P6-09) : sessions web et jetons de l'application
// (me/sessions, « Cet appareil » jamais révocable d'ici), « Déconnecter tous les autres appareils » (DELETE
// me/sessions), puis téléphones reliés à l'application (me/devices : retirer coupe synchronisation et push).
// `refresh()` relit la liste (après un changement de mot de passe, qui ferme les autres accès).
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { timeAgo } from '@/feed/format';
import { settingsApi } from '@/settings/api';
import type { DeviceRow, SessionRow } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';

const props = defineProps<{ sessions: SessionRow[]; devices: DeviceRow[] }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const toast = useToast();

const sessions = ref<SessionRow[]>([...props.sessions]);
const devices = ref<DeviceRow[]>([...props.devices]);
const busy = ref<string | null>(null);
const others = computed(() => sessions.value.filter((row) => !row.current));
const ago = (iso: string | null) => timeAgo(iso, t);

async function act(key: string, action: () => Promise<void>) {
    if (busy.value) return;
    busy.value = key;
    try {
        await action();
    } catch {
        // useApi a déjà affiché l'erreur (toast) ; la liste reste telle quelle.
    } finally {
        busy.value = null;
    }
}

const revoke = (row: SessionRow) => act(row.id, async () => {
    await api.revokeSession(row.id);
    sessions.value = sessions.value.filter((item) => item.id !== row.id);
    toast.success(t('profile.settings.toasts.session_revoked'));
});

const revokeOthers = () => act('others', async () => {
    await api.revokeOtherSessions();
    sessions.value = sessions.value.filter((item) => item.current);
    toast.success(t('profile.settings.toasts.sessions_revoked'));
});

const revokeDevice = (row: DeviceRow) => act(row.device_uuid, async () => {
    await api.revokeDevice(row.device_uuid);
    devices.value = devices.value.map((item) => (item.device_uuid === row.device_uuid ? { ...item, revoked: true } : item));
    toast.success(t('profile.settings.toasts.device_revoked'));
});

async function refresh() {
    try {
        sessions.value = await api.sessions();
    } catch {
        // Liste gardée : elle se relit au prochain passage sur la page.
    }
}

defineExpose({ refresh });
</script>

<template>
    <section id="appareils" class="card" aria-labelledby="appareils-title">
        <div class="card-header border-0 pb-0"><h2 id="appareils-title" class="h6 mb-0">{{ t('profile.settings.sessions.title') }}</h2></div>
        <div class="card-body">
            <p class="small text-secondary">{{ t('profile.settings.sessions.intro') }}</p>
            <ul class="list-group list-group-flush mb-3" data-xm-sessions>
                <li v-for="row in sessions" :key="row.id" class="list-group-item px-0 d-flex flex-wrap align-items-center gap-2">
                    <i class="bi fs-5" :class="row.kind === 'web' ? 'bi-laptop' : 'bi-phone'" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <p class="mb-0 text-truncate">{{ row.name }} <span class="small text-secondary">· {{ t(`profile.settings.sessions.kind_${row.kind}`) }}</span></p>
                        <p class="small text-secondary mb-0"><span v-if="row.current" class="badge bg-success-subtle text-success-emphasis me-1">{{ t('profile.settings.sessions.current') }}</span><template v-if="row.last_active_at">{{ t('profile.settings.sessions.last_active', { ago: ago(row.last_active_at) }) }}</template></p>
                    </div>
                    <XmButton v-if="!row.current" variant="outline-secondary" size="sm" :loading="busy === row.id" :aria-label="t('profile.settings.sessions.revoke_label', { name: row.name })" @click="revoke(row)">{{ t('profile.settings.sessions.revoke') }}</XmButton>
                </li>
            </ul>
            <p v-if="others.length === 0" class="small text-secondary">{{ t('profile.settings.sessions.empty') }}</p>
            <XmButton v-else variant="danger-soft" size="sm" icon="box-arrow-right" :loading="busy === 'others'" @click="revokeOthers">{{ t('profile.settings.sessions.revoke_all') }}</XmButton>

            <h3 class="h6 mt-4">{{ t('profile.settings.devices.title') }}</h3>
            <p class="small text-secondary">{{ t('profile.settings.devices.intro') }}</p>
            <ul v-if="devices.length" class="list-group list-group-flush" data-xm-devices>
                <li v-for="row in devices" :key="row.device_uuid" class="list-group-item px-0 d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-phone fs-5" aria-hidden="true"></i>
                    <div class="flex-grow-1 min-w-0">
                        <p class="mb-0 text-truncate">{{ row.label || row.platform || row.device_uuid }}<span v-if="row.app_version" class="small text-secondary"> · {{ row.app_version }}</span></p>
                        <p v-if="row.last_seen_at" class="small text-secondary mb-0">{{ t('profile.settings.devices.last_seen', { ago: ago(row.last_seen_at) }) }}</p>
                    </div>
                    <span v-if="row.revoked" class="badge bg-secondary-subtle text-secondary-emphasis">{{ t('profile.settings.devices.revoked') }}</span>
                    <XmButton v-else variant="outline-secondary" size="sm" :loading="busy === row.device_uuid" :aria-label="t('profile.settings.devices.revoke_label', { name: row.label || row.platform || row.device_uuid })" @click="revokeDevice(row)">{{ t('profile.settings.devices.revoke') }}</XmButton>
                </li>
            </ul>
            <p v-else class="small text-secondary mb-0">{{ t('profile.settings.devices.empty') }}</p>
        </div>
    </section>
</template>
