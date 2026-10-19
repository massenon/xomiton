// Contrat de la page `Member/Profile` (lot profil, P8-07, P8-08) : props de ProfilePageController et réponses de l'API
// `members/{pseudo}…`, `me/activity`, `me/profile` (docs/API.md §3 et §18). Le pseudonyme est la seule identité (RG-02).
import type { EventItem } from '@/events/types';

export type ProfileTab = 'posts' | 'about' | 'connections' | 'media' | 'events' | 'activity';

export interface ProfileTabLink {
    key: ProfileTab;
    href: string;
    count: number | null;
}

export interface SensitiveItem {
    field: 'gender_identity' | 'orientation' | 'ally_status';
    label: string;
    value: string;
    value_label: string;
    shared: boolean;
}

export interface MemberProfile {
    id: number;
    pseudo: string;
    url: string;
    is_self: boolean;
    avatar_url: string | null;
    cover_url: string;
    role: string | null;
    role_label: string;
    verified: boolean;
    member_type: string | null;
    member_type_label: string | null;
    pronouns: string | null;
    bio: string | null;
    zone_label: string | null;
    joined_at: string | null;
    joined_label: string | null;
    interests: { slug: string; label: string }[];
    sensitive: SensitiveItem[];
    stats: { posts: number; connections: number; followers: number; following: number };
    relationship: { following: boolean; follows_you: boolean; connected: boolean } | null;
    message_url: string | null;
    completion?: number;
    visibility?: string | null;
    visibility_label?: string | null;
    show_zone?: boolean;
    /** Son profil : e-mail confirmé, avatar et couverture modifiables (route `verified`). */
    can_change_images?: boolean;
    has_avatar?: boolean;
    has_cover?: boolean;
}

export interface ProfileConnection {
    id: number;
    pseudo: string;
    avatar_url: string | null;
    role_label: string;
    zone_label: string | null;
    url: string;
    message_url: string | null;
}

export interface ConnectionsPage {
    data: ProfileConnection[];
    meta: { current_page: number; last_page: number; per_page: number; total: number; q: string | null };
}

export interface ProfileMediaItem {
    id: string;
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
    href: string | null;
    at: string | null;
}

export interface ActivityEntry {
    id: string;
    type: string;
    icon: string;
    label: string;
    detail: string | null;
    href: string | null;
    at: string;
}

export interface ProfileSide {
    media: ProfileMediaItem[];
    connections: ProfileConnection[];
}

export interface ProfileLinks {
    settings?: string;
    privacy?: string;
    data?: string;
    media_library: string | null;
    who_to_follow: string | null;
    events: string;
}

export type ProgressiveGroup = 'pronouns' | 'member_type' | 'ally_status' | 'gender_identity' | 'orientation' | 'age' | 'location' | 'interests';

export interface ProgressiveState {
    groups: { key: ProgressiveGroup; answered: boolean }[];
    next: ProgressiveGroup | null;
    done: boolean;
    answered: number;
    total: number;
    sensitive_consent: boolean;
}

export interface ProgressiveValues {
    pronouns: string | null;
    member_type: string | null;
    ally_status: string | null;
    gender_identity: string | null;
    orientation: string | null;
    age_range: string | null;
    zone_id: number | null;
    commune_id: number | null;
    interests: string[];
    shared_fields: string[];
    /** « Afficher ma zone sur mon profil » : le département seul, jamais la commune, pour les autres comptes. */
    show_zone: boolean;
}

export interface ProgressiveOptions {
    member_types: { value: string; label: string }[];
    age_ranges: { value: string; label: string }[];
    zones: { id: number; name: string; communes: { id: number; name: string }[] }[];
    interests: { slug: string; label: string }[];
}

export interface ProgressiveProps {
    state: ProgressiveState;
    values: ProgressiveValues;
    options: ProgressiveOptions;
}

export type ProfilePanel =
    | { data: unknown[]; meta?: Record<string, unknown>; suggestions?: EventItem[] }
    | null;
