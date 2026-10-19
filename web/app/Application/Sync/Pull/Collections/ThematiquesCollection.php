<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Http\Resources\Content\ThematiqueCardResource;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `thematiques` : fiches publiées, lisibles hors connexion (carte + corps, sections, avertissement), pour tous les
 * comptes. Une fiche programmée entre dans le tirage à son heure de publication (position = la plus tardive de
 * `updated_at` et `published_at`) ; dépubliée ou supprimée : retirée de l'appareil. 25 fiches par page (corps longs).
 */
final class ThematiquesCollection extends EloquentSyncCollection
{
    public const NAME = 'thematiques';

    private const PAGE_SIZE = 25;

    public function name(): string
    {
        return self::NAME;
    }

    public function availableTo(User $user): bool
    {
        return true;
    }

    public function pageSize(): int
    {
        return min(parent::pageSize(), self::PAGE_SIZE);
    }

    protected function model(): string
    {
        return Thematique::class;
    }

    protected function visible(User $user): Builder
    {
        return Thematique::query()->published();
    }

    protected function changeExpression(string $table): string
    {
        $changed = parent::changeExpression($table);

        return sprintf('CASE WHEN %1$s.published_at IS NOT NULL AND %1$s.published_at > %2$s THEN %1$s.published_at ELSE %2$s END', $table, $changed);
    }

    protected function with(): array
    {
        return ['category'];
    }

    public function payload(Model $row, Request $request): array
    {
        /** @var Thematique $row */
        return ThematiqueCardResource::make($row)->resolve($request) + [
            'content_warning' => filled($row->content_warning) ? (string) $row->content_warning : null,
            'body' => filled($row->body) ? (string) $row->body : null,
            'sections' => array_values(array_map(static fn (array $section): array => [
                'title' => (string) ($section['title'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ], array_filter($row->sections ?? [], 'is_array'))),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }
}
