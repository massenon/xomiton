// Données de test des écrans de l'espace pair (lot espace-pair, P9-03, P9-04, P9-08) : listes de la collecte, règles
// actives du catalogue, dossier, étape de suivi, alerte routée et détail, sous la forme des props Inertia et de l'API
// `pair/` (docs/API.md §22).
import type { AlertDetail, CaseItem, CaseSheet, CollecteForm, CollecteOptions, Followup, RoutedAlert, ValidationRule } from '@/peer/types';

export const TODAY = '2026-10-20';

export const collecteForm = (): CollecteForm => ({
    kinds: [{ value: 'VBG', label: 'Violence basée sur le genre' }, { value: 'VDH', label: 'Violation des droits humains' }],
    zones: [
        { id: 4, name: 'Zou', communes: [{ id: 41, name: 'Abomey' }, { id: 42, name: 'Bohicon' }] },
        { id: 8, name: 'Littoral', communes: [{ id: 81, name: 'Cotonou' }] },
    ],
    populations: [{ id: 1, label: 'Femmes' }, { id: 2, label: 'Personnes LBTQ+' }],
    sexes: [{ value: 'femme', label: 'Femme' }, { value: 'homme', label: 'Homme' }],
    violence_types: [{ id: 3, label: 'Violence physique' }, { id: 5, label: 'Violence psychologique' }],
});

export const collecteOptions = (): CollecteOptions => ({
    age_ranges: [{ value: '15-17', label: '15-17' }, { value: '18-24', label: '18-24' }],
    referrals: ['Point focal', 'Juriste'],
    followup_steps: ['Accueil', 'Écoute', 'Clôture'],
    notes_min_length: 20,
});

export const activeRules = (...keys: string[]): ValidationRule[] => (keys.length > 0 ? keys : ['required', 'date_not_future', 'age_range', 'commune_in_zone', 'min_len_20'])
    .map((key) => ({ key, label: key, description: null, locked: key === 'required', params: null }));

export function makeFollowup(overrides: Partial<Followup> = {}): Followup {
    return {
        id: 1,
        date: '2026-10-10',
        step: 'Accueil',
        outcome: 'realise',
        outcome_label: 'Réalisé',
        outcome_badge: 'success',
        next_on: null,
        overdue: false,
        comment: null,
        author: 'Awa_S',
        by_me: true,
        ...overrides,
    };
}

export function makeCase(overrides: Partial<CaseItem> = {}): CaseItem {
    return {
        id: 1,
        code: 'VBG-0042',
        kind: 'VBG',
        kind_label: 'Violence basée sur le genre',
        occurred_on: '2026-10-01',
        zone: { id: 4, name: 'Zou' },
        commune: { id: 41, name: 'Abomey' },
        population: { id: 1, label: 'Femmes' },
        sex: 'femme',
        sex_label: 'Femme',
        age_range: '18-24',
        age_range_label: '18-24',
        violence_type: { id: 3, label: 'Violence physique' },
        referral: 'Juriste',
        status: 'ouvert',
        status_label: 'Ouvert',
        status_badge: 'info',
        anonymized: false,
        notes: 'Accueil réalisé.',
        followups_count: 0,
        followups: [],
        closed_at: null,
        can: { update: true, add_followup: true },
        ...overrides,
    };
}

export function makeSheet(overrides: Partial<CaseSheet> = {}): CaseSheet {
    return {
        filled: false,
        reference: null,
        identification: { pseudo: null, sex: 'Femme', age_range: '18-24', population: 'Femmes', zone: 'Zou', commune: 'Abomey' },
        facts: { date: '2026-10-01', place: null, type: 'Violence physique', description: 'Accueil réalisé.' },
        services: { items: [], note: null },
        orientation: { referral: 'Juriste', contact: null, date: null, reason: null, urgency: null },
        consent: { given: null, mode: null, sharing: null },
        ...overrides,
    };
}

export function makeAlert(overrides: Partial<RoutedAlert> = {}): RoutedAlert {
    return {
        id: '01JAAAAAAAAAAAAAAAAAAAAAA1',
        type: 'sos',
        type_label: 'SOS',
        status: 'nouveau',
        status_label: 'Nouveau',
        priority: 'critique',
        priority_label: 'Critique',
        place: 'Abomey',
        acknowledged: false,
        created_at: new Date(Date.now() - 5 * 60_000).toISOString(),
        ...overrides,
    };
}

export function makeAlertDetail(overrides: Partial<AlertDetail> = {}): AlertDetail {
    return {
        id: '01JAAAAAAAAAAAAAAAAAAAAAA1',
        type: 'sos',
        type_label: 'SOS',
        status: 'nouveau',
        status_label: 'Nouveau',
        priority: 'critique',
        priority_label: 'Critique',
        category: 'physique',
        category_label: 'Violence physique',
        needs: [{ slug: 'mise_en_securite', label: 'Mise en sécurité' }],
        occurred_on: null,
        description: 'Je suis enfermée chez moi.',
        author: 'Anonyme',
        anonymous: true,
        contact: '+22901000000',
        preferred_channel_label: 'Appel',
        place: 'Abomey',
        position: { lat: 7.18, lng: 1.99, accuracy: 20 },
        acknowledged: false,
        acknowledged_at: null,
        cancelled: false,
        safe: false,
        created_at: new Date(Date.now() - 5 * 60_000).toISOString(),
        closed_at: null,
        history: [{ status: 'nouveau', status_label: 'Nouveau', at: new Date(Date.now() - 5 * 60_000).toISOString() }],
        can: { acknowledge: true, take: true, close: true, statuses: ['pris_en_charge', 'en_cours', 'clos'] },
        ...overrides,
    };
}
