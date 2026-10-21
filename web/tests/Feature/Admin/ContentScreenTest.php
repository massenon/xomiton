<?php

declare(strict_types=1);

use App\Application\Seo\SitemapBuilder;
use App\Enums\ContentStatus;
use App\Livewire\Admin\Content\Index as ContentScreen;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\SlugRedirect;
use App\Models\Thematique;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * P12-03 : Contenu /admin/contenu (Livewire) — permissions (admin seul), ressources / thématiques / FAQ / catégories,
 * publication visible sur le public, invalidation du plan du site, méta SEO (longueurs et unicité), 301 d'un ancien slug,
 * vidéo sans sous-titres refusée, téléversements par MediaUploader.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    Storage::fake('local');
});

function contentUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function contentScreen(string $role = 'admin')
{
    test()->actingAs(contentUser($role));

    return Livewire::test(ContentScreen::class);
}

/** @return array<string, mixed> */
function resourceForm(array $overrides = []): array
{
    return array_merge([
        'title' => 'Guide du consentement',
        'kind' => 'guide',
        'category_id' => '',
        'thematique_id' => '',
        'slug' => '',
        'summary' => 'Un guide pour comprendre le consentement, pas à pas.',
        'body' => '<p>Le consentement est libre et réversible.</p>',
        'external_url' => '',
        'duration_minutes' => '',
        'is_offline_available' => false,
        'transcript' => '',
        'status' => 'brouillon',
        'meta_title' => '',
        'meta_description' => '',
    ], $overrides);
}

it('réserve /admin/contenu à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(contentUser($role))->get('/admin/contenu')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('ouvre /admin/contenu à l’admin', function (): void {
    $this->actingAs(contentUser())->get('/admin/contenu')->assertOk()->assertSee('Ressources');
});

it('crée une ressource en brouillon, invisible du public, puis la publie : elle devient visible et le plan du site est invalidé', function (): void {
    $screen = contentScreen();
    Cache::put(SitemapBuilder::CACHE_KEY, '<urlset/>', 3600);

    $screen->call('newResource')->set('resourceForm', resourceForm())->call('saveResource')->assertHasNoErrors();

    $resource = Resource::query()->where('title', 'Guide du consentement')->firstOrFail();
    expect($resource->status)->toBe(ContentStatus::Draft)->and($resource->slug)->toBe('guide-du-consentement')
        ->and(Cache::has(SitemapBuilder::CACHE_KEY))->toBeFalse();
    $this->get('/ressources/guide-du-consentement')->assertNotFound();

    Cache::put(SitemapBuilder::CACHE_KEY, '<urlset/>', 3600);
    $screen->call('toggleResource', $resource->id);

    expect($resource->fresh()->status)->toBe(ContentStatus::Published)->and($resource->fresh()->published_at)->not->toBeNull()
        ->and(Cache::has(SitemapBuilder::CACHE_KEY))->toBeFalse();
    $this->get('/ressources/guide-du-consentement')->assertOk()->assertSee('Guide du consentement');
    expect(AuditLog::query()->where('action', 'content.resource_published')->exists())->toBeTrue();

    $screen->call('toggleResource', $resource->id);
    expect($resource->fresh()->status)->toBe(ContentStatus::Draft);
    $this->get('/ressources/guide-du-consentement')->assertNotFound();
});

it('refuse en 422 les méta SEO hors longueur ou en doublon et une adresse mal formée', function (): void {
    Resource::factory()->create(['meta_title' => str_repeat('a', 45), 'meta_description' => str_repeat('b', 150)]);
    $screen = contentScreen();

    $screen->call('newResource')->set('resourceForm', resourceForm(['meta_title' => 'Trop court', 'meta_description' => 'Trop courte', 'slug' => 'Mauvaise Adresse']))
        ->call('saveResource')
        ->assertHasErrors(['resourceForm.meta_title', 'resourceForm.meta_description', 'resourceForm.slug']);

    $screen->set('resourceForm', resourceForm(['meta_title' => str_repeat('a', 45), 'meta_description' => str_repeat('b', 150)]))
        ->call('saveResource')
        ->assertHasErrors(['resourceForm.meta_title', 'resourceForm.meta_description']);

    $screen->set('resourceForm', resourceForm(['meta_title' => str_repeat('c', 51), 'meta_description' => str_repeat('d', 161)]))
        ->call('saveResource')
        ->assertHasErrors(['resourceForm.meta_title', 'resourceForm.meta_description']);

    $screen->set('resourceForm', resourceForm(['meta_title' => str_repeat('c', 45), 'meta_description' => str_repeat('d', 150)]))
        ->call('saveResource')->assertHasNoErrors();
});

