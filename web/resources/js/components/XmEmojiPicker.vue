<script setup lang="ts">
// Sélecteur d'émojis (publications, sondages, commentaires, messagerie). Bouton déclencheur (aria-haspopup="dialog",
// aria-expanded, « Ajouter un émoji ») et panneau role="dialog" : onglets de catégories (tablist, flèches, Début / Fin),
// recherche française sans accents, grille de vrais boutons (aria-label = nom français, flèches), récents de l'appareil
// (localStorage, 24 au plus), teinte de peau mémorisée. Échap ferme (sans fermer la modale qui l'entoure) et rend le
// focus au bouton ; clic à l'extérieur ferme ; Tab reste dans le panneau.
// Le panneau n'est PAS téléporté : il reste dans la modale Bootstrap, dont le piège à focus refuserait un panneau
// hors d'elle ; il est en position fixe, calé sur la fenêtre (360 px de large et plus, sans défilement horizontal).
// Insertion : à la position du curseur de `target` (sélecteur CSS, élément ou fonction), le focus et le curseur sont
// remis après l'émoji ; la longueur est comptée en points de code (comme le serveur) et l'émoji n'est pas ajouté si la
// limite serait dépassée (`maxlength` : propriété, sinon attribut maxlength de la cible). Les données (emojis.json,
// ~35 Ko) sont chargées à la première ouverture (morceau séparé). Émet `insert` (l'émoji) après chaque insertion.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import {
    TONES, TONE_KEYS, applyTone, baseKey, buildIndex, insertAtCursor, loadEmojis, normalize, pushRecent, readRecents,
    readTone, search, writeTone,
    type EmojiCategory, type EmojiData, type SearchIndexEntry, type TextTarget,
} from '@/emoji/emoji';

type TargetSpec = string | TextTarget | (() => TextTarget | null) | null;

const props = withDefaults(defineProps<{ target?: TargetSpec; maxlength?: number | null; disabled?: boolean }>(), { target: null, maxlength: null, disabled: false });
const emit = defineEmits<{ insert: [emoji: string] }>();

interface Item { key: string; char: string; name: string; label: string }

let counter = 0;
const uid = `xm-emoji-${++counter}-${Math.random().toString(36).slice(2, 7)}`;
const RECENTS = 'recents';

const { t } = useI18n();
const open = ref(false);
const status = ref<'idle' | 'loading' | 'ready' | 'error'>('idle');
const data = ref<EmojiData | null>(null);
const index = ref<SearchIndexEntry[]>([]);
const names = ref<Map<string, string>>(new Map());
const recents = ref<string[]>([]);
const tone = ref(0);
const activeTab = ref<string>(RECENTS);
const activeItem = ref(0);
const query = ref('');
const announcement = ref('');
const resultsNote = ref('');
const panelStyle = ref<Record<string, string>>({});

const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const panel = ref<HTMLElement | null>(null);
const searchField = ref<HTMLInputElement | null>(null);
const grid = ref<HTMLElement | null>(null);
const tabButtons = ref<Record<string, HTMLButtonElement | null>>({});
let selection: { start: number; end: number } | undefined;
let noteTimer: ReturnType<typeof setTimeout> | null = null;

const tabs = computed(() => [{ id: RECENTS, label: t('emoji.recents'), icon: '🕘' }, ...(data.value?.categories ?? []).map((c: EmojiCategory) => ({ id: c.id, label: c.label, icon: c.icon }))]);
const searching = computed(() => normalize(query.value) !== '');
const tabLabel = computed(() => tabs.value.find((tab) => tab.id === activeTab.value)?.label ?? '');

const toned = (entry: { c: string; n: string; t?: 1 }): Item => {
    const char = entry.t ? applyTone(entry.c, tone.value) : entry.c;
    const label = entry.t && tone.value > 0 ? t('emoji.with_tone', { name: entry.n, tone: t(`emoji.tones.${TONE_KEYS[tone.value]}`).toLowerCase() }) : entry.n;
    return { key: entry.c, char, name: entry.n, label };
};

