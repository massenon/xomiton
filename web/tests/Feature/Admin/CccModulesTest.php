<?php

declare(strict_types=1);

use App\Application\Admin\Content\ManageCccModules;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Enums\OutboundKind;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\OutboundPost;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * Modules du manuel du pair animateur (onglet « Manuel » de /admin/ccc) : création, modification, chapitres, ordre,
 * publication et suppression. Permission `ccc_tools.manage` revérifiée à chaque action, identifiants verrouillés, journal sans contenu.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function moduleAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['admin']);

    return $user;
}

/** @param array<string, mixed> $overrides */
function moduleOf(array $overrides = []): CccTool
{
    return CccTool::factory()->create([
        'key' => 'm'.random_int(10, 9999),
        'title' => 'Module '.random_int(10, 9999),
        'chapters' => ['Un', 'Deux', 'Trois'],
        'duration_minutes' => 45,
        'is_published' => false,
        ...$overrides,
    ]);
}

/** @return list<string> */
function moduleTitles(): array
{
    return CccTool::query()->ofKind(CccKind::Manual)->ordered()->pluck('title')->map(static fn (mixed $t): string => (string) $t)->all();
}

function manualScreen(): Testable
{
    return Livewire::test(CccScreen::class)->call('setTab', 'manuel');
}

it('crée un module masqué sans chapitre : clé m{n} suivante, dernière position de sa famille', function (): void {
    moduleOf(['key' => 'm7', 'position' => 3]);
    CccTool::factory()->comic()->create(['key' => 'bd1', 'position' => 9]);
    $admin = moduleAdmin();
    $this->actingAs($admin);

    manualScreen()->call('openModuleCreate')->set('moduleTitle', "Parler d'égalité")->set('moduleDescription', 'Une introduction.')->set('moduleDuration', '50')
        ->call('saveModule')->assertHasNoErrors()->assertDispatched('toast', type: 'success', message: "Module «\u{00A0}Parler d’égalité\u{00A0}» créé.");

    $module = CccTool::query()->where('key', 'm8')->firstOrFail();
    expect($module->kind)->toBe(CccKind::Manual)->and($module->title)->toBe('Parler d’égalité')->and($module->description)->toBe('Une introduction.')
        ->and($module->duration_minutes)->toBe(50)->and($module->position)->toBe(4)->and($module->chapters)->toBe([])->and($module->is_published)->toBeFalse();

    $log = AuditLog::query()->where('action', 'ccc.module_created')->firstOrFail();
    expect($log->detail)->toBe('Parler d’égalité')->and($log->user_id)->toBe($admin->id);
});

it('accepte description et durée vides', function (): void {
    $this->actingAs(moduleAdmin());

    manualScreen()->call('openModuleCreate')->set('moduleTitle', 'Court')->call('saveModule')->assertHasNoErrors();

    $module = CccTool::query()->where('title', 'Court')->firstOrFail();
    expect($module->description)->toBeNull()->and($module->duration_minutes)->toBeNull();
});

