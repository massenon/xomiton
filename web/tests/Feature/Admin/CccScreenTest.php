<?php

declare(strict_types=1);

use App\Enums\CccKind;
use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\Law;
use App\Models\OutboundPost;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * P12-04 : Outils CCC /admin/ccc (Livewire) — permission `ccc_tools.manage` (admin seul), publié / masqué (un outil masqué
 * n'est plus servi par CccTool::published(), un texte masqué disparaît de /lois), remplacement d'image, alt par planche de BD.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
});

function cccUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('réserve /admin/ccc à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(cccUser($role))->get('/admin/ccc')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('masque un outil CCC : il disparaît de la liste servie aux membres et aux pair·es, puis se republie', function (): void {
    $tool = CccTool::factory()->create(['kind' => CccKind::ImageBox, 'key' => 'bi99', 'is_published' => true]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'boite_images')->assertSee($tool->title);

    expect(CccTool::query()->published()->whereKey($tool->id)->exists())->toBeTrue();

    $screen->call('toggle', $tool->id);
    expect($tool->fresh()->is_published)->toBeFalse()->and(CccTool::query()->published()->whereKey($tool->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'ccc.tool_unpublished')->exists())->toBeTrue();

    $screen->call('toggle', $tool->id);
    expect(CccTool::query()->published()->whereKey($tool->id)->exists())->toBeTrue();
});

it('masque un texte de loi : il disparaît de la page publique /lois', function (): void {
    $law = Law::factory()->create(['title' => 'Loi test visible', 'is_published' => true]);
    $this->get('/lois')->assertOk()->assertSee('Loi test visible');

    $this->actingAs(cccUser());
    Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('toggleLaw', $law->id);

    expect($law->fresh()->is_published)->toBeFalse();
    auth()->logout();
    $this->get('/lois')->assertOk()->assertDontSee('Loi test visible');
});

it('remplace l’image d’un outil : l’ancienne est retirée, la planche d’une boîte à images suit', function (): void {
    $tool = CccTool::factory()->create([
        'kind' => CccKind::ImageBox, 'key' => 'bi98', 'image_path' => 'assets/images/xomiton/hero-03.jpg',
        'pages' => [['image' => 'assets/images/xomiton/hero-03.jpg', 'caption' => 'Légende', 'alt' => 'Une image']],
    ]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'boite_images')->call('openImage', $tool->id)
        ->set('image', UploadedFile::fake()->image('nouvelle.jpg', 800, 450))->call('saveImage')->assertHasNoErrors();

    $tool->refresh();
    expect($tool->image_path)->not->toStartWith('assets/')->and($tool->pages[0]['image'])->toBe($tool->image_path);
    Storage::disk('public')->assertExists($tool->image_path);

    $screen->call('openImage', $tool->id)->set('image', UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'))->call('saveImage')->assertHasErrors(['image']);
});

function cccComic(array $attributes = []): CccTool
{
    return CccTool::factory()->comic()->create([
        'pages' => [
            ['image' => 'a.jpg', 'caption' => 'Un', 'alt' => 'Alt un'],
            ['image' => 'b.jpg', 'caption' => 'Deux', 'alt' => 'Alt deux'],
            ['image' => 'c.jpg', 'caption' => 'Trois', 'alt' => 'Alt trois'],
        ],
        ...$attributes,
    ]);
}

/** @return list<string> */
function cccAlts(CccTool $tool): array
{
    return array_map(static fn (array $page): string => $page['alt'], $tool->fresh()->pages);
}

it('enregistre un texte alternatif par planche de BD et refuse une planche sans description', function (): void {
    $tool = cccComic(['key' => 'bd99']);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id)
        ->assertSet('pageEdits.0.alt', 'Alt un');

    $screen->set('pageEdits.1.alt', '')->call('savePages')->assertHasErrors(['pageEdits.1.alt']);
    expect(cccAlts($tool))->toBe(['Alt un', 'Alt deux', 'Alt trois']);

    $screen->set('pageEdits.1.alt', str_repeat('x', 201))->call('savePages')->assertHasErrors(['pageEdits.1.alt']);

    $screen->set('pageEdits.0.alt', 'Planche 1 : une jeune femme parle à une amie.')->set('pageEdits.1.alt', 'Planche 2 : elles entrent dans un centre de santé.')
        ->set('pageEdits.1.caption', 'Nouvelle légende')->call('savePages')->assertHasNoErrors();
    expect($tool->fresh()->pages[1])->toMatchArray(['alt' => 'Planche 2 : elles entrent dans un centre de santé.', 'caption' => 'Nouvelle légende']);
});

