<script setup lang="ts">
// Onglets : même HTML que <x-tabs> (pattern ARIA tablist : aria-selected, tabindex roulant, flèches gauche / droite,
// Début / Fin ; .nav.nav-pills.gap-2). L'état est tenu par Vue (v-model:active), pas par Bootstrap Tab. L'onglet actif
// est gardé dans l'adresse (?tab=, history.replaceState : l'état Inertia de l'historique est conservé).
// tabs : { cle: 'Titre' } (les clés sont les noms des slots des panneaux) ; label ; id (préfixe) ; active ; pills ;
// param (paramètre d'adresse, null = aucun).
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    tabs: Record<string, string>;
    label?: string | null;
    id?: string;
    active?: string | null;
    pills?: boolean;
    param?: string | null;
}>(), { label: null, id: 'tab', active: null, pills: true, param: 'tab' });

const emit = defineEmits<{ 'update:active': [key: string] }>();

const { t } = useI18n();
const keys = computed(() => Object.keys(props.tabs));
const fromUrl = (): string | null => {
    if (!props.param || typeof window === 'undefined') return null;
    const value = new URLSearchParams(window.location.search).get(props.param);
    return value !== null && value in props.tabs ? value : null;
};
const current = ref<string>(fromUrl() ?? (props.active && props.active in props.tabs ? props.active : (keys.value[0] ?? '')));
const buttons = ref<Record<string, HTMLButtonElement | null>>({});

watch(() => props.active, (value) => { if (value && value in props.tabs) current.value = value; });

function select(key: string, focus = false) {
    if (!(key in props.tabs)) return;
    current.value = key;
    emit('update:active', key);
    if (props.param && typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set(props.param, key);
        window.history.replaceState(window.history.state, '', url.toString());
    }
    if (focus) nextTick(() => buttons.value[key]?.focus());
}

function onKeydown(event: KeyboardEvent, key: string) {
    const list = keys.value;
    const index = list.indexOf(key);
    const moves: Record<string, number> = { ArrowRight: index + 1, ArrowDown: index + 1, ArrowLeft: index - 1, ArrowUp: index - 1, Home: 0, End: list.length - 1 };
    if (!(event.key in moves)) return;
    event.preventDefault();
    const next = list[(moves[event.key] + list.length) % list.length];
    if (next !== undefined) select(next, true);
}
</script>

<template>
    <div class="xm-tabs">
        <ul :class="['nav', pills ? 'nav-pills' : 'nav-tabs', 'gap-2 mb-4']" role="tablist" :aria-label="label ?? t('components.tabs.label')">
            <li v-for="(title, key) in tabs" :key="key" class="nav-item" role="presentation">
                <button :ref="(el) => { buttons[key] = el as HTMLButtonElement | null; }" :class="['nav-link', { active: key === current }]" :id="`${id}-${key}`" type="button" role="tab" :aria-controls="`${id}-${key}-panel`" :aria-selected="key === current ? 'true' : 'false'" :tabindex="key === current ? undefined : '-1'" @click="select(String(key))" @keydown="onKeydown($event, String(key))">{{ title }}</button>
            </li>
        </ul>
        <div class="tab-content">
            <div v-for="(title, key) in tabs" :key="key" :class="['tab-pane fade', { 'show active': key === current }]" :id="`${id}-${key}-panel`" role="tabpanel" :aria-labelledby="`${id}-${key}`" tabindex="0"><slot :name="key" /></div>
        </div>
    </div>
</template>
