// Types de l'espace pair (lot espace-pair, P9-03, P9-04, P9-08) : formes des props Inertia (PeerHomePageController,
// CollectePageController, PeerCasesPageController) et des ressources de l'API `pair/` (docs/API.md §22).

export interface PeerKpi {
    collected: number;
    this_month: number;
    in_progress: number;
    closed: number;
}

export interface PeerTask {
    key: 'alerts_to_take' | 'cases_to_contact';
    count: number;
}

/** Alerte routée vers moi, en résumé (RoutedAlertResource) : jamais le récit ni le contact. */
export interface RoutedAlert {
    id: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    place: string | null;
    acknowledged: boolean;
    created_at: string | null;
}

export interface RoutedAlerts {
    open: number;
    new: number;
    items: RoutedAlert[];
}

export interface AlertDetail {
    id: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    category: string | null;
    category_label: string | null;
    needs: Array<{ slug: string; label: string }>;
    occurred_on: string | null;
    description: string | null;
    author: string | null;
    anonymous: boolean;
    contact: string | null;
    preferred_channel_label: string | null;
    place: string | null;
    position: { lat: number; lng: number; accuracy: number | null } | null;
    acknowledged: boolean;
    acknowledged_at: string | null;
    cancelled: boolean;
    safe: boolean;
    created_at: string | null;
    closed_at: string | null;
    history: Array<{ status: string; status_label: string; at: string | null }>;
    can: { acknowledge: boolean; take: boolean; close: boolean; statuses: string[] };
}

export interface Option {
    value: string;
    label: string;
}

export interface ZoneOption {
    id: number;
    name: string;
    communes: Array<{ id: number; name: string }>;
}

export interface CollecteForm {
    kinds: Option[];
    zones: ZoneOption[];
    populations: Array<{ id: number; label: string }>;
    sexes: Option[];
    violence_types: Array<{ id: number; label: string }>;
}

export interface CollecteOptions {
    age_ranges: Option[];
    referrals: string[];
    followup_steps: string[];
    notes_min_length: number;
}

/** Règle active du catalogue (`GET pair/validation-rules`). */
export interface ValidationRule {
    key: string;
    label: string;
    description: string | null;
    locked: boolean;
    params: Record<string, unknown> | null;
}

export interface CaseItem {
    id: number;
    code: string;
    kind: string;
    kind_label: string;
    occurred_on: string | null;
    zone: { id: number; name: string } | null;
    commune: { id: number; name: string } | null;
    population: { id: number; label: string } | null;
    sex: string | null;
    sex_label: string | null;
    age_range: string | null;
    age_range_label: string | null;
    violence_type: { id: number; label: string } | null;
    referral: string | null;
    status: string;
    status_label: string;
    status_badge: string;
    anonymized: boolean;
    notes?: string | null;
    followups_count?: number;
    followups?: Followup[];
    closed_at: string | null;
    can: { update: boolean; add_followup: boolean };
}

export interface Followup {
    id: number;
    date: string | null;
    step: string;
    outcome: string;
    outcome_label: string;
    outcome_badge: string;
    next_on: string | null;
    overdue: boolean;
    comment: string | null;
    author: string | null;
    by_me: boolean;
}

export interface CasePage {
    data: CaseItem[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface CaseFilters {
    status: string | null;
    kind: string | null;
    q: string | null;
}

export interface CaseSheet {
    filled: boolean;
    reference: string | null;
    identification: { pseudo: string | null; sex: string | null; age_range: string | null; population: string | null; zone: string | null; commune: string | null };
    facts: { date: string | null; place: string | null; type: string | null; description: string | null };
    services: { items: string[]; note: string | null };
    orientation: { referral: string | null; contact: string | null; date: string | null; reason: string | null; urgency: string | null };
    consent: { given: boolean | null; mode: string | null; sharing: boolean | null };
}

/** Valeurs du formulaire de collecte (chaînes : champs natifs). */
export interface CollecteValues {
    kind: string;
    occurred_on: string;
    violence_type_id: string;
    zone_id: string;
    commune_id: string;
    population_id: string;
    sex: string;
    age_range: string;
    referral: string;
    notes: string;
}
