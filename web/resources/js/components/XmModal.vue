<script setup lang="ts">
// Modale : même HTML que <x-modal> (Bootstrap Modal : focus piégé, Échap, aria-modal ; pied fixe par
// modal-dialog-scrollable ; « Annuler » à gauche, action principale à droite). Ouverte par v-model:open ou show() /
// hide() exposés ; le focus revient à l'élément qui l'a ouverte. Téléportée dans <body> (empilement des modales).
// id, title (obligatoires) ; type : confirm | form | delete | fullscreen ; size : sm | md | lg | xl ; scrollable ;
// centered ; form → contenu <form> (émet `submit`, bouton principal en submit) ; action (libellé du bouton principal,
// émet `action`) ; confirmWord (delete : mot à saisir pour activer l'action) ; dirty + dirtyGuard (confirmation avant
// de fermer un formulaire modifié, fond statique) ; static ; teleport.
// Slots : défaut (corps), footer (boutons supplémentaires).
// Démontée ouverte (visite Inertia lancée depuis la modale) : Modal.dispose() de Bootstrap ne rend pas le défilement,
// releaseBody() retire donc body.modal-open, le fond et les réglages de ScrollBarHelper (overflow, padding-right,
// margin-right des éléments fixes), sauf si une autre modale reste ouverte.
// Bootstrap chargé à la demande (P4-12, shared/bootstrap-lazy.js) : jamais importé en dur, sans quoi il reviendrait dans
// le morceau de chaque page qui importe une modale. show() attend son arrivée (s'il n'est pas déjà là) ; hide() et la
// libération au démontage passent par loadedBootstrap() : une modale ouverte implique Bootstrap chargé. Échec du
// chargement : la modale reste fermée et `update:open` repasse à false (un nouvel essai refait la demande).
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Modal } from '../shared/bootstrap.js';
import { loadBootstrap, loadedBootstrap } from '../shared/bootstrap-lazy.js';

const props = withDefaults(defineProps<{
    id: string;
    title: string;
    open?: boolean;
    type?: 'confirm' | 'form' | 'delete' | 'fullscreen';
    size?: 'sm' | 'md' | 'lg' | 'xl';
    scrollable?: boolean;
    centered?: boolean;
    form?: boolean;
    action?: string | null;
    confirmWord?: string | null;
    dirty?: boolean;
    dirtyGuard?: boolean;
    static?: boolean;
    busy?: boolean;
    teleport?: boolean;
}>(), { open: false, type: 'confirm', size: 'md', scrollable: true, centered: true, form: false, action: null, confirmWord: null, dirty: false, dirtyGuard: false, static: false, busy: false, teleport: true });

const emit = defineEmits<{ 'update:open': [open: boolean]; action: []; submit: [event: Event]; shown: []; hidden: [] }>();

const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const word = ref('');
let instance: InstanceType<typeof Modal> | null = null;
let opener: HTMLElement | null = null;
let confirmedClose = false;
let mounted = false;
/** show() demandé avant l'arrivée de Bootstrap ; hide() ou le démontage l'annulent. */
let wanted = false;

const FIXED_CONTENT = '.fixed-top, .fixed-bottom, .is-fixed, .sticky-top';

/** Même remise à zéro que ScrollBarHelper.reset() (Bootstrap 5.3) : valeur gardée dans data-bs-<propriété>. */
function restoreStyle(element: HTMLElement, property: string) {
    const attribute = `data-bs-${property}`;
    const saved = element.getAttribute(attribute);
    if (saved === null) {
        element.style.removeProperty(property);
        return;
    }
    element.removeAttribute(attribute);
    element.style.setProperty(property, saved);
}

function releaseBody(self: HTMLElement | null) {
    if ([...document.querySelectorAll('.modal.show')].some((modal) => modal !== self)) return;
    const body = document.body;
    body.classList.remove('modal-open');
    restoreStyle(body, 'overflow');
    restoreStyle(body, 'padding-right');
    body.querySelectorAll<HTMLElement>(FIXED_CONTENT).forEach((element) => restoreStyle(element, 'padding-right'));
    body.querySelectorAll<HTMLElement>('.sticky-top').forEach((element) => restoreStyle(element, 'margin-right'));
    document.querySelectorAll('.modal-backdrop').forEach((element) => element.remove());
}

const isDelete = computed(() => props.type === 'delete');
const actionLabel = computed(() => props.action ?? (isDelete.value ? t('components.modal.delete_action') : null));
const actionDisabled = computed(() => props.busy || (Boolean(props.confirmWord) && word.value.trim() !== props.confirmWord));
const staticBackdrop = computed(() => (props.static || props.dirtyGuard ? 'static' : undefined));

/** Instance Bootstrap de la modale si Bootstrap est déjà chargé (celle de l'API data-bs-* si elle existe), sinon null. */
function ensureInstance(): InstanceType<typeof Modal> | null {
    if (instance || !root.value) return instance;
    const bootstrap = loadedBootstrap();
    if (!bootstrap) return null;
    instance = bootstrap.Modal.getOrCreateInstance(root.value, { backdrop: staticBackdrop.value === 'static' ? 'static' : true }) as InstanceType<typeof Modal>;
    return instance;
}

