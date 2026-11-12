<script setup lang="ts">
// Réglages de la bulle choisie dans l'éditeur des conversations en BD (P20-07) : type (9 types de la grammaire),
// personnage parmi le casting de la scène ou « Narration » (cartouche), texte (compteur de mots, 20 au plus), mots en
// gras (emphase), queue vers le personnage, l'écran ou un bord, bulle superposée ; déplacer la bulle et la pointe de la
// queue par boutons (pas de 2 %, cibles de 44 px) ; ordre de lecture (monter, descendre) ; supprimer. Champs étiquetés,
// erreurs du serveur sous le champ.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { words, type BubbleType } from '../bubbles';
import { moveBubble, moveTail, parseEmphasis, setCharacter, setTail, setType, STEP, type EditorBubble, type Scene } from '../editor';

const props = defineProps<{
    bubble: EditorBubble;
    index: number;
    total: number;
    scene: Scene | undefined;
    types: { value: string; label: string }[];
    tails: { value: string; label: string }[];
    wordsMax: number;
    textMax: number;
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{ update: [bubble: EditorBubble]; reorder: [direction: -1 | 1]; remove: [] }>();

const { t } = useI18n();
const id = computed(() => `bde-${props.bubble.uid}`);
const count = computed(() => words(props.bubble.texte).length);
const narration = '__narration__';
const character = computed(() => (props.bubble.type === 'cartouche' || props.bubble.personnage === null ? narration : props.bubble.personnage));
const tailEditable = computed(() => props.bubble.type !== 'cartouche' && props.bubble.queue !== null && !String(props.bubble.queue.vers).startsWith('bord-'));
const err = (field: string): string | undefined => props.errors?.[field];

const update = (bubble: EditorBubble) => emit('update', bubble);
const onType = (event: Event) => update(setType(props.bubble, (event.target as HTMLSelectElement).value as BubbleType, props.scene));
const onCharacter = (event: Event) => {
    const value = (event.target as HTMLSelectElement).value;
    update(setCharacter(props.bubble, value === narration ? null : value, props.scene));
};
const onText = (event: Event) => update({ ...props.bubble, texte: (event.target as HTMLTextAreaElement).value });
const onEmphasis = (event: Event) => update({ ...props.bubble, emphase: parseEmphasis((event.target as HTMLInputElement).value) });
const onTail = (event: Event) => update(setTail(props.bubble, (event.target as HTMLSelectElement).value));
const onOverlap = (event: Event) => update({ ...props.bubble, superpose: (event.target as HTMLInputElement).checked });
const nudge = (dx: number, dy: number) => update(moveBubble(props.bubble, dx, dy));
const nudgeTail = (dx: number, dy: number) => update(moveTail(props.bubble, dx, dy));

const arrows: { key: string; dx: number; dy: number; icon: string }[] = [
    { key: 'left', dx: -STEP, dy: 0, icon: 'arrow-left' },
    { key: 'up', dx: 0, dy: -STEP, icon: 'arrow-up' },
    { key: 'down', dx: 0, dy: STEP, icon: 'arrow-down' },
    { key: 'right', dx: STEP, dy: 0, icon: 'arrow-right' },
];
</script>

<template>
    <fieldset class="xm-bde-form border rounded p-3" :aria-labelledby="`${id}-legend`">
        <legend :id="`${id}-legend`" class="h6 float-none w-auto px-1 mb-2">{{ t('conversations_bd.editor.bubble.legend', { n: String(index + 1) }) }}</legend>
        <div class="row g-2">
            <div class="col-sm-6">
                <label class="form-label" :for="`${id}-type`">{{ t('conversations_bd.editor.bubble.type') }}</label>
                <select :id="`${id}-type`" class="form-select" :value="bubble.type" @change="onType">
                    <option v-for="option in types" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label" :for="`${id}-who`">{{ t('conversations_bd.editor.bubble.character') }}</label>
                <select :id="`${id}-who`" :class="['form-select', { 'is-invalid': err('personnage') }]" :value="character" :aria-invalid="err('personnage') ? 'true' : undefined" @change="onCharacter">
                    <option v-for="person in scene?.characters ?? []" :key="person.key" :value="person.key">{{ person.name }}</option>
                    <option :value="narration">{{ t('conversations_bd.editor.narration') }}</option>
                </select>
                <div v-if="err('personnage')" class="invalid-feedback d-block">{{ err('personnage') }}</div>
            </div>
            <div class="col-12">
                <label class="form-label" :for="`${id}-text`">{{ t('conversations_bd.editor.bubble.text') }}</label>
                <textarea :id="`${id}-text`" :class="['form-control', { 'is-invalid': err('texte') || count > wordsMax }]" rows="2" :maxlength="textMax" :value="bubble.texte"
                          :aria-describedby="`${id}-count${err('texte') ? ` ${id}-text-error` : ''}`" :aria-invalid="err('texte') || count > wordsMax ? 'true' : undefined" @input="onText"></textarea>
                <div :id="`${id}-count`" :class="['form-text', { 'text-danger fw-semibold': count > wordsMax }]">{{ t('conversations_bd.editor.bubble.words', { count: String(count), max: String(wordsMax) }) }}</div>
                <div v-if="err('texte')" :id="`${id}-text-error`" class="invalid-feedback d-block">{{ err('texte') }}</div>
            </div>
            <div class="col-12">
                <label class="form-label" :for="`${id}-emphasis`">{{ t('conversations_bd.editor.bubble.emphasis') }} <span class="text-secondary small">{{ t('components.field.optional') }}</span></label>
                <input :id="`${id}-emphasis`" class="form-control" type="text" :value="bubble.emphase.join(', ')" :aria-describedby="`${id}-emphasis-hint`" @change="onEmphasis">
                <div :id="`${id}-emphasis-hint`" class="form-text">{{ t('conversations_bd.editor.bubble.emphasis_hint') }}</div>
            </div>
            <div v-if="bubble.type !== 'cartouche'" class="col-sm-8">
                <label class="form-label" :for="`${id}-tail`">{{ t('conversations_bd.editor.bubble.tail') }}</label>
                <select :id="`${id}-tail`" class="form-select" :value="bubble.queue?.vers ?? 'personnage'" @change="onTail">
                    <option v-for="option in tails" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <div class="col-sm-4 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input :id="`${id}-overlap`" class="form-check-input" type="checkbox" :checked="bubble.superpose" @change="onOverlap">
                    <label class="form-check-label" :for="`${id}-overlap`">{{ t('conversations_bd.editor.bubble.overlap') }}</label>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mt-3">
            <div role="group" :aria-label="t('conversations_bd.editor.bubble.move_bubble')">
                <p class="small fw-semibold mb-1" aria-hidden="true">{{ t('conversations_bd.editor.bubble.move_bubble') }}</p>
                <div class="btn-group">
                    <button v-for="arrow in arrows" :key="arrow.key" type="button" class="btn btn-outline-secondary xm-bde-touch" :aria-label="t(`conversations_bd.editor.bubble.arrows.${arrow.key}`)" @click="nudge(arrow.dx, arrow.dy)">
                        <i :class="['bi', `bi-${arrow.icon}`]" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <div v-if="tailEditable" role="group" :aria-label="t('conversations_bd.editor.bubble.move_tail')">
                <p class="small fw-semibold mb-1" aria-hidden="true">{{ t('conversations_bd.editor.bubble.move_tail') }}</p>
                <div class="btn-group">
                    <button v-for="arrow in arrows" :key="arrow.key" type="button" class="btn btn-outline-secondary xm-bde-touch" :aria-label="t(`conversations_bd.editor.bubble.tail_arrows.${arrow.key}`)" @click="nudgeTail(arrow.dx, arrow.dy)">
                        <i :class="['bi', `bi-${arrow.icon}`]" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" :disabled="index === 0" @click="emit('reorder', -1)">
                <i class="bi bi-chevron-up me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.bubble.earlier') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" :disabled="index === total - 1" @click="emit('reorder', 1)">
                <i class="bi bi-chevron-down me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.bubble.later') }}
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger xm-bde-touch ms-auto" @click="emit('remove')">
                <i class="bi bi-trash me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.bubble.remove') }}
            </button>
        </div>
    </fieldset>
</template>