it('modifie titre, description et durée sans toucher à la clé, à la position ni aux chapitres', function (): void {
    $module = moduleOf(['key' => 'm42', 'position' => 3, 'title' => 'Ancien', 'description' => null]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleEdit', $module->id)->assertSet('moduleTitle', 'Ancien')->assertSet('moduleDuration', '45');

    $screen->set('moduleTitle', '')->call('saveModule')->assertHasErrors(['moduleTitle' => 'required']);
    $screen->set('moduleTitle', 'Nouveau')->set('moduleDescription', 'Décrit.')->set('moduleDuration', '')->call('saveModule')->assertHasNoErrors();

    $module->refresh();
    expect($module->key)->toBe('m42')->and($module->position)->toBe(3)->and($module->title)->toBe('Nouveau')->and($module->description)->toBe('Décrit.')
        ->and($module->duration_minutes)->toBeNull()->and($module->chapters)->toBe(['Un', 'Deux', 'Trois'])
        ->and(AuditLog::query()->where('action', 'ccc.module_updated')->value('detail'))->toBe('Nouveau');
});

it('valide les bornes : titre, description, durée 1 à 600, texte sans balises', function (): void {
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleCreate');

    $screen->call('saveModule')->assertHasErrors(['moduleTitle' => 'required']);
    $screen->set('moduleTitle', str_repeat('t', 201))->set('moduleDescription', str_repeat('d', 501))->call('saveModule')->assertHasErrors(['moduleTitle' => 'max', 'moduleDescription' => 'max']);
    $screen->set('moduleTitle', '<b>Titre</b>')->set('moduleDescription', 'Texte <script>x</script>')->call('saveModule')->assertHasErrors(['moduleTitle' => 'not_regex', 'moduleDescription' => 'not_regex']);

    $screen->set('moduleTitle', 'Valide')->set('moduleDescription', '');
    foreach (['0', '601', '-5', '1.5', 'abc'] as $duration) {
        $screen->set('moduleDuration', $duration)->call('saveModule')->assertHasErrors(['moduleDuration']);
    }

    foreach (['1', '600'] as $duration) {
        $screen->call('openModuleCreate')->set('moduleTitle', 'Durée '.$duration)->set('moduleDuration', $duration)->call('saveModule')->assertHasNoErrors();
    }

    expect(CccTool::query()->ofKind(CccKind::Manual)->count())->toBe(2);
});

it('nettoie côté serveur : balises retirées, typographie française, bornes refusées', function (): void {
    $actor = moduleAdmin();
    $manage = app(ManageCccModules::class);

    $module = $manage->create("<b>Qu'est-ce que le genre ?</b>", "<script>x</script>Une  phrase.\nUne autre.", null, $actor);
    expect($module->title)->toBe("Qu’est-ce que le genre\u{00A0}?")->and($module->description)->toBe('xUne phrase. Une autre.');

    $manage->addChapter($module, "<i>Pourquoi ?</i>\n", $actor);
    expect($manage->chaptersOf($module->fresh()))->toBe(["Pourquoi\u{00A0}?"]);

    expect(fn () => $manage->create('<i></i>', '', null, $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->create('Ok', '', 0, $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->create('Ok', '', 601, $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->create('Ok', str_repeat('d', 501), null, $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->addChapter($module, '<b></b>', $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->addChapter($module, str_repeat('c', 201), $actor))->toThrow(ContentRefused::class)
        ->and(CccTool::query()->ofKind(CccKind::Manual)->count())->toBe(1);
});

it('refuse de dépasser 255 modules', function (): void {
    foreach (range(1, ManageCccModules::MODULES_MAX) as $i) {
        CccTool::factory()->create(['key' => 'm'.$i, 'title' => 'Module '.$i, 'position' => min($i, 255)]);
    }

    expect(fn () => app(ManageCccModules::class)->create('Un de trop', '', null, moduleAdmin()))->toThrow(ContentRefused::class);
    expect(CccTool::query()->ofKind(CccKind::Manual)->count())->toBe(ManageCccModules::MODULES_MAX);
});

it('ajoute, modifie, déplace et retire des chapitres', function (): void {
    $module = moduleOf(['chapters' => ['Un', 'Deux']]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleChapters', $module->id)->assertSet('chapterEdits', ['Un', 'Deux']);

    $screen->set('newChapter', 'Trois')->call('addChapter')->assertHasNoErrors()->assertSet('newChapter', '')->assertSet('chapterEdits', ['Un', 'Deux', 'Trois']);
    expect($module->fresh()->chapters)->toBe(['Un', 'Deux', 'Trois']);

    $screen->set('chapterEdits.1', "Qu'en dire ?")->call('saveChapters')->assertHasNoErrors();
    expect($module->fresh()->chapters)->toBe(['Un', "Qu’en dire\u{00A0}?", 'Trois']);

    $screen->call('moveChapter', 2, -1)->assertHasNoErrors()->assertDispatched('xm-ccc-focus');
    expect($module->fresh()->chapters)->toBe(['Un', 'Trois', "Qu’en dire\u{00A0}?"]);
    $screen->call('moveChapter', 0, 1);
    expect($module->fresh()->chapters)->toBe(['Trois', 'Un', "Qu’en dire\u{00A0}?"]);

    $screen->call('askRemoveChapter', 1)->assertSet('removeChapterIndex', 1)->call('cancelRemoveChapter')->assertSet('removeChapterIndex', null);
    expect($module->fresh()->chapters)->toHaveCount(3);
    $screen->call('askRemoveChapter', 1)->call('removeChapter')->assertHasNoErrors()->assertSet('removeChapterIndex', null);
    expect($module->fresh()->chapters)->toBe(['Trois', "Qu’en dire\u{00A0}?"]);

    $screen->call('moveChapter', 0, -1)->assertHasErrors(['chapters']);
    expect($module->fresh()->chapters)->toBe(['Trois', "Qu’en dire\u{00A0}?"]);
});

it('les textes saisis et pas encore enregistrés suivent leur chapitre lors d’un déplacement', function (): void {
    $module = moduleOf(['chapters' => ['Un', 'Deux']]);
    $this->actingAs(moduleAdmin());

    manualScreen()->call('openModuleChapters', $module->id)->set('chapterEdits.0', 'Un modifié')->call('moveChapter', 0, 1)
        ->assertSet('chapterEdits', ['Deux', 'Un modifié']);
});

it('valide les chapitres : texte obligatoire, 200 caractères, sans balises, 30 au plus', function (): void {
    $module = moduleOf(['chapters' => ['Un']]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleChapters', $module->id);

    $screen->call('addChapter')->assertHasErrors(['newChapter' => 'required']);
    $screen->set('newChapter', str_repeat('c', 201))->call('addChapter')->assertHasErrors(['newChapter' => 'max']);
    $screen->set('newChapter', '<b>Gras</b>')->call('addChapter')->assertHasErrors(['newChapter' => 'not_regex']);
    $screen->set('newChapter', str_repeat('c', 200))->call('addChapter')->assertHasNoErrors();

    $screen->set('chapterEdits.0', '')->call('saveChapters')->assertHasErrors(['chapterEdits.0' => 'required']);
    $screen->set('chapterEdits.0', '<script>x</script>')->call('saveChapters')->assertHasErrors(['chapterEdits.0' => 'not_regex']);
    $screen->set('chapterEdits.0', str_repeat('c', 201))->call('saveChapters')->assertHasErrors(['chapterEdits.0' => 'max']);
    expect($module->fresh()->chapters)->toBe(['Un', str_repeat('c', 200)]);

    $module->forceFill(['chapters' => array_map(static fn (int $i): string => 'Chapitre '.$i, range(1, ManageCccModules::CHAPTERS_MAX))])->save();
    $screen->call('openModuleChapters', $module->id)->set('newChapter', 'Un de trop')->call('addChapter')->assertHasErrors(['newChapter']);
    expect($module->fresh()->chapters)->toHaveCount(ManageCccModules::CHAPTERS_MAX);
});

it('refuse d’enregistrer des chapitres devenus différents entre-temps', function (): void {
    $module = moduleOf(['chapters' => ['Un', 'Deux']]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleChapters', $module->id);

    $module->forceFill(['chapters' => ['Un']])->save();
    $screen->call('saveChapters')->assertHasErrors(['chapters']);
    expect($module->fresh()->chapters)->toBe(['Un']);
});

it('ne publie pas un module sans chapitre et garde au moins un chapitre à un module publié', function (): void {
    $empty = moduleOf(['chapters' => []]);
    $one = moduleOf(['chapters' => ['Seul'], 'is_published' => true]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen();

    $screen->call('toggle', $empty->id)->assertDispatched('toast', type: 'error');
    expect($empty->fresh()->is_published)->toBeFalse()->and(AuditLog::query()->where('action', 'ccc.module_published')->exists())->toBeFalse();

    $screen->call('openModuleChapters', $one->id)->call('askRemoveChapter', 0)->call('removeChapter')->assertHasErrors(['chapters']);
    expect($one->fresh()->chapters)->toBe(['Seul']);

    $screen->call('toggle', $one->id);
    expect($one->fresh()->is_published)->toBeFalse();
    $screen->call('openModuleChapters', $one->id)->call('askRemoveChapter', 0)->call('removeChapter')->assertHasNoErrors();
    expect($one->fresh()->chapters)->toBe([]);

    $screen->call('addChapter')->set('newChapter', 'Revenu')->call('addChapter');
    $screen->call('toggle', $one->id);
    expect($one->fresh()->is_published)->toBeTrue()->and(CccTool::query()->published()->whereKey($one->id)->exists())->toBeTrue();
});

it('publie et masque un module avec le journal ccc.module_published / ccc.module_unpublished', function (): void {
    $module = moduleOf(['title' => 'Module visible', 'is_published' => false]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('toggle', $module->id);

    expect($module->fresh()->is_published)->toBeTrue();
    $screen->call('toggle', $module->id);
    expect($module->fresh()->is_published)->toBeFalse()
        ->and(AuditLog::query()->whereIn('action', ['ccc.module_published', 'ccc.module_unpublished'])->orderBy('id')->pluck('action')->all())
        ->toBe(['ccc.module_published', 'ccc.module_unpublished']);
});

it('réordonne les modules : positions renumérées de 1 à n, même à rangs égaux, famille isolée', function (): void {
    foreach (['Module A', 'Module B', 'Module C'] as $i => $title) {
        moduleOf(['key' => 'm'.(20 + $i), 'title' => $title, 'position' => 1]);
    }
    $box = CccTool::factory()->imageBox()->create(['key' => 'bi1', 'position' => 1]);
    [$a, $b, $c] = CccTool::query()->ofKind(CccKind::Manual)->ordered()->get()->all();
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->assertSeeHtml('id="xmModuleUp'.$a->id.'"')->assertSeeHtml('id="xmModuleDown'.$c->id.'"');

    $screen->call('moveModule', $c->id, -1)->assertHasNoErrors()->assertDispatched('xm-ccc-focus');
    expect(moduleTitles())->toBe(['Module A', 'Module C', 'Module B'])->and(CccTool::query()->ofKind(CccKind::Manual)->ordered()->pluck('position')->all())->toBe([1, 2, 3]);

    $screen->call('moveModule', $a->id, 1);
    expect(moduleTitles())->toBe(['Module C', 'Module A', 'Module B']);

    $screen->call('moveModule', $c->id, -1)->assertDispatched('toast', type: 'error');
    expect(moduleTitles())->toBe(['Module C', 'Module A', 'Module B'])->and($box->fresh()->position)->toBe(1)
        ->and(AuditLog::query()->where('action', 'ccc.module_moved')->count())->toBe(2);
});

it('supprime un module après confirmation et renumérote l’ordre', function (): void {
    $a = moduleOf(['title' => 'Module A', 'position' => 1]);
    $b = moduleOf(['title' => 'Module B', 'position' => 2]);
    $c = moduleOf(['title' => 'Module C', 'position' => 7]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleDelete', $b->id)->assertSet('moduleId', $b->id);

    expect(CccTool::query()->count())->toBe(3);
    $screen->call('deleteModule')->assertHasNoErrors()->assertDispatched('toast')->assertSet('moduleId', null);

    expect(CccTool::query()->whereKey($b->id)->exists())->toBeFalse()->and(CccTool::query()->ofKind(CccKind::Manual)->ordered()->pluck('position', 'id')->all())->toBe([$a->id => 1, $c->id => 2])
        ->and(AuditLog::query()->where('action', 'ccc.module_deleted')->value('detail'))->toBe('Module B');

    expect(fn () => $screen->call('openModuleDelete', $b->id))->toThrow(ModelNotFoundException::class);
    $screen->call('deleteModule')->assertNotFound();
});

it('refuse de supprimer un module repris par une publication sortante planifiée', function (): void {
    $module = moduleOf(['key' => 'm55', 'title' => 'Module planifié']);
    OutboundPost::factory()->create(['kind' => OutboundKind::Bd, 'reference' => 'm55']);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleDelete', $module->id)->call('deleteModule')->assertHasErrors(['delete']);

    expect(CccTool::query()->whereKey($module->id)->exists())->toBeTrue()->and(AuditLog::query()->where('action', 'ccc.module_deleted')->exists())->toBeFalse();

    OutboundPost::query()->where('reference', 'm55')->delete();
    $screen->call('deleteModule')->assertHasNoErrors();
    expect(CccTool::query()->whereKey($module->id)->exists())->toBeFalse();
});

it('ne touche jamais un outil d’une autre famille', function (): void {
    $comic = CccTool::factory()->comic()->create(['key' => 'bd3', 'title' => 'Une BD']);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen();

    foreach (['openModuleEdit', 'openModuleChapters', 'openModuleDelete'] as $action) {
        expect(fn () => $screen->call($action, $comic->id))->toThrow(ModelNotFoundException::class);
    }

    expect(fn () => app(ManageCccModules::class)->delete($comic, moduleAdmin()))->toThrow(ContentRefused::class);
    expect($comic->fresh())->not->toBeNull();
});

it('verrouille moduleId et removeChapterIndex : ils ne se modifient pas depuis le navigateur', function (): void {
    $module = moduleOf();
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleChapters', $module->id);

    expect(fn () => $screen->set('moduleId', $module->id + 1))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $screen->set('removeChapterIndex', 0))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('vérifie la permission à chaque action, pas seulement à l’affichage', function (string $action, array $arguments): void {
    $module = moduleOf(['title' => 'Module protégé', 'position' => 1, 'chapters' => ['Un', 'Deux'], 'is_published' => true]);
    $other = moduleOf(['position' => 2]);
    $admin = moduleAdmin();
    $this->actingAs($admin);
    $screen = manualScreen()->call('openModuleChapters', $module->id)->set('newChapter', 'Nouveau');

    $admin->syncRoles(['membre']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...array_map(static fn ($v) => $v === '@id' ? $module->id : $v, $arguments))->assertForbidden();
    expect(CccTool::query()->count())->toBe(2)->and($module->fresh()->title)->toBe('Module protégé')->and($module->fresh()->position)->toBe(1)
        ->and($module->fresh()->is_published)->toBeTrue()->and($module->fresh()->chapters)->toBe(['Un', 'Deux'])->and($other->fresh()->position)->toBe(2);
})->with([
    'ouvrir la création' => ['openModuleCreate', []],
    'ouvrir la modification' => ['openModuleEdit', ['@id']],
    'ouvrir les chapitres' => ['openModuleChapters', ['@id']],
    'ouvrir la suppression' => ['openModuleDelete', ['@id']],
    'enregistrer' => ['saveModule', []],
    'supprimer' => ['deleteModule', []],
    'monter' => ['moveModule', ['@id', -1]],
    'descendre' => ['moveModule', ['@id', 1]],
    'publier' => ['toggle', ['@id']],
    'ajouter un chapitre' => ['addChapter', []],
    'enregistrer les chapitres' => ['saveChapters', []],
    'monter un chapitre' => ['moveChapter', [1, -1]],
    'demander le retrait' => ['askRemoveChapter', [0]],
    'annuler le retrait' => ['cancelRemoveChapter', []],
    'retirer un chapitre' => ['removeChapter', []],
]);

it('refuse l’écran sans la permission (403)', function (string $role): void {
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    $this->actingAs($user)->get('/admin/ccc')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('journalise sans jamais écrire la description ni le texte des chapitres', function (): void {
    $this->actingAs(moduleAdmin());
    $screen = manualScreen()->call('openModuleCreate')->set('moduleTitle', 'Module journalisé')->set('moduleDescription', 'DESCRIPTION SECRÈTE')->call('saveModule');
    $module = CccTool::query()->where('title', 'Module journalisé')->firstOrFail();

    $screen->call('openModuleChapters', $module->id)->set('newChapter', 'CHAPITRE SECRET')->call('addChapter')->set('newChapter', 'AUTRE SECRET')->call('addChapter')
        ->set('chapterEdits.0', 'TEXTE MODIFIÉ SECRET')->call('saveChapters')->call('moveChapter', 0, 1)->call('askRemoveChapter', 0)->call('removeChapter');
    $screen->call('toggle', $module->id)->call('toggle', $module->id)->call('openModuleEdit', $module->id)->set('moduleDescription', 'AUTRE DESCRIPTION SECRÈTE')->call('saveModule')
        ->call('moveModule', $module->id, 1)->call('openModuleDelete', $module->id)->call('deleteModule');

    $rows = AuditLog::query()->where('action', 'like', 'ccc.module_%')->get();
    expect($rows->pluck('action')->unique()->values()->all())->toContain('ccc.module_created', 'ccc.module_updated', 'ccc.module_chapters_updated', 'ccc.module_published', 'ccc.module_unpublished', 'ccc.module_deleted')
        ->and($rows->pluck('detail')->unique()->values()->all())->toBe(['Module journalisé']);
    expect(json_encode($rows->map->toArray()->all()))->not->toContain('SECR');
});

it('rend les modales accessibles : champs liés à leur aide et à leurs erreurs, vrais boutons', function (): void {
    $module = moduleOf(['chapters' => ['Un', 'Deux']]);
    $this->actingAs(moduleAdmin());
    $screen = manualScreen();

    $screen->assertSeeHtml('<button type="button" class="btn btn-sm btn-outline-primary" wire:click="openModuleChapters('.$module->id.')"')
        ->assertSeeHtml('wire:click="moveModule('.$module->id.', 1)"');

    $screen->call('openModuleCreate')->call('saveModule')->assertSeeHtml('for="xmModuleTitle"')->assertSeeHtml('id="xmModuleTitleError"')->assertSeeHtml('aria-invalid="true"');
    expect($screen->html())->toMatch('/aria-describedby="xmModuleTitleHelp\s+xmModuleTitleError\s*"/')->toMatch('/aria-describedby="xmModuleDurationHelp\s*"/');

    $screen->call('openModuleChapters', $module->id)->call('addChapter')
        ->assertSeeHtml('id="xmCccChapterUp0"')->assertSeeHtml('id="xmCccChapterDown1"')->assertSeeHtml('role="status" aria-live="polite"')->assertSeeHtml('id="xmCccNewChapterError"');
    expect($screen->html())->toMatch('/aria-describedby="xmCccNewChapterHelp\s+xmCccNewChapterError\s*"/')->toMatch('/aria-describedby="xmCccChapterHelp0\s*"/')
        ->toMatch('/<button type="button"[^>]*id="xmCccChapterUp0"/');

    $screen->set('chapterEdits.0', '')->call('saveChapters')->assertSeeHtml('id="xmCccChapterError0"');
    expect($screen->html())->toMatch('/aria-describedby="xmCccChapterHelp0\s+xmCccChapterError0\s*"/');
    $screen->call('moveChapter', 1, -1)->assertSee('Chapitre déplacé en position 1 sur 2.');
});