const items = computed<Item[]>(() => {
    if (!data.value) return [];
    if (searching.value) return search(index.value, query.value).map(toned);
    if (activeTab.value === RECENTS) {
        return recents.value.map((char) => {
            const name = names.value.get(baseKey(char)) ?? '';
            return { key: char, char, name, label: name || char };
        });
    }
    const category = data.value.categories.find((c) => c.id === activeTab.value);
    return (category?.emojis ?? []).map(toned);
});

watch(items, (list) => { if (activeItem.value >= list.length) activeItem.value = 0; });
watch(activeTab, () => { activeItem.value = 0; });
watch(query, () => {
    activeItem.value = 0;
    if (noteTimer) clearTimeout(noteTimer);
    noteTimer = setTimeout(() => { resultsNote.value = searching.value ? t('emoji.results_count', { count: items.value.length }, items.value.length) : ''; }, 400);
});

function resolveTarget(): TextTarget | null {
    const spec = props.target;
    if (typeof spec === 'string') return document.querySelector<TextTarget>(spec);
    if (typeof spec === 'function') return spec();
    return spec ?? null;
}

const limit = (target: TextTarget | null): number | null => {
    if (props.maxlength && props.maxlength > 0) return props.maxlength;
    return target && target.maxLength > 0 ? target.maxLength : null;
};

function place() {
    const button = trigger.value;
    if (!button) return;
    const rect = button.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const width = Math.min(352, vw - 16);
    const height = Math.min(400, Math.max(240, vh - 16));
    const left = Math.max(8, Math.min(rect.left, vw - width - 8));
    let top: number;
    if (vh - rect.bottom - 8 >= height) top = rect.bottom + 4;
    else if (rect.top - 8 >= height) top = rect.top - height - 4;
    else top = Math.max(8, vh - height - 8);
    panelStyle.value = { top: `${Math.round(top)}px`, left: `${Math.round(left)}px`, width: `${Math.round(width)}px`, height: `${Math.round(height)}px` };
}

const onOutside = (event: Event) => {
    if (root.value && event.target instanceof Node && !root.value.contains(event.target)) close(false);
};

async function show() {
    const target = resolveTarget();
    selection = target ? { start: target.selectionStart ?? target.value.length, end: target.selectionEnd ?? target.selectionStart ?? target.value.length } : undefined;
    recents.value = readRecents();
    tone.value = readTone();
    query.value = '';
    resultsNote.value = '';
    open.value = true;
    place();
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
    document.addEventListener('pointerdown', onOutside, true);
    await ensureData();
    chooseStartTab();
    await nextTick();
    focusActiveTab();
}

/** À chaque ouverture : « Récents » s'il y en a, sinon la première catégorie. */
function chooseStartTab() {
    if (data.value) activeTab.value = recents.value.length > 0 ? RECENTS : (data.value.categories[0]?.id ?? RECENTS);
}

async function ensureData() {
    if (data.value) return;
    status.value = 'loading';
    try {
        const loaded = await loadEmojis();
        data.value = loaded;
        index.value = buildIndex(loaded);
        names.value = new Map(loaded.categories.flatMap((category) => category.emojis.map((entry) => [baseKey(entry.c), entry.n] as [string, string])));
        status.value = 'ready';
    } catch {
        status.value = 'error';
    }
}

async function retry() {
    status.value = 'idle';
    await ensureData();
    chooseStartTab();
    await nextTick();
    focusActiveTab();
}

function close(returnFocus: boolean) {
    if (!open.value) return;
    open.value = false;
    window.removeEventListener('resize', place);
    window.removeEventListener('scroll', place, true);
    document.removeEventListener('pointerdown', onOutside, true);
    if (returnFocus) nextTick(() => trigger.value?.focus());
}

function toggle() {
    if (props.disabled) return;
    if (open.value) close(true);
    else void show();
}

function focusActiveTab() {
    if (!open.value) return;
    (tabButtons.value[activeTab.value] ?? searchField.value ?? panel.value?.querySelector<HTMLElement>('button'))?.focus();
}

