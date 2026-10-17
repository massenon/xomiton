<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Point d'entrée des seeders (DATABASE_ENGINEERING.md §6) : référentiels d'abord, puis les
 * données applicatives de chaque lot ; les comptes de démonstration ne sont semés que hors
 * production. Tous les seeders appelés sont idempotents (updateOrCreate / firstOrCreate).
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeders de référence, dans l'ordre de dépendance (reference → identity → contenus → …).
     *
     * @var list<class-string<Seeder>>
     */
    public const REFERENCE_SEEDERS = [
        ReferenceDataSeeder::class,
        RolesAndPermissionsSeeder::class,
        ContentSeeder::class,
        ForumsSeeder::class,
        ValidationRulesSeeder::class,
        IndicatorsSeeder::class,
        SocialChannelsSeeder::class,
        EmailTemplatesSeeder::class,
        NewsletterTaxonomySeeder::class,
        SettingsSeeder::class,
        AppVersionsSeeder::class,
    ];

    /**
     * Environnements dans lesquels les comptes de démonstration sont créés.
     *
     * @var list<string>
     */
    public const DEMO_ENVIRONMENTS = ['local', 'testing', 'staging'];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(self::REFERENCE_SEEDERS);

        if (app()->environment(self::DEMO_ENVIRONMENTS)) {
            $this->call(DemoAccountsSeeder::class);
        }
    }
}
