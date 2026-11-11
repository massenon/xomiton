<script setup lang="ts">
// « Appareils connectés » de /parametres (P8-09, P2-06, P6-09) : sessions web et jetons de l'application
// (me/sessions, « Cet appareil » jamais révocable d'ici), « Déconnecter tous les autres appareils » (DELETE
// me/sessions), puis téléphones reliés à l'application (me/devices : retirer coupe synchronisation et push).
// Effacer à distance (téléphone perdu ou volé, POST me/devices/{uuid}/wipe) : confirmation forte en ligne — bouton qui
// déplie un avertissement et le mot de passe ressaisi ; Échap ou « Annuler » referme et rend le focus au bouton ; l'appareil
// passe à « Effacement demandé » (ordre en attente d'accusé) puis « Effacé ». `refresh()` relit la liste (après un changement de mot de passe, qui ferme les autres accès).
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { timeAgo } from '@/feed/format';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { DeviceRow, SessionRow } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

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

const wiping = useSection();
const wipeFor = ref<string | null>(null);
const wipePassword = ref('');
const deviceName = (row: DeviceRow) => row.label || row.platform || row.device_uuid;
const wipeState = (row: DeviceRow): 'wiped' | 'pending' | null => (row.wiped_at ? 'wiped' : row.wipe || row.wipe_requested_at ? 'pending' : null);

function openWipe(row: DeviceRow) {
    wipePassword.value = '';
    wiping.errors.value = {};
    wipeFor.value = row.device_uuid;
    nextTick(() => document.getElementById(`wipe-password-${row.device_uuid}`)?.focus());
}

function closeWipe() {
    const uuid = wipeFor.value;
    wipeFor.value = null;
    wipePassword.value = '';
    wiping.errors.value = {};
    if (uuid) nextTick(() => document.getElementById(`wipe-open-${uuid}`)?.focus());
}

async function wipe(row: DeviceRow) {
    if (!wipePassword.value) {
        wiping.errors.value = { password: t('profile.settings.devices.wipe_password_required') };
        return;
    }
    const ok = await wiping.run(async () => {
        const updated = await api.wipeDevice(row.device_uuid, wipePassword.value);
        devices.value = devices.value.map((item) => (item.device_uuid === row.device_uuid ? { ...item, ...updated, revoked: true } : item));
    }, t('profile.settings.toasts.device_wipe_requested'));
    if (ok) closeWipe();
}

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
                    <span v-if="wipeState(row) === 'wiped'" class="badge bg-secondary-subtle text-secondary-emphasis" data-xm-wipe-state="wiped">{{ t('profile.settings.devices.wiped') }}</span>
                    <span v-else-if="wipeState(row) === 'pending'" class="badge bg-warning-subtle text-warning-emphasis" role="status" data-xm-wipe-state="pending">{{ t('profile.settings.devices.wipe_pending') }}</span>
                    <template v-else>
                        <span v-if="row.revoked" class="badge bg-secondary-subtle text-secondary-emphasis">{{ t('profile.settings.devices.revoked') }}</span>
                        <XmButton v-else variant="outline-secondary" size="sm" :loading="busy === row.device_uuid" :aria-label="t('profile.settings.devices.revoke_label', { name: deviceName(row) })" @click="revokeDevice(row)">{{ t('profile.settings.devices.revoke') }}</XmButton>
                        <XmButton :id="`wipe-open-${row.device_uuid}`" variant="danger-soft" size="sm" icon="eraser" :aria-expanded="wipeFor === row.device_uuid" :aria-controls="`wipe-panel-${row.device_uuid}`" :aria-label="t('profile.settings.devices.wipe_label', { name: deviceName(row) })" @click="wipeFor === row.device_uuid ? closeWipe() : openWipe(row)">{{ t('profile.settings.devices.wipe') }}</XmButton>
                    </template>
                    <form v-if="wipeFor === row.device_uuid" :id="`wipe-panel-${row.device_uuid}`" class="w-100 border border-danger-subtle rounded p-3 mt-1" role="group" :aria-labelledby="`wipe-title-${row.device_uuid}`" novalidate @submit.prevent="wipe(row)" @keydown.esc.stop="closeWipe">
                        <p :id="`wipe-title-${row.device_uuid}`" class="fw-semibold text-danger mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ t('profile.settings.devices.wipe_title', { name: deviceName(row) }) }}</p>
                        <p class="small mb-2">{{ t('profile.settings.devices.wipe_warning') }}</p>
                        <XmField v-model="wipePassword" :id="`wipe-password-${row.device_uuid}`" name="wipe_password" type="password" :label="t('profile.settings.password.current')" :hint="t('profile.settings.devices.wipe_password_help')" :error="wiping.errors.value.password ?? wiping.errors.value.device_uuid" autocomplete="current-password" required />
                        <div class="d-flex flex-wrap justify-content-end gap-2">
                            <XmButton variant="light" size="sm" :disabled="wiping.saving.value" @click="closeWipe">{{ t('common.cancel') }}</XmButton>
                            <XmButton type="submit" variant="danger" size="sm" icon="eraser" :loading="wiping.saving.value">{{ t('profile.settings.devices.wipe_confirm') }}</XmButton>
                        </div>
                    </form>
                </li>
            </ul>
            <p v-else class="small text-secondary mb-0">{{ t('profile.settings.devices.empty') }}</p>
        </div>
    </section>
</template>
