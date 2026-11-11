// Données de test de « Qui suivre et invitations » (lot qui-suivre, P8-06) : objets au format de NetworkPersonResource
// et InvitationResource (docs/API.md §17).
import type { Invitation, Suggestion } from '@/network/types';

export function makeSuggestion(id: number, overrides: Partial<Suggestion> = {}): Suggestion {
    return {
        id,
        pseudo: `membre-${id}`,
        avatar_url: null,
        role: 'member',
        role_label: 'Membre',
        zone: { id: 4, name: 'Zou' },
        profile_url: null,
        following: false,
        can: { follow: true, invite: true },
        ...overrides,
    };
}

export function makeInvitation(id: number, direction: 'received' | 'sent', overrides: Partial<Invitation> = {}): Invitation {
    return {
        id,
        status: 'en_attente',
        status_label: 'En attente',
        direction,
        person: { id: 100 + id, pseudo: `personne-${id}`, avatar_url: null, role: 'peer', role_label: 'Pair animateur·rice', zone: { id: 4, name: 'Zou' }, profile_url: null },
        created_at: new Date(Date.now() - 2 * 3_600_000).toISOString(),
        responded_at: null,
        can: { accept: direction === 'received', refuse: direction === 'received', cancel: direction === 'sent' },
        ...overrides,
    };
}

/** Liste par curseur (NetworkPage) : `nextCursor` non nul = « Charger plus ». */
export const paged = <T>(data: T[], nextCursor: string | null = null) => ({ data, meta: { next_cursor: nextCursor, per_page: data.length } });