function show() {
    if (!root.value || root.value.classList.contains('show')) return;
    if (!wanted) opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    const ready = ensureInstance();
    if (ready) {
        wanted = false;
        ready.show();
        return;
    }
    if (wanted) return;
    wanted = true;
    loadBootstrap().then(() => {
        if (!wanted || !mounted) return;
        wanted = false;
        ensureInstance()?.show();
    }, () => {
        if (!wanted || !mounted) return;
        wanted = false;
        opener = null;
        emit('update:open', false);
    });
}
function hide() {
    wanted = false;
    ensureInstance()?.hide();
}

function onHide(event: Event) {
    if (props.dirtyGuard && props.dirty && !confirmedClose) {
        if (!window.confirm(t('components.modal.dirty_guard'))) {
            event.preventDefault();
            return;
        }
    }
    confirmedClose = false;
}
function onShown() {
    emit('update:open', true);
    emit('shown');
}
function onHidden() {
    word.value = '';
    emit('update:open', false);
    emit('hidden');
    const target = opener;
    opener = null;
    if (target && document.contains(target)) nextTick(() => target.focus());
}

function primary(event: Event) {
    if (actionDisabled.value) {
        event.preventDefault();
        return;
    }
    if (!props.form) emit('action');
}
function submit(event: Event) {
    event.preventDefault();
    if (actionDisabled.value) return;
    emit('submit', event);
}
/** Fermeture voulue (après un envoi réussi) : sans la garde « formulaire modifié ». */
function close() {
    confirmedClose = true;
    hide();
}

onMounted(() => {
    if (!root.value) return;
    mounted = true;
    ensureInstance();
    root.value.addEventListener('hide.bs.modal', onHide);
    root.value.addEventListener('shown.bs.modal', onShown);
    root.value.addEventListener('hidden.bs.modal', onHidden);
    if (props.open) show();
});
onBeforeUnmount(() => {
    mounted = false;
    wanted = false;
    const element = root.value;
    const wasOpen = Boolean(element?.classList.contains('show'));
    if (element) {
        element.removeEventListener('hide.bs.modal', onHide);
        element.removeEventListener('shown.bs.modal', onShown);
        element.removeEventListener('hidden.bs.modal', onHidden);
    }
    (instance ?? (element ? loadedBootstrap()?.Modal.getInstance(element) : null))?.dispose();
    instance = null;
    if (wasOpen || document.body.classList.contains('modal-open')) releaseBody(element);
});
watch(() => props.open, (open) => (open ? show() : hide()));

defineExpose({ show, hide, close });
</script>

<template>
    <Teleport to="body" :disabled="!teleport">
        <div ref="root" :class="['modal fade', { 'xm-modal--delete': isDelete }]" :id="id" tabindex="-1" :aria-labelledby="`${id}Title`" aria-hidden="true" :data-bs-backdrop="staticBackdrop">
            <div :class="['modal-dialog', { 'modal-dialog-centered': centered, 'modal-dialog-scrollable': scrollable, 'modal-sm': size === 'sm', 'modal-lg': size === 'lg', 'modal-xl modal-fullscreen-lg-down': size === 'xl', 'modal-fullscreen-sm-down': type === 'fullscreen' }]">
                <component :is="form ? 'form' : 'div'" class="modal-content" :novalidate="form ? true : undefined" @submit="submit">
                    <div :class="['modal-header', { 'border-0': isDelete }]">
                        <h5 :class="['modal-title', { 'text-danger': isDelete }]" :id="`${id}Title`"><i v-if="isDelete" class="bi bi-exclamation-triangle me-2" aria-hidden="true"></i>{{ title }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" :aria-label="t('common.close')"></button>
                    </div>
                    <div :class="['modal-body', { 'pt-0': isDelete }]">
                        <slot />
                        <div v-if="isDelete && confirmWord" class="alert alert-danger small mt-3 mb-0">
                            <label class="form-label mb-1" :for="`${id}Word`">{{ t('components.modal.confirm_word_label') }} <code>{{ confirmWord }}</code></label>
                            <input v-model="word" class="form-control form-control-sm font-monospace" :id="`${id}Word`" name="confirm_word" autocomplete="off" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ t('common.cancel') }}</button>
                        <slot name="footer" />
                        <button v-if="actionLabel" :type="form ? 'submit' : 'button'" :class="['btn', isDelete ? 'btn-danger' : 'btn-primary']" :disabled="actionDisabled" :aria-busy="busy ? 'true' : undefined" @click="primary"><i v-if="isDelete" class="bi bi-trash me-1" aria-hidden="true"></i>{{ actionLabel }}</button>
                    </div>
                </component>
            </div>
        </div>
    </Teleport>
</template>