function announce(text: string) {
    announcement.value = '';
    nextTick(() => { announcement.value = text; });
}

function choose(item: Item) {
    const target = resolveTarget();
    if (target) {
        const result = insertAtCursor(target, item.char, limit(target), selection);
        if (!result.inserted) {
            announce(t('emoji.limit_reached'));
            return;
        }
    }
    recents.value = pushRecent(item.char);
    announce(t('emoji.added', { name: item.name || item.char }));
    emit('insert', item.char);
    close(false);
}

function selectTab(id: string, focus = false) {
    activeTab.value = id;
    if (focus) nextTick(() => tabButtons.value[id]?.focus());
}

function onTabKeydown(event: KeyboardEvent) {
    const ids = tabs.value.map((tab) => tab.id);
    const at = ids.indexOf(activeTab.value);
    let next = -1;
    if (event.key === 'ArrowRight') next = (at + 1) % ids.length;
    else if (event.key === 'ArrowLeft') next = (at - 1 + ids.length) % ids.length;
    else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = ids.length - 1;
    if (next === -1) return;
    event.preventDefault();
    selectTab(ids[next], true);
    tabButtons.value[ids[next]]?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
}

/** Colonnes de la grille : boutons de la première rangée (même position verticale). */
function columns(): number {
    const buttons = grid.value?.querySelectorAll<HTMLElement>('[data-emoji]') ?? [];
    if (buttons.length === 0) return 1;
    const top = buttons[0].offsetTop;
    let count = 0;
    for (const button of buttons) {
        if (button.offsetTop !== top) break;
        count++;
    }
    return Math.max(1, count);
}

function focusItem(position: number) {
    activeItem.value = position;
    nextTick(() => grid.value?.querySelector<HTMLElement>(`[data-emoji="${position}"]`)?.focus());
}

function onGridKeydown(event: KeyboardEvent) {
    const count = items.value.length;
    if (count === 0) return;
    const at = activeItem.value;
    let next = at;
    if (event.key === 'ArrowRight') next = Math.min(count - 1, at + 1);
    else if (event.key === 'ArrowLeft') next = Math.max(0, at - 1);
    else if (event.key === 'ArrowDown') next = Math.min(count - 1, at + columns());
    else if (event.key === 'ArrowUp') {
        if (at - columns() < 0) { event.preventDefault(); (searching.value ? searchField.value : tabButtons.value[activeTab.value])?.focus(); return; }
        next = at - columns();
    } else if (event.key === 'Home') next = 0;
    else if (event.key === 'End') next = count - 1;
    else return;
    event.preventDefault();
    focusItem(next);
}

function onSearchKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown' && items.value.length > 0) {
        event.preventDefault();
        focusItem(0);
    }
}

function setTone(value: number) {
    tone.value = value;
    writeTone(value);
}

/** Échap ferme le panneau seul (jamais la modale autour) ; Tab boucle dans le panneau. */
function onPanelKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close(true);
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;
    const focusable = [...panel.value.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled])')].filter((el) => el.tabIndex >= 0);
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    const current = document.activeElement;
    if (event.shiftKey && (current === first || !panel.value.contains(current))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (current === last || !panel.value.contains(current))) {
        event.preventDefault();
        first.focus();
    }
}

onBeforeUnmount(() => {
    window.removeEventListener('resize', place);
    window.removeEventListener('scroll', place, true);
    document.removeEventListener('pointerdown', onOutside, true);
    if (noteTimer) clearTimeout(noteTimer);
});

defineExpose({ close: () => close(false) });
</script>

