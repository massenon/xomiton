<script setup lang="ts">
// Bouton ou lien-bouton : même HTML que <x-button> (resources/views/components/button.blade.php).
// variant : primary | secondary (rouge : attention) | danger | outline | outline-secondary | ghost | light | primary-soft
//           | danger-soft | soft (alias primary-soft) | icon (btn-light carré, label obligatoire)
// size : sm | md | lg ; href → <a> (désactivé : aria-disabled + tabindex -1) ; inertia → <Link> (visite Inertia) au lieu
// d'un <a> simple (les écrans encore en Blade se rejoignent par un <a>) ; loading → spinner + « Envoi… », aria-busy.
import { computed, useAttrs } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineOptions({ inheritAttrs: false });

const props = withDefaults(defineProps<{
    variant?: string;
    size?: 'sm' | 'md' | 'lg';
    href?: string | null;
    inertia?: boolean;
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
    icon?: string | null;
    label?: string | null;
}>(), { variant: 'primary', size: 'md', href: null, inertia: false, type: 'button', loading: false, disabled: false, icon: null, label: null });

const VARIANTS: Record<string, string> = {
    primary: 'btn-primary', secondary: 'btn-secondary', danger: 'btn-danger',
    outline: 'btn-outline-primary', 'outline-secondary': 'btn-outline-secondary',
    ghost: 'btn-link text-decoration-none', light: 'btn-light',
    'primary-soft': 'btn-primary-soft', soft: 'btn-primary-soft', 'danger-soft': 'btn-danger-soft', icon: 'btn-light',
};
const SIZES: Record<string, string | null> = { sm: 'btn-sm', md: null, lg: 'btn-lg' };

if (props.variant === 'icon' && !props.label) {
    throw new Error('<XmButton variant="icon"> exige une prop label (nom accessible).');
}

const { t } = useI18n();
const attrs = useAttrs();
const isIcon = computed(() => props.variant === 'icon');
const isDisabled = computed(() => props.loading || props.disabled);
const tag = computed(() => (props.href ? (props.inertia ? Link : 'a') : 'button'));
const classes = computed(() => ['btn', VARIANTS[props.variant] ?? 'btn-primary', SIZES[props.size] ?? null, {
    'xm-btn-icon': isIcon.value,
    disabled: isDisabled.value && Boolean(props.href),
}]);
const bound = computed(() => {
    const extra: Record<string, unknown> = props.href ? { href: props.href } : { type: props.type };
    if (props.label) Object.assign(extra, { 'aria-label': props.label, title: props.label });
    if (props.loading) extra['aria-busy'] = 'true';
    if (isDisabled.value) Object.assign(extra, props.href ? { 'aria-disabled': 'true', tabindex: '-1' } : { disabled: true });
    return { ...attrs, ...extra };
});
</script>

<template>
    <component :is="tag" v-bind="bound" :class="classes">
        <span v-if="loading" :class="['spinner-border spinner-border-sm', { 'me-1': !isIcon }]" aria-hidden="true"></span>
        <i v-else-if="icon" :class="['bi', `bi-${icon}`, { 'me-1': !isIcon }]" aria-hidden="true"></i>
        <template v-if="!isIcon">{{ loading ? t('common.sending') : '' }}<slot v-if="!loading" /></template>
    </component>
</template>
