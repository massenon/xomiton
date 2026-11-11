// Contrat de l'API de messagerie (P8-01, docs/API.md §12 ; App\Http\Resources\Messaging\*).

export interface Participant {
    id: number;
    pseudo: string;
    avatar_url: string | null;
    role?: string | null;
    is_me?: boolean;
}

export interface LastMessage {
    id: number;
    preview: string | null;
    preview_hidden: boolean;
    mine: boolean;
    author: string | null;
    deleted: boolean;
    attachment: boolean;
    at: string | null;
}

export interface Conversation {
    id: string;
    kind: 'private' | 'group';
    name: string;
    title: string | null;
    avatar_url: string | null;
    other: Participant | null;
    /** `GET conversations/{ulid}` seulement : la liste (sondée) l'omet (docs/API.md §12). */
    participants?: Participant[];
    participants_count: number;
    last_message: LastMessage | null;
    last_message_at: string | null;
    unread_count: number;
    muted: boolean;
    muted_until: string | null;
    role: string | null;
    can_send: boolean;
    can_manage: boolean;
    blocked_by_me: boolean;
    others_count: number;
    created_at: string | null;
}

export interface Attachment {
    kind: 'image' | 'document';
    url: string;
}

/** Message du serveur, ou message local en attente d'envoi (`state`). */
export interface ChatMessage {
    id: number | null;
    client_uuid: string | null;
    body: string | null;
    deleted: boolean;
    mine: boolean;
    author: Participant | null;
    attachment: Attachment | null;
    status: 'sent' | 'seen' | null;
    created_at: string | null;
    /** Local : `pending` (horloge grisée jusqu'à confirmation), `failed` (refusé, à réessayer ou retirer). */
    state?: 'pending' | 'failed';
    error?: string | null;
    /** Local : nom du fichier joint encore à envoyer. */
    file_name?: string | null;
    /** Local : faux si la pièce jointe n'a pas été retrouvée après un rechargement (Réessayer masqué). */
    retryable?: boolean;
}

export interface MessagingConfig {
    poll_seconds: number;
    list_poll_seconds: number;
    list_poll_idle_seconds?: number;
    body_max_length: number;
    recipient_search_min: number;
    group_title_min: number;
    mute_durations: string[];
    report_reasons: Array<{ value: string; label: string }>;
    attachment_accept: string;
}

export interface MessagesMeta {
    read_up_to?: number | null;
    /** Sondage `?after=` : messages déjà livrés supprimés pour tout le monde depuis `since`. */
    deleted_ids?: number[];
    checked_at?: string;
    poll_seconds?: number;
    next_cursor?: string | null;
    last_id?: number;
    has_more?: boolean;
}
