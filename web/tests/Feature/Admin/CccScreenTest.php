<?php

declare(strict_types=1);

use App\Enums\CccKind;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\Law;
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

it('enregistre un texte alternatif par planche de BD et refuse une planche sans description', function (): void {
    $tool = CccTool::factory()->create([
        'kind' => CccKind::Comic, 'key' => 'bd99',
        'pages' => [['image' => 'a.jpg', 'caption' => 'Un', 'alt' => 'Ancien un'], ['image' => 'a.jpg', 'caption' => 'Deux', 'alt' => 'Ancien deux']],
    ]);
    $this->actingAs(cccUser());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')->call('openAlts', $tool->id);

    $screen->set('alts', ['Planche 1 : une jeune femme parle à une amie.', ''])->call('saveAlts')->assertHasErrors(['alts']);
    expect($tool->fresh()->pages[0]['alt'])->toBe('Ancien un');

    $screen->set('alts', ['Planche 1 : une jeune femme parle à une amie.', 'Planche 2 : elles entrent dans un centre de santé.'])->call('saveAlts')->assertHasNoErrors();
    expect($tool->fresh()->pages[1]['alt'])->toBe('Planche 2 : elles entrent dans un centre de santé.');
});
