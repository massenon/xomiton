<script setup lang="ts">
// Page `/parametres` (lot parametres-notifications, P8-09 ; prototype settings.php l.100-101), page Inertia
// `Member/Settings` dans le gabarit membre (col-lg-9 à côté du sidenav, comme le prototype) : menu des sections
// (col-lg-3, collant) et sections dans l'ordre du prototype — Profil · Mot de passe · Confidentialité ·
// Notifications · Langue · Apparence · Sécurité · Appareils connectés · Newsletter · Mes données · Supprimer mon
// compte ; *sans* sortie rapide (D8). Chaque section enregistre seule par l'API me/* (P6-06, P6-09, P8-09) et garde
// son état ; les ancres (#motdepasse, #securite…) sont celles du prototype.
// Props : SettingsPageController (title, identity, zones, privacy, visibilities, notifications, security,
// appearance, language, newsletter, sessions, devices, limits, can).
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { DeviceRow, Identity, LanguageSettings, Limits, Newsletter, NotificationSettings, Privacy, Security, SessionRow, Theme, ZoneOption } from '@/settings/types';
import SettingsAppearance from '@/components/settings/SettingsAppearance.vue';
import SettingsData from '@/components/settings/SettingsData.vue';
import SettingsLanguage from '@/components/settings/SettingsLanguage.vue';
import SettingsNewsletter from '@/components/settings/SettingsNewsletter.vue';
import SettingsNotifications from '@/components/settings/SettingsNotifications.vue';
import SettingsPassword from '@/components/settings/SettingsPassword.vue';
import SettingsPrivacy from '@/components/settings/SettingsPrivacy.vue';
import SettingsProfile from '@/components/settings/SettingsProfile.vue';
import SettingsSecurity from '@/components/settings/SettingsSecurity.vue';
import SettingsSessions from '@/components/settings/SettingsSessions.vue';

defineProps<{
    title: string;
    identity: Identity;
    zones: ZoneOption[];
    privacy: Privacy;
    visibilities: { value: string; label: string }[];
    notifications: NotificationSettings;
    security: Security;
    appearance: { theme: Theme };
    language: LanguageSettings;
    newsletter: Newsletter;
    sessions: SessionRow[];
    devices: DeviceRow[];
    limits: Limits;
    can: { delete_account: boolean };
}>();

const { t } = useI18n();

const SECTIONS = [
    { id: 'profil', icon: 'person', label: 'profile.settings.sections.profile' },
    { id: 'motdepasse', icon: 'key', label: 'profile.settings.sections.password' },
    { id: 'confidentialite', icon: 'lock', label: 'profile.settings.sections.privacy' },
    { id: 'notifications', icon: 'bell', label: 'profile.settings.sections.notifications' },
    { id: 'langue', icon: 'translate', label: 'profile.settings.sections.language' },
    { id: 'apparence', icon: 'palette', label: 'profile.settings.sections.appearance' },
    { id: 'securite', icon: 'shield-lock', label: 'profile.settings.sections.security' },
    { id: 'appareils', icon: 'phone', label: 'profile.settings.sections.sessions' },
    { id: 'newsletter', icon: 'envelope', label: 'profile.settings.sections.newsletter' },
    { id: 'donnees', icon: 'download', label: 'profile.settings.sections.data' },
    { id: 'supprimer', icon: 'trash', label: 'profile.settings.sections.delete', danger: true },
];

const devicesSection = ref<InstanceType<typeof SettingsSessions> | null>(null);
const onPasswordChanged = () => { devicesSection.value?.refresh(); };
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9">
        <div class="row g-4">
            <div class="col-lg-3">
                <nav class="card xm-settings-nav" :aria-label="t('profile.settings.nav_label')">
                    <div class="list-group list-group-flush rounded">
                        <a v-for="section in SECTIONS" :key="section.id" class="list-group-item list-group-item-action" :class="{ 'text-danger': section.danger }" :href="`#${section.id}`"><i class="bi me-2" :class="`bi-${section.icon}`" aria-hidden="true"></i>{{ t(section.label) }}</a>
                    </div>
                </nav>
            </div>
            <div class="col-lg-9 vstack gap-4">
                <div>
                    <h1 class="h4 mb-1">{{ title }}</h1>
                    <p class="small text-secondary mb-0">{{ t('profile.settings.intro') }}</p>
                </div>
                <SettingsProfile :identity="identity" :zones="zones" :limits="limits" />
                <SettingsPassword :min="limits.password_min" @changed="onPasswordChanged" />
                <SettingsPrivacy :privacy="privacy" :visibilities="visibilities" />
                <SettingsNotifications :notifications="notifications" />
                <SettingsLanguage :language="language" />
                <SettingsAppearance :theme="appearance.theme" />
                <SettingsSecurity :security="security" :title="title" />
                <SettingsSessions ref="devicesSection" :sessions="sessions" :devices="devices" />
                <SettingsNewsletter :newsletter="newsletter" />
                <SettingsData :limits="limits" :can-delete="can.delete_account" />
            </div>
        </div>
    </div>
</template>
