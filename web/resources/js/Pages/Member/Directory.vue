<script setup lang="ts">
// Page `/annuaire` (lot annuaire-membre, P8-12 ; prototype annuaire.php), page Inertia `Member/Directory` chargée à la
// demande par app.ts (import.meta.glob) dans le gabarit membre (col-lg-9 à côté du sidenav) : en-tête « Annuaire
// dynamique » + « Urgences », filtres recherche · rôle · département · commune (visite partielle Inertia, adresse
// ?q=&role=&zone=&commune= gardée ; la commune suit le département), carte schématique du Bénin par département
// (<DirectoryMap>, pastille = lien ?zone=), cartes <ContactCard> (Appeler `tel:`, WhatsApp, E-mail), « Charger plus »
// (API `GET directory?page=`), modale « Demander à être rappelé·e » chargée à la demande (<CallbackModal>, P5-02).
// Portée du compte (DirectoryReach) : `network` → bandeau de discrétion, fiches réservées au réseau marquées (sans
// `directory.view`, la route répond 403). Page d'une session : le service worker ne la garde jamais (P5-10, X-Xm-Audience).
// Props : DirectoryPageController (title, reach, filters, roles, zones, contacts, map, limits).
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { directoryApi } from '@/directory/api';
import type { CallbackPayload, CallbackResult, ContactPage, DirectoryContact, DirectoryMapData, Filters, Limits, Reach, RoleOption, ZoneOption } from '@/directory/types';
import { lazyComponent } from '@/feed/lazy';
import ContactCard from '@/components/directory/ContactCard.vue';
import DirectoryMap from '@/components/directory/DirectoryMap.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    reach?: Reach;
    filters?: Filters;
    roles?: RoleOption[];
    zones?: ZoneOption[];
    contacts: ContactPage;
    map?: DirectoryMapData;
    limits?: Limits;
}>(), {
    reach: 'public',
    filters: () => ({ q: null, role: null, zone: null, commune: null }),
    roles: () => [],
    zones: () => [],
    map: () => ({ zones: [], national: 0, total: 0 }),
    limits: () => ({ search_max: 80, message_max: 1000, contact_max: 190 }),
});

const RELOAD = ['filters', 'contacts', 'map'];

const { t } = useI18n();
const toast = useToast();
const api = directoryApi(useApi());
const base = route('annuaire', undefined, '/annuaire');
const urgences = route('urgences', undefined, '/urgences');
const modal = lazyComponent(() => import('@/components/directory/CallbackModal.vue'));
const CallbackModal = modal.component;

const contacts = ref<DirectoryContact[]>([...props.contacts.data]);
const page = ref(props.contacts.meta.current_page);
const lastPage = ref(props.contacts.meta.last_page);
const q = ref(props.filters.q ?? '');
const role = ref(props.filters.role ?? '');
const zone = ref(props.filters.zone !== null ? String(props.filters.zone) : '');
const commune = ref(props.filters.commune !== null ? String(props.filters.commune) : '');
const switching = ref(false);
const loading = ref(false);
const announce = ref('');
const target = ref<DirectoryContact | null>(null);
const sent = ref<{ name: string; code: string } | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const sentRef = ref<HTMLElement | null>(null);
let focusSent = false;

const total = computed(() => props.contacts.meta.total);
const hasMore = computed(() => page.value < lastPage.value);
const communes = computed(() => props.zones.find((item) => String(item.id) === zone.value)?.communes ?? []);

watch(() => props.contacts, (next) => {
    contacts.value = [...next.data];
    page.value = next.meta.current_page;
    lastPage.value = next.meta.last_page;
});
watch(() => props.filters, (next) => {
    q.value = next.q ?? '';
    role.value = next.role ?? '';
    zone.value = next.zone !== null ? String(next.zone) : '';
    commune.value = next.commune !== null ? String(next.commune) : '';
});
watch(zone, () => {
    if (!communes.value.some((item) => String(item.id) === commune.value)) commune.value = '';
});

/** Paramètres d'adresse des filtres (valeurs vides omises). */
function params(values: { q?: string | null; role?: string | null; zone?: string | number | null; commune?: string | number | null }): Record<string, string> {
    const out: Record<string, string> = {};
    if (values.q && values.q.trim() !== '') out.q = values.q.trim();
    if (values.role) out.role = String(values.role);
    if (values.zone !== null && values.zone !== undefined && values.zone !== '') out.zone = String(values.zone);
    if (out.zone && values.commune !== null && values.commune !== undefined && values.commune !== '') out.commune = String(values.commune);
    return out;
}

