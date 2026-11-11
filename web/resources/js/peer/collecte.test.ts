// @vitest-environment jsdom
// Logique du formulaire de collecte (P9-04) : validation par les règles ACTIVES du catalogue (mêmes contrôles que le
// serveur, CaseRules), charge utile de POST pair/cases, brouillon sans le champ « notes ».
import { describe, expect, it } from 'vitest';
import { activeRules, collecteForm, collecteOptions, TODAY } from '@/test/peer';
import { draftIsEmpty, DRAFT_EXCLUDED, emptyValues, restoreDraft, toPayload, validate, type CollecteContext } from './collecte';
import { sanitizeDraft } from '@/stores/drafts';
import type { CollecteValues } from './types';

const t = (key: string, named?: Record<string, unknown>) => (named ? `${key}:${JSON.stringify(named)}` : key);

const context = (rules = activeRules()): CollecteContext => ({ form: collecteForm(), options: collecteOptions(), rules, today: TODAY });
const valid = (overrides: Partial<CollecteValues> = {}): CollecteValues => ({
    kind: 'VBG', occurred_on: '2026-10-18', violence_type_id: '3', zone_id: '4', commune_id: '41', population_id: '1', sex: 'femme', age_range: '18-24', referral: 'Juriste', notes: '', ...overrides,
});

describe('validate', () => {
    it('accepte une fiche complète', () => {
        expect(validate(valid(), context(), t)).toEqual({});
        expect(validate(valid({ commune_id: '', referral: '', notes: '' }), context(), t)).toEqual({});
    });

    it('exige les champs obligatoires et des valeurs des listes (règle verrouillée required)', () => {
        const errors = validate(emptyValues(TODAY), context(), t);
        expect(Object.keys(errors).sort()).toEqual(['age_range', 'kind', 'population_id', 'sex', 'violence_type_id', 'zone_id']);
        expect(errors.kind).toBe('peer.collecte.errors.required');
        expect(validate(valid({ sex: 'robot', zone_id: '999', age_range: '5-9', referral: 'Voisin' }), context(), t))
            .toMatchObject({ sex: 'peer.collecte.errors.invalid', zone_id: 'peer.collecte.errors.invalid', age_range: 'peer.collecte.errors.invalid', referral: 'peer.collecte.errors.invalid' });
    });

    it('date_not_future (RG-07) : refuse demain seulement si la règle est active', () => {
        expect(validate(valid({ occurred_on: '2026-10-21' }), context(), t).occurred_on).toBe('peer.collecte.errors.date_future');
        expect(validate(valid({ occurred_on: TODAY }), context(), t)).toEqual({});
        expect(validate(valid({ occurred_on: '2026-10-21' }), context(activeRules('required')), t)).toEqual({});
        expect(validate(valid({ occurred_on: '2026-02-30' }), context(), t).occurred_on).toBe('peer.collecte.errors.date_invalid');
        expect(validate(valid({ occurred_on: '' }), context(), t).occurred_on).toBe('peer.collecte.errors.required');
    });

    it('commune_in_zone (RG-08) : la commune doit être du département ; règle désactivée, elle doit seulement exister', () => {
        expect(validate(valid({ zone_id: '8', commune_id: '41' }), context(), t).commune_id).toBe('peer.collecte.errors.commune_in_zone');
        expect(validate(valid({ zone_id: '8', commune_id: '41' }), context(activeRules('required')), t)).toEqual({});
        expect(validate(valid({ commune_id: '9999' }), context(activeRules('required')), t).commune_id).toBe('peer.collecte.errors.commune_in_zone');
    });

    it('min_len_20 : notes vides ou d’au moins 20 caractères, seulement si la règle est active', () => {
        expect(validate(valid({ notes: 'trop court' }), context(), t).notes).toBe('peer.collecte.errors.notes_min:{"min":20}');
        expect(validate(valid({ notes: '   ' }), context(), t)).toEqual({});
        expect(validate(valid({ notes: 'Accueil réalisé, orientation faite.' }), context(), t)).toEqual({});
        expect(validate(valid({ notes: 'trop court' }), context(activeRules('required')), t)).toEqual({});
    });

    it('age_range : seules les tranches proposées par le serveur (Q25 a) sont admises', () => {
        expect(validate(valid({ age_range: '10-14' }), context(), t).age_range).toBe('peer.collecte.errors.invalid');
    });
});

describe('toPayload', () => {
    it('convertit les identifiants en nombres, omet la commune, l’orientation et les notes vides, rogne les notes', () => {
        expect(toPayload(valid({ commune_id: '', referral: '', notes: '  ' }))).toEqual({
            kind: 'VBG', occurred_on: '2026-10-18', zone_id: 4, population_id: 1, sex: 'femme', age_range: '18-24', violence_type_id: 3,
        });
        expect(toPayload(valid({ notes: ' Résumé non identifiant de la prise en charge. ' }))).toMatchObject({ commune_id: 41, referral: 'Juriste', notes: 'Résumé non identifiant de la prise en charge.' });
    });
});

describe('brouillon', () => {
    it('ne garde jamais les notes ni de champ sensible', () => {
        const draft = sanitizeDraft({ ...valid({ notes: 'détail sensible' }), contact: '+22901000000' }, DRAFT_EXCLUDED);
        expect(draft).not.toHaveProperty('notes');
        expect(draft).not.toHaveProperty('contact');
        expect(draft).toMatchObject({ kind: 'VBG', zone_id: '4' });
    });

    it('relit un brouillon : chaînes connues seulement, date du jour par défaut, jamais de notes', () => {
        expect(restoreDraft({ kind: 'VDH', zone_id: '8', notes: 'x', inconnu: 'y', sex: 12 }, TODAY)).toEqual({ ...emptyValues(TODAY), kind: 'VDH', zone_id: '8' });
        expect(restoreDraft(null, TODAY)).toEqual(emptyValues(TODAY));
    });

    it('un formulaire intact (date du jour, notes ignorées) n’est pas un brouillon', () => {
        expect(draftIsEmpty(emptyValues(TODAY), TODAY)).toBe(true);
        expect(draftIsEmpty({ ...emptyValues(TODAY), notes: 'texte' }, TODAY)).toBe(true);
        expect(draftIsEmpty({ ...emptyValues(TODAY), kind: 'VBG' }, TODAY)).toBe(false);
    });
});