it('crée une BD : clé bd{n} automatique, dernière position, couverture et planches de départ', function (): void {
    CccTool::factory()->comic()->create(['key' => 'bd7', 'position' => 7]);
    $this->actingAs($admin = cccUser());

    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openCreate')
        ->set('title', '  Ma nouvelle BD  ')
        ->set('cover', UploadedFile::fake()->image('couverture.jpg', 800, 450))
        ->set('published', true)
        ->call('addDraftPage')
        ->set('draftPages.0.file', UploadedFile::fake()->image('p1.jpg', 800, 600))
        ->set('draftPages.0.caption', 'Première')
        ->set('draftPages.0.alt', 'Une jeune femme sourit.')
        ->call('saveComic')->assertHasNoErrors()->assertDispatched('toast');

    $tool = CccTool::query()->where('title', 'Ma nouvelle BD')->firstOrFail();
    expect($tool->key)->toBe('bd8')->and($tool->kind)->toBe(CccKind::Comic)->and($tool->position)->toBe(8)->and($tool->is_published)->toBeTrue()
        ->and($tool->pages)->toHaveCount(1)->and($tool->pages[0]['alt'])->toBe('Une jeune femme sourit.');
    Storage::disk('public')->assertExists($tool->image_path);
    Storage::disk('public')->assertExists($tool->pages[0]['image']);

    $log = AuditLog::query()->where('action', 'ccc.comic_created')->firstOrFail();
    expect($log->detail)->toBe('Ma nouvelle BD')->and($log->user_id)->toBe($admin->id);
});

it('crée une BD masquée sans image ni planche et refuse une BD publiée sans planche', function (): void {
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openCreate');

    $screen->set('title', '')->call('saveComic')->assertHasErrors(['title' => 'required']);
    $screen->set('title', str_repeat('t', 201))->call('saveComic')->assertHasErrors(['title' => 'max']);
    $screen->set('title', 'BD vide')->set('published', true)->call('saveComic')->assertHasErrors(['published']);
    expect(CccTool::query()->where('title', 'BD vide')->exists())->toBeFalse();

    $screen->set('published', false)->call('saveComic')->assertHasNoErrors();
    $tool = CccTool::query()->where('title', 'BD vide')->firstOrFail();
    expect($tool->key)->toBe('bd1')->and($tool->image_path)->toBeNull()->and($tool->pages)->toBe([])->and($tool->is_published)->toBeFalse();
});

it('exige un texte alternatif pour chaque planche de départ et ne crée rien en cas d’erreur', function (): void {
    $this->actingAs(cccUser());

    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openCreate')
        ->set('title', 'BD refusée')->call('addDraftPage')
        ->set('draftPages.0.file', UploadedFile::fake()->image('p1.jpg'))->set('draftPages.0.alt', '')
        ->call('saveComic')->assertHasErrors(['draftPages.0.alt' => 'required']);

    expect(CccTool::query()->where('title', 'BD refusée')->exists())->toBeFalse();
});

