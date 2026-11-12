<script setup lang="ts">
// Plan de travail d'une case dans l'éditeur des conversations en BD (P20-07) : la case rendue par XmBdPanel (même SVG
// que la page publique, bulles dimensionnées d'après leur texte) et, par-dessus, une poignée par bulle et une par pointe
// de queue. Déplacer : **glisser** (souris, doigt, stylet : Pointer Events) ou **clavier** sur la poignée (flèches : 2 %,
// Maj + flèches : 10 %). Chaque poignée est un bouton nommé (« Bulle 2 : … ») ; la position est annoncée (aria-live).
// Cibles d'au moins 44 px (pointe de queue comprise).
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import XmBdPanel from '../XmBdPanel.vue';
import { boxPercent } from '../bubbles';
import { arrowDelta, moveBubble, moveTail, placeBubble, type EditorBubble } from '../editor';

const props = withDefaults(defineProps<{
    bubbles: EditorBubble[];
    names: Record<string, string>;
    image?: string | null;
    alt?: string;
    n?: number;
    count?: number;
    title?: string;
    selected?: number | null;
    readonly?: boolean;
}>(), { image: null, alt: '', n: 1, count: 1, title: '', selected: null, readonly: false });

const emit = defineEmits<{ select: [index: number]; update: [index: number, bubble: EditorBubble] }>();

const { t } = useI18n();
const overlay = ref<HTMLElement | null>(null);
const announce = ref('');
const drag = ref<{ index: number; kind: 'bubble' | 'tail'; startX: number; startY: number; origin: EditorBubble; pointer: number } | null>(null);

const boxes = computed(() => props.bubbles.map((bubble) => boxPercent(bubble)));
const tails = computed(() => props.bubbles.map((bubble) => (bubble.queue && !String(bubble.queue.vers).startsWith('bord-') && bubble.queue.x !== null && bubble.queue.y !== null
    ? { x: Number(bubble.queue.x), y: Number(bubble.queue.y) }
    : null)));

const label = (bubble: EditorBubble, index: number): string => {
    const who = bubble.type === 'cartouche' ? t('conversations_bd.editor.narration') : (bubble.personnage ? props.names[bubble.personnage] ?? bubble.personnage : t('conversations_bd.sr.someone'));
    const text = bubble.texte.trim() === '' ? t('conversations_bd.editor.stage.empty_text') : bubble.texte.trim();
    return t('conversations_bd.editor.stage.bubble_handle', { n: String(index + 1), who, text });
};

function say(index: number, bubble: EditorBubble, kind: 'bubble' | 'tail') {
    const point = kind === 'bubble' ? bubble.position : { x: bubble.queue?.x ?? 0, y: bubble.queue?.y ?? 0 };
    announce.value = t(kind === 'bubble' ? 'conversations_bd.editor.stage.moved' : 'conversations_bd.editor.stage.tail_moved', {
        n: String(index + 1), x: String(Math.round(Number(point.x))), y: String(Math.round(Number(point.y))),
    });
}

function onKey(event: KeyboardEvent, index: number, kind: 'bubble' | 'tail') {
    if (props.readonly) return;
    const delta = arrowDelta(event.key, event.shiftKey);
    if (!delta) return;
    event.preventDefault();
    const bubble = props.bubbles[index];
    const next = kind === 'bubble' ? moveBubble(bubble, delta[0], delta[1]) : moveTail(bubble, delta[0], delta[1]);
    emit('update', index, next);
    say(index, next, kind);
}

function onDown(event: PointerEvent, index: number, kind: 'bubble' | 'tail') {
    emit('select', index);
    if (props.readonly || !overlay.value) return;
    (event.currentTarget as HTMLElement).setPointerCapture?.(event.pointerId);
    drag.value = { index, kind, startX: event.clientX, startY: event.clientY, origin: props.bubbles[index], pointer: event.pointerId };
}

function onMove(event: PointerEvent) {
    const current = drag.value;
    if (!current || current.pointer !== event.pointerId || !overlay.value) return;
    const rect = overlay.value.getBoundingClientRect();
    if (rect.width === 0 || rect.height === 0) return;
    const dx = ((event.clientX - current.startX) / rect.width) * 100;
    const dy = ((event.clientY - current.startY) / rect.height) * 100;
    const origin = current.origin;
    const next = current.kind === 'bubble'
        ? placeBubble(origin, origin.position.x + dx, origin.position.y + dy)
        : moveTail(origin, dx, dy);
    emit('update', current.index, next);
}

function onUp(event: PointerEvent) {
    const current = drag.value;
    if (!current || current.pointer !== event.pointerId) return;
    drag.value = null;
    const bubble = props.bubbles[current.index];
    if (bubble) say(current.index, bubble, current.kind);
}
</script>

<template>
    <div class="xm-bde-stage">
        <XmBdPanel :bubbles="bubbles" :names="names" :image="image" :alt="alt" :n="n" :count="count" :title="title" />
        <div v-if="!readonly" ref="overlay" class="xm-bde-overlay" @pointermove="onMove" @pointerup="onUp" @pointercancel="onUp">
            <template v-for="(bubble, index) in bubbles" :key="bubble.uid">
                <button type="button" :class="['xm-bde-handle', { 'is-selected': selected === index }]"
                        :style="{ left: `${boxes[index].x}%`, top: `${boxes[index].y}%`, width: `${boxes[index].w}%`, height: `${boxes[index].h}%` }"
                        :aria-label="label(bubble, index)" :aria-pressed="selected === index ? 'true' : 'false'"
                        :data-xm-bde-handle="index"
                        @pointerdown="onDown($event, index, 'bubble')" @keydown="onKey($event, index, 'bubble')" @focus="emit('select', index)">
                    <span class="xm-bde-handle-n" aria-hidden="true">{{ index + 1 }}</span>
                </button>
                <button v-if="tails[index] && selected === index" type="button" class="xm-bde-tail"
                        :style="{ left: `${tails[index]!.x}%`, top: `${tails[index]!.y}%` }"
                        :aria-label="t('conversations_bd.editor.stage.tail_handle', { n: String(index + 1) })"
                        :data-xm-bde-tail="index"
                        @pointerdown="onDown($event, index, 'tail')" @keydown="onKey($event, index, 'tail')">
                    <span aria-hidden="true"></span>
                </button>
            </template>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
</template>
