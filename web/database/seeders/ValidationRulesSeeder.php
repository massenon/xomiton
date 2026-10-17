<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ValidationRule;
use Illuminate\Database\Seeder;

/**
 * Catalogue des règles de validation du lot 7 (DATABASE_DICTIONARY.md §6 `validation_rules`), repris de
 * prototype_social/admin/validation.php ($catalog, $plaus, $dupFieldsAll, $dupFields). Les clés `rules_off`,
 * `plausibility`, `dup_fields` et `dup_enabled` des paramètres du prototype deviennent `is_active` et `params` ;
 * la règle « required » est verrouillée (`is_locked`) : toujours active, non désactivable.
 * Idempotent : rejouable sans doublon ; libellés, exemples, position et verrou sont réalignés, `is_active`
 * et `params` sont conservés s'ils ont été modifiés par l'administration (seule la clé documentaire `forms`
 * des `params` est réalignée).
 */
class ValidationRulesSeeder extends Seeder
{
    /**
     * Ordre du dictionnaire. `forms` = colonne « S'applique à » de l'administration (clé documentaire des `params`).
     *
     * @var array<string, array{label: string, description: string, forms: list<string>, valid: string|null, invalid: string|null, params: array<string, mixed>|null, locked?: bool}>
     */
    private const RULES = [
        'required' => [
            'label' => 'Champ obligatoire',
            'description' => 'Le champ ne peut pas être vide (espaces ignorés).',
            'forms' => ['Tous les formulaires'],
            'valid' => 'Cotonou',
            'invalid' => null,
            'params' => null,
            'locked' => true,
        ],
        'email' => [
            'label' => 'Adresse e-mail valide',
            'description' => 'Structure nom@domaine.tld.',
            'forms' => ['Inscription', 'Annuaire', 'Paramètres'],
            'valid' => 'awa@xomiton.bj',
            'invalid' => 'awa@',
            'params' => null,
        ],
        'phone_bj' => [
            'label' => 'Téléphone béninois',
            'description' => 'Format +229 01 XX XX XX XX (8 à 10 chiffres, indicatif facultatif).',
            'forms' => ['Inscription', 'Annuaire', 'Collecte', 'Profil'],
            'valid' => '+229 01 97 38 88 47',
            'invalid' => '12-AB',
            'params' => null,
        ],
        'date_not_future' => [
            'label' => 'Date non postérieure à aujourd’hui',
            'description' => 'Empêche la saisie d’un événement ou d’une collecte datée du futur (RG-07).',
            'forms' => ['Collecte de données', 'Registres', 'Suivi'],
            'valid' => '2026-01-15',
            'invalid' => '2099-01-01',
            'params' => null,
        ],
        'age_range' => [
            'label' => 'Âge entre 10 et 99 ans',
            'description' => 'Valeur plausible pour les populations accompagnées.',
            'forms' => ['Collecte de données', 'Fiches VBG/VDH'],
            'valid' => '24',
            'invalid' => '150',
            'params' => null,
        ],
        'min_len_20' => [
            'label' => 'Description d’au moins 20 caractères',
            'description' => 'Évite les signalements vides ou inexploitables.',
            'forms' => ['Signalement', 'SOS', 'Besoins'],
            'valid' => 'Menaces répétées par un voisin depuis une semaine.',
            'invalid' => 'Menaces',
            'params' => null,
        ],
        'positive_int' => [
            'label' => 'Entier positif',
            'description' => 'Effectifs, seuils, cibles d’indicateurs.',
            'forms' => ['Indicateurs', 'Paramètres'],
            'valid' => '12',
            'invalid' => '-3',
            'params' => null,
        ],
        'commune_in_zone' => [
            'label' => 'Commune cohérente avec le département',
            'description' => 'La commune choisie doit appartenir au département sélectionné (liste en cascade).',
            'forms' => ['Collecte', 'Inscription', 'Annuaire', 'Signalement'],
            'valid' => 'Cotonou',
            'invalid' => null,
            'params' => null,
        ],
        'case_code' => [
            'label' => 'Code dossier VBG-0000 / VDH-0000',
            'description' => 'Format normalisé des codes de registre : préfixe selon la nature du dossier, quatre chiffres (RG-06).',
            'forms' => ['Registres', 'Suivi'],
            'valid' => 'VBG-0042',
            'invalid' => 'vbg42',
            'params' => null,
        ],
        'password_strong' => [
            'label' => 'Mot de passe fort',
            'description' => '8 caractères minimum, une majuscule, un chiffre.',
            'forms' => ['Inscription', 'Réinitialisation', 'Utilisateurs'],
            'valid' => 'Xomiton2026',
            'invalid' => 'abc',
            'params' => null,
        ],
        'plausibility' => [
            'label' => 'Règles de plausibilité',
            'description' => 'Âge entre les bornes, date ni future ni plus ancienne que l’ancienneté maximale (avertissement), coordonnées GPS dans le Bénin.',
            'forms' => ['Collecte de données', 'Fiches VBG/VDH', 'SOS'],
            'valid' => 'Âge 24 · 2026-01-15 · lat 6.37, lng 2.39',
            'invalid' => 'Âge 150 · 2099-01-01 · lat 48.85, lng 2.35',
            'params' => [
                'age_min' => 10,
                'age_max' => 99,
                'max_past_years' => 5,
                'lat' => [6.2, 12.5],
                'lng' => [0.7, 3.9],
            ],
        ],
        'duplicates' => [
            'label' => 'Détection des doublons',
            'description' => 'Deux dossiers partageant la date, la commune, la population et la tranche d’âge sont signalés comme suspects à la saisie.',
            'forms' => ['Collecte de données', 'Registres'],
            'valid' => 'Dates ou communes différentes',
            'invalid' => 'VBG-0012 et VBG-0013 : 2026-03-02 · Cotonou · Femmes lesbiennes · 18-24',
            'params' => [
                'fields' => ['date', 'commune', 'population', 'age'],
                'available_fields' => ['date', 'commune', 'population', 'age', 'type', 'sexe'],
            ],
        ],
    ];

    public function run(): void
    {
        $position = 0;

        foreach (self::RULES as $key => $rule) {
            $position++;
            $locked = $rule['locked'] ?? false;
            $existing = ValidationRule::query()->withKey($key)->first();

            ValidationRule::query()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $rule['label'],
                    'description' => $rule['description'],
                    'valid_example' => $rule['valid'],
                    'invalid_example' => $rule['invalid'],
                    'position' => $position,
                    'is_locked' => $locked,
                    // Une règle verrouillée est toujours active ; sinon le réglage de l'administration est conservé.
                    'is_active' => $locked || ($existing?->is_active ?? true),
                    'params' => $this->params($rule, $existing?->params),
                ],
            );
        }
    }

    /**
     * Paramètres conservés de l'existant (bornes, champs) ; les formulaires concernés sont documentaires et
     * toujours réalignés sur le catalogue.
     *
     * @param  array{forms: list<string>, params: array<string, mixed>|null}  $rule
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function params(array $rule, ?array $existing): array
    {
        return array_merge($existing ?? $rule['params'] ?? [], ['forms' => $rule['forms']]);
    }
}
