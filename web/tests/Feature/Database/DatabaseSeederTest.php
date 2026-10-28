<?php

declare(strict_types=1);

use App\Models\AppVersion;
use App\Models\Commune;
use App\Models\EmailTemplate;
use App\Models\Forum;
use App\Models\Indicator;
use App\Models\NewsletterInterest;
use App\Models\NewsletterTag;
use App\Models\Setting;
use App\Models\SocialChannel;
use App\Models\Thematique;
use App\Models\User;
use App\Models\ValidationRule;
use App\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Spatie\Permission\Models\Role;

/*
 * Intégration de la phase 1 : le DatabaseSeeder enchaîne tous les seeders de référence des lots
 * puis, hors production, les comptes de démonstration. Il doit être rejouable sans doublon.
 */

it('alimente tous les référentiels des lots dans l’ordre et reste idempotent', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Zone::count())->toBe(12)
        ->and(Commune::count())->toBe(29)
        ->and(Role::count())->toBe(5)
        ->and(Thematique::count())->toBe(29)
        ->and(Forum::count())->toBe(5)
        ->and(ValidationRule::count())->toBe(12)
        ->and(Indicator::count())->toBe(16)
        ->and(SocialChannel::count())->toBe(6)
        ->and(EmailTemplate::count())->toBe(12)
        ->and(NewsletterTag::count())->toBe(5)
        ->and(NewsletterInterest::count())->toBe(6)
        ->and(Setting::count())->toBeGreaterThan(0)
        ->and(AppVersion::count())->toBe(3);
});

it('crée les comptes de démonstration en testing et jamais en production', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(count(DemoAccountsSeeder::accounts()))
        ->and(User::where('email', 'admin@xomiton.bj')->first()?->hasRole('superadmin'))->toBeTrue();

    User::query()->forceDelete();
    $this->app->detectEnvironment(fn (): string => 'production');

    // En production, db:seed exige --force (ConfirmableTrait) : on passe par artisan directement.
    $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();

    expect(User::count())->toBe(0)
        ->and(Zone::count())->toBe(12);
});

it('sème un compte de démonstration par rôle, dont modérateur·rice et pair en attente, rejouable sans doublon', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoAccountsSeeder::class);

    $roles = collect(DemoAccountsSeeder::accounts())->pluck('role')->unique()->sort()->values()->all();

    expect($roles)->toBe(['admin', 'membre', 'moderateur', 'pair', 'superadmin'])
        ->and(User::count())->toBe(count(DemoAccountsSeeder::accounts()))
        ->and(User::where('email', 'moderateur@xomiton.bj')->first()?->hasRole('moderateur'))->toBeTrue()
        ->and(User::where('email', 'pair.attente@xomiton.bj')->first()?->status)->toBe(App\Enums\UserStatus::Pending)
        ->and(User::where('email', 'membre@xomiton.bj')->first()?->status)->toBe(App\Enums\UserStatus::Active);
});

it('déclare les seeders de référence dans l’ordre des dépendances', function (): void {
    $order = DatabaseSeeder::REFERENCE_SEEDERS;

    expect($order[0])->toBe(Database\Seeders\ReferenceDataSeeder::class)
        ->and($order[1])->toBe(Database\Seeders\RolesAndPermissionsSeeder::class)
        ->and($order)->not->toContain(DemoAccountsSeeder::class)
        ->and(DatabaseSeeder::DEMO_ENVIRONMENTS)->not->toContain('production');

    foreach ($order as $seeder) {
        expect(class_exists($seeder))->toBeTrue("Seeder manquant : {$seeder}");
    }
});
