<?php

declare(strict_types=1);

namespace App\Domain\Cases;

/**
 * Champs de détection des doublons suspects (règle `duplicates` du catalogue, `params.fields`) : deux dossiers qui
 * partagent toutes ces valeurs sont signalés à la saisie (avertissement, jamais un refus). Noms du prototype
 * (admin/validation.php `$dupFieldsAll`) → colonnes de `cases`. Un champ inconnu est ignoré ; aucun champ connu →
 * les champs par défaut (date, commune, population, tranche d'âge).
 */
final class DuplicateCriteria
{
    /** @var array<string, string> champ du catalogue => colonne */
    public const COLUMNS = [
        'date' => 'occurred_on',
        'commune' => 'commune_id',
        'population' => 'population_id',
        'age' => 'age_range',
        'type' => 'violence_type_id',
        'sexe' => 'sex',
    ];

    /** @var list<string> */
    public const DEFAULT_FIELDS = ['date', 'commune', 'population', 'age'];

    /**
     * @param  list<string>  $fields
     */
    private function __construct(public readonly array $fields) {}

    /**
     * @param  array<string, mixed>|null  $params
     */
    public static function fromParams(?array $params): self
    {
        $raw = is_array($params['fields'] ?? null) ? $params['fields'] : [];
        $fields = array_values(array_unique(array_filter(
            array_map(static fn (mixed $field): string => is_string($field) ? $field : '', $raw),
            static fn (string $field): bool => array_key_exists($field, self::COLUMNS),
        )));

        return new self($fields === [] ? self::DEFAULT_FIELDS : $fields);
    }

    /**
     * Colonnes à comparer, dans l'ordre des champs.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return array_map(static fn (string $field): string => self::COLUMNS[$field], $this->fields);
    }

    /**
     * Valeurs du dossier pour les colonnes comparées ; null si l'une manque (un champ vide ne prouve aucun doublon).
     *
     * @param  array<string, mixed>  $attributes  colonne => valeur scalaire
     * @return array<string, scalar>|null
     */
    public function valuesFrom(array $attributes): ?array
    {
        $values = [];

        foreach ($this->columns() as $column) {
            $value = $attributes[$column] ?? null;

            if ($value === null || $value === '' || ! is_scalar($value)) {
                return null;
            }

            $values[$column] = $value;
        }

        return $values;
    }
}