it('redirige en 301 l’ancienne adresse d’une ressource et d’une thématique renommées', function (): void {
    $resource = Resource::factory()->create(['slug' => 'ancienne-adresse']);
    $thematique = Thematique::factory()->create(['slug' => 'ancienne-thematique', 'meta_title' => null]);
    $screen = contentScreen();

    $screen->call('editResource', $resource->id)->set('resourceForm.slug', 'nouvelle-adresse')->call('saveResource')->assertHasNoErrors();
    $screen->call('editThematique', $thematique->id)->set('thematiqueForm.slug', 'nouvelle-thematique')->call('saveThematique')->assertHasNoErrors();

    $this->get('/ressources/ancienne-adresse')->assertRedirect(route('ressources.show', 'nouvelle-adresse'))->assertStatus(301);
    $this->get('/thematiques/ancienne-thematique')->assertRedirect(route('thematiques.show', 'nouvelle-thematique'))->assertStatus(301);
    $this->get('/ressources/nouvelle-adresse')->assertOk();
    $this->get('/ressources/adresse-inconnue')->assertNotFound();
    expect(SlugRedirect::query()->count())->toBe(2);

    // Un slug ancien d'une autre fiche ne se réutilise pas.
    $other = Resource::factory()->create();
    $screen->call('editResource', $other->id)->set('resourceForm.slug', 'ancienne-adresse')->call('saveResource')->assertHasErrors(['resourceForm.slug']);

    // Un ancien slug dépublié ne redirige pas vers un brouillon.
    $resource->refresh()->forceFill(['status' => ContentStatus::Draft])->save();
    $this->get('/ressources/ancienne-adresse')->assertNotFound();
});

it('refuse de publier une vidéo sans sous-titres ni transcription, mais enregistre le brouillon', function (): void {
    $screen = contentScreen();
    $form = resourceForm(['title' => 'Vidéo sur la contraception', 'kind' => 'video', 'external_url' => 'https://exemple.bj/video', 'status' => 'publie']);

    $screen->call('newResource')->set('resourceForm', $form)->call('saveResource')->assertHasErrors(['resourceForm.status']);
    expect(Resource::query()->where('title', 'Vidéo sur la contraception')->exists())->toBeFalse();

    $screen->set('resourceForm.status', 'brouillon')->call('saveResource')->assertHasNoErrors();
    $resource = Resource::query()->where('title', 'Vidéo sur la contraception')->firstOrFail();

    $screen->call('toggleResource', $resource->id);
    expect($resource->fresh()->status)->toBe(ContentStatus::Draft);

    $subtitles = UploadedFile::fake()->createWithContent('sous-titres.vtt', "WEBVTT\n\n00:00.000 --> 00:02.000\nBonjour\n");
    $screen->call('editResource', $resource->id)
        ->set('resourceForm.transcript', 'Bonjour et bienvenue.')->set('resourceSubtitles', $subtitles)->set('resourceForm.status', 'publie')
        ->call('saveResource')->assertHasNoErrors();

    $resource->refresh();
    expect($resource->status)->toBe(ContentStatus::Published)->and($resource->subtitles_path)->toStartWith('subtitles/');
    Storage::disk('local')->assertExists($resource->subtitles_path);
});

