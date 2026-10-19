<script setup lang="ts">
// Suggestions instantanées du champ de recherche de l'en-tête (lot recherche-membre, P8-13) : morceau chargé à la
// demande par XmHeader au premier focus du champ (budget de app.ts), motif « combobox » ARIA 1.2 — le champ garde le
// focus, la liste `listbox` est groupée par famille (`group` nommé), l'option active est annoncée par
// `aria-activedescendant` (état remonté à XmHeader par `state`). Clavier : ↓ ↑ parcourent (en boucle), Entrée ouvre
// l'option active (sinon le formulaire part vers /recherche), Échap referme, Tab referme ; la perte du focus referme
// aussi (`close()` sur blur du champ) et son retour rouvre (`reopen()`) si le terme est assez long. Souris : clic sur une option.
// Requêtes : anti-rebond 300 ms, une seule à la fois (search/suggest.ts), API `search/suggestions` en silence, rien
// hors connexion (message), aucun historique (ni stockage local, ni requête « récente »). Nombre de suggestions
// annoncé dans une région polite. Props : query (saisie du champ), listboxId, searchBase (adresse de /recherche),
// min / max (bornes du terme, `ui.search` du serveur), discreet (mode discret : « Voir tous les résultats » confie le
// terme à la page en mémoire au lieu de l'adresse), online. Émet `state` ({ expanded, activeId }) et `chosen` (une option ouverte : XmHeader vide son champ).
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { browser, notificationTarget } from '@/app/memberNav';
import { searchApi } from '@/search/api';
import { canSuggest, cleanTerm, createSuggester, holdTerm, MAX_LENGTH, MIN_LENGTH, searchHref } from '@/search/suggest';
import type { SearchSection, Suggestion } from '@/search/types';
import XmAvatar from '@/components/XmAvatar.vue';

const props = withDefaults(defineProps<{
    query: string;
    listboxId: string;
    searchBase: string;
    min?: number;
    max?: number;
    discreet?: boolean;
    online?: boolean;
}>(), { min: MIN_LENGTH, max: MAX_LENGTH, discreet: false, online: true });
const emit = defineEmits<{ state: [state: { expanded: boolean; activeId: string | null }]; chosen: [] }>();

interface Option {
    id: string;
    suggestion: Suggestion | null;
}

const { t } = useI18n();
const api = searchApi(useApi());

const sections = ref<SearchSection<Suggestion>[]>([]);
const loading = ref(false);
const failed = ref(false);
const open = ref(false);
const active = ref(-1);
const announcement = ref('');

const term = computed(() => cleanTerm(props.query, props.max));
const searchable = computed(() => canSuggest(term.value, props.min));
const count = computed(() => sections.value.reduce((sum, section) => sum + section.items.length, 0));
const options = computed<Option[]>(() => {
    const items = sections.value.flatMap((section) => section.items.map((item) => ({ id: `${props.listboxId}-${item.type}-${item.id}`, suggestion: item })));
    return items.length > 0 ? [...items, { id: `${props.listboxId}-all`, suggestion: null }] : items;
});
const expanded = computed(() => open.value && searchable.value);
const activeId = computed(() => (expanded.value && active.value >= 0 ? options.value[active.value]?.id ?? null : null));
const allIndex = computed(() => options.value.length - 1);
const indexOf = (id: string) => options.value.findIndex((option) => option.id === id);

watch([expanded, activeId], ([isExpanded, id]) => emit('state', { expanded: isExpanded, activeId: id }), { immediate: true });

async function announce(message: string) {
    announcement.value = '';
    await nextTick();
    announcement.value = message;
}

const suggester = createSuggester<SearchSection<Suggestion>[]>({
    min: props.min,
    load: (q, signal) => api.suggestions(q, signal),
    onStart: () => {
        loading.value = true;
        failed.value = false;
    },
    onResult: (result) => {
        loading.value = false;
        sections.value = result;
        active.value = -1;
        void announce(t('search.suggest.count', { count: count.value }, count.value));
    },
    onClear: () => {
        loading.value = false;
        sections.value = [];
        active.value = -1;
    },
    onError: () => {
        loading.value = false;
        sections.value = [];
        failed.value = true;
    },
});

watch(() => props.query, () => {
    open.value = searchable.value;
    if (!props.online) {
        suggester.cancel();
        loading.value = false;
        sections.value = [];
        return;
    }
    suggester.schedule(term.value);
}, { immediate: true });

function close() {
    open.value = false;
    active.value = -1;
}

/** Retour du focus dans le champ : la liste revient si le terme est assez long. */
function reopen() {
    open.value = searchable.value;
}

