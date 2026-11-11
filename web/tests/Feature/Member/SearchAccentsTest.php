<?php

declare(strict_types=1);

use App\Models\Resource;
use App\Support\SearchText;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Tests\Support\FeedFixtures as F;

/* B5 (GUIDE_RECETTE §6) : la recherche ignore accents et casse (SQLite via xom_fold, MySQL via la collation _ci). */

it('normalise : minuscules, sans diacritiques, apostrophe typographique', function (): void {
    expect(SearchText::normalize('ÉTÉ'))->toBe('ete')
        ->and(SearchText::normalize('L’écoute'))->toBe("l'ecoute")
        ->and(SearchText::normalize(null))->toBe('');
});

it('trouve sans tenir compte des accents ni de la casse', function (string $title, string $term): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Resource::factory()->create(['title' => $title]);

    $this->actingAs(F::member())->get('/recherche?q='.urlencode($term))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('sections.0.total', 1));
})->with([
    ['Santé des femmes', 'sante'],
    ['Santé des femmes', 'SANTÉ'],
    ['Cercle d’écoute', 'ecoute'],
    ['Cercle d’écoute', "d'écoute"],
    ['Fête de l’été', 'ÉTÉ'],
]);

it('ne journalise aucun terme de recherche', function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Log::spy();

    $this->actingAs(F::member())->get('/recherche?q=secretterme')->assertOk();

    Log::shouldNotHaveReceived('info', fn ($args) => str_contains(json_encode($args), 'secretterme'));
});
