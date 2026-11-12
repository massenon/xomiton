<?php

declare(strict_types=1);

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Livewire\Admin\Content\Index as ContentScreen;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\Thematique;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * P19-04 / P19-06 : administration des contenus des violences numériques — interrupteur « Page publique » d'une BD dans
 * /admin/ccc (journalisé, effet immédiat sur /bd/{key}) et champ « Références juridiques » d'une fiche dans /admin/contenu
 * (permission existante, HTML nettoyé, rejouer l'import ne l'efface pas).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('public');
    app(ImportViolencesNumeriques::class)();
});

function vnAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('rend une BD publique ou privée depuis /admin/ccc, avec effet immédiat sur sa page publique', function (): void {
    $this->actingAs(vnAdmin());
    $tool = CccTool::query()->where('key', 'vn04')->firstOrFail();
    $legacy = CccTool::factory()->comic()->create(['key' => 'bd1']);

    $screen = Livewire::test(CccScreen::class)->call('setTab', 'bd')
        ->assertSee(__('admin_ccc.public.label'));

    $screen->call('togglePublic', $tool->id);
    expect($tool->fresh()->is_public)->toBeFalse()
        ->and(AuditLog::query()->where('action', 'ccc.tool_made_private')->exists())->toBeTrue();
    $this->get('/bd/vn04')->assertNotFound();

    $screen->call('togglePublic', $legacy->id);
    expect($legacy->fresh()->is_public)->toBeTrue();
    $this->get('/bd/bd1')->assertOk();
});

it('refuse l’interrupteur public hors administration et pour un outil qui n’est pas une BD', function (): void {
    $this->actingAs(vnAdmin('moderateur'))->get('/admin/ccc')->assertForbidden();

    $this->actingAs(vnAdmin());
    $box = CccTool::factory()->imageBox()->create();
    Livewire::test(CccScreen::class)->call('setTab', 'boite_images')->call('togglePublic', $box->id);

    expect($box->fresh()->is_public)->toBeFalse();
});

it('enregistre les références juridiques d’une fiche (HTML nettoyé), affichées puis préservées par l’import', function (): void {
    $this->actingAs(vnAdmin());
    $fiche = Thematique::query()->where('slug', 'traque-gps-reseaux')->firstOrFail();

    Livewire::test(ContentScreen::class)
        ->call('editThematique', $fiche->id)
        ->assertSet('thematiqueForm.legal_references', '')
        ->set('thematiqueForm.legal_references', '<p>Références validées.</p><script>x()</script>')
        ->call('saveThematique')
        ->assertHasNoErrors();

    expect($fiche->fresh()->legal_references)->toBe('<p>Références validées.</p>');

    app(ImportViolencesNumeriques::class)();
    expect($fiche->fresh()->legal_references)->toBe('<p>Références validées.</p>');

    auth()->logout();
    $this->get('/thematiques/traque-gps-reseaux')->assertOk()->assertSee('Références validées.')->assertSee(__('pages/violences_numeriques.legal_validated'));
});

it('accepte une catégorie des violences numériques pour une fiche et vide les références juridiques', function (): void {
    $this->actingAs(vnAdmin());
    $fiche = Thematique::query()->where('slug', 'deepfakes')->firstOrFail();
    $fiche->forceFill(['legal_references' => '<p>Ancien texte.</p>'])->save();

    Livewire::test(ContentScreen::class)
        ->call('editThematique', $fiche->id)
        ->set('thematiqueForm.legal_references', '')
        ->call('saveThematique')
        ->assertHasNoErrors();

    expect($fiche->fresh()->legal_references)->toBeNull();
    $this->get('/thematiques/deepfakes')->assertOk()->assertDontSee('id="fiche-loi"', false);
});
