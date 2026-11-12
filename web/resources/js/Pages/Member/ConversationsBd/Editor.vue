<script setup lang="ts">
// Éditeur d'une conversation en BD (P20-07 à P20-09 ; `/mes-conversations-bd/{ulid}`, ConversationsBdPageController::
// edit), chargé à la demande. Brouillon **privé**, enregistré automatiquement (anti-rebond 1,5 s, indicateur
// « Enregistré » annoncé) par `PUT /api/v1/bd-conversations/{ulid}` ; le serveur applique les mêmes contrôles
// (ConversationGrammar, filtres de contenu) et refuse un texte qui contient un numéro, une adresse e-mail, un lien ou un
// mot interdit (message sous le champ).
//  - Cases : 3 à 8, choisies dans la bibliothèque de scènes actives (aucun import d'image) ; changer de scène, déplacer,
//    retirer une case.
//  - Bulles : ajouter, régler (type, personnage du casting ou narration, texte, emphase, queue, superposée), déplacer au
//    glisser ou au clavier (flèches : 2 %), réordonner, supprimer ; la bulle se dimensionne d'après son texte (XmBdPanel).
//  - Contrôles de la grammaire en direct (grammar.ts, jumeau du serveur) ; « Proposer à la publication » (case « Afficher
//    mon pseudonyme » décochée par défaut : anonyme).
//  - Aperçu de toutes les cases ; exports PDF (serveur) et PNG (navigateur, shared/bd-png.ts chargé au clic).
// Création proposée, publiée : lecture seule (aperçu, exports, retirer). Titre d'onglet neutre si la personne l'a choisi
// (gabarit membre), jamais d'aperçu de partage (page membre noindex).
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import XmField from '@/components/XmField.vue';
import XmBdPanel from '@/components/bd/XmBdPanel.vue';
import BdStage from '@/components/bd/editor/BdStage.vue';
import BdBubbleForm from '@/components/bd/editor/BdBubbleForm.vue';
import BdScenePicker from '@/components/bd/editor/BdScenePicker.vue';
import type { Bubble } from '@/components/bd/bubbles';
import { check, errors as grammarErrors, type Issue } from '@/components/bd/grammar';
import { adaptToScene, debounce, fromGrammar, move, newBubble, payload, reorder, uid, type EditorBubble, type EditorPanel, type Scene } from '@/components/bd/editor';

interface Conversation {
    ulid: string;
    title: string;
    summary: string;
    status: string;
    status_label: string;
    editable: boolean;
    show_pseudonym: boolean;
    refusal_reason: string | null;
    updated_at: string | null;
    public_url: string | null;
    pdf_url: string;
    panels: { scene: string; bubbles: Bubble[] }[];
}

interface Library {
    scenes: Scene[];
    characters: Record<string, string>;
    types: { value: string; label: string }[];
    tails: { value: string; label: string }[];
    limits: { title_min: number; title_max: number; summary_max: number; text_max: number; words_max: number; bubbles_max: number; panels_min: number; panels_max: number };
}

const props = withDefaults(defineProps<{ title: string; conversation: Conversation; library: Library; saveDelay?: number }>(), { saveDelay: 1500 });

const { t, locale } = useI18n();
const api = useApi();
const toast = useToast();

const state = ref<Conversation>({ ...props.conversation });
const title = ref(props.conversation.title);
const summary = ref(props.conversation.summary);
const panels = ref<EditorPanel[]>(props.conversation.panels.map((panel) => ({ uid: uid('p'), scene: panel.scene, bubbles: panel.bubbles.map(fromGrammar) })));
const current = ref(0);
const selected = ref<number | null>(panels.value[0]?.bubbles.length ? 0 : null);
const picker = ref<null | { mode: 'add' } | { mode: 'replace'; index: number }>(null);
const mode = ref<'edit' | 'preview'>(props.conversation.editable ? 'edit' : 'preview');
const saveState = ref<'idle' | 'saving' | 'saved' | 'error' | 'blocked'>('idle');
const savedAt = ref<string | null>(props.conversation.updated_at);
const saveMessage = ref('');
const fieldErrors = ref<Record<string, string>>({});
const showPseudonym = ref(false);
const submitting = ref(false);
const submitted = ref<HTMLElement | null>(null);
const issuesBox = ref<HTMLElement | null>(null);
const exportStatus = ref('');
const preview = ref<HTMLElement | null>(null);

