<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\Category;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\ViolenceType;
use App\Models\Zone;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Référentiels en cache (P3-06, PERFORMANCE.md « cache des référentiels 24 h ») : départements avec leurs communes,
 * types de violence, besoins, populations, catégories — actifs seulement, ordonnés, sous forme de tableaux prêts pour
 * les Resources de l'API, les vues Blade et Vue.
 *
 * Clé versionnée : `referentials:v<n>:<nom>` ; chaque écriture sur un modèle de référentiel (App\Observers\ReferentialObserver)
 * incrémente `<n>` et rend obsolètes toutes les entrées d'un coup, sans dépendre des tags (pilote `file` sur cPanel, D7).
 * `php artisan cache:clear` repart de la version 1. L'ETag HTTP des routes publiques est dérivé du contenu (hash de la
 * réponse, middleware `cache.headers`), donc il change dès que le référentiel change.
 */
final class Referentials
{
    public const VERSION_KEY = 'referentials:version';

    public const ZONES = 'zones';

    public const VIOLENCE_TYPES = 'violence_types';

    public const NEEDS = 'needs';

    public const POPULATIONS = 'populations';

    public const CATEGORIES = 'categories';

    /**
     * Départements actifs avec leurs communes actives.
     *
     * @return list<array{id: int, name: string, slug: string, position: int, communes: list<array{id: int, name: string, slug: string, position: int}>}>
     */
    public function zones(): array
    {
        return $this->remember(self::ZONES, static fn (): array => Zone::query()
            ->active()
            ->ordered()
            ->with(['communes' => static fn ($query) => $query->active()->ordered()])
            ->get()
            ->map(static fn (Zone $zone): array => [
                'id' => (int) $zone->getKey(),
                'name' => (string) $zone->name,
                'slug' => (string) $zone->slug,
                'position' => (int) $zone->position,
                'communes' => $zone->communes->map(static fn (Commune $commune): array => [
                    'id' => (int) $commune->getKey(),
                    'name' => (string) $commune->name,
                    'slug' => (string) $commune->slug,
                    'position' => (int) $commune->position,
                ])->values()->all(),
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, slug: string, label: string, icon: string|null, description: string|null, position: int}>
     */
    public function violenceTypes(): array
    {
        return $this->remember(self::VIOLENCE_TYPES, static fn (): array => ViolenceType::query()
            ->active()
            ->ordered()
            ->get()
            ->map(static fn (ViolenceType $type): array => [
                'id' => (int) $type->getKey(),
                'slug' => (string) $type->slug,
                'label' => (string) $type->label,
                'icon' => $type->icon,
                'description' => $type->description,
                'position' => (int) $type->position,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, slug: string, label: string, routed_role: string|null, position: int}>
     */
    public function needs(): array
    {
        return $this->remember(self::NEEDS, static fn (): array => Need::query()
            ->active()
            ->ordered()
            ->get()
            ->map(static fn (Need $need): array => [
                'id' => (int) $need->getKey(),
                'slug' => (string) $need->slug,
                'label' => (string) $need->label,
                'routed_role' => $need->routed_role?->value,
                'position' => (int) $need->position,
            ])
            ->values()
            ->all());
    }

    /**
     * @return list<array{id: int, slug: string, label: string, position: int}>
     */
    public function populations(): array
    {
        return $this->remember(self::POPULATIONS, static fn (): array => Population::query()
            ->active()
            ->ordered()
            ->get()
            ->map(static fn (Population $population): array => [
                'id' => (int) $population->getKey(),
                'slug' => (string) $population->slug,
                'label' => (string) $population->label,
                'position' => (int) $population->position,
            ])
            ->values()
            ->all());
    }

    /**
     * Catégories actives, toutes ou d'un seul `kind` (thematique, resource, faq, event).
     *
     * @return list<array{id: int, kind: string, slug: string, label: string, icon: string|null, color: string|null, position: int}>
     */
    public function categories(?string $kind = null): array
    {
        $all = $this->remember(self::CATEGORIES, static fn (): array => Category::query()
            ->active()
            ->orderBy('kind')
            ->ordered()
            ->get()
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->getKey(),
                'kind' => (string) $category->kind,
                'slug' => (string) $category->slug,
                'label' => (string) $category->label,
                'icon' => $category->icon,
                'color' => $category->color,
                'position' => (int) $category->position,
            ])
            ->values()
            ->all());

        if ($kind === null) {
            return $all;
        }

        return array_values(array_filter($all, static fn (array $category): bool => $category['kind'] === $kind));
    }

    /** Empreinte stable d'un référentiel (sha1 de son JSON), utilisable comme ETag ou clé de cache côté client. */
    public function hash(string $name): string
    {
        $data = match ($name) {
            self::ZONES => $this->zones(),
            self::VIOLENCE_TYPES => $this->violenceTypes(),
            self::NEEDS => $this->needs(),
            self::POPULATIONS => $this->populations(),
            self::CATEGORIES => $this->categories(),
            default => throw new \InvalidArgumentException(sprintf('Unknown referential [%s].', $name)),
        };

        return sha1((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Version courante du cache (1 après `cache:clear`). */
    public function version(): int
    {
        $version = Cache::get(self::VERSION_KEY);

        return is_numeric($version) ? max(1, (int) $version) : 1;
    }

    /** Invalide tous les référentiels d'un coup (appelé par l'observer et par ReferenceDataSeeder). */
    public function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, $this->version() + 1);
    }

    public function key(string $name): string
    {
        return sprintf('referentials:v%d:%s', $this->version(), $name);
    }

    public static function ttlSeconds(): int
    {
        return max(60, (int) config('xomiton.referentials.ttl_seconds', 86400));
    }

    /**
     * @template T of array
     *
     * @param  Closure(): T  $resolver
     * @return T
     */
    private function remember(string $name, Closure $resolver): array
    {
        /** @var T $value */
        $value = Cache::remember($this->key($name), self::ttlSeconds(), $resolver);

        return $value;
    }
}
