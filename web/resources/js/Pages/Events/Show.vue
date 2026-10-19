<script setup lang="ts">
// Fiche `/evenements/{ulid}` des membres (lot evenements, P8-04 ; prototype event-details.php), page Inertia
// `Events/Show` dans le gabarit membre (col-md-8 col-lg-6 après le sidenav + colonne col-lg-3) : fil d'Ariane, visuel,
// catégorie, h1, date · heure · ville ou « En ligne », **adresse exacte et lien de connexion seulement après « Je
// participe »** (sinon mention « partagée avec les participant·es »), description, organisation et charte, « Je
// participe » / « Je participe – annuler » (bouton d'action, nom accessible « Je participe à … » / « Annuler ma
// participation à … », sans aria-pressed ; événement passé : badge), rappel de la veille (interrupteur, tant qu'il
// n'est pas parti : `reminder_available`), « Partager »
// (Web Share, sinon copie du lien + toast). Colonne : nombre de participant·es (jamais leur liste, par discrétion),
// autres événements, « Tous les événements ». Props : EventsPageController::show (title, event, others).
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { useToast } from '@/app/useToast';
import { useRsvp } from '@/events/useRsvp';
import type { EventItem, EventSidebarItem } from '@/events/types';
import XmBreadcrumb from '@/components/XmBreadcrumb.vue';

const props = withDefaults(defineProps<{ title: string; event: EventItem; others?: EventSidebarItem[] }>(), { others: () => [] });

const { t } = useI18n();
const toast = useToast();
const current = ref<EventItem>({ ...props.event });
watch(() => props.event, (event) => { current.value = { ...event }; });

const { busy, toggle, setReminder } = useRsvp((event) => { current.value = event; });

const index = route('evenements.index', undefined, '/evenements');
const crumbs = computed(() => [{ label: t('community.events.title'), url: index }, { label: current.value.title }]);
const going = computed(() => current.value.rsvp !== null);
const hours = computed(() => (current.value.end_time_label ? `${current.value.time_label} – ${current.value.end_time_label}` : current.value.time_label));
const canShare = typeof navigator !== 'undefined' && (typeof navigator.share === 'function' || Boolean(navigator.clipboard));

async function share() {
    const url = current.value.url;
    try {
        if (typeof navigator.share === 'function') {
            await navigator.share({ title: current.value.title, url });
            return;
        }
        await navigator.clipboard.writeText(url);
        toast.show(t('community.events.detail.link_copied'), 'success');
    } catch {
        // Partage annulé par la personne : rien à signaler.
    }
}