const editable = computed(() => state.value.editable);
const scenes = computed(() => new Map(props.library.scenes.map((scene) => [scene.key, scene])));
const names = computed(() => props.library.characters);
const limits = computed(() => props.library.limits);
const panel = computed<EditorPanel | undefined>(() => panels.value[current.value]);
const scene = computed<Scene | undefined>(() => (panel.value ? scenes.value.get(panel.value.scene) : undefined));
const bubble = computed<EditorBubble | undefined>(() => (selected.value !== null ? panel.value?.bubbles[selected.value] : undefined));
const issues = computed<Issue[]>(() => check(panels.value));
const blocking = computed(() => grammarErrors(issues.value));
const sceneOf = (key: string): Scene | undefined => scenes.value.get(key);
const altOf = (key: string): string => sceneOf(key)?.description ?? '';
const imageOf = (key: string): string | null => sceneOf(key)?.image ?? null;

const issueText = (issue: Issue): string => {
    const params = Object.fromEntries(Object.entries(issue.params).map(([k, v]) => [k, String(v)]));
    const message = t(`conversations_bd.grammar.${issue.code}`, params);
    if (issue.panel === null) return message;
    return `${issue.bubble !== null
        ? t('conversations_bd.grammar.where_bubble', { panel: String(issue.panel), bubble: String(issue.bubble) })
        : t('conversations_bd.grammar.where_panel', { panel: String(issue.panel) })} ${message}`;
};

const bubbleErrors = computed<Record<string, string>>(() => {
    const prefix = `panels.${current.value}.bubbles.${selected.value}.`;
    return Object.fromEntries(Object.entries(fieldErrors.value).filter(([key]) => key.startsWith(prefix)).map(([key, value]) => [key.slice(prefix.length), value]));
});

const savedLabel = computed(() => {
    switch (saveState.value) {
        case 'saving': return t('conversations_bd.editor.save.saving');
        case 'saved': return t('conversations_bd.editor.save.saved', { time: time(savedAt.value) });
        case 'error': return t('conversations_bd.editor.save.error');
        case 'blocked': return t('conversations_bd.editor.save.blocked', { message: saveMessage.value });
        default: return savedAt.value ? t('conversations_bd.editor.save.saved', { time: time(savedAt.value) }) : '';
    }
});

function time(value: string | null): string {
    if (!value) return '';
    try {
        return new Intl.DateTimeFormat(locale.value || 'fr', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
    } catch {
        return '';
    }
}

// --- Enregistrement automatique ------------------------------------------------------------------------------------

async function save(): Promise<void> {
    if (!editable.value) return;
    saveState.value = 'saving';
    try {
        const response = await api.put<{ data: { saved_at: string | null } }>(`/api/v1/bd-conversations/${state.value.ulid}`, payload(title.value, summary.value, panels.value), { silent: true });
        savedAt.value = response.data.data.saved_at;
        fieldErrors.value = {};
        saveMessage.value = '';
        saveState.value = 'saved';
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            fieldErrors.value = Object.fromEntries(Object.entries(cause.errors).map(([key, list]) => [key, list[0] ?? '']));
            saveMessage.value = cause.message;
            saveState.value = 'blocked';
        } else {
            saveMessage.value = cause instanceof ApiError ? cause.message : '';
            saveState.value = 'error';
        }
    }
}

const saver = debounce(save, props.saveDelay);
watch([title, summary, panels], () => {
    if (editable.value) saver.schedule();
}, { deep: true });
onBeforeUnmount(() => {
    if (saver.pending()) saver.flush().catch(() => undefined);
});

// --- Cases -----------------------------------------------------------------------------------------------------------

