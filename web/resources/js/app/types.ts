// Props partagées par HandleInertiaRequests (P6-02) : contrat entre Laravel et l'espace Vue.

export interface SharedUser {
    id: number;
    pseudo: string;
    avatar_url: string | null;
    role: string | null;
    roles: string[];
    email_verified: boolean;
    pending_validation: boolean;
    profile: {
        completion: number;
        member_type: string | null;
        discreet_mode: boolean;
    };
    settings: {
        theme: string | null;
        language: string | null;
        neutral_tab_title: boolean;
        mask_sensitive_notifications: boolean;
        sos_share_gps: boolean;
    };
}

export interface FlashMessage {
    /** Identifiant propre à la réponse (HandleInertiaRequests) : un toast n'est montré qu'une fois (app/flash.ts). */
    id: string;
    message: string;
    type: ToastType;
}

export type ToastType = 'primary' | 'secondary' | 'success' | 'danger' | 'warning' | 'info';

/** Gabarit membre Vue (P6-03, MemberShell::layout + jeton CSRF) : null sans session. */
export interface SharedShell {
    zone: string | null;
    stats: { posts: number; followers: number; following: number };
    sos: { endpoint: string; zone_id: number | null; commune_id: number | null; number: string | null };
    admin_url: string | null;
    csrf_token: string | null;
}

export interface SharedProps {
    [key: string]: unknown;
    auth: { user: SharedUser | null };
    can: Record<string, boolean>;
    counters: { notifications: number; messages: number } | null;
    shell?: SharedShell | null;
    flash: { toast: FlashMessage | null; status: FlashMessage | null };
    lang: { locale: string; fallback: string };
    ui: {
        neutral_title: boolean;
        neutral_label: string;
        bell_poll_seconds: number;
        outbox: { account: string; ttl_days: number };
        sos: { hold_ms: number; countdown_seconds: number };
    };
}
