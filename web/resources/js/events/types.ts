// Contrat des écrans des événements (lot evenements, P8-04) : objet de l'API /api/v1/events (docs/API.md §15,
// App\Http\Resources\Events\EventResource) et props Inertia de App\Http\Controllers\Web\EventsPageController (premières
// pages sous la forme de l'API) et de la modale « Créer un événement » (App\Application\Community\Events\EventCreateOptions).

export interface EventRsvp {
    /** `going` */
    status: string;
    reminder: boolean;
}

export interface EventItem {
    id: string;
    title: string;
    starts_at: string;
    ends_at: string | null;
    day: string;
    month_short: string;
    date_label: string;
    time_label: string;
    end_time_label: string | null;
    /** Ville ou zone, montrée à toute personne qui voit l'événement. */
    place: string | null;
    is_online: boolean;
    location_label: string;
    /** Présents seulement pour qui a répondu « Je participe », l'organisation et la modération. */
    address?: string | null;
    online_url?: string | null;
    exact_place_reserved: boolean;
    category: { slug: string; label: string } | null;
    description: string | null;
    image_url: string;
    organizer: string;
    group: { id: string; name: string; url: string } | null;
    going_count: number;
    is_public: boolean;
    is_past: boolean;
    status: string;
    url: string;
    rsvp: EventRsvp | null;
    /** Le rappel de la veille n'est pas encore parti (faux une fois envoyé ou l'événement commencé). */
    reminder_available: boolean;
    can: { rsvp: boolean; cancel: boolean; update: boolean };
    created_at: string | null;
}

export interface EventOption {
    value: string;
    label: string;
}

export interface EventCreateOptions {
    can: boolean;
    can_publish_public: boolean;
    default_time: string;
    categories: EventOption[];
    groups: EventOption[];
    limits: { title_min: number; title_max: number; place_max: number; address_max: number; description_max: number; max_duration_days: number };
}

export interface EventCategory {
    slug: string;
    label: string;
}

export interface EventSidebarItem {
    id: string;
    title: string;
    href: string;
    image_url: string;
    meta: string;
}

export interface EventPage {
    data: EventItem[];
    meta: { current_page: number; last_page: number; total?: number; [key: string]: unknown };
}

export interface EventDraftBody {
    title: string;
    starts_at: string;
    ends_at: string | null;
    place: string | null;
    address: string | null;
    is_online: boolean;
    online_url: string | null;
    category_id: number | null;
    description: string | null;
    group: string | null;
    is_public: boolean;
}
