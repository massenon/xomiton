// Types de l'écran /parametres (lot parametres-notifications, P8-09) : props de SettingsPageController, sous la forme
// des ressources de l'API me/* (docs/API.md §3).

export interface Identity {
    name: string | null;
    pseudo: string;
    email: string;
    phone: string | null;
    bio: string | null;
    zone_id: number | null;
    commune_id: number | null;
}

export interface ZoneOption {
    id: number;
    name: string;
    communes: { id: number; name: string }[];
}

export interface Privacy {
    visibility: string;
    show_zone: boolean;
    searchable: boolean;
    sos_share_gps: boolean;
    /** Consentement aux données sensibles (P8-08) : le retirer efface identité de genre et orientation. */
    sensitive_consent: boolean;
}

export type Channel = 'in_app' | 'email' | 'push';
export type ChannelPrefs = Record<Channel, boolean>;

export interface NotificationSettings {
    prefs: Record<string, ChannelPrefs>;
    categories: { key: string; locked: boolean }[];
    channels: Channel[];
    digest: string;
    digests: string[];
}

export interface Security {
    discreet: boolean;
    mask_previews: boolean;
    pin_enabled: boolean;
    neutral_tab_title: boolean;
}

export interface LanguageSettings {
    current: string;
    available: string[];
    planned: string[];
}

export interface Newsletter {
    subscribed: boolean;
    status: string | null;
    pending_confirmation: boolean;
    interests: string[];
    available_interests: { slug: string; label: string }[];
    confirmed_at: string | null;
}

export interface SessionRow {
    id: string;
    kind: 'token' | 'web';
    name: string;
    ip: string | null;
    current: boolean;
    last_active_at: string | null;
    created_at: string | null;
}

export interface DeviceRow {
    device_uuid: string;
    label: string | null;
    platform: string | null;
    app_version: string | null;
    revoked: boolean;
    last_seen_at: string | null;
    last_sync_at: string | null;
    created_at: string | null;
}

export interface Limits {
    password_min: number;
    name_max: number;
    bio_max: number;
    deletion_days: number;
    export_hours: number;
}

export type Theme = 'light' | 'dark' | 'auto';
