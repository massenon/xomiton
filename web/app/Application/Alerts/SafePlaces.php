<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Support\Settings;

/**
 * Lieux sûrs de la page `/urgences` (P4-06, prototype urgences.php l.15-20 : « Où aller ? »). Aucune table ne les
 * porte (DATABASE_DICTIONARY.md) : paramètre `safe_places` (liste de `{name, where, text}`, administrable en P13 avec
 * les numéros `emergency_numbers`), sinon les quatre structures génériques du prototype (lang/fr/pages/urgences.php),
 * signalées « exemples » comme dans le prototype. Aucune adresse ni numéro : ceux-ci viennent de l'annuaire.
 */
final class SafePlaces
{
    private function __construct() {}

    /**
     * @return array{items: list<array{name: string, where: string, text: string}>, examples: bool}
     */
    public static function all(): array
    {
        $administered = self::wellFormed(Settings::array('safe_places', []));

        if ($administered !== []) {
            return ['items' => $administered, 'examples' => false];
        }

        return ['items' => self::wellFormed((array) trans('pages/urgences.places.items')), 'examples' => true];
    }

    /**
     * Entrées complètes seulement (nom non vide ; lieu et texte facultatifs), dans l'ordre.
     *
     * @param  array<mixed>  $rows
     * @return list<array{name: string, where: string, text: string}>
     */
    private static function wellFormed(array $rows): array
    {
        $places = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null) || trim($row['name']) === '') {
                continue;
            }

            $places[] = [
                'name' => trim($row['name']),
                'where' => is_string($row['where'] ?? null) ? trim($row['where']) : '',
                'text' => is_string($row['text'] ?? null) ? trim($row['text']) : '',
            ];
        }

        return $places;
    }
}