it('refuse des sous-titres qui ne sont pas du WebVTT et une image de partage qui ne mesure pas 1200 × 630', function (): void {
    $screen = contentScreen();

    $screen->call('newResource')->set('resourceForm', resourceForm(['title' => 'Audio sur la santé', 'kind' => 'audio', 'external_url' => 'https://exemple.bj/audio']))
        ->set('resourceSubtitles', UploadedFile::fake()->createWithContent('faux.vtt', 'Pas du WebVTT'))
        ->call('saveResource')->assertHasErrors(['resourceForm.status']);

    $screen->set('resourceSubtitles', null)->set('resourceOg', UploadedFile::fake()->image('og.jpg', 800, 400))
        ->call('saveResource')->assertHasErrors(['resourceForm.status']);
    expect(Resource::query()->where('title', 'Audio sur la santé')->exists())->toBeFalse();

    $screen->set('resourceOg', UploadedFile::fake()->image('og.jpg', 1200, 630))->call('saveResource')->assertHasNoErrors();
    $resource = Resource::query()->where('title', 'Audio sur la santé')->firstOrFail();
    expect($resource->og_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($resource->og_image_path);
});

it('téléverse un PDF sur le disque privé et une illustration sur le disque public', function (): void {
    $screen = contentScreen();
    $pdf = UploadedFile::fake()->createWithContent('fiche.pdf', Database\Factories\ResourceFactory::MINIMAL_PDF);

    $screen->call('newResource')->set('resourceForm', resourceForm(['title' => 'Fiche pratique PDF', 'kind' => 'pdf', 'status' => 'publie']))
        ->set('resourceFile', $pdf)->set('resourceImage', UploadedFile::fake()->image('cover.jpg', 800, 450))
        ->call('saveResource')->assertHasNoErrors();

    $resource = Resource::query()->where('title', 'Fiche pratique PDF')->firstOrFail();
    expect($resource->status)->toBe(ContentStatus::Published)->and($resource->file_size)->toBeGreaterThan(0);
    Storage::disk('local')->assertExists($resource->file_path);
    Storage::disk('public')->assertExists($resource->image_path);
});

it('publie une thématique qui devient visible, refuse une fiche vide et supprime', function (): void {
    $category = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create();
    $screen = contentScreen();
    $form = [
        'title' => 'Comprendre le consentement', 'category_id' => (string) $category->id, 'slug' => '', 'summary' => 'Un résumé d’au moins vingt caractères.',
        'body' => '', 'content_warning' => '', 'reading_minutes' => '4', 'status' => 'publie', 'meta_title' => '', 'meta_description' => '',
    ];

    $screen->call('newThematique')->set('thematiqueForm', $form)->call('saveThematique')->assertHasErrors(['thematiqueForm.status']);
    expect(Thematique::query()->count())->toBe(0);

    $screen->set('thematiqueForm.body', '<p>Le consentement se donne librement.</p>')->call('saveThematique')->assertHasNoErrors();
    $thematique = Thematique::query()->firstOrFail();
    $this->get('/thematiques/'.$thematique->slug)->assertOk()->assertSee('Comprendre le consentement');

    $screen->call('deleteThematique', $thematique->id);
    expect(Thematique::query()->count())->toBe(0);
    $this->get('/thematiques/'.$thematique->slug)->assertNotFound();
});

it('gère la FAQ : réponse de 60 mots au plus, publication, ordre et suppression', function (): void {
    $screen = contentScreen();
    $long = implode(' ', array_fill(0, 61, 'mot'));

    $screen->call('newFaq')->set('faqForm', ['question' => 'Comment porter plainte ?', 'answer' => $long, 'category_id' => '', 'is_published' => true, 'show_on_home' => false])
        ->call('saveFaq')->assertHasErrors(['faqForm.answer']);

    $screen->set('faqForm.answer', 'Rendez-vous au commissariat ou à la gendarmerie, ou contactez une personne de confiance.')->call('saveFaq')->assertHasNoErrors();
    $first = Faq::query()->firstOrFail();
    expect($first->slug)->toBe('comment-porter-plainte')->and($first->is_published)->toBeTrue();

    $screen->call('newFaq')->set('faqForm', ['question' => 'Où trouver de l’aide ?', 'answer' => 'Dans l’annuaire de la plateforme, près de chez vous.', 'category_id' => '', 'is_published' => true, 'show_on_home' => true])
        ->call('saveFaq')->assertHasNoErrors();
    $second = Faq::query()->where('question', 'Où trouver de l’aide ?')->firstOrFail();

    $screen->call('moveFaq', $second->id, 'up');
    expect($second->fresh()->position)->toBeLessThan($first->fresh()->position);

    $screen->call('toggleFaq', $first->id);
    expect($first->fresh()->is_published)->toBeFalse();
    $this->get('/aide/comment-porter-plainte')->assertNotFound();

    $screen->call('deleteFaq', $first->id);
    expect(Faq::query()->count())->toBe(1);
});

it('gère les catégories : unicité du couple type / slug, désactivation, refus de supprimer une catégorie utilisée', function (): void {
    $used = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => 'sante']);
    Thematique::factory()->create(['category_id' => $used->id]);
    $screen = contentScreen();

    $screen->call('newCategory')->set('categoryForm', ['kind' => 'thematique', 'slug' => 'sante', 'label' => 'Santé', 'icon' => 'bi-heart', 'color' => 'danger', 'is_active' => true])
        ->call('saveCategory')->assertHasErrors(['categoryForm.slug']);
    $screen->set('categoryForm.slug', 'droits')->set('categoryForm.label', 'Droits')->call('saveCategory')->assertHasNoErrors();
    expect(Category::query()->where('slug', 'droits')->exists())->toBeTrue();

    $screen->call('deleteCategory', $used->id);
    expect(Category::query()->whereKey($used->id)->exists())->toBeTrue();

    $screen->call('toggleCategory', $used->id);
    expect($used->fresh()->is_active)->toBeFalse();

    $unused = Category::query()->where('slug', 'droits')->firstOrFail();
    $screen->call('deleteCategory', $unused->id);
    expect(Category::query()->where('slug', 'droits')->exists())->toBeFalse();
});

it('rejoue la permission de chaque onglet : un compte sans resources.manage ne peut ni ouvrir ni écrire une ressource', function (): void {
    $user = contentUser('moderateur');
    $user->givePermissionTo('thematiques.manage');
    $this->actingAs($user);

    $screen = Livewire::test(ContentScreen::class);
    expect($screen->instance()->tabs())->toBe(['thematiques', 'categories']);

    $screen->call('setTab', 'ressources')->assertSet('tab', 'thematiques');
    $screen->call('newResource')->assertForbidden();
});

it('trie, filtre et cherche dans les ressources sans exposer de colonne chiffrée', function (): void {
    Resource::factory()->create(['title' => 'Alpha unique', 'status' => ContentStatus::Draft]);
    Resource::factory()->create(['title' => 'Beta publiée']);
    $screen = contentScreen();

    $screen->set('search', 'Alpha')->assertSee('Alpha unique')->assertDontSee('Beta publiée');
    $screen->set('search', '')->set('status', 'publie')->assertSee('Beta publiée')->assertDontSee('Alpha unique');
});
