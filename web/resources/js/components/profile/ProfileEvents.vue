<script setup lang="ts">
// Onglet « Événements » (lot profil, P8-07 ; prototype my-profile-events.php) : son profil — « Événements auxquels je
// participe » (participations et événements organisés) puis « À venir pour toi » ; le profil d'une autre personne —
// seulement les événements qu'elle organise (sa participation n'est jamais montrée). Vignette, titre vers la fiche,
// date · heure · lieu (ville ou zone : l'adresse exacte reste réservée aux participant·es, EventResource).
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { EventItem } from '@/events/types';
import type { MemberProfile, ProfileLinks } from '@/profile/types';

defineProps<{
    profile: MemberProfile;
    events: EventItem[];
    suggestions: EventItem[];
    links: ProfileLinks;
}>();

const { t } = useI18n();
const meta = (event: EventItem) => [event.date_label, event.time_label, event.location_label].filter(Boolean).join(' · ');
</script>

<template>
    <section class="card" aria-labelledby="profileEventsTitle">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <h2 id="profileEventsTitle" class="h5 card-title mb-0">{{ profile.is_self ? t('profile.events_tab.title_self') : t('profile.events_tab.title_other', { name: profile.pseudo }) }}</h2>
            <a v-if="profile.is_self" class="btn btn-primary-soft btn-sm" :href="links.events">{{ t('profile.events_tab.discover') }}</a>
        </div>
        <div class="card-body">
            <ul v-if="events.length" class="list-unstyled mb-0 vstack gap-3">
                <li v-for="event in events" :key="event.id" class="d-flex gap-3">
                    <div class="avatar avatar-lg flex-shrink-0"><img class="avatar-img rounded-2" :src="event.image_url" alt="" loading="lazy" decoding="async"></div>
                    <div>
                        <h3 class="h6 mb-0"><Link :href="event.url">{{ event.title }}</Link></h3>
                        <p class="small text-secondary mb-0"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i>{{ meta(event) }}</p>
                        <span v-if="profile.is_self && event.rsvp" class="badge bg-success bg-opacity-10 text-success mt-1">{{ t('profile.events_tab.going') }}</span>
                    </div>
                </li>
            </ul>
            <p v-else class="text-secondary text-center mb-0">
                {{ profile.is_self ? t('profile.empty.events') : t('profile.events_tab.empty_other', { name: profile.pseudo }) }}
                <a v-if="profile.is_self" :href="links.events">{{ t('profile.events_tab.discover') }}</a>
            </p>
        </div>
    </section>

    <section v-if="profile.is_self && suggestions.length" class="card" aria-labelledby="profileSuggestionsTitle">
        <div class="card-header border-0 pb-0"><h2 id="profileSuggestionsTitle" class="h5 card-title mb-0">{{ t('profile.events_tab.suggestions') }}</h2></div>
        <div class="card-body">
            <ul class="list-unstyled mb-0 vstack gap-3">
                <li v-for="event in suggestions" :key="event.id" class="d-flex gap-3">
                    <div class="avatar avatar-lg flex-shrink-0"><img class="avatar-img rounded-2" :src="event.image_url" alt="" loading="lazy" decoding="async"></div>
                    <div>
                        <h3 class="h6 mb-0"><Link :href="event.url">{{ event.title }}</Link></h3>
                        <p class="small text-secondary mb-0">{{ meta(event) }}</p>
                    </div>
                </li>
            </ul>
        </div>
    </section>
</template>
