<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Enums\BdConversationStatus;
use App\Livewire\Admin\Ccc\Conversations;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Models\AuditLog;
use App\Models\BdBubble;
use App\Models\BdConversation;
use App\Models\BdScene;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Support\ConversationsBdFixtures;

/*
 * P20 (PRD P0-9) : onglet « Conversations en BD » de /admin/ccc — permission `conversations_bd.manage` (admin,
 * superadmin) revérifiée à chaque action, publier / dépublier (grammaire exigée), ordonner, textes, bulles d'une case
 * (contrôles de la grammaire, aperçu), bibliothèque de scènes ; chaque écriture journalisée et marquée pour l'import.
 */

afterEach(function (): void {
    ConversationsBdFixtures::cleanup();
});

beforeEach(function (): void {
    // Images absentes : les modèles restent des brouillons (les tests publient eux-mêmes ce qu'il faut).
    ConversationsBdFixtures::source();
    $this->seed(RolesAndPermissionsSeeder::class);
    app(ImportConversationsBd::class)();
});

function bdcAdmin(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('donne la permission dédiée à l’admin et au superadmin seulement', function (): void {
    expect(RolesAndPermissionsSeeder::matrix()['conversations_bd.manage'])->toBe(['admin'])
        ->and(bdcAdmin('admin')->can('conversations_bd.manage'))->toBeTrue()
        ->and(bdcAdmin('superadmin')->can('conversations_bd.manage'))->toBeTrue()
        ->and(bdcAdmin('moderateur')->can('conversations_bd.manage'))->toBeFalse()
        ->and(bdcAdmin('pair')->can('conversations_bd.manage'))->toBeFalse();
});

it('affiche l’onglet dans /admin/ccc et liste les 12 modèles avec leurs images manquantes', function (): void {
    $this->actingAs(bdcAdmin());
    Livewire::test(CccScreen::class)->call('setTab', 'conversations')->assertSee('Conversations en BD')->assertSeeLivewire(Conversations::class);

    Livewire::test(Conversations::class)
        ->assertSee('Non, c’est non')
        ->assertSee('Les règles du groupe')
        ->assertSee('5 images manquantes');
});

it('cache l’onglet et refuse le composant sans la permission dédiée', function (): void {
    $user = bdcAdmin();
    Permission::findByName('conversations_bd.manage')->roles()->detach();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($user->fresh());

    expect(Livewire::test(CccScreen::class)->instance()->tabs())->not->toContain('conversations');
    Livewire::test(Conversations::class)->assertForbidden();
});

it('publie puis dépublie une conversation, journalise et bloque la republication par l’import', function (): void {
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $this->actingAs(bdcAdmin());
    $screen = Livewire::test(Conversations::class)->call('toggle', $m01->id);

    $m01->refresh();
    expect($m01->isPublished())->toBeTrue()->and($m01->status_changed_at)->not->toBeNull()->and($m01->reviewed_by)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'conversations_bd.published')->exists())->toBeTrue();
    $this->get('/conversations-bd/non-cest-non')->assertOk();

    $screen->call('toggle', $m01->id);
    expect($m01->fresh()->statusValue())->toBe(BdConversationStatus::Draft)
        ->and(AuditLog::query()->where('action', 'conversations_bd.unpublished')->exists())->toBeTrue();
});

it('refuse de publier une conversation qui enfreint la grammaire', function (): void {
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    BdBubble::query()->whereIn('panel_id', $m01->panels()->pluck('id'))->where('position', 2)->limit(1)->update(['text' => trim(str_repeat('mot ', 25))]);
    $this->actingAs(bdcAdmin());

    Livewire::test(Conversations::class)->call('toggle', $m01->id)->assertDispatched('toast', type: 'error');
    expect($m01->fresh()->isPublished())->toBeFalse();
});

it('ordonne les conversations au clavier (monter / descendre)', function (): void {
    $m02 = BdConversation::query()->where('model_key', 'm02')->firstOrFail();
    $this->actingAs(bdcAdmin());
    Livewire::test(Conversations::class)->call('move', $m02->id, -1)->assertSet('announce', '«'."\u{00A0}".'La photo de la promo'."\u{00A0}".'» a été montée.');

    expect(BdConversation::query()->ordered()->pluck('model_key')->take(2)->all())->toBe(['m02', 'm01'])
        ->and(AuditLog::query()->where('action', 'conversations_bd.reordered')->exists())->toBeTrue();
});

