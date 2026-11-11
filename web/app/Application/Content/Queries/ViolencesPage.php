<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\ViolencesContent;
use App\Application\Shared\Referentials;

/**
 * Contenu de la page publique `/violences` (P4-04, prototype violences.php) : formes de violences du référentiel
 * `violence_types` (Referentials : actives, ordonnées) avec leurs signes à repérer (lang/fr/pages/violences.php, rangés
 * par slug, liste vide pour un type sans signes), cycle, questions de l'auto-évaluation et seuils du prototype
 * (violences.php l.173-175 : 0 ou 1 « oui » → faible, 2 à 4 → moyen, au-delà → élevé). Lecture seule.
 */
final readonly class ViolencesPage
{
    public const LOW_MAX = 1;

    public const MEDIUM_MAX = 4;

    private const FALLBACK_ICON = 'bi-shield-exclamation';

    public function __construct(private Referentials $referentials) {}

    public function __invoke(): ViolencesContent
    {
        $signs = (array) trans('pages/violences.forms.signs');

        return new ViolencesContent(
            forms: array_map(static fn (array $type): array => [
                'slug' => $type['slug'],
                'label' => $type['label'],
                'description' => $type['description'],
                'icon' => self::icon($type['icon']),
                'signs' => array_values(array_filter((array) ($signs[$type['slug']] ?? []), is_string(...))),
            ], $this->referentials->violenceTypes()),
            cycle: array_values((array) trans('pages/violences.cycle.steps')),
            questions: array_values(array_filter((array) trans('pages/violences.check.questions'), is_string(...))),
            lowMax: self::LOW_MAX,
            mediumMax: self::MEDIUM_MAX,
        );
    }

    /** Classe Bootstrap Icons du référentiel (`bi-…` ou nom nu), repli neutre si absente ou mal formée. */
    private static function icon(?string $icon): string
    {
        $name = preg_replace('/^bi-/', '', trim((string) $icon));

        return is_string($name) && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) === 1 ? 'bi-'.$name : self::FALLBACK_ICON;
    }
}
