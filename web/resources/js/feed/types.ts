// Contrat des écrans du fil (lot fil-ecrans, P7-03 à P7-06) : objets de l'API /api/v1 (docs/API.md §8.4 :
// App\Http\Resources\Feed\PostResource, PollResource, CommentResource) et props Inertia de
// App\Http\Controllers\Web\Member\FeedPageController (première page sous la même forme que l'API).

export interface FeedAuthor {
    id: number | null;
    pseudo: string;
    avatar_url: string | null;
    role: string | null;
    anonymous: boolean;
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
    kind: string;
    url: string;
    alt: string | null;
    width: number | null;
    height: number | null;
}

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
    };
}

export interface FeaturedThematique {
    slug: string;
    title: string;
    href: string;
    cover_url: string;
    category: string | null;
}