function visit(query: Record<string, string>) {
    router.get(base, query, {
        only: RELOAD,
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function onFilter() {
    visit(params({ q: q.value, role: role.value, zone: zone.value, commune: commune.value }));
}

function onReset(event: MouseEvent) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    visit({});
}

/** Adresse d'un département de la carte : filtres appliqués (recherche, rôle) + département. */
function zoneHref(zoneId: number): string {
    const query = new URLSearchParams(params({ q: props.filters.q, role: props.filters.role, zone: zoneId })).toString();
    return query ? `${base}?${query}` : base;
}

function onZone(zoneId: number) {
    visit(params({ q: props.filters.q, role: props.filters.role, zone: zoneId }));
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    let first: number | null = null;
    try {
        const next = await api.list(props.filters, page.value + 1);
        const known = new Set(contacts.value.map((item) => item.id));
        const fresh = next.data.filter((item) => !known.has(item.id));
        contacts.value = [...contacts.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('directory.loaded', { count: fresh.length }, fresh.length);
        first = fresh[0]?.id ?? null;
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
    // « Charger plus » disparaît à la dernière page : le focus passe à la première fiche ajoutée.
    await nextTick();
    if (first !== null && !(document.activeElement instanceof HTMLElement && document.activeElement.isConnected && document.activeElement !== document.body)) {
        document.querySelector<HTMLElement>(`[data-xm-contact="${first}"] a[href], [data-xm-contact="${first}"] button`)?.focus();
    }
}

async function openCallback(contact: DirectoryContact) {
    target.value = contact;
    if (!(await modal.load())) {
        target.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

/** Envoi de la modale (directory/api.ts) vers le contact ouvert. */
function sendCallback(payload: CallbackPayload, idempotencyKey: string): Promise<CallbackResult> {
    if (!target.value) return Promise.reject(new Error('Aucun contact choisi'));
    return api.callback(target.value.id, payload, idempotencyKey);
}

function onSent(result: CallbackResult, contact: DirectoryContact) {
    sent.value = { name: result.routed?.name ?? contact.name, code: result.tracking_code_display };
    focusSent = true;
}

/** Modale fermée : après un envoi, le focus va à l'annonce (XmModal le rend d'abord au bouton qui l'a ouverte). */
function onHidden() {
    target.value = null;
    if (!focusSent) return;
    focusSent = false;
    window.setTimeout(() => sentRef.value?.focus(), 0);
}

function dismissSent() {
    sent.value = null;
    void nextTick(() => titleRef.value?.focus());
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 ref="titleRef" class="h3 mb-0" tabindex="-1">{{ t('directory.heading') }}</h1>
                <p class="text-secondary mb-0">{{ t('directory.intro') }}</p>
            </div>
            <a class="btn btn-danger-soft" :href="urgences"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ t('directory.urgences') }}</a>
        </div>

        <div v-if="sent" ref="sentRef" class="alert alert-success d-flex align-items-start gap-2 mb-0" role="status" tabindex="-1">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <div class="flex-grow-1">
                <p class="mb-1">{{ t('alerts.callback.sent', { name: sent.name }) }}</p>
                <p class="small mb-0">{{ t('directory.callback.tracking', { code: sent.code }) }}</p>
            </div>
            <button type="button" class="btn-close" :aria-label="t('common.close')" @click="dismissSent"></button>
        </div>

        <p v-if="reach === 'network'" class="alert alert-info small d-flex gap-2 mb-0"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>{{ t('directory.network_notice') }}</span></p>

        <div class="row g-4">
            <div class="col-xl-7">
                <form class="card card-body h-100" role="search" :aria-label="t('directory.filters.label')" :action="base" method="get" @submit.prevent="onFilter">
                    <div class="row g-2">
                        <div class="col-12">
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                                <input v-model="q" class="form-control" type="search" name="q" :maxlength="limits.search_max" :placeholder="t('directory.filters.search')" :aria-label="t('directory.filters.search_label')">
                            </div>
                        </div>
                        <div class="col-sm-4">
                            <select v-model="role" class="form-select" name="role" :aria-label="t('directory.filters.role')">
                                <option value="">{{ t('directory.filters.all_roles') }}</option>
                                <option v-for="item in roles" :key="item.value" :value="item.value">{{ item.label }}</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select v-model="zone" class="form-select" name="zone" :aria-label="t('directory.filters.zone')">
                                <option value="">{{ t('directory.filters.all_zones') }}</option>
                                <option v-for="item in zones" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                            </select>
                        </div>
                        <div class="col-sm-4">
                            <select v-model="commune" class="form-select" name="commune" :aria-label="t('directory.filters.commune')" :disabled="communes.length === 0">
                                <option value="">{{ t('directory.filters.all_communes') }}</option>
                                <option v-for="item in communes" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1" aria-hidden="true"></i>{{ t('directory.filters.submit') }}</button>
                            <a class="btn btn-light" :href="base" @click="onReset">{{ t('directory.filters.reset') }}</a>
                            <span class="ms-auto align-self-center small text-secondary" aria-live="polite">{{ t('directory.filters.results', { count: total }, total) }}</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-xl-5">
                <div class="card card-body h-100 p-2">
                    <DirectoryMap :zones="map.zones" :national="map.national" :selected="filters.zone" :href="zoneHref" @select="onZone" />
                </div>
            </div>
        </div>

        <div :aria-busy="switching || loading ? 'true' : 'false'">
            <div v-if="switching" class="row g-3">
                <div v-for="n in 2" :key="n" class="col-md-6"><XmSkeleton type="card" :label="t('microcopy.loading.more')" /></div>
            </div>
            <template v-else>
                <ul v-if="contacts.length > 0" class="row g-3 list-unstyled mb-0">
                    <ContactCard v-for="contact in contacts" :key="contact.id" :contact="contact" @callback="openCallback" />
                </ul>
                <div v-else class="card card-body text-center text-secondary">
                    <i class="bi bi-person-x fs-1" aria-hidden="true"></i>
                    <p class="mb-1">{{ t('directory.empty') }}</p>
                    <a :href="base" @click="onReset">{{ t('directory.empty_all') }}</a>
                </div>
            </template>
        </div>

        <div v-if="!switching && hasMore" class="xm-load-more">
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>

    <component :is="CallbackModal" v-if="target && CallbackModal" :contact="target" :limits="limits" :send="sendCallback" @sent="onSent" @hidden="onHidden" />
</template>