it('refuse un fichier qui n’est pas une image à la création sans laisser de média orphelin', function (): void {
    $this->actingAs(cccUser());

    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openCreate')
        ->set('title', 'BD piégée')
        ->set('cover', UploadedFile::fake()->image('couverture.jpg', 400, 300))
        ->call('addDraftPage')
        ->set('draftPages.0.file', UploadedFile::fake()->createWithContent('faux.jpg', 'ce n’est pas une image'))
        ->set('draftPages.0.alt', 'Description')
        ->call('saveComic')->assertHasErrors(['comic']);

    expect(CccTool::query()->where('title', 'BD piégée')->exists())->toBeFalse()->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('modifie le titre d’une BD sans toucher à sa clé', function (): void {
    $tool = cccComic(['key' => 'bd42', 'title' => 'Ancien titre']);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openEdit', $tool->id)->assertSet('title', 'Ancien titre');

    $screen->set('title', '')->call('saveComic')->assertHasErrors(['title']);
    $screen->set('title', 'Nouveau titre')->call('saveComic')->assertHasNoErrors();

    expect($tool->fresh()->title)->toBe('Nouveau titre')->and($tool->fresh()->key)->toBe('bd42')
        ->and(AuditLog::query()->where('action', 'ccc.comic_renamed')->exists())->toBeTrue();
});

it('n’édite jamais un outil qui n’est pas une BD', function (): void {
    $manual = CccTool::factory()->create(['key' => 'm98', 'title' => 'Module intact']);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd');

    foreach (['openEdit', 'openPages', 'openDelete'] as $action) {
        expect(fn () => $screen->call($action, $manual->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    }

    expect($manual->fresh()->title)->toBe('Module intact');
});

it('verrouille les identifiants : toolId et removeIndex ne se modifient pas depuis le navigateur', function (): void {
    $comic = cccComic();
    $manual = CccTool::factory()->create(['key' => 'm97']);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $comic->id);

    expect(fn () => $screen->set('toolId', $manual->id))->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    expect(fn () => $screen->set('removeIndex', 0))->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
});

it('ajoute une planche : image téléversée, légende, texte alternatif obligatoire', function (): void {
    $tool = cccComic();
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id);

    $screen->set('newImage', UploadedFile::fake()->image('p4.jpg', 800, 600))->set('newAlt', '')->call('addPage')->assertHasErrors(['newAlt' => 'required']);
    $screen->set('newAlt', str_repeat('x', 201))->call('addPage')->assertHasErrors(['newAlt' => 'max']);
    $screen->set('newImage', null)->set('newAlt', 'Quatrième planche')->call('addPage')->assertHasErrors(['newImage' => 'required']);
    expect($tool->fresh()->pages)->toHaveCount(3);

    $screen->set('newImage', UploadedFile::fake()->image('p4.jpg', 800, 600))->set('newCaption', 'Quatre')->set('newAlt', 'Quatrième planche')
        ->call('addPage')->assertHasNoErrors()->assertSet('newAlt', '')->assertSet('pageEdits.3.alt', 'Quatrième planche');

    $pages = $tool->fresh()->pages;
    expect($pages)->toHaveCount(4)->and($pages[3])->toMatchArray(['caption' => 'Quatre', 'alt' => 'Quatrième planche']);
    Storage::disk('public')->assertExists($pages[3]['image']);

    $screen->set('newImage', UploadedFile::fake()->create('script.pdf', 10, 'application/pdf'))->set('newAlt', 'PDF')->call('addPage')->assertHasErrors(['newImage']);
    expect($tool->fresh()->pages)->toHaveCount(4);
});

it('retire une planche après confirmation et supprime son média', function (): void {
    $path = UploadedFile::fake()->image('p.jpg', 400, 300)->store('images', 'public');
    $tool = cccComic(['pages' => [
        ['image' => 'assets/images/xomiton/hero-10.jpg', 'caption' => 'Un', 'alt' => 'Alt un'],
        ['image' => $path, 'caption' => 'Deux', 'alt' => 'Alt deux'],
    ]]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id);

    $screen->call('askRemovePage', 1)->assertSet('removeIndex', 1);
    expect($tool->fresh()->pages)->toHaveCount(2);

    $screen->call('removePage')->assertHasNoErrors()->assertSet('removeIndex', null)->assertCount('pageEdits', 1);
    expect($tool->fresh()->pages)->toHaveCount(1)->and($tool->fresh()->pages[0]['alt'])->toBe('Alt un');
    Storage::disk('public')->assertMissing($path);
    expect(AuditLog::query()->where('action', 'ccc.comic_page_removed')->exists())->toBeTrue();
});

it('garde au moins une planche à une BD publiée', function (): void {
    $tool = cccComic(['pages' => [['image' => 'a.jpg', 'caption' => '', 'alt' => 'Seule']], 'is_published' => true]);
    $this->actingAs(cccUser());

    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id)->call('askRemovePage', 0)->call('removePage')->assertHasErrors(['pages']);
    expect($tool->fresh()->pages)->toHaveCount(1);
});

it('réordonne les planches avec « Monter » et « Descendre » : l’ordre est persistant et les textes saisis suivent', function (): void {
    $tool = cccComic();
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id)
        ->assertSeeHtml('id="xmCccPageUp0"')->assertSeeHtml('id="xmCccPageDown2"');

    $screen->set('pageEdits.2.alt', 'Alt trois modifié')->call('movePage', 2, -1)->assertHasNoErrors()->assertDispatched('xm-ccc-focus');
    expect(cccAlts($tool))->toBe(['Alt un', 'Alt trois', 'Alt deux'])
        ->and($screen->get('pageEdits.1.alt'))->toBe('Alt trois modifié');

    $screen->call('movePage', 0, 1);
    expect(cccAlts($tool))->toBe(['Alt trois', 'Alt un', 'Alt deux']);

    $screen->call('movePage', 0, -1)->assertHasErrors(['pages']);
    expect(cccAlts($tool))->toBe(['Alt trois', 'Alt un', 'Alt deux'])
        ->and(AuditLog::query()->where('action', 'ccc.comic_page_moved')->count())->toBe(2);
});

it('supprime une BD avec sa couverture et ses planches', function (): void {
    $cover = UploadedFile::fake()->image('c.jpg', 400, 300)->store('images', 'public');
    $page = UploadedFile::fake()->image('p.jpg', 400, 300)->store('images', 'public');
    $tool = cccComic(['image_path' => $cover, 'pages' => [['image' => $page, 'caption' => '', 'alt' => 'Alt']]]);
    $other = cccComic(['key' => 'bd88']);
    $this->actingAs(cccUser());

    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openDelete', $tool->id)->call('deleteComic')->assertHasNoErrors();

    expect(CccTool::query()->whereKey($tool->id)->exists())->toBeFalse()->and(CccTool::query()->whereKey($other->id)->exists())->toBeTrue();
    Storage::disk('public')->assertMissing($cover);
    Storage::disk('public')->assertMissing($page);
    expect(AuditLog::query()->where('action', 'ccc.comic_deleted')->exists())->toBeTrue();
});

