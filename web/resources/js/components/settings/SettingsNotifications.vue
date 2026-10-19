<script setup lang="ts">
// Section « Notifications » de /parametres (P8-09, NOTIFICATIONS_INSTRUCTION.md §4.3) : un interrupteur par catégorie
// et par canal (cloche, e-mail, push ; App\Application\Shared\NotificationPreferences), sécurité du compte et SOS
// verrouillés (cochés, désactivés, « Toujours actif » écrit : jamais la seule couleur), résumé d'activité jamais ·
// quotidien · hebdomadaire. PATCH me/settings n'envoie que les catégories modifiables.
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { ChannelPrefs, NotificationSettings } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

const props = defineProps<{ notifications: NotificationSettings }>();

const { t } = useI18n();
const api = settingsApi(useApi());
const { saving, errors, run } = useSection();

const prefs = reactive<Record<string, ChannelPrefs>>(JSON.parse(JSON.stringify(props.notifications.prefs)));
const digest = ref(props.notifications.digest);
const digestOptions = computed(() => Object.fromEntries(props.notifications.digests.map((key) => [key, t(`notifications.preferences.digest_options.${key}`)])));
const channelLabel = (channel: string) => t(`notifications.channels.${channel}`);
const categoryLabel = (category: string) => t(`notifications.categories.${category}`);

async function submit() {
    const editable = Object.fromEntries(props.notifications.categories.filter((c) => !c.locked).map((c) => [c.key, { ...prefs[c.key] }]));
    await run(() => api.updateSettings({ notification_prefs: editable, notification_digest: digest.value }), t('profile.settings.toasts.saved'));
}
</script>

<template>
    <section id="notifications" class="card" aria-labelledby="notifications-title">
        <div class="card-header border-0 pb-0"><h2 id="notifications-title" class="h6 mb-0">{{ t('notifications.preferences.title') }}</h2></div>
        <div class="card-body">
            <p class="small text-secondary">{{ t('notifications.preferences.intro') }}</p>
            <form novalidate @submit.prevent="submit">
                <div class="table-responsive mb-3">
                    <table class="table align-middle mb-0">
                        <caption class="visually-hidden">{{ t('profile.settings.notifications.caption') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ t('profile.settings.notifications.category') }}</th>
                                <th v-for="channel in notifications.channels" :key="channel" scope="col" class="text-center small">{{ channelLabel(channel) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="category in notifications.categories" :key="category.key" :data-category="category.key">
                                <th scope="row" class="fw-normal">
                                    {{ categoryLabel(category.key) }}
                                    <span v-if="category.locked" class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ t('profile.settings.notifications.locked') }}</span>
                                </th>
                                <td v-for="channel in notifications.channels" :key="channel" class="text-center">
                                    <div class="form-check form-switch d-inline-block m-0">
                                        <input
                                            :id="`np-${category.key}-${channel}`"
                                            v-model="prefs[category.key][channel]"
                                            class="form-check-input"
                                            type="checkbox"
                                            role="switch"
                                            :name="`notification_prefs[${category.key}][${channel}]`"
                                            :disabled="category.locked"
                                            :aria-label="t('profile.settings.notifications.channel_for', { channel: channelLabel(channel), category: categoryLabel(category.key) })"
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <XmField v-model="digest" name="notification_digest" type="select" :label="t('notifications.preferences.digest')" :options="digestOptions" :error="errors.notification_digest" />
                <div class="text-end"><XmButton type="submit" :loading="saving">{{ t('profile.settings.save') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
