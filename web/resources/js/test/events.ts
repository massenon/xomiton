// Données de test des écrans des événements (lot evenements, P8-04) : objets au format d'EventResource et
// d'EventCreateOptions (docs/API.md §15).
import type { EventCreateOptions, EventItem } from '@/events/types';

export const EVENT_ID = '01JE1000000000000000000001';

export function makeEvent(overrides: Partial<EventItem> = {}): EventItem {
    const id = overrides.id ?? EVENT_ID;
    return {
        id,
        title: 'Atelier « Dire non sans culpabiliser »',
        starts_at: '2030-11-07T15:00:00+00:00',
        ends_at: null,
        day: '7',
        month_short: 'nov.',
        date_label: 'jeudi 7 novembre 2030',
        time_label: '15 h',
        end_time_label: null,
        place: 'Porto-Novo',
        is_online: false,
        location_label: 'Porto-Novo',
        exact_place_reserved: true,
        category: { slug: 'atelier', label: 'Atelier' },
        description: 'Jeux de rôle et exercices.',
        image_url: '/assets/images/xomiton/hero-12.jpg',
        organizer: 'Équipe Xomiton',
        group: null,
        going_count: 34,
        is_public: false,
        is_past: false,
        status: 'publie',
        url: `/evenements/${id}`,
        rsvp: null,
        reminder_available: true,
        can: { rsvp: true, cancel: false, update: false },
        created_at: '2030-10-01T10:00:00+00:00',
        ...overrides,
    };
}

/** Réponse de l'API après « Je participe » : adresse exacte livrée, compteur + 1. */
export const attending = (event: EventItem, address = 'Maison des jeunes, salle 3'): EventItem => ({
    ...event,
    address,
    exact_place_reserved: false,
    going_count: event.going_count + 1,
    rsvp: { status: 'going', reminder: true },
    can: { ...event.can, rsvp: false, cancel: true },
});

export function eventOptions(overrides: Partial<EventCreateOptions> = {}): EventCreateOptions {
    return {
        can: true,
        can_publish_public: false,
        default_time: '15:00',
        categories: [{ value: '3', label: 'Atelier' }, { value: '5', label: 'Santé' }],
        groups: [{ value: '01JC1000000000000000000001', label: 'Cercle de Bohicon' }],
        limits: { title_min: 3, title_max: 200, place_max: 120, address_max: 255, description_max: 2000, max_duration_days: 31 },
        ...overrides,
    };
}
