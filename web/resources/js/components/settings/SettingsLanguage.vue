<script setup lang="ts">
// Section « Langue » de /parametres (P8-09, NFR-11, Q40) : seules les locales complètes de config('xomiton.locales')
// sont choisissables, les autres sont annoncées « (bientôt) » et désactivées. PATCH me/settings {locale}, puis le
// catalogue vue-i18n de la locale est chargé et appliqué sans recharger la page (<html lang> compris).
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { i18n, loadLocale } from '@/app/i18n';
import { useSessionStore } from '@/stores/session';
import { settingsApi } from '@/settings/api';
import { useSection } from '@/settings/useSection';
import type { LanguageSettings } from '@/settings/types';
import XmButton from '@/components/XmButton.vue';

const props = defineProps<{ language: LanguageSettings }>();

const ORDER = ['fr', 'fon', 'yo', 'en'];

const { t } = useI18n();
const api = settingsApi(useApi());
const session = useSessionStore();
const { saving, errors, run } = useSection();

const locale = ref(props.language.current);
const options = [...new Set([...ORDER, ...props.language.available, ...props.language.planned])]
    .filter((code) => props.language.available.includes(code) || props.language.planned.includes(code))
    .map((code) => {
        const available = props.language.available.includes(code);
        const name = t(`profile.settings.language.options.${code}`);
        return { code, available, label: available ? name : `${name} ${t('profile.settings.language.soon')}` };
    });

async function submit() {
    await run(async () => {
        await api.updateSettings({ locale: locale.value });
        const applied = await loadLocale(locale.value, 'fr');
        session.locale = applied;
        document.documentElement.lang = i18n.global.locale.value;
    }, t('profile.settings.toasts.language_saved'));
}
</script>

<template>
    <section id="langue" class="card" aria-labelledby="langue-title">
        <div class="card-header border-0 pb-0"><h2 id="langue-title" class="h6 mb-0">{{ t('profile.settings.sections.language') }}</h2></div>
        <div class="card-body">
            <form class="row g-3 align-items-end" novalidate @submit.prevent="submit">
                <div class="col-md-8">
                    <label class="form-label" for="f-locale">{{ t('profile.settings.language.title') }}</label>
                    <select id="f-locale" v-model="locale" class="form-select" :class="{ 'is-invalid': errors.locale }" name="locale" aria-describedby="f-locale-hint" :aria-invalid="errors.locale ? 'true' : undefined">
                        <option v-for="option in options" :key="option.code" :value="option.code" :disabled="!option.available">{{ option.label }}</option>
                    </select>
                    <div id="f-locale-hint" class="form-text">{{ t('profile.settings.language.help') }}</div>
                    <div v-if="errors.locale" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.locale }}</div>
                </div>
                <div class="col-md-4 mb-md-4"><XmButton type="submit" class="w-100" :loading="saving">{{ t('profile.settings.save') }}</XmButton></div>
            </form>
        </div>
    </section>
</template>
