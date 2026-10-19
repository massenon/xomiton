<script setup lang="ts">
// Section « Apparence » de /parametres (P8-09, prototype settings.php `data-theme-set`) : clair, sombre, automatique.
// Appliqué tout de suite et mémorisé sur l'appareil (settings/theme.ts, même clé que shared/theme.js), puis enregistré
// sur le compte (PATCH me/settings {theme}) pour l'application mobile ; l'échec de l'enregistrement n'annule pas le
// thème de l'appareil.
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { settingsApi } from '@/settings/api';
import { THEMES, applyTheme, storedTheme } from '@/settings/theme';
import type { Theme } from '@/settings/types';

const props = defineProps<{ theme: Theme }>();

const ICONS: Record<Theme, string> = { light: 'sun', dark: 'moon-stars', auto: 'circle-half' };

const { t } = useI18n();
const api = settingsApi(useApi());
const toast = useToast();
const current = ref<Theme>(storedTheme(props.theme));

async function choose(theme: Theme) {
    current.value = theme;
    applyTheme(theme);
    toast.success(t('profile.settings.appearance.applied'));
    await api.updateSettings({ theme }).catch(() => undefined);
}
</script>

<template>
    <section id="apparence" class="card" aria-labelledby="apparence-title">
        <div class="card-header border-0 pb-0"><h2 id="apparence-title" class="h6 mb-0">{{ t('profile.settings.sections.appearance') }}</h2></div>
        <div class="card-body">
            <p class="small text-secondary">{{ t('profile.settings.appearance.help') }}</p>
            <div class="btn-group flex-wrap" role="group" :aria-label="t('profile.settings.appearance.title')">
                <button v-for="theme in THEMES" :key="theme" type="button" class="btn btn-outline-primary" :class="{ active: current === theme }" :aria-pressed="current === theme ? 'true' : 'false'" :data-theme-set="theme" @click="choose(theme)"><i class="bi me-1" :class="`bi-${ICONS[theme]}`" aria-hidden="true"></i>{{ t(`profile.settings.appearance.${theme}`) }}</button>
            </div>
        </div>
    </section>
</template>
