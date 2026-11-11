<script setup lang="ts">
// Badge : même HTML que <x-badge> (resources/views/components/badge.blade.php). Toujours un libellé en toutes lettres.
// color : primary | secondary | danger | success | warning | info | violet | neutral ; soft : fond 10 % ; icon ; pill.
// scope + status : statut prêt à l'emploi (libellé, couleur, icône de lang/fr/components.php « status », exporté à plat
// dans resources/js/lang/fr.json) : case, alert, priority, alert_type.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    color?: string;
    soft?: boolean;
    icon?: string | null;
    pill?: boolean;
    scope?: string | null;
    status?: string | null;
}>(), { color: 'primary', soft: false, icon: null, pill: false, scope: null, status: null });

// Booléens de lang/fr/components.php (« soft ») : l'export JSON ne garde que les chaînes.
const SOFT_STATUSES = new Set(['alert_type.denonciation', 'alert_type.besoin']);

const { t, te } = useI18n();

const resolved = computed(() => {
    if (props.scope === null || props.status === null) {
        return { text: null as string | null, color: props.color, icon: props.icon, soft: props.soft };
    }
    const base = `components.status.${props.scope}.${props.status}`;
    if (!te(`${base}.label`)) return { text: String(props.status), color: 'neutral', icon: props.icon, soft: props.soft };
    return {
        text: t(`${base}.label`),
        color: t(`${base}.color`),
        icon: te(`${base}.icon`) ? t(`${base}.icon`) : props.icon,
        soft: SOFT_STATUSES.has(`${props.scope}.${props.status}`) || props.soft,
    };
});

const classes = computed(() => {
    const { color, soft, icon } = resolved.value;
    let palette: string;
    if (color === 'violet') palette = 'xm-badge-violet';
    else if (color === 'neutral') palette = 'bg-light text-body border';
    else if (soft) palette = `bg-${color} bg-opacity-10 text-${color}`;
    else if (color === 'warning') palette = 'bg-warning text-dark';
    else palette = `bg-${color}`;
    return ['badge', palette, { 'rounded-pill': props.pill, 'd-inline-flex align-items-center gap-1': Boolean(icon) }];
});
</script>

<template>
    <span :class="classes"><i v-if="resolved.icon" :class="`bi bi-${resolved.icon}`" aria-hidden="true"></i><template v-if="resolved.text !== null">{{ resolved.text }}</template><slot v-else /></span>
</template>