it('refuse de supprimer une BD référencée par une publication sortante non envoyée, mais pas une publiée ou annulée', function (): void {
    $tool = cccComic(['key' => 'bd55']);
    $post = OutboundPost::factory()->create(['kind' => OutboundKind::Bd, 'reference' => 'bd55', 'status' => OutboundStatus::Scheduled]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openDelete', $tool->id)->call('deleteComic');

    $screen->assertHasErrors(['delete']);
    expect(CccTool::query()->whereKey($tool->id)->exists())->toBeTrue();
    expect(AuditLog::query()->where('action', 'ccc.comic_deleted')->exists())->toBeFalse();

    $post->forceFill(['status' => OutboundStatus::Cancelled])->save();
    $screen->call('deleteComic')->assertHasNoErrors();
    expect(CccTool::query()->whereKey($tool->id)->exists())->toBeFalse();
});

it('vérifie la permission à chaque action, pas seulement à l’affichage', function (string $action, array $arguments): void {
    $tool = cccComic();
    $admin = cccUser();
    $this->actingAs($admin);
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id);

    // Le rôle est retiré alors que l'écran reste ouvert.
    $admin->syncRoles(['membre']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...($arguments === [] ? [] : array_map(static fn ($v) => $v === '@id' ? $tool->id : $v, $arguments)))->assertForbidden();
    expect($tool->fresh()->pages)->toHaveCount(3)->and(CccTool::query()->count())->toBe(1);
})->with([
    'créer' => ['saveComic', []],
    'ouvrir la création' => ['openCreate', []],
    'ajouter une planche' => ['addPage', []],
    'enregistrer les planches' => ['savePages', []],
    'monter' => ['movePage', [1, -1]],
    'retirer' => ['removePage', []],
    'supprimer' => ['deleteComic', []],
    'publier' => ['toggle', ['@id']],
    'ouvrir la suppression' => ['openDelete', ['@id']],
]);

it('journalise sans jamais écrire le texte des planches', function (): void {
    $tool = cccComic();
    $this->actingAs(cccUser());
    Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openPages', $tool->id)
        ->set('newImage', UploadedFile::fake()->image('p4.jpg'))->set('newCaption', 'LÉGENDE SECRÈTE')->set('newAlt', 'ALT SECRET')->call('addPage')
        ->set('pageEdits.0.caption', 'AUTRE LÉGENDE SECRÈTE')->set('pageEdits.0.alt', 'AUTRE ALT SECRET')->call('savePages')
        ->call('movePage', 0, 1);

    $details = AuditLog::query()->where('action', 'like', 'ccc.comic_%')->pluck('detail')->implode(' | ');
    expect(AuditLog::query()->where('action', 'like', 'ccc.comic_%')->count())->toBe(3)
        ->and($details)->not->toContain('SECRET')->and($details)->not->toContain('SECRÈTE');
});

it('ne publie pas une BD sans planche mais publie une BD qui en a', function (): void {
    $empty = cccComic(['pages' => [], 'is_published' => false]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('toggle', $empty->id);

    expect($empty->fresh()->is_published)->toBeFalse();
    $full = cccComic(['is_published' => false]);
    $screen->call('toggle', $full->id);
    expect($full->fresh()->is_published)->toBeTrue();
});

it('sert aux lecteurs membre et pair des BD créées, réordonnées puis supprimées dans un ordre stable', function (): void {
    CccTool::query()->delete();
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd');

    foreach (['Première BD', 'Deuxième BD'] as $title) {
        $screen->call('openCreate')->set('title', $title)->set('published', true)->call('addDraftPage')
            ->set('draftPages.0.file', UploadedFile::fake()->image('p.jpg'))->set('draftPages.0.alt', 'Planche de '.$title)->call('saveComic')->assertHasNoErrors();
    }

    $served = fn () => CccTool::query()->published()->ofKind(CccKind::Comic)->ordered()->pluck('title')->all();
    expect($served())->toBe(['Première BD', 'Deuxième BD']);

    $first = CccTool::query()->where('title', 'Première BD')->firstOrFail();
    $screen->call('openPages', $first->id)->set('newImage', UploadedFile::fake()->image('p2.jpg'))->set('newAlt', 'Seconde planche')->call('addPage')->call('movePage', 1, -1);
    expect($first->fresh()->pages[0]['alt'])->toBe('Seconde planche')->and($first->fresh()->pagesCount())->toBe(2);

    $screen->call('openDelete', $first->id)->call('deleteComic');
    expect($served())->toBe(['Deuxième BD']);
});
