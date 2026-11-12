// Données de test des écrans du fil (lot fil-ecrans) : publication, sondage et réponses JSON de l'API au format de
// PostResource / PollResource (docs/API.md §8.4), et faux `fetch` enregistrant les appels.
import { vi } from 'vitest';
import type { ComposerOptions, Poll, Post, PostMedia } from '@/feed/types';

export function makePoll(overrides: Partial<Poll> = {}): Poll {
    return {
        question: 'Quel thème pour la causerie ?',
        multiple: false,
        results_before_vote: false,
        ends_at: new Date(Date.now() + 3 * 86_400_000).toISOString(),
        closed: false,
        closed_at: null,
        voters_count: 4,
        votes_count: null,
        results_visible: false,
        has_voted: false,
        my_choices: [],
        can_vote: true,
        options: [
            { id: 11, label: 'Santé', position: 1, votes_count: null, percentage: null, leading: null },
            { id: 12, label: 'Droits', position: 2, votes_count: null, percentage: null, leading: null },
            { id: 13, label: 'Bien-être', position: 3, votes_count: null, percentage: null, leading: null },
        ],
        ...overrides,
    };
}

export function makePost(overrides: Partial<Post> = {}): Post {
    const id = overrides.id ?? '01JC0000000000000000000001';
    return {
        id,
        author: { id: 12, pseudo: 'Awa_S', avatar_url: '/assets/images/avatar/01.jpg', role: 'membre', anonymous: false },
        is_mine: false,
        is_anonymous: false,
        body: 'Merci à toutes pour vos messages.\nÇa fait du bien.',
        tag: 'temoignage',
        tag_label: 'Témoignage',
        tag_color: 'success',
        visibility: 'public',
        visibility_label: 'Public',
        moderated: false,
        likes_count: 3,
        comments_count: 1,
        liked: false,
        saved: false,
        media: [],
        review_state: null,
        review_label: null,
        review_reason: null,
        poll: null,
        url: `http://localhost/fil/${id}`,
        share_url: `http://localhost/p/${id}`,
        published_at: new Date(Date.now() - 5 * 60_000).toISOString(),
        edited: false,
        can: { update: false, delete: false, report: true, comment: true },
        ...overrides,
    };
}

export const makeImage = (n = 1, overrides: Partial<PostMedia> = {}): PostMedia => ({
    kind: 'image', url: `https://xomiton.test/media/local/images/2026/11/photo-${n}.webp?signature=x${n}`, alt: `Photo ${n}`, width: 1600, height: 1200, position: n - 1, mime: 'image/webp', size: 184233, ...overrides,
});

export const makeVideo = (overrides: Partial<PostMedia> = {}): PostMedia => ({
    kind: 'video', url: 'https://xomiton.test/media/fil/01JC0000000000000000000001/video?expires=1&viewer=7&signature=abc', alt: 'Des femmes assises en cercle qui discutent d’un projet.', width: null, height: null, position: 0, mime: 'video/mp4', size: 8192, ...overrides,
});

export function composerOptions(overrides: Partial<ComposerOptions> = {}): ComposerOptions {
    return {
        can_post: true,
        can_poll: true,
        events_available: false,
        tags: [{ value: 'temoignage', label: 'Témoignage' }, { value: 'question', label: 'Question' }],
        visibilities: [{ value: 'public', label: 'Communauté' }, { value: 'followers', label: 'Abonné·es' }, { value: 'me', label: 'Moi seulement' }],
        durations: [1, 3, 7, 14, 30, 0].map((value) => ({ value, label: `${value} j` })),
        default_duration: 7,
        limits: { body_max: 3000, question_min: 5, question_max: 200, options_min: 2, options_max: 6, option_max: 80, image_max_kb: 5120, image_types: ['image/jpeg', 'image/png', 'image/webp'],
            max_images: 4, video_max_kb: 30 * 1024, video_types: ['video/mp4', 'video/webm'], video_description_min: 5, video_description_max: 500,
        },
        ...overrides,
    };
}

export const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

export interface FetchCall {
    url: string;
    method: string;
    body: unknown;
    headers: Record<string, string>;
}

/** Faux fetch : `handler(call)` rend la réponse ; les appels sont gardés dans `calls`. */
export function fakeFetch(handler: (call: FetchCall) => Response | Promise<Response>) {
    const calls: FetchCall[] = [];
    const fetch = vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
        const raw = init.body;
        const body = typeof raw === 'string' ? JSON.parse(raw) : raw ?? null;
        const call = { url: String(input), method: String(init.method ?? 'GET'), body, headers: (init.headers ?? {}) as Record<string, string> };
        calls.push(call);
        return handler(call);
    });
    vi.stubGlobal('fetch', fetch);
    return { fetch, calls };
}