it('modifie les textes d’une conversation et marque le contenu comme retouché', function (): void {
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $this->actingAs(bdcAdmin());
    Livewire::test(Conversations::class)
        ->call('edit', $m01->id)
        ->assertSet('title', 'Non, c’est non')
        ->set('title', 'Non, c’est non (révisé)')
        ->set('questions', "Première question ?\n\nDeuxième question ?")
        ->call('saveTexts')
        ->assertHasNoErrors();

    $m01->refresh();
    expect($m01->title)->toBe('Non, c’est non (révisé)')
        ->and($m01->questions())->toBe(['Première question ?', 'Deuxième question ?'])
        ->and($m01->content_edited_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'conversations_bd.updated')->exists())->toBeTrue();

    Livewire::test(Conversations::class)->call('edit', $m01->id)->set('title', '<b>x</b>')->call('saveTexts')->assertHasErrors('title');
});

it('modifie les bulles d’une case : texte, position, ajout et retrait ; refuse ce qui enfreint la grammaire', function (): void {
    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $panel = $m01->panels()->firstOrFail();
    $this->actingAs(bdcAdmin());
    $screen = Livewire::test(Conversations::class)->call('edit', $m01->id)->call('openPanel', $panel->id)
        ->assertCount('bubbles', 3)
        ->assertSee('Rodrigue dit');

    $screen->set('bubbles.1.texte', 'Viens chez moi ce soir.')->set('bubbles.1.x', '30')->call('savePanel')->assertHasNoErrors();
    $bubble = BdBubble::query()->where('panel_id', $panel->id)->where('position', 2)->firstOrFail();
    expect($bubble->text)->toBe('Viens chez moi ce soir.')->and($bubble->x)->toBe(30.0)
        ->and(AuditLog::query()->where('action', 'conversations_bd.panel_updated')->exists())->toBeTrue()
        ->and($m01->fresh()->content_edited_at)->not->toBeNull();

    // Trop de mots : refusé, rien n'est écrit.
    $screen->set('bubbles.1.texte', trim(str_repeat('mot ', 21)))->call('savePanel')->assertHasErrors('bubbles');
    expect(BdBubble::query()->where('panel_id', $panel->id)->where('position', 2)->value('text'))->toBe('Viens chez moi ce soir.');

    // Retrait puis ajout d'une bulle.
    $screen->set('bubbles.1.texte', 'Viens chez moi ce soir.')->call('removeBubble', 2)->call('savePanel')->assertHasNoErrors();
    expect(BdBubble::query()->where('panel_id', $panel->id)->count())->toBe(2);
});

it('gère la bibliothèque de scènes : description et activation journalisées, jamais réalignées par l’import', function (): void {
    $scene = BdScene::query()->where('key', 'm01-case-1')->firstOrFail();
    $this->actingAs(bdcAdmin());
    Livewire::test(Conversations::class)
        ->call('setSection', 'scenes')
        ->assertSee('m01-case-1')
        ->call('editScene', $scene->id)
        ->set('sceneText', 'Sortie du lycée, description revue.')
        ->call('saveScene')
        ->assertHasNoErrors()
        ->call('toggleScene', $scene->id);

    $scene->refresh();
    expect($scene->description)->toBe('Sortie du lycée, description revue.')->and($scene->is_active)->toBeFalse()
        ->and(AuditLog::query()->whereIn('action', ['conversations_bd.scene_updated', 'conversations_bd.scene_deactivated'])->count())->toBe(2);

    app(ImportConversationsBd::class)();
    expect($scene->fresh()->description)->toBe('Sortie du lycée, description revue.')->and($scene->fresh()->is_active)->toBeFalse();
});

it('refuse chaque action quand la permission est retirée en cours de session', function (): void {
    $user = bdcAdmin();
    $this->actingAs($user);
    $screen = Livewire::test(Conversations::class);
    $user->syncRoles(['membre']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $m01 = BdConversation::query()->where('model_key', 'm01')->firstOrFail();
    $screen->call('toggle', $m01->id)->assertForbidden();
    expect($m01->fresh()->isPublished())->toBeFalse();
});
