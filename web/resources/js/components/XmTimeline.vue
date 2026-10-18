<script setup lang="ts">
// Chronologie d'un dossier : même HTML que <x-timeline> (resources/views/components/timeline.blade.php, frise de suivi
// du prototype admin/suivi.php) — liste ordonnée bordée, point coloré par étape, étape en gras + badge de résultat,
// « date · par », commentaire, prochain suivi, « En retard » en rouge (jamais la couleur seule : badge en toutes lettres).
// items : [{ title, date, datetime, by, kind (internal | shared | status : annoncé aux lecteurs d'écran, couleur du
// point), badge { label, color }, text, next, late, color (prime sur kind) }] ; label (nom de la liste) ; empty.
import { useI18n } from 'vue-i18n';
import XmBadge from './XmBadge.vue';

export interface TimelineItem {
    title: string;
    date?: string | null;
    datetime?: string | null;
    by?: string | null;
    kind?: 'internal' | 'shared' | 'status' | null;
    badge?: { label: string; color?: string | null } | null;
    text?: string | null;
    next?: string | null;
    late?: boolean;
    color?: string | null;
}

withDefaults(defineProps<{
    items?: TimelineItem[];
    label?: string | null;
    empty?: string | null;
}>(), { items: () => [], label: null, empty: null });

const { t } = useI18n();
const KIND_COLORS: Record<string, string> = { internal: 'neutral', shared: 'primary', status: 'info' };

function dotClass(item: TimelineItem): string {
    const color = item.late ? 'danger' : (item.color ?? KIND_COLORS[item.kind ?? ''] ?? 'primary');
    return color === 'neutral' ? 'xm-timeline__dot--neutral' : `bg-${color}`;
}
</script>

<template>
    <p v-if="items.length === 0" class="text-secondary mb-0">{{ empty ?? t('components.timeline.empty') }}</p>
    <ol v-else class="xm-timeline list-unstyled mb-0 ms-2 border-start border-2 ps-3" :aria-label="label ?? t('components.timeline.label')">
        <li v-for="(item, i) in items" :key="i" class="xm-timeline__item mb-3 position-relative">
            <span :class="['xm-timeline__dot position-absolute rounded-circle', dotClass(item)]" aria-hidden="true"></span>
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <span v-if="item.kind" class="visually-hidden">{{ t(`components.timeline.kinds.${item.kind}`) }} : </span>
                    <span class="fw-semibold">{{ item.title }}</span>
                    <XmBadge v-if="item.badge" :color="item.badge.color ?? 'neutral'" class="ms-1">{{ item.badge.label }}</XmBadge>
                    <XmBadge v-if="item.late" color="danger" class="ms-1">{{ t('components.timeline.late') }}</XmBadge>
                </div>
                <span v-if="item.date || item.by" class="small text-secondary"><time v-if="item.date" :datetime="item.datetime || undefined">{{ item.date }}</time> <template v-if="item.by">· {{ item.by }}</template></span>
            </div>
            <div v-if="item.text" class="small">{{ item.text }}</div>
            <div v-if="item.next" :class="['small', item.late ? 'text-danger fw-semibold' : 'text-secondary']"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ t('components.timeline.next', { date: item.next }) }}</div>
        </li>
    </ol>
</template>