function choose(key: string) {
    const target = picker.value;
    if (!target) return;
    const chosen = sceneOf(key);
    if (target.mode === 'add') {
        panels.value = [...panels.value, { uid: uid('p'), scene: key, bubbles: [] }];
        current.value = panels.value.length - 1;
        selected.value = null;
    } else {
        panels.value = panels.value.map((p, i) => (i === target.index ? { ...p, scene: key, bubbles: adaptToScene(p.bubbles, chosen) } : p));
        current.value = target.index;
    }
    picker.value = null;
    nextTick(() => document.getElementById('bde-stage-title')?.focus());
}

function movePanel(index: number, direction: -1 | 1) {
    panels.value = move(panels.value, index, direction);
    current.value = Math.min(Math.max(index + direction, 0), panels.value.length - 1);
}

function removePanel(index: number) {
    if (!window.confirm(t('conversations_bd.editor.panels.confirm_remove', { n: String(index + 1) }))) return;
    panels.value = panels.value.filter((_, i) => i !== index);
    current.value = Math.max(0, Math.min(current.value, panels.value.length - 1));
    selected.value = null;
}

function openPanel(index: number) {
    current.value = index;
    selected.value = panels.value[index]?.bubbles.length ? 0 : null;
}

// --- Bulles ----------------------------------------------------------------------------------------------------------

function setBubbles(list: EditorBubble[]) {
    panels.value = panels.value.map((p, i) => (i === current.value ? { ...p, bubbles: list } : p));
}

function addBubble() {
    if (!panel.value) return;
    const list = [...panel.value.bubbles, newBubble(scene.value, panel.value.bubbles)];
    setBubbles(list);
    selected.value = list.length - 1;
    nextTick(() => document.getElementById(`bde-${list[list.length - 1].uid}-text`)?.focus());
}

function updateBubble(index: number, next: EditorBubble) {
    if (!panel.value) return;
    setBubbles(panel.value.bubbles.map((b, i) => (i === index ? next : b)));
}

function reorderBubble(direction: -1 | 1) {
    if (!panel.value || selected.value === null) return;
    const index = selected.value;
    setBubbles(reorder(panel.value.bubbles, index, direction));
    selected.value = Math.min(Math.max(index + direction, 0), panel.value.bubbles.length - 1);
}

function removeBubble() {
    if (!panel.value || selected.value === null) return;
    const index = selected.value;
    setBubbles(panel.value.bubbles.filter((_, i) => i !== index));
    selected.value = panel.value.bubbles.length > 0 ? Math.max(0, index - 1) : null;
}

// --- Proposer, retirer ------------------------------------------------------------------------------------------------

async function submit() {
    if (submitting.value) return;
    await saver.flush();
    if (blocking.value.length > 0 || saveState.value === 'blocked') {
        await nextTick();
        issuesBox.value?.focus();
        return;
    }
    submitting.value = true;
    try {
        const response = await api.post<{ data: Partial<Conversation> }>(`/api/v1/bd-conversations/${state.value.ulid}/submit`, { show_pseudonym: showPseudonym.value }, { silent: true });
        state.value = { ...state.value, ...response.data.data, editable: false };
        mode.value = 'preview';
        await nextTick();
        submitted.value?.focus();
    } catch (cause) {
        toast.show(cause instanceof ApiError ? cause.message : t('microcopy.error.generic'), 'warning');
    } finally {
        submitting.value = false;
    }
}

async function withdraw() {
    if (!window.confirm(t('conversations_bd.member.confirm_withdraw', { title: state.value.title }))) return;
    try {
        const response = await api.post<{ data: Partial<Conversation> }>(`/api/v1/bd-conversations/${state.value.ulid}/withdraw`, {}, { silent: true });
        state.value = { ...state.value, ...response.data.data };
        if (state.value.editable) mode.value = 'edit';
        toast.show(t('conversations_bd.member.withdrawn', { title: state.value.title }), 'info');
    } catch (cause) {
        toast.show(cause instanceof ApiError ? cause.message : t('microcopy.error.generic'), 'warning');
    }
}

