// Données de test de la page profil (lot profil, P8-07, P8-08) : même forme que ProfilePageController et l'API
// `members/{pseudo}…` (docs/API.md §18).
import type { MemberProfile, ProfileTabLink, ProgressiveProps } from '@/profile/types';

export function makeProfile(overrides: Partial<MemberProfile> = {}): MemberProfile {
    return {
        id: 7,
        pseudo: 'Awa_S',
        url: '/profil',
        is_self: true,
        avatar_url: null,
        cover_url: '/assets/images/xomiton/profile-cover.jpg',
        role: 'membre',
        role_label: 'Membre',
        verified: false,
        member_type: 'membre',
        member_type_label: 'Membre',
        pronouns: 'Elle',
        bio: null,
        zone_label: 'Cotonou, Littoral',
        joined_at: '2026-03-01T10:00:00+01:00',
        joined_label: 'mars 2026',
        interests: [{ slug: 'sante', label: 'Santé' }],
        sensitive: [],
        stats: { posts: 3, connections: 2, followers: 5, following: 4 },
        relationship: null,
        message_url: null,
        completion: 57,
        visibility: 'community',
        visibility_label: 'Toute la communauté',
        show_zone: true,
        can_change_images: true,
        has_avatar: false,
        has_cover: false,
        ...overrides,
    };
}

export const otherProfile = (overrides: Partial<MemberProfile> = {}): MemberProfile => makeProfile({
    id: 9,
    pseudo: 'Bea_K',
    url: '/membres/Bea_K',
    is_self: false,
    completion: undefined,
    visibility: undefined,
    visibility_label: undefined,
    show_zone: undefined,
    can_change_images: undefined,
    has_avatar: undefined,
    has_cover: undefined,
    relationship: { following: true, follows_you: false, connected: true },
    message_url: '/messages?with=9',
    ...overrides,
});

export function makeTabs(base: string, self = true, posts = 3): ProfileTabLink[] {
    const tabs: ProfileTabLink[] = [
        { key: 'posts', href: base, count: posts },
        { key: 'about', href: `${base}/a-propos`, count: null },
        { key: 'connections', href: `${base}/connexions`, count: null },
        { key: 'media', href: `${base}/medias`, count: null },
        { key: 'events', href: `${base}/evenements`, count: null },
    ];
    return self ? [...tabs, { key: 'activity', href: `${base}/activite`, count: null }] : tabs;
}

export const selfLinks = {
    settings: '/parametres',
    privacy: '/parametres#confidentialite',
    data: '/parametres#donnees',
    media_library: '/medias',
    who_to_follow: '/qui-suivre',
    events: '/evenements',
};

export function makeProgressive(next: ProgressiveProps['state']['next'] = 'gender_identity', consent = false): ProgressiveProps {
    const keys = ['pronouns', 'member_type', 'ally_status', 'gender_identity', 'orientation', 'age', 'location', 'interests'] as const;
    const index = next === null ? keys.length : keys.indexOf(next);
    return {
        state: {
            groups: keys.map((key, i) => ({ key, answered: i < index })),
            next,
            done: next === null,
            answered: index,
            total: keys.length,
            sensitive_consent: consent,
        },
        values: {
            pronouns: 'Elle', member_type: 'membre', ally_status: null, gender_identity: null, orientation: null,
            age_range: null, zone_id: null, commune_id: null, interests: [], shared_fields: [], show_zone: true,
        },
        options: {
            member_types: [{ value: 'membre', label: 'Membre' }, { value: 'pair', label: 'Pair éducateur·rice' }],
            age_ranges: [{ value: '18-24', label: '18-24 ans' }],
            zones: [{ id: 4, name: 'Littoral', communes: [{ id: 12, name: 'Cotonou' }] }],
            interests: [{ slug: 'sante', label: 'Santé' }, { slug: 'droits', label: 'Droits' }],
        },
    };
}
