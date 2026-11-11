<?php

declare(strict_types=1);

use App\Enums\AgeRange;
use App\Enums\DirectoryRole;
use App\Enums\Sex;
use App\Models\Category;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\ViolenceType;
use App\Models\Zone;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\QueryException;

/*
 * Lot 1 « référentiels » : une création par factory et une règle métier par table
 * (DATABASE_DICTIONARY.md §1). Tourne avec RefreshDatabase (tests/Pest.php).
 */

it('crée un département et refuse un nom en double', function (): void {
    $zone = Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral']);

    expect($zone->is_active)->toBeTrue()
        ->and(Zone::query()->active()->count())->toBe(1);

    expect(fn () => Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral-2']))
        ->toThrow(QueryException::class);
});

it('crée une commune rattachée à son département avec un slug unique par département', function (): void {
    $commune = Commune::factory()->create(['name' => 'Cotonou', 'slug' => 'cotonou']);

    expect($commune->zone)->toBeInstanceOf(Zone::class)
        ->and(Commune::query()->inZone($commune->zone)->count())->toBe(1);

    // Même slug dans un autre département : autorisé.
    Commune::factory()->create(['slug' => 'cotonou']);

    expect(fn () => Commune::factory()->create(['zone_id' => $commune->zone_id, 'slug' => 'cotonou']))
        ->toThrow(QueryException::class);
});

it('empêche la suppression d’un département qui a des communes', function (): void {
    $commune = Commune::factory()->create();

    expect(fn () => $commune->zone->delete())->toThrow(QueryException::class);
});

it('crée un type de violence avec un slug unique et un scope ordonné', function (): void {
    ViolenceType::factory()->create(['slug' => 'physique', 'position' => 2]);
    ViolenceType::factory()->create(['slug' => 'sexuelle', 'position' => 1]);

    expect(ViolenceType::query()->ordered()->pluck('slug')->all())->toBe(['sexuelle', 'physique']);

    expect(fn () => ViolenceType::factory()->create(['slug' => 'physique']))->toThrow(QueryException::class);
});

it('crée une population et filtre les inactives', function (): void {
    Population::factory()->create();
    Population::factory()->inactive()->create();

    expect(Population::query()->count())->toBe(2)
        ->and(Population::query()->active()->count())->toBe(1);
});

it('crée un besoin dont le rôle ciblé est un enum DirectoryRole', function (): void {
    $need = Need::factory()->routedTo(DirectoryRole::Juriste)->create(['slug' => 'soutien_juridique']);
    $focal = Need::factory()->create(['slug' => 'hebergement']);

    expect($need->fresh()->routed_role)->toBe(DirectoryRole::Juriste)
        ->and($need->getRawOriginal('routed_role'))->toBe('juriste')
        ->and($focal->targetRole())->toBe(DirectoryRole::PointFocal)
        ->and(Need::query()->routedTo(DirectoryRole::Juriste)->count())->toBe(1)
        ->and(Need::query()->routedTo(null)->count())->toBe(1);
});

it('crée une catégorie typée avec un slug unique par type', function (): void {
    Category::factory()->create(['slug' => 'droits']);
    Category::factory()->ofKind(Category::KIND_FAQ)->create(['slug' => 'droits']);

    expect(Category::query()->ofKind(Category::KIND_THEMATIQUE)->count())->toBe(1)
        ->and(Category::query()->ofKind(Category::KIND_FAQ)->count())->toBe(1);

    expect(fn () => Category::factory()->create(['slug' => 'droits']))->toThrow(QueryException::class);
});

it('alimente les référentiels du prototype de façon idempotente', function (): void {
    $this->seed(ReferenceDataSeeder::class);
    $this->seed(ReferenceDataSeeder::class);

    expect(Zone::query()->count())->toBe(12)
        ->and(Commune::query()->count())->toBe(29) // x_zones() du prototype compte 29 communes (le dictionnaire annonce 31)
        ->and(ViolenceType::query()->count())->toBe(5)
        ->and(Population::query()->count())->toBe(7)
        ->and(Need::query()->count())->toBe(7)
        ->and(Category::query()->ofKind(Category::KIND_THEMATIQUE)->count())->toBe(6)
        ->and(Need::query()->where('slug', 'soutien_medical')->sole()->routed_role)->toBe(DirectoryRole::Gynecologue)
        ->and(Zone::query()->where('slug', 'oueme')->sole()->communes()->count())->toBe(3);
});

it('expose les relations inverses vers les autres lots', function (): void {
    $commune = Commune::factory()->create();
    $zone = $commune->zone;

    expect($zone->communes)->toHaveCount(1)
        ->and($zone->users()->count())->toBe(0)
        ->and($zone->directoryContacts()->count())->toBe(0)
        ->and($zone->alerts()->count())->toBe(0)
        ->and($zone->cases()->count())->toBe(0)
        ->and($commune->users()->count())->toBe(0)
        ->and($commune->alerts()->count())->toBe(0)
        ->and(Category::factory()->create()->thematiques()->count())->toBe(0)
        ->and(Population::factory()->create()->cases()->count())->toBe(0)
        ->and(ViolenceType::factory()->create()->cases()->count())->toBe(0);
});

it('traduit et colore les enums du lot', function (): void {
    expect(AgeRange::FiftyPlus->label())->toBe('50 ans et plus')
        ->and(AgeRange::From10To14->isMinor())->toBeTrue()
        ->and(Sex::Autre->label())->toBe('Autre / non-binaire')
        ->and(Sex::Autre->badge())->toBe('violet')
        ->and(DirectoryRole::tryFromLabel('Pair animateur·rice'))->toBe(DirectoryRole::PairAnimateur)
        ->and(DirectoryRole::Gynecologue->isRoutable())->toBeTrue()
        ->and(DirectoryRole::options())->toHaveCount(8);
});
