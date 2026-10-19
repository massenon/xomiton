// Contrat des écrans des forums (lot forums, P8-05) : objets de l'API /api/v1/forums (docs/API.md §16 :
// App\Http\Resources\Forums\ForumResource, ForumTopicResource, ForumReplyResource) et props Inertia de
// App\Http\Controllers\Web\Member\ForumsPageController (premières pages sous la forme de l'API).
import type { Option } from '@/feed/types';

export interface Forum {
    /** Clé courte du prototype (`f1`…), `?forum=` des listes. */
    key: string;
    slug: string;
    name: string;
    description: string;
    /** Bootstrap Icons, sans « bi- ». */
    icon: string;
    topics_count: number;
    posts_count: number;
    url: string;
}

/** Auteur·rice (RG-02) : anonyme → « Membre anonyme », `id`, `role` et `badge` nuls. */
export interface ForumAuthor {
    id: number | null;
    pseudo: string;
    avatar_url: string;
    role: string | null;
    anonymous: boolean;
    badge: string | null;
}

export interface ForumTopic {
    id: string;
    forum: { key: string; name: string; url: string } | null;
    title: string;
    /** Fiche seulement. */
    body?: string;
    author: ForumAuthor;
    is_mine: boolean;
    is_anonymous: boolean;
    is_pinned: boolean;
    is_locked: boolean;
    moderated: boolean;
    replies_count: number;
    views_count: number;
    created_at: string | null;
    last_reply_at: string | null;
    url: string;
    can: { reply: boolean; delete: boolean; report: boolean };
}

export interface ForumReply {
    id: number;
    body: string;
    author: ForumAuthor;
    is_mine: boolean;
    is_anonymous: boolean;
    created_at: string | null;
    can: { delete: boolean; report: boolean };
}

export type { PagedList } from '@/community/types';

export interface TopicCreateOptions {
    can: boolean;
    forums: Option[];
    limits: { title_min: number; title_max: number; body_min: number; body_max: number };
}

export interface ReplyOptions {
    limits: { min: number; max: number };
}
