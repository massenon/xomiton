<script setup lang="ts">
// Résumé d'erreurs de formulaire : même HTML que <x-error-summary> (.alert.alert-danger role="alert", focalisé après un
// envoi raté, un lien par champ vers #f-{nom}, l'identifiant de <XmField>).
// messages : { champ: 'message' | ['message', …] } (fieldErrors() de useApi, erreurs Inertia) ; title ; autofocus.
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    messages?: Record<string, string | string[]>;
    title?: string | null;
    autofocus?: boolean;
}>(), { messages: () => ({}), title: null, autofocus: true });

const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const list = computed(() => Object.entries(props.messages)
    .map(([key, value]) => [key, Array.isArray(value) ? (value[0] ?? '') : value] as const)
    .filter(([, message]) => message !== ''));
const anchor = (key: string) => `#f-${key.replace(/[[\].]/g, '-')}`;
const focus = () => nextTick(() => root.value?.focus());

onMounted(() => { if (props.autofocus && list.value.length > 0) focus(); });
watch(() => list.value.length, (count, before) => { if (props.autofocus && count > 0 && count !== before) focus(); });
</script>

<template>
    <div v-if="list.length > 0" ref="root" class="alert alert-danger xm-error-summary" role="alert" tabindex="-1">
        <p class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ title ?? t('components.error_summary.title', { count: list.length }, list.length) }}</p>
        <ul class="mb-0">
            <li v-for="[key, message] in list" :key="key"><a class="alert-link" :href="anchor(key)">{{ message }}</a></li>
        </ul>
    </div>
</template>
