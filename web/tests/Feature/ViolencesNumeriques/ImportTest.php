<?php

declare(strict_types=1);

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use App\Enums\CccKind;
use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\CccTool;
use App\Models\Faq;
use App\Models\Thematique;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ViolencesNumeriquesSeeder;
use Tests\Support\ViolencesNumeriquesFixtures;

/*
 * P19-06 : import idempotent des contenus des violences numériques (commande xomiton:violences-numeriques:import et
 * ViolencesNumeriquesSeeder, seeder de base aussi joué par xomiton:install) — mêmes lignes après deux exécutions, textes
 * réalignés, références juridiques et choix de l'administration préservés.
 */

/** @return array<string, int> */
function vnCounts(): array
{
    return [
        'categories' => Category::query()->where('kind', Category::KIND_DIGITAL_VIOLENCE)->count(),
        'thematiques' => Thematique::query()->withTrashed()->count(),
        'published' => Thematique::query()->published()->count(),
        'faqs' => Faq::query()->whereNotNull('thematique_id')->count(),
        'comics' => CccTool::query()->where('key', 'like', 'vn%')->count(),
    ];
}

it('importe 6 catégories, 23 fiches publiées, 69 questions et 23 BD publiques', function (): void {
    $this->artisan('xomiton:violences-numeriques:import')->assertSuccessful();

    expect(vnCounts())->toBe(['categories' => 6, 'thematiques' => 23, 'published' => 23, 'faqs' => 69, 'comics' => 23]);

    $fiche = Thematique::query()->where('slug', 'sextorsion')->firstOrFail();
    expect($fiche->category?->kind)->toBe(Category::KIND_DIGITAL_VIOLENCE)
        ->and($fiche->sections)->toHaveCount(5)
        ->and($fiche->content_warning)->not->toBeEmpty()
        ->and($fiche->cover_path)->toBe('assets/images/violences-numeriques/sextorsion/couverture.webp')
        ->and($fiche->og_image_path)->toBe('assets/images/violences-numeriques/sextorsion/partage.webp')
        ->and($fiche->legal_references)->toBeNull()
        ->and(mb_strlen((string) $fiche->meta_title))->toBeBetween(40, 50)
        ->and(mb_strlen((string) $fiche->meta_description))->toBeBetween(140, 160);

    // Les liens de la section « Où trouver de l’aide » survivent au nettoyage HTML.
    expect(collect($fiche->sections)->last()['body'])->toContain('href="/signaler"');

    $comic = CccTool::query()->where('key', 'vn02')->firstOrFail();
    expect($comic->getRawOriginal('kind'))->toBe(CccKind::Comic->value)
        ->and($comic->is_published)->toBeTrue()
        ->and($comic->is_public)->toBeTrue()
        ->and($comic->thematique_id)->toBe($fiche->id)
        ->and($comic->pages)->toHaveCount(4)
        ->and(collect($comic->pages)->every(fn (array $page): bool => $page['alt'] !== '' && $page['caption'] !== ''))->toBeTrue();
});

it('importe un sujet à venir en brouillon vide, sans BD ni questions', function (): void {
    ViolencesNumeriquesFixtures::withUpcoming('revenge-porn');
    $this->artisan('xomiton:violences-numeriques:import')->assertSuccessful();

    expect(vnCounts())->toBe(['categories' => 6, 'thematiques' => 23, 'published' => 22, 'faqs' => 66, 'comics' => 22]);
    $upcoming = Thematique::query()->where('slug', 'revenge-porn')->firstOrFail();
    expect($upcoming->getRawOriginal('status'))->toBe(ContentStatus::Draft->value)->and($upcoming->published_at)->toBeNull();
});

it('donne des méta SEO uniques à chaque fiche', function (): void {
    app(ImportViolencesNumeriques::class)();
    $fiches = Thematique::query()->published()->get();

    expect($fiches->pluck('meta_title')->unique())->toHaveCount(23)
        ->and($fiches->pluck('meta_description')->unique())->toHaveCount(23);
});

it('est rejouable : mêmes lignes, textes réalignés, références juridiques et choix de l’administration préservés', function (): void {
    $import = app(ImportViolencesNumeriques::class);
    $import();
    $before = vnCounts();

    $fiche = Thematique::query()->where('slug', 'harcelement-en-ligne')->firstOrFail();
    $fiche->forceFill(['title' => 'Titre modifié', 'legal_references' => '<p>Loi validée par l’administration.</p>'])->save();
    Faq::query()->where('thematique_id', $fiche->id)->where('position', 1)->update(['answer' => 'Réponse modifiée.']);
    CccTool::query()->where('key', 'vn01')->update(['is_public' => false, 'title' => 'Autre titre']);
    Thematique::query()->where('slug', 'sextorsion')->update(['status' => ContentStatus::Draft->value]);

    $report = $import();

    // Une fiche remise en brouillon par l'équipe le reste : seule la fiche « sextorsion » manque aux publiées.
    expect(vnCounts())->toEqual(['published' => $before['published'] - 1] + $before)->and($report['created'])->toBe(0);

    $fiche->refresh();
    expect($fiche->title)->toBe('Harcèlement en ligne')
        ->and($fiche->legal_references)->toBe('<p>Loi validée par l’administration.</p>');
    expect(Faq::query()->where('thematique_id', $fiche->id)->where('position', 1)->value('answer'))->not->toBe('Réponse modifiée.');

    $comic = CccTool::query()->where('key', 'vn01')->firstOrFail();
    expect($comic->title)->toBe('Les messages ne s’arrêtent pas')
        ->and($comic->is_public)->toBeFalse();
    expect(Thematique::query()->where('slug', 'sextorsion')->value('status'))->toBe(ContentStatus::Draft);
});

it('ne réaligne plus un sujet à venir que l’équipe a rédigé', function (): void {
    ViolencesNumeriquesFixtures::withUpcoming('revenge-porn');
    $import = app(ImportViolencesNumeriques::class);
    $import();
    Thematique::query()->where('slug', 'revenge-porn')->update(['title' => 'Revenge porn : fiche rédigée', 'body' => '<p>Contenu rédigé.</p>']);

    $import();

    expect(Thematique::query()->where('slug', 'revenge-porn')->value('title'))->toBe('Revenge porn : fiche rédigée');
});

it('laisse bd1 à bd3 réservées aux membres (is_public faux par défaut)', function (): void {
    $legacy = CccTool::factory()->comic()->create(['key' => 'bd1']);

    app(ImportViolencesNumeriques::class)();

    expect($legacy->fresh()->is_public)->toBeFalse();
});

it('fait partie des seeders de base (DatabaseSeeder et xomiton:install)', function (): void {
    expect(DatabaseSeeder::REFERENCE_SEEDERS)->toContain(ViolencesNumeriquesSeeder::class);
});
