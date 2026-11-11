<?php

declare(strict_types=1);

namespace App\Application\Admin\Referentials;

use App\Application\Shared\Referentials;
use App\Domain\Referentials\Exceptions\ReferentialRefused;
use App\Enums\DirectoryRole;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Écritures des référentiels administrables (décision client du 11/11/2026, permission `referentials.manage` revérifiée par
 * l'écran) : zones (de tout pays) et communes, types de violence, besoins, populations. Création, modification, activation,
 * ordre (Monter / Descendre), suppression refusée tant que l'élément est utilisé (on le désactive). Le code (`slug`) est
 * fixé à la création et ne change plus (clé des API, des signalements, des imports) ; le pays d'une zone ne change que tant
 * qu'elle n'est utilisée nulle part. Chaque écriture est journalisée (libellé et code seulement) ; l'invalidation du cache des
 * référentiels et donc de l'ETag de l'API est faite par App\Observers\ReferentialObserver à chaque `save` / `delete`.
 */
final class ManageReferentials
{
    /** Valeur maximale de `position` (colonne `unsignedTinyInteger`). */
    public const MAX_POSITION = 255;

    /**
     * @param  array<string, mixed>  $data  validé par ReferentialRules::zone()
     *
     * @throws ReferentialRefused si le pays change alors que la zone est utilisée
     */
    public function saveZone(?Zone $zone, array $data, User $actor): Zone
    {
        $zone ??= new Zone;
        $creating = ! $zone->exists;
        $country = strtoupper((string) $data['country_code']);

        if (! $creating && $country !== $zone->country_code && ! $this->countryEditable($zone)) {
            throw new ReferentialRefused('country_locked', ['label' => $zone->name]);
        }

        $zone->fill([
            'name' => trim((string) $data['label']),
            'country_code' => $country,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($creating) {
            $zone->slug = (string) $data['slug'];
            $zone->position = $this->nextPosition(Zone::query()->inCountry($country)->max('position'));
        }

        $zone->save();
        Audit::log($creating ? 'referentials.zone_created' : 'referentials.zone_updated', $zone, sprintf('%s (%s, %s)', $zone->name, $zone->slug, $zone->country_code), $actor);

        return $zone;
    }

    /**
     * @param  array<string, mixed>  $data  validé par ReferentialRules::commune()
     */
    public function saveCommune(Zone $zone, ?Commune $commune, array $data, User $actor): Commune
    {
        $commune ??= new Commune;
        $creating = ! $commune->exists;

        $commune->fill([
            'name' => trim((string) $data['label']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($creating) {
            $commune->zone_id = (int) $zone->getKey();
            $commune->slug = (string) $data['slug'];
            $commune->position = $this->nextPosition(Commune::query()->inZone($zone)->max('position'));
        }

        $commune->save();
        Audit::log($creating ? 'referentials.commune_created' : 'referentials.commune_updated', $commune, sprintf('%s (%s, zone %s)', $commune->name, $commune->slug, $zone->slug), $actor);

        return $commune;
    }

    /**
     * @param  array<string, mixed>  $data  validé par ReferentialRules::violenceType()
     */
    public function saveViolenceType(?ViolenceType $type, array $data, User $actor): ViolenceType
    {
        $type ??= new ViolenceType;
        $creating = ! $type->exists;

        $type->fill([
            'label' => trim((string) $data['label']),
            'icon' => trim((string) $data['icon']),
            'description' => trim((string) $data['description']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($creating) {
            $type->slug = (string) $data['slug'];
            $type->position = $this->nextPosition(ViolenceType::query()->max('position'));
        }

        $type->save();
        Audit::log($creating ? 'referentials.violence_type_created' : 'referentials.violence_type_updated', $type, sprintf('%s (%s)', $type->label, $type->slug), $actor);

        return $type;
    }

    /**
     * @param  array<string, mixed>  $data  validé par ReferentialRules::need()
     */
    public function saveNeed(?Need $need, array $data, User $actor): Need
    {
        $need ??= new Need;
        $creating = ! $need->exists;
        $role = isset($data['routed_role']) && is_string($data['routed_role']) && $data['routed_role'] !== '' ? DirectoryRole::tryFrom($data['routed_role']) : null;

        $need->fill([
            'label' => trim((string) $data['label']),
            'routed_role' => $role,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($creating) {
            $need->slug = (string) $data['slug'];
            $need->position = $this->nextPosition(Need::query()->max('position'));
        }

        $need->save();
        Audit::log($creating ? 'referentials.need_created' : 'referentials.need_updated', $need, sprintf('%s (%s%s)', $need->label, $need->slug, $role === null ? '' : ' → '.$role->value), $actor);

        return $need;
    }

    /**
     * @param  array<string, mixed>  $data  validé par ReferentialRules::population()
     */
    public function savePopulation(?Population $population, array $data, User $actor): Population
    {
        $population ??= new Population;
        $creating = ! $population->exists;

        $population->fill([
            'label' => trim((string) $data['label']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($creating) {
            $population->slug = (string) $data['slug'];
            $population->position = $this->nextPosition(Population::query()->max('position'));
        }

        $population->save();
        Audit::log($creating ? 'referentials.population_created' : 'referentials.population_updated', $population, sprintf('%s (%s)', $population->label, $population->slug), $actor);

        return $population;
    }

    /** Active ou désactive : un élément désactivé quitte les listes de saisie mais reste lisible sur l'existant. */
    public function toggle(Model $item, User $actor): bool
    {
        $active = ! (bool) $item->getAttribute('is_active');
        $item->setAttribute('is_active', $active);
        $item->save();
        Audit::log('referentials.'.self::kindOf($item).($active ? '_activated' : '_deactivated'), $item, self::describe($item), $actor);

        return $active;
    }

    /**
     * Monter (−1) ou descendre (+1) dans l'ordre de sa liste (zones d'un même pays, communes d'une même zone, ou liste complète).
     * Renvoie la nouvelle position (à partir de 1), ou null si l'élément est déjà en bout de liste.
     */
    public function move(Model $item, int $direction, User $actor): ?int
    {
        $ids = $this->orderedIds($item);
        $index = array_search((int) $item->getKey(), $ids, true);

        if ($index === false) {
            return null;
        }

        $target = $index + ($direction < 0 ? -1 : 1);

        if (! isset($ids[$target])) {
            return null;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        foreach ($ids as $position => $id) {
            $item->newQuery()->whereKey($id)->update(['position' => min($position + 1, self::MAX_POSITION)]);
        }

        // `update()` ne déclenche aucun événement de modèle : le cache des référentiels (et l'ETag de l'API) est invalidé à la main.
        app(Referentials::class)->invalidate();
        Audit::log('referentials.'.self::kindOf($item).'_moved', $item, self::describe($item).' → '.($target + 1), $actor);

        return $target + 1;
    }

    /**
     * @throws ReferentialRefused si l'élément est utilisé (ou si la zone a encore des communes)
     */
    public function delete(Model $item, User $actor): void
    {
        $label = self::label($item);

        if ($item instanceof Zone) {
            if ($item->communes()->exists()) {
                throw new ReferentialRefused('zone_has_communes', ['label' => $label]);
            }
        }

        if (self::usage($item) > 0) {
            throw new ReferentialRefused('in_use', ['label' => $label]);
        }

        $item->delete();
        Audit::log('referentials.'.self::kindOf($item).'_deleted', $item, self::describe($item), $actor);
    }

    /** Nombre d'utilisations de l'élément (hors communes d'une zone). */
    public static function usage(Model $item): int
    {
        return match (true) {
            $item instanceof Zone => ReferentialUsage::zone($item),
            $item instanceof Commune => ReferentialUsage::commune($item),
            $item instanceof ViolenceType => ReferentialUsage::violenceType($item),
            $item instanceof Need => ReferentialUsage::need($item),
            $item instanceof Population => ReferentialUsage::population($item),
            default => 0,
        };
    }

    /** Le pays d'une zone ne se change que si elle n'a ni commune ni référence. */
    public function countryEditable(Zone $zone): bool
    {
        return ! $zone->communes()->exists() && ReferentialUsage::zone($zone) === 0;
    }

    /**
     * Code ISO à partir d'un libellé : minuscules, sans accents, mots reliés (`_` pour un besoin, `-` sinon), tronqué à `$max`.
     */
    public static function slugFrom(string $label, string $separator = '-', int $max = 40): string
    {
        return rtrim(Str::limit(Str::slug($label, $separator), $max, ''), '-_');
    }

    private function nextPosition(mixed $max): int
    {
        return min((is_numeric($max) ? (int) $max : 0) + 1, self::MAX_POSITION);
    }

    /**
     * @return list<int>
     */
    private function orderedIds(Model $item): array
    {
        $ids = match (true) {
            $item instanceof Zone => Zone::query()->inCountry((string) $item->country_code)->ordered()->pluck('id')->all(),
            $item instanceof Commune => Commune::query()->inZone((int) $item->zone_id)->ordered()->pluck('id')->all(),
            $item instanceof ViolenceType => ViolenceType::query()->ordered()->pluck('id')->all(),
            $item instanceof Need => Need::query()->ordered()->pluck('id')->all(),
            $item instanceof Population => Population::query()->ordered()->pluck('id')->all(),
            default => [],
        };

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    private static function kindOf(Model $item): string
    {
        return match (true) {
            $item instanceof Zone => 'zone',
            $item instanceof Commune => 'commune',
            $item instanceof ViolenceType => 'violence_type',
            $item instanceof Need => 'need',
            default => 'population',
        };
    }

    private static function label(Model $item): string
    {
        $label = $item instanceof Zone || $item instanceof Commune ? $item->name : $item->getAttribute('label');

        return is_string($label) ? $label : '';
    }

    private static function describe(Model $item): string
    {
        $slug = $item->getAttribute('slug');

        return self::label($item).' ('.(is_string($slug) ? $slug : '').')';
    }
}
