// Objets de « Qui suivre et invitations » (lot qui-suivre, P8-06 ; docs/API.md §17) : personne (NetworkPersonPayload,
// sans donnée privée : pseudonyme, avatar, rôle public, zone si affichée), suggestion (NetworkPersonResource : état
// « suivi·e » et gestes permis), invitation (InvitationResource : l'autre personne seulement).

export type PublicRole = 'member' | 'peer' | 'team';

export interface NetworkPerson {
    id: number;
    pseudo: string;
    avatar_url: string | null;
    role: PublicRole;
    role_label: string;
    zone: { id: number; name: string } | null;
    profile_url: string | null;
}

export interface Suggestion extends NetworkPerson {
    following: boolean;
    can: { follow: boolean; invite: boolean };
}

export interface Invitation {
    id: number;
    status: 'en_attente' | 'acceptee' | 'refusee';
    status_label: string;
    direction: 'received' | 'sent';
    person: NetworkPerson | null;
    created_at: string | null;
    responded_at: string | null;
    can: { accept: boolean; refuse: boolean; cancel: boolean };
}

export interface Counts {
    received: number;
    sent: number;
}

/** Liste par curseur (NetworkPage) : la suite reprend après le dernier élément reçu, jamais à un rang. */
export interface PagedList<T> {
    data: T[];
    meta: { next_cursor: string | null; per_page?: number; counts?: Counts };
}

export interface FollowState {
    user_id: number;
    following: boolean;
    changed: boolean;
}

/** `/qui-suivre?invite=<id>` : issue prévue d'une invitation, sans l'avoir envoyée. */
export interface InvitePreview {
    status: 'ready' | 'pending' | 'connected' | 'from_them' | 'unavailable';
    person: Suggestion | null;
}

export type Tab = 'suggestions' | 'received' | 'sent';
