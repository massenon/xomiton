<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Commune;
use App\Models\Population;
use App\Models\User;
use App\Models\ValidationRule;
use App\Models\ViolenceType;
use App\Models\Zone;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Support\Str;

/**
 * Données de test des dossiers et de l'API `pair/` (P9-01, P9-02) : référentiels, rôles et catalogue de validation
 * semés, comptes pair·e, charge utile de collecte valide, interrupteurs du catalogue.
 */
final class CaseFixtures
{
    public static function seed(): void
    {
        test()->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, ValidationRulesSeeder::class]);
    }

    public static function peer(): User
    {
        return FeedFixtures::member('pair');
    }

    public static function zone(string $name = 'Littoral'): Zone
    {
        return Zone::query()->where('name', $name)->firstOrFail();
    }

    public static function commune(Zone $zone): Commune
    {
        return Commune::query()->where('zone_id', $zone->getKey())->orderBy('position')->firstOrFail();
    }

    /**
     * Charge utile de `POST pair/cases` valide (collecte.php : tous les champs obligatoires).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = []): array
    {
        $zone = self::zone();

        return array_replace([
            'kind' => 'VBG',
            'occurred_on' => now('Africa/Porto-Novo')->subDays(3)->format('Y-m-d'),
            'zone_id' => $zone->id,
            'commune_id' => self::commune($zone)->id,
            'population_id' => Population::query()->orderBy('position')->value('id'),
            'sex' => 'femme',
            'age_range' => '18-24',
            'violence_type_id' => ViolenceType::query()->orderBy('position')->value('id'),
            'referral' => 'Psychologue',
            'notes' => 'Accueil réalisé, orientation vers une psychologue.',
            'client_uuid' => (string) Str::uuid(),
        ], $overrides);
    }

    public static function disable(string $key): void
    {
        ValidationRule::query()->where('key', $key)->update(['is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function params(string $key, array $params): void
    {
        $rule = ValidationRule::query()->where('key', $key)->firstOrFail();
        $rule->params = array_merge($rule->params ?? [], $params);
        $rule->save();
    }
}
