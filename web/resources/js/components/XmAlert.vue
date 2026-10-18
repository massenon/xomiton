<script setup lang="ts">
// Message contextuel : même HTML que <x-alert> (.alert.alert-{type}, icône + texte, jamais la couleur seule).
// type : info | success | warning | danger | primary ; title ; dismissible (bouton Fermer, émet `dismiss`) ;
// icon : nom Bootstrap Icons, null = icône du type, false = aucune. Rôle status (info, succès) ou alert (warning, danger).
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    type?: string;
    title?: string | null;
    dismissible?: boolean;
    icon?: string | false | null;
}>(), { type: 'info', title: null, dismissible: false, icon: null });

const emit = defineEmits<{ dismiss: [] }>();

const ICONS: Record<string, string> = { primary: 'info-circle', info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', danger: 'exclamation-octagon' };
const { t } = useI18n();
const visible = ref(true);
const iconName = computed(() => (props.icon === false ? null : (props.icon ?? ICONS[props.type] ?? 'info-circle')));
const role = computed(() => (['danger', 'warning'].includes(props.type) ? 'alert' : 'status'));

function close() {
    visible.value = false;
    emit('dismiss');
}
</script>

<template>
    <div v-if="visible" :class="['alert', `alert-${type}`, 'd-flex align-items-start gap-2', { 'alert-dismissible fade show': dismissible }]" :role="role">
        <i v-if="iconName" :class="`bi bi-${iconName} flex-shrink-0`" aria-hidden="true"></i>
        <div class="flex-grow-1"><p v-if="title" class="fw-bold mb-1">{{ title }}</p><slot /></div>
        <button v-if="dismissible" type="button" class="btn-close" :aria-label="t('common.close')" @click="close"></button>
    </div>
</template>