async function exportPng(kind: 'panels' | 'strip') {
    exportStatus.value = t('conversations_bd.editor.export.working');
    try {
        const module = await import('@/shared/bd-png');
        await nextTick();
        await module.exportPng(preview.value ?? document, kind, title.value, t('conversations_bd.editor.export.source', { site: window.location.host }));
        exportStatus.value = t('conversations_bd.editor.export.done');
    } catch {
        exportStatus.value = t('conversations_bd.editor.export.failed');
    }
}

const listUrl = route('mes-conversations-bd.index', undefined, '/mes-conversations-bd');
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-3 xm-bde" data-xm-bd-editor>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <Link :href="listUrl" class="btn btn-sm btn-outline-secondary xm-bde-touch"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.back') }}</Link>
            <span class="badge text-bg-light border">{{ state.status_label }}</span>
            <p class="small text-secondary mb-0 ms-auto" role="status" aria-live="polite" data-xm-bde-saved>{{ editable ? savedLabel : '' }}</p>
        </div>

        <div class="card">
            <div class="card-body">
                <h1 class="h4 mb-1">{{ t('conversations_bd.editor.title') }}</h1>
                <p class="text-secondary small mb-3">{{ editable ? t('conversations_bd.editor.intro') : t(`conversations_bd.editor.readonly.${state.status}`) }}</p>
                <p v-if="state.status === 'refusee' && state.refusal_reason" class="alert alert-warning small">
                    <strong>{{ t('conversations_bd.member.refusal') }}</strong> {{ state.refusal_reason }}
                </p>
                <template v-if="editable">
                    <XmField v-model="title" name="title" :label="t('conversations_bd.editor.fields.title')" :maxlength="limits.title_max" :error="fieldErrors.title" />
                    <XmField v-model="summary" name="summary" type="textarea" :rows="2" optional :label="t('conversations_bd.editor.fields.summary')" :maxlength="limits.summary_max" :error="fieldErrors.summary" />
                </template>
                <div class="btn-group" role="group" :aria-label="t('conversations_bd.editor.modes')">
                    <button v-if="editable" type="button" :class="['btn', 'xm-bde-touch', mode === 'edit' ? 'btn-primary' : 'btn-outline-primary']" :aria-pressed="mode === 'edit' ? 'true' : 'false'" @click="mode = 'edit'">
                        <i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.mode_edit') }}
                    </button>
                    <button type="button" :class="['btn', 'xm-bde-touch', mode === 'preview' ? 'btn-primary' : 'btn-outline-primary']" :aria-pressed="mode === 'preview' ? 'true' : 'false'" @click="mode = 'preview'">
                        <i class="bi bi-eye me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.mode_preview') }}
                    </button>
                </div>
            </div>
        </div>

        <template v-if="mode === 'edit' && editable">
            <section class="card" aria-labelledby="bde-panels-title">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h2 id="bde-panels-title" class="h5 mb-0 me-auto">{{ t('conversations_bd.editor.panels.title', { count: String(panels.length), max: String(limits.panels_max) }) }}</h2>
                        <button type="button" class="btn btn-primary btn-sm xm-bde-touch" :disabled="panels.length >= limits.panels_max" @click="picker = { mode: 'add' }">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.panels.add') }}
                        </button>
                    </div>
                    <p class="small text-secondary">{{ t('conversations_bd.editor.panels.hint', { min: String(limits.panels_min), max: String(limits.panels_max) }) }}</p>
                    <ol v-if="panels.length > 0" class="list-unstyled d-flex flex-wrap gap-2 mb-0" :aria-label="t('conversations_bd.editor.panels.list')">
                        <li v-for="(item, index) in panels" :key="item.uid">
                            <button type="button" :class="['btn', 'btn-sm', 'xm-bde-touch', index === current ? 'btn-primary' : 'btn-outline-primary']" :aria-current="index === current ? 'true' : undefined" @click="openPanel(index)">
                                {{ t('conversations_bd.editor.panels.open', { n: String(index + 1), count: String(item.bubbles.length) }) }}
                            </button>
                        </li>
                    </ol>
                    <p v-else class="mb-0">{{ t('conversations_bd.editor.panels.empty') }}</p>
                </div>
            </section>

            <BdScenePicker v-if="picker" :scenes="library.scenes" :heading="picker.mode === 'add' ? t('conversations_bd.editor.scenes.title_add') : t('conversations_bd.editor.scenes.title_replace', { n: String((picker as { index: number }).index + 1) })" @choose="choose" @cancel="picker = null" />

            <section v-if="panel && !picker" class="card" aria-labelledby="bde-stage-title">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h2 id="bde-stage-title" class="h5 mb-0 me-auto" tabindex="-1">{{ t('conversations_bd.editor.stage.title', { n: String(current + 1), count: String(panels.length) }) }}</h2>
                        <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" :disabled="current === 0" :aria-label="t('conversations_bd.editor.panels.earlier', { n: String(current + 1) })" @click="movePanel(current, -1)"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" :disabled="current === panels.length - 1" :aria-label="t('conversations_bd.editor.panels.later', { n: String(current + 1) })" @click="movePanel(current, 1)"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" @click="picker = { mode: 'replace', index: current }"><i class="bi bi-images me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.panels.change_scene') }}</button>
                        <button type="button" class="btn btn-sm btn-outline-danger xm-bde-touch" @click="removePanel(current)"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.panels.remove') }}</button>
                    </div>
                    <p class="small text-secondary">{{ t('conversations_bd.editor.stage.help') }}</p>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <BdStage :bubbles="panel.bubbles" :names="names" :image="imageOf(panel.scene)" :alt="altOf(panel.scene)" :n="current + 1" :count="panels.length" :title="title" :selected="selected" @select="selected = $event" @update="updateBubble" />
                            <p v-if="scene" class="small text-secondary mt-2 mb-0"><span class="fw-semibold">{{ t('conversations_bd.editor.scenes.cast') }}</span> {{ scene.characters.map((c) => c.name).join(', ') || t('conversations_bd.editor.scenes.no_cast') }}<span v-if="scene.zone"> · {{ t('conversations_bd.editor.scenes.zone', { zone: scene.zone }) }}</span></p>
                        </div>
                        <div class="col-lg-5 vstack gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <h3 class="h6 mb-0 me-auto">{{ t('conversations_bd.editor.bubbles.title', { count: String(panel.bubbles.length) }) }}</h3>
                                <button type="button" class="btn btn-sm btn-primary xm-bde-touch" data-xm-bde-add @click="addBubble"><i class="bi bi-chat-left-text me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.bubbles.add') }}</button>
                            </div>
                            <ol v-if="panel.bubbles.length > 0" class="list-unstyled d-flex flex-wrap gap-2 mb-1" :aria-label="t('conversations_bd.editor.bubbles.list')">
                                <li v-for="(item, index) in panel.bubbles" :key="item.uid">
                                    <button type="button" :class="['btn', 'btn-sm', 'xm-bde-touch', index === selected ? 'btn-secondary' : 'btn-outline-secondary']" :aria-pressed="index === selected ? 'true' : 'false'" @click="selected = index">
                                        {{ t('conversations_bd.editor.bubbles.item', { n: String(index + 1), type: library.types.find((type) => type.value === item.type)?.label ?? item.type }) }}
                                    </button>
                                </li>
                            </ol>
                            <p v-else class="small text-secondary mb-0">{{ t('conversations_bd.editor.bubbles.empty') }}</p>
                            <BdBubbleForm v-if="bubble && selected !== null" :key="bubble.uid" :bubble="bubble" :index="selected" :total="panel.bubbles.length" :scene="scene"
                                          :types="library.types" :tails="library.tails" :words-max="limits.words_max" :text-max="limits.text_max" :errors="bubbleErrors"
                                          @update="updateBubble(selected!, $event)" @reorder="reorderBubble" @remove="removeBubble" />
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <section v-if="mode === 'preview'" class="card" aria-labelledby="bde-preview-title">
            <div class="card-body vstack gap-3">
                <h2 id="bde-preview-title" class="h5 mb-0">{{ t('conversations_bd.editor.preview_title') }}</h2>
                <div ref="preview" class="vstack gap-4">
                    <XmBdPanel v-for="(item, index) in panels" :key="item.uid" :bubbles="item.bubbles" :names="names" :image="imageOf(item.scene)" :alt="altOf(item.scene)" :n="index + 1" :count="panels.length" :title="title" />
                </div>
                <p v-if="panels.length === 0" class="text-secondary mb-0">{{ t('conversations_bd.editor.panels.empty') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary xm-bde-touch" :href="state.pdf_url" download><i class="bi bi-filetype-pdf me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.export.pdf') }}</a>
                    <button type="button" class="btn btn-outline-primary xm-bde-touch" :disabled="panels.length === 0" @click="exportPng('panels')"><i class="bi bi-images me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.export.png_panels') }}</button>
                    <button type="button" class="btn btn-outline-primary xm-bde-touch" :disabled="panels.length === 0" @click="exportPng('strip')"><i class="bi bi-image me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.export.png_strip') }}</button>
                    <a v-if="state.public_url" class="btn btn-outline-primary xm-bde-touch" :href="state.public_url"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.public') }}</a>
                </div>
                <p class="small text-secondary mb-0" role="status" aria-live="polite">{{ exportStatus }}</p>
            </div>
        </section>

        <section v-if="editable" class="card" aria-labelledby="bde-checks-title">
            <div class="card-body">
                <h2 id="bde-checks-title" ref="issuesBox" class="h5" tabindex="-1">{{ t('conversations_bd.editor.checks.title') }}</h2>
                <p v-if="issues.length === 0" class="text-success mb-0" data-xm-bde-ok><i class="bi bi-check-circle me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.checks.ok') }}</p>
                <ul v-else class="small mb-0 ps-3" data-xm-bde-issues>
                    <li v-for="(issue, index) in issues" :key="index" :class="issue.level === 'error' ? 'text-danger' : 'text-warning-emphasis'">
                        <span class="visually-hidden">{{ issue.level === 'error' ? t('conversations_bd.editor.checks.error') : t('conversations_bd.editor.checks.warning') }} </span>{{ issueText(issue) }}
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="editable" class="card border-primary" aria-labelledby="bde-submit-title">
            <div class="card-body">
                <h2 id="bde-submit-title" class="h5">{{ t('conversations_bd.editor.submit.title') }}</h2>
                <p class="small text-secondary">{{ t('conversations_bd.editor.submit.text') }}</p>
                <div class="form-check mb-3">
                    <input id="bde-pseudonym" v-model="showPseudonym" class="form-check-input" type="checkbox" aria-describedby="bde-pseudonym-hint">
                    <label class="form-check-label" for="bde-pseudonym">{{ t('conversations_bd.editor.submit.pseudonym') }}</label>
                    <div id="bde-pseudonym-hint" class="form-text">{{ t('conversations_bd.editor.submit.pseudonym_hint') }}</div>
                </div>
                <button type="button" class="btn btn-primary xm-bde-touch" :disabled="submitting" :aria-describedby="blocking.length > 0 ? 'bde-submit-blocked' : undefined" @click="submit">
                    <i class="bi bi-send me-1" aria-hidden="true"></i>{{ t('conversations_bd.editor.submit.button') }}
                </button>
                <p v-if="blocking.length > 0" id="bde-submit-blocked" class="small text-danger mt-2 mb-0">{{ t('conversations_bd.editor.submit.blocked', { count: String(blocking.length) }) }}</p>
            </div>
        </section>

        <section v-if="!editable" class="card" aria-labelledby="bde-status-title">
            <div class="card-body">
                <h2 id="bde-status-title" ref="submitted" class="h5" tabindex="-1">{{ t('conversations_bd.editor.status_title', { status: state.status_label }) }}</h2>
                <p class="mb-2">{{ t(`conversations_bd.editor.readonly.${state.status}`) }}</p>
                <button v-if="state.status === 'proposee' || state.status === 'publiee'" type="button" class="btn btn-outline-warning xm-bde-touch" @click="withdraw">
                    <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>{{ t('conversations_bd.member.withdraw') }}
                </button>
            </div>
        </section>
    </div>
</template>