const onReminder = (event: Event) => { void setReminder(current.value, (event.target as HTMLInputElement).checked); };
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <XmBreadcrumb :items="crumbs" inertia :label="t('community.events.detail.breadcrumb')" />
        <article class="card overflow-hidden" aria-labelledby="eventTitle">
            <div class="xm-event-cover"><img class="xm-img-cover" :src="current.image_url" :alt="t('community.events.image_alt', { title: current.title })" decoding="async" fetchpriority="high" width="1200" height="675"></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span v-if="current.category" class="xm-chip">{{ current.category.label }}</span>
                    <span v-if="current.is_public" class="badge bg-info bg-opacity-10 text-info align-self-center"><i class="bi bi-globe2 me-1" aria-hidden="true"></i>{{ t('community.events.public_badge') }}</span>
                    <Link v-if="current.group" class="badge bg-primary bg-opacity-10 text-primary align-self-center" :href="current.group.url"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.events.group_badge', { name: current.group.name }) }}</Link>
                </div>
                <h1 id="eventTitle" class="h4">{{ current.title }}</h1>
                <ul class="nav nav-divider small mb-3">
                    <li class="nav-item"><time :datetime="current.starts_at"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ current.date_label }}</time></li>
                    <li class="nav-item"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ hours }}</li>
                    <li class="nav-item"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ current.location_label }}</li>
                </ul>

                <div v-if="current.address || current.online_url" class="alert alert-success small" data-xm-exact-place>
                    <p v-if="current.address" class="mb-1"><i class="bi bi-pin-map me-1" aria-hidden="true"></i><strong>{{ t('community.events.detail.address') }}</strong>&nbsp;: {{ current.address }}</p>
                    <p v-if="current.online_url" class="mb-0"><i class="bi bi-camera-video me-1" aria-hidden="true"></i><a class="alert-link" :href="current.online_url" target="_blank" rel="noopener noreferrer">{{ t('community.events.detail.online_link') }}</a></p>
                </div>
                <p v-else-if="current.exact_place_reserved" class="small text-secondary d-flex gap-2"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>{{ current.is_online && !current.place ? t('community.events.detail.link_hidden') : t('community.events.detail.address_hidden') }}</span></p>

                <h2 class="h6">{{ t('community.events.detail.description') }}</h2>
                <p class="xm-event-description">{{ current.description || t('community.events.detail.no_description') }}</p>
                <p class="small text-secondary mb-3">{{ t('community.events.detail.organizer', { name: current.organizer }) }}. {{ t('community.events.detail.charter') }}</p>

                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span v-if="current.is_past" class="badge bg-secondary align-self-center">{{ t('community.events.past_badge') }}</span>
                    <button v-else-if="going || current.can.rsvp" :class="['btn', going ? 'btn-success-soft' : 'btn-primary']" type="button" :aria-label="going ? t('community.events.cancel_label', { title: current.title }) : t('community.events.going_label', { title: current.title })" :disabled="busy === current.id" :aria-busy="busy === current.id ? 'true' : undefined" @click="toggle(current)"><i :class="`bi bi-${going ? 'check2-circle' : 'calendar-plus'} me-1`" aria-hidden="true"></i>{{ going ? t('community.events.going_cancel') : t('community.events.going') }}</button>
                    <button v-if="canShare" class="btn btn-light" type="button" @click="share"><i class="bi bi-share me-1" aria-hidden="true"></i>{{ t('community.events.detail.share') }}</button>
                </div>
                <div v-if="going && !current.is_past && current.reminder_available" class="form-check form-switch mt-3">
                    <input id="eventReminder" class="form-check-input" type="checkbox" role="switch" :checked="current.rsvp?.reminder ?? true" :disabled="busy === current.id" @change="onReminder">
                    <label class="form-check-label small" for="eventReminder">{{ t('community.events.detail.reminder_toggle') }}</label>
                </div>
            </div>
        </article>
    </div>

    <aside class="col-md-4 col-lg-3 vstack gap-4" :aria-label="t('community.events.detail.others')">
        <section class="card" aria-labelledby="eventParticipants">
            <div class="card-header border-0 pb-0"><h2 id="eventParticipants" class="h5 card-title mb-0">{{ t('community.events.participants', { count: current.going_count }, current.going_count) }}</h2></div>
            <div class="card-body"><p class="small text-secondary mb-0">{{ t('community.events.detail.participants_private') }}</p></div>
        </section>
        <section class="card" aria-labelledby="eventOthers">
            <div class="card-header border-0 pb-0"><h2 id="eventOthers" class="h5 card-title mb-0">{{ t('community.events.detail.others') }}</h2></div>
            <div class="card-body">
                <div v-for="item in others" :key="item.id" class="d-flex mb-3">
                    <div class="avatar flex-shrink-0"><img class="avatar-img rounded-2" :src="item.image_url" alt="" loading="lazy" decoding="async"></div>
                    <div class="ms-2"><h3 class="h6 card-title mb-0"><Link :href="item.href">{{ item.title }}</Link></h3><p class="small mb-0">{{ item.meta }}</p></div>
                </div>
                <p v-if="others.length === 0" class="small text-secondary mb-0">{{ t('community.events.detail.others_empty') }}</p>
            </div>
        </section>
        <Link class="btn btn-light w-100" :href="index"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('community.events.detail.all_events') }}</Link>
    </aside>
</template>
