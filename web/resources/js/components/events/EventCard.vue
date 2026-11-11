<script setup lang="ts">
// Carte d'un événement (lot evenements, P8-04 ; prototype events.php l.68-86) : visuel 16/9 décoratif hors tabulation
// (le titre porte le lien), pastille de date (jour + mois court), catégorie, titre, heure · ville ou « En ligne » (jamais
// l'adresse exacte sur une carte), nombre de participant·es (jamais leur liste), « Je participe ? » / « Je participe »
// (bascule, aria-pressed) et « Détails ». Le geste est confié à la page (émet `toggle`), qui tient l'état et les toasts.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { EventItem } from '@/events/types';

withDefaults(defineProps<{ event: EventItem; busy?: boolean }>(), { busy: false });
const emit = defineEmits<{ toggle: [event: EventItem] }>();

const { t } = useI18n();
</script>

<template>
    <div class="col-sm-6 col-xl-4">
        <article class="card h-100 overflow-hidden xm-card-hover" :aria-labelledby="`event-${event.id}`">
            <Link :href="event.url" class="position-relative d-block" tabindex="-1" aria-hidden="true">
                <div class="xm-ratio-16x9"><img class="xm-img-cover" :src="event.image_url" alt="" loading="lazy" decoding="async" width="640" height="360"></div>
                <span class="position-absolute top-0 start-0 m-2 bg-body rounded text-center px-2 py-1 shadow-sm"><span class="d-block h5 mb-0 lh-1">{{ event.day }}</span><small class="text-uppercase">{{ event.month_short }}</small></span>
            </Link>
            <div class="card-body">
                <span v-if="event.category" class="xm-chip mb-2">{{ event.category.label }}</span>
                <h2 :id="`event-${event.id}`" class="h6 mb-2"><Link :href="event.url">{{ event.title }}</Link></h2>
                <p class="small mb-1"><time :datetime="event.starts_at"><i class="bi bi-clock me-1" aria-hidden="true"></i><span class="visually-hidden">{{ event.date_label }}, </span>{{ event.time_label }}</time> {{ t('common.separator') }} <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ event.location_label }}</p>
                <p class="small text-secondary mb-0"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.events.participants', { count: event.going_count }, event.going_count) }}</p>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <div class="d-flex gap-2">
                    <span v-if="event.is_past" class="badge bg-secondary align-self-center flex-grow-1 py-2">{{ t('community.events.past_badge') }}</span>
                    <button v-else-if="event.rsvp || event.can.rsvp" :class="['btn btn-sm flex-grow-1', event.rsvp ? 'btn-success-soft' : 'btn-primary-soft']" type="button" :aria-pressed="event.rsvp ? 'true' : 'false'" :disabled="busy" :aria-busy="busy ? 'true' : undefined" :aria-label="t('community.events.going_label', { title: event.title })" @click="emit('toggle', event)"><i :class="`bi bi-${event.rsvp ? 'check2-circle' : 'calendar-plus'} me-1`" aria-hidden="true"></i>{{ event.rsvp ? t('community.events.going') : t('community.events.going_ask') }}</button>
                    <Link class="btn btn-sm btn-light" :href="event.url" :aria-label="t('community.events.details_label', { title: event.title })">{{ t('community.events.details') }}</Link>
                </div>
            </div>
        </article>
    </div>
</template>
