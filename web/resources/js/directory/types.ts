// Types de l'annuaire membre (lot annuaire-membre, P8-12 ; docs/API.md §19) : fiche (DirectoryContactResource), carte
// par département (BrowseDirectory::map), filtres d'adresse, réponse de la demande de rappel (AlertResource).

/** Portée servie (sans `directory.view` : 403, page comme API). */
export type Reach = 'public' | 'network';

export interface Place {
    id: number;
    name: string;
}

export interface DirectoryContact {
    id: number;
    name: string;
    role: string;
    role_label: string;
    role_color: string;
    org: string | null;
    phone: string | null;
    tel: string | null;
    whatsapp: string | null;
    whatsapp_url: string | null;
    email: string | null;
    availability: string | null;
    zone: Place | null;
    commune: Place | null;
    national: boolean;
    is_public: boolean;
    can: { callback: boolean };
}

export interface PageMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

export interface ContactPage {
    data: DirectoryContact[];
    meta: PageMeta;
}

export interface MapZone {
    id: number;
    name: string;
    slug: string;
    count: number;
}

export interface DirectoryMapData {
    zones: MapZone[];
    national: number;
    total: number;
}

export interface Filters {
    q: string | null;
    role: string | null;
    zone: number | null;
    commune: number | null;
}

export interface ZoneOption extends Place {
    communes: Place[];
}

export interface RoleOption {
    value: string;
    label: string;
}

export interface Limits {
    search_max: number;
    message_max: number;
    contact_max: number;
}

export interface CallbackPayload {
    contact: string;
    message: string | null;
    anonymous: boolean;
}

export interface CallbackResult {
    id: string;
    tracking_code: string;
    tracking_code_display: string;
    routed: { role: string; name?: string; phone?: string | null; whatsapp?: string | null } | null;
}