function move(step: number) {
    const total = options.value.length;
    if (total === 0) return;
    open.value = true;
    active.value = active.value < 0 ? (step > 0 ? 0 : total - 1) : (active.value + step + total) % total;
}

function choose(option: Option) {
    const suggestion = option.suggestion;
    if (!suggestion && props.discreet) holdTerm(term.value);
    const target = notificationTarget(suggestion ? suggestion.url : searchHref(props.searchBase, props.discreet ? '' : term.value));
    suggester.cancel();
    close();
    if (!target) return;
    emit('chosen');
    if (target.kind === 'inertia' || (target.url.startsWith('/') && (suggestion === null || suggestion.inertia))) router.visit(target.url);
    else browser.assign(target.url);
}

/** Touches du champ (relayées par XmHeader) ; Entrée sans option active laisse partir le formulaire. */
function onKeydown(event: KeyboardEvent) {
    switch (event.key) {
        case 'ArrowDown':
        case 'ArrowUp':
            if (options.value.length === 0) return;
            event.preventDefault();
            move(event.key === 'ArrowDown' ? 1 : -1);
            break;
        case 'Enter': {
            const option = activeId.value ? options.value[active.value] : undefined;
            if (!option) return;
            event.preventDefault();
            choose(option);
            break;
        }
        case 'Escape':
            if (!expanded.value) return;
            event.preventDefault();
            close();
            break;
        case 'Tab':
            close();
            break;
        default:
            break;
    }
}

onBeforeUnmount(() => suggester.cancel());

defineExpose({ onKeydown, close, reopen });
</script>

<template>
    <div v-show="expanded" class="xm-search-suggest card shadow" data-xm-search-suggest>
        <p v-if="!online" class="small text-secondary px-3 py-2 mb-0"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ t('search.suggest.offline') }}</p>
        <p v-else-if="loading && options.length === 0" class="small text-secondary px-3 py-2 mb-0">{{ t('search.suggest.loading') }}</p>
        <p v-else-if="options.length === 0" class="small text-secondary px-3 py-2 mb-0">{{ failed ? t('search.failed') : t('search.suggest.none') }}</p>
        <div :id="listboxId" role="listbox" :aria-label="t('search.suggest.label')" class="py-1">
            <div v-for="section in sections" :key="section.type" role="group" :aria-labelledby="`${listboxId}-${section.type}`">
                <div :id="`${listboxId}-${section.type}`" role="presentation" class="xm-search-suggest__group small fw-semibold text-secondary px-3 pt-2 pb-1">
                    <i :class="`bi ${section.icon} me-1`" aria-hidden="true"></i>{{ section.label }}
                </div>
                <div
                    v-for="item in section.items"
                    :id="`${listboxId}-${item.type}-${item.id}`"
                    :key="`${item.type}-${item.id}`"
                    role="option"
                    :aria-selected="activeId === `${listboxId}-${item.type}-${item.id}` ? 'true' : 'false'"
                    :class="['xm-search-suggest__option d-flex align-items-center gap-2 px-3 py-2', { active: activeId === `${listboxId}-${item.type}-${item.id}` }]"
                    @mousedown.prevent
                    @mousemove="active = indexOf(`${listboxId}-${item.type}-${item.id}`)"
                    @click="choose({ id: `${listboxId}-${item.type}-${item.id}`, suggestion: item })"
                >
                    <XmAvatar v-if="item.type === 'membres'" size="xs" :name="item.title" :src="item.avatar_url" aria-hidden="true" />
                    <i v-else :class="`bi ${item.icon} xm-fw`" aria-hidden="true"></i>
                    <span class="xm-search-suggest__text">
                        <span class="d-block text-truncate">{{ item.title }}</span>
                        <span v-if="item.meta" class="d-block small text-secondary text-truncate">{{ item.meta }}</span>
                    </span>
                </div>
            </div>
            <div
                v-if="options.length > 0"
                :id="`${listboxId}-all`"
                role="option"
                :aria-selected="active === allIndex ? 'true' : 'false'"
                :class="['xm-search-suggest__option xm-search-suggest__all px-3 py-2 border-top small fw-semibold', { active: active === allIndex }]"
                @mousedown.prevent
                @mousemove="active = allIndex"
                @click="choose({ id: `${listboxId}-all`, suggestion: null })"
            >
                <i class="bi bi-search me-2" aria-hidden="true"></i>{{ t('search.suggest.see_all') }}
            </div>
        </div>
    </div>
    <span class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-xm-search-live>{{ announcement }}</span>
</template>
