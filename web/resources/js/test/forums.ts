// Données de test des écrans des forums (lot forums, P8-05) : objets au format de ForumResource, ForumTopicResource et
// ForumReplyResource (docs/API.md §16) et props de ForumsPageController.
import type { Forum, ForumAuthor, ForumReply, ForumTopic, TopicCreateOptions } from '@/forums/types';

export const TOPIC_ID = '01JC3000000000000000000001';

const FORUMS: Array<[string, string, string, string]> = [
    ['f1', 'Parler librement', 'Espace d’échange général, sans jugement.', 'chat-heart'],
    ['f2', 'Droits & démarches', 'Questions juridiques, plaintes, recours.', 'bank'],
    ['f3', 'Santé & bien-être', 'Santé mentale, santé sexuelle, hygiène menstruelle.', 'heart-pulse'],
    ['f4', 'Sororité & entraide', 'Cercles de solidarité, témoignages positifs.', 'people'],
    ['f5', 'Autonomisation', 'Emploi, finance, formations.', 'graph-up-arrow'],
];

export const makeForums = (): Forum[] => FORUMS.map(([key, name, description, icon], index) => ({
    key, slug: key, name, description, icon, topics_count: index === 2 ? 4 : 0, posts_count: index === 2 ? 9 : 0, url: `/forums?forum=${key}`,
}));

export const author = (overrides: Partial<ForumAuthor> = {}): ForumAuthor => ({
    id: 31, pseudo: 'Nadia_B', avatar_url: '/storage/avatars/nadia.jpg', role: 'membre', anonymous: false, badge: null, ...overrides,
});

export const anonymousAuthor = (): ForumAuthor => ({
    id: null, pseudo: 'Membre anonyme', avatar_url: '/assets/images/xomiton/personnage-1.png', role: null, anonymous: true, badge: null,
});

export function makeTopic(overrides: Partial<ForumTopic> = {}): ForumTopic {
    const id = overrides.id ?? TOPIC_ID;
    return {
        id,
        forum: { key: 'f3', name: 'Santé & bien-être', url: '/forums?forum=f3' },
        title: 'Règles douloureuses',
        body: 'Que faire quand la douleur\nempêche d’aller en cours ?',
        author: author(),
        is_mine: false,
        is_anonymous: false,
        is_pinned: false,
        is_locked: false,
        moderated: false,
        replies_count: 1,
        views_count: 12,
        created_at: new Date(Date.now() - 2 * 3_600_000).toISOString(),
        last_reply_at: null,
        url: `/forums/${id}`,
        can: { reply: true, delete: false, report: true },
        ...overrides,
    };
}

export function makeReply(id: number, overrides: Partial<ForumReply> = {}): ForumReply {
    return {
        id,
        body: `Réponse ${id}`,
        author: author({ id: 40 + id, pseudo: `Membre_${id}` }),
        is_mine: false,
        is_anonymous: false,
        created_at: new Date(Date.now() - 3_600_000).toISOString(),
        can: { delete: false, report: true },
        ...overrides,
    };
}

export function createOptions(overrides: Partial<TopicCreateOptions> = {}): TopicCreateOptions {
    return {
        can: true,
        forums: FORUMS.map(([value, label]) => ({ value, label })),
        limits: { title_min: 5, title_max: 140, body_min: 10, body_max: 5000 },
        ...overrides,
    };
}
