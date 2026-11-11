// Logique du formulaire de collecte `/collecte` (P9-04 ; prototype collecte.php), sans Vue : valeurs, validation par les
// **règles actives du catalogue** (les mêmes que le serveur, CaseRules : `GET pair/validation-rules`, servies dans les
// props de la page pour fonctionner sans réseau), charge utile de `POST pair/cases` et brouillon.
// Toujours appliqués (structure de la base, règle verrouillée `required`) : champs obligatoires, valeurs des listes ;
// selon le catalogue : `date_not_future` (RG-07), `age_range` (RG-08, Q25 a : tranches proposées par le serveur),
// `commune_in_zone` (RG-08), `min_len_20` (notes vides ou ≥ longueur minimale). Messages : peer.collecte.errors.* (tu).
import type { CollecteForm, CollecteOptions, CollecteValues, ValidationRule } from './types';

export const DRAFT_NAME = 'collecte';
/** Champs du brouillon : aucun texte libre (les notes, seul champ pouvant contenir un détail, ne sont jamais gardées). */
export const DRAFT_EXCLUDED = ['notes'] as const;

export type Translate = (key: string, named?: Record<string, unknown>) => string;

export interface CollecteContext {
    form: CollecteForm;
    options: CollecteOptions;
    rules: ValidationRule[];
    today: string;
}

export function emptyValues(today: string): CollecteValues {
    return { kind: '', occurred_on: today, violence_type_id: '', zone_id: '', commune_id: '', population_id: '', sex: '', age_range: '', referral: '', notes: '' };
}

export const isActive = (rules: ValidationRule[], key: string): boolean => rules.some((rule) => rule.key === key);

const DATE = /^\d{4}-\d{2}-\d{2}$/;
const validDate = (value: string): boolean => DATE.test(value) && !Number.isNaN(Date.parse(`${value}T00:00:00Z`)) && new Date(`${value}T00:00:00Z`).toISOString().startsWith(value);
const inList = (value: string, list: Array<string | number>): boolean => list.map(String).includes(value);

/** Erreurs par champ (première erreur de chaque champ) ; vide = formulaire valide. */
export function validate(values: CollecteValues, context: CollecteContext, t: Translate): Record<string, string> {
    const { form, options, rules, today } = context;
    const errors: Record<string, string> = {};
    const required = (field: keyof CollecteValues, list: Array<string | number>): void => {
        if (values[field] === '') errors[field] = t('peer.collecte.errors.required');
        else if (!inList(values[field], list)) errors[field] = t('peer.collecte.errors.invalid');
    };

    required('kind', form.kinds.map((kind) => kind.value));
    required('violence_type_id', form.violence_types.map((type) => type.id));
    required('zone_id', form.zones.map((zone) => zone.id));
    required('population_id', form.populations.map((population) => population.id));
    required('sex', form.sexes.map((sex) => sex.value));
    required('age_range', options.age_ranges.map((range) => range.value));

    if (values.occurred_on === '') errors.occurred_on = t('peer.collecte.errors.required');
    else if (!validDate(values.occurred_on)) errors.occurred_on = t('peer.collecte.errors.date_invalid');
    else if (isActive(rules, 'date_not_future') && values.occurred_on > today) errors.occurred_on = t('peer.collecte.errors.date_future');

    if (values.referral !== '' && !options.referrals.includes(values.referral)) errors.referral = t('peer.collecte.errors.invalid');

    const zone = form.zones.find((item) => String(item.id) === values.zone_id);
    if (values.commune_id !== '') {
        const known = form.zones.some((item) => item.communes.some((commune) => String(commune.id) === values.commune_id));
        const inZone = zone?.communes.some((commune) => String(commune.id) === values.commune_id) ?? false;
        // Règle désactivée : la commune doit seulement exister (comme CaseRules) ; active : être dans le département.
        if (!known || (isActive(rules, 'commune_in_zone') && !inZone)) errors.commune_id = t('peer.collecte.errors.commune_in_zone');
    }

    const notes = values.notes.trim();
    if (isActive(rules, 'min_len_20') && notes !== '' && Array.from(notes).length < options.notes_min_length) {
        errors.notes = t('peer.collecte.errors.notes_min', { min: options.notes_min_length });
    }

    return errors;
}

/** Charge utile de `POST pair/cases` (StoreCaseRequest) ; `client_uuid` est posé par la boîte d'envoi (clé d'idempotence). */
export function toPayload(values: CollecteValues): Record<string, unknown> {
    const notes = values.notes.trim();
    return {
        kind: values.kind,
        occurred_on: values.occurred_on,
        zone_id: Number(values.zone_id),
        ...(values.commune_id !== '' ? { commune_id: Number(values.commune_id) } : {}),
        population_id: Number(values.population_id),
        sex: values.sex,
        age_range: values.age_range,
        violence_type_id: Number(values.violence_type_id),
        ...(values.referral !== '' ? { referral: values.referral } : {}),
        ...(notes !== '' ? { notes } : {}),
    };
}

/** Brouillon relu : seulement des chaînes connues du formulaire, la date du jour si elle manque. */
export function restoreDraft(draft: unknown, today: string): CollecteValues {
    const base = emptyValues(today);
    if (draft === null || typeof draft !== 'object') return base;
    const source = draft as Record<string, unknown>;
    for (const key of Object.keys(base) as Array<keyof CollecteValues>) {
        if (key !== 'notes' && typeof source[key] === 'string') base[key] = source[key] as string;
    }
    return base;
}

export const draftIsEmpty = (values: CollecteValues, today: string): boolean => (Object.keys(values) as Array<keyof CollecteValues>)
    .every((key) => key === 'notes' || values[key] === '' || (key === 'occurred_on' && values[key] === today));