<template>
    <div ref="root" class="xm-emoji">
        <button ref="trigger" type="button" class="btn btn-light xm-btn-icon" :disabled="disabled" aria-haspopup="dialog" :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="open ? `${uid}-panel` : undefined" :aria-label="t('emoji.trigger')" :title="t('emoji.trigger')" data-xm-emoji-trigger @click="toggle">
            <i class="bi bi-emoji-smile" aria-hidden="true"></i>
        </button>
        <div v-if="open" :id="`${uid}-panel`" ref="panel" class="xm-emoji__panel" role="dialog" :aria-label="t('emoji.title')" :style="panelStyle" data-xm-emoji-panel @keydown="onPanelKeydown">
            <div class="xm-emoji__head">
                <div class="xm-emoji__search">
                    <label class="visually-hidden" :for="`${uid}-search`">{{ t('emoji.search_label') }}</label>
                    <input :id="`${uid}-search`" ref="searchField" v-model="query" type="search" class="form-control" autocomplete="off" enterkeyhint="search" spellcheck="false"
                        :placeholder="t('emoji.search_placeholder')" :disabled="status !== 'ready'" @keydown="onSearchKeydown">
                    <button v-if="query !== ''" type="button" class="xm-emoji__clear" :aria-label="t('emoji.search_clear')" @click="query = ''; searchField?.focus()"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <button type="button" class="btn btn-light xm-btn-icon" :aria-label="t('emoji.close')" :title="t('emoji.close')" @click="close(true)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>

            <p v-if="status === 'loading' || status === 'idle'" class="xm-emoji__state" role="status">{{ t('emoji.loading') }}</p>
            <div v-else-if="status === 'error'" class="xm-emoji__state" role="alert">
                <p class="mb-2">{{ t('emoji.load_failed') }}</p>
                <button type="button" class="btn btn-primary-soft btn-sm" @click="retry">{{ t('emoji.retry') }}</button>
            </div>
            <template v-else>
                <div v-show="!searching" class="xm-emoji__tabs" role="tablist" :aria-label="t('emoji.categories_label')" @keydown="onTabKeydown">
                    <button v-for="tab in tabs" :id="`${uid}-tab-${tab.id}`" :key="tab.id" :ref="(el) => { tabButtons[tab.id] = el as HTMLButtonElement | null; }" type="button" role="tab"
                        class="xm-emoji__tab" :aria-selected="activeTab === tab.id ? 'true' : 'false'" :aria-controls="`${uid}-grid`" :tabindex="activeTab === tab.id ? 0 : -1"
                        :aria-label="tab.label" :title="tab.label" :data-tab="tab.id" @click="selectTab(tab.id)"><span aria-hidden="true">{{ tab.icon }}</span></button>
                </div>
                <div :id="`${uid}-grid`" class="xm-emoji__body" role="tabpanel" :aria-labelledby="searching ? undefined : `${uid}-tab-${activeTab}`" :aria-label="searching ? t('emoji.results') : undefined" tabindex="-1">
                    <h3 class="xm-emoji__title">{{ searching ? t('emoji.results') : tabLabel }}</h3>
                    <p v-if="items.length === 0" class="xm-emoji__empty">{{ searching ? t('emoji.no_results') : t('emoji.recents_empty') }}</p>
                    <div v-else ref="grid" class="xm-emoji__grid" role="group" :aria-label="searching ? t('emoji.results') : tabLabel" @keydown="onGridKeydown">
                        <button v-for="(item, position) in items" :key="`${item.key}-${position}`" type="button" class="xm-emoji__item" :data-emoji="position" :aria-label="item.label" :title="item.label"
                            :tabindex="activeItem === position ? 0 : -1" @click="choose(item)"><span aria-hidden="true">{{ item.char }}</span></button>
                    </div>
                </div>
                <div class="xm-emoji__tones" role="group" :aria-label="t('emoji.tone_label')">
                    <button v-for="(modifier, value) in TONES" :key="value" type="button" class="xm-emoji__tone" :aria-pressed="tone === value ? 'true' : 'false'"
                        :aria-label="t(`emoji.tones.${TONE_KEYS[value]}`)" :title="t(`emoji.tones.${TONE_KEYS[value]}`)" @click="setTone(value)"><span aria-hidden="true">{{ applyTone('✋', value) }}</span></button>
                </div>
            </template>
            <p class="visually-hidden" role="status" aria-live="polite" data-xm-emoji-count>{{ resultsNote }}</p>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-xm-emoji-live>{{ announcement }}</p>
    </div>
</template>
