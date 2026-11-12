// Contrat des écrans du fil (lot fil-ecrans, P7-03 à P7-06) : objets de l'API /api/v1 (docs/API.md §8.4 :
// App\Http\Resources\Feed\PostResource, PollResource, CommentResource) et props Inertia de
// App\Http\Controllers\Web\Member\FeedPageController (première page sous la même forme que l'API).
import type { EventCreateOptions } from '@/events/types';

/** Badge vérifié par l'administration (P18-09) : `membre_verifie` ou `pair_animateur`. */
export interface VerifiedBadge {
    key: string;
    label: string;
    icon: string;
}

export interface FeedAuthor {
    id: number | null;
    pseudo: string;
    avatar_url: string | null;
    role: string | null;
    anonymous: boolean;
    /** P18-09 : badges vérifiés (vide pour un contenu anonyme). */
    badges?: VerifiedBadge[];
}

export interface PollOption {
    id: number;
    label: string;
    position: number;
    /** null tant que les résultats ne sont pas visibles (RG-09). */
    votes_count: number | null;
    percentage: number | null;
    leading: boolean | null;
}

export interface Poll {
    question: string;
    multiple: boolean;
    results_before_vote: boolean;
    ends_at: string | null;
    closed: boolean;
    closed_at: string | null;
    voters_count: number;
    votes_count: number | null;
    results_visible: boolean;
    has_voted: boolean;
    my_choices: number[];
    can_vote: boolean;
    options: PollOption[];
}

export interface PostMedia {
    /** `image` ou `video` (docs/API.md §8.4). */
    kind: string;
    /** Lien signé : image 60 min ; vidéo, lien court propre à la personne qui lit (§8.7). */
    url: string;
    /** Texte alternatif d'une image (peut être nul) ou description d'une vidéo. */
    alt: string | null;
    width: number | null;
    height: number | null;
    position?: number;
    mime?: string;
    size?: number;
}

/** Relecture d'une vidéo (§8.7) : nul pour une publication ordinaire. */
export type ReviewState = 'pending' | 'approved' | 'rejected';

export interface Post {
    id: string;
    author: FeedAuthor;
    is_mine: boolean;
    is_anonymous: boolean;
    body: string | null;
    tag: string;
    tag_label: string;
    /** Variante de <XmBadge soft> (PostTag::badge(), couleurs du prototype). */
    tag_color: string;
    visibility: string;
    visibility_label: string;
    moderated: boolean;
    likes_count: number;
    comments_count: number;
    liked: boolean;
    saved: boolean;
    media: PostMedia[];
    /** Relecture d'une vidéo avant parution (Q96) ; `review_reason` : auteur·rice seulement, après un refus. */
    review_state?: ReviewState | null;
    review_label?: string | null;
    review_reason?: string | null;
    poll: Poll | null;
    url: string;
    share_url: string;
    published_at: string | null;
    edited: boolean;
    can: { update: boolean; delete: boolean; report: boolean; comment: boolean };
}

export interface Comment {
    id: number;
    parent_id: number | null;
    body: string;
    author: FeedAuthor;
    is_mine: boolean;
    is_anonymous: boolean;
    created_at: string | null;
    can: { delete: boolean; report: boolean };
}

export interface CursorPage<T> {
    data: T[];
    meta: { next_cursor: string | null; per_page?: number; comments_count?: number; [key: string]: unknown };
}

export interface Option<T = string> {
    value: T;
    label: string;
}

export interface ComposerOptions {
    /** Publication dans un groupe (lot groupes-pages, P8-02) : ULID envoyé en `group` (docs/API.md §8.3). */
    group?: string | null;
    can_post: boolean;
    can_poll: boolean;
    events_available: boolean;
    /** Réglages de la modale « Créer un événement » (P8-04), null sans ce droit. */
    event?: EventCreateOptions | null;
    tags: Option[];
    visibilities: Option[];
    durations: Option<number>[];
    default_duration: number;
    limits: {
        body_max: number;
        question_min: number;
        question_max: number;
        options_min: number;
        options_max: number;
        option_max: number;
        image_max_kb: number;
        image_types: string[];
        /** Q96 (docs/API.md §8.7) : absents d'une ancienne page, repli sur les valeurs par défaut du serveur. */
        max_images?: number;
        video_max_kb?: number;
        video_types?: string[];
        video_description_min?: number;
        video_description_max?: number;
    };
}

export interface FeaturedThematique {
    slug: string;
    title: string;
    href: string;
    cover_url: string;
    category: string | null;
}
