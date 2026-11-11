<?php

declare(strict_types=1);

use App\Application\Admin\Content\ManageLaws;
use App\Application\Content\Queries\ListLaws;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Livewire\Admin\Ccc\Index as CccScreen;
use App\Models\AuditLog;
use App\Models\Law;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
 * P12-04, onglet « Lois » de /admin/ccc : création, modification, suppression, ordre et publication des textes lus par
 * /lois (ListLaws). Permissions `laws.manage` + `ccc_tools.manage` revérifiées à chaque action, journal sans le texte.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function lawAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['admin']);

    return $user;
}

/** @return list<string> */
function lawTitles(): array
{
    return array_map(static fn (array $law): string => $law['title'], app(ListLaws::class)()->laws);
}

/** @return array<string, string> */
function lawForm(array $overrides = []): array
{
    return [
        'lawTitle' => 'Loi n° 2026-01 sur la protection',
        'lawSubtitle' => 'Protection des personnes',
        'lawCategory' => 'Violences',
        'lawSummary' => 'Pénalise les violences et protège les victimes.',
        'lawSimple' => 'La loi vous protège.',
        'lawUrl' => 'https://sgg.gouv.bj/doc/loi-2026-01',
        ...$overrides,
    ];
}

function fillLaw(Testable $screen, array $values): Testable
{
    foreach ($values as $field => $value) {
        $screen->set($field, $value);
    }

    return $screen;
}

it('crée un texte de loi : clé dérivée du titre, dernière position, publié sur /lois', function (): void {
    Law::factory()->create(['slug' => 'loi-a', 'title' => 'Texte A', 'position' => 4]);
    $this->actingAs($admin = lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawCreate');

    fillLaw($screen, lawForm(['lawPublished' => true]))->call('saveLaw')->assertHasNoErrors()->assertDispatched('toast');

    $law = Law::query()->where('title', 'like', 'Loi n° 2026-01%')->firstOrFail();
    expect($law->slug)->toBe('loi-n-2026-01-sur-la-protection')->and($law->position)->toBe(5)->and($law->is_published)->toBeTrue()
        ->and($law->category)->toBe('Violences')->and($law->simple_summary)->toBe('La loi vous protège.')
        ->and($law->official_url)->toBe('https://sgg.gouv.bj/doc/loi-2026-01')->and($law->subtitle)->toBe('Protection des personnes');

    $log = AuditLog::query()->where('action', 'ccc.law_created')->firstOrFail();
    expect($log->detail)->toBe($law->title)->and($log->user_id)->toBe($admin->id);

    $this->get('/lois')->assertOk()->assertSee('Loi n° 2026-01 sur la protection', false)->assertSee('https://sgg.gouv.bj/doc/loi-2026-01', false);
});

it('crée un texte masqué avec les seuls champs obligatoires et ne le sert pas sur /lois', function (): void {
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawCreate');

    fillLaw($screen, ['lawTitle' => 'Texte discret', 'lawCategory' => 'Famille', 'lawSummary' => 'Résumé.'])->call('saveLaw')->assertHasNoErrors();

    $law = Law::query()->where('title', 'Texte discret')->firstOrFail();
    expect($law->is_published)->toBeFalse()->and($law->subtitle)->toBeNull()->and($law->simple_summary)->toBeNull()->and($law->official_url)->toBeNull();
    $this->get('/lois')->assertDontSee('Texte discret');
});

it('génère une clé unique quand deux textes ont le même titre', function (): void {
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois');

    foreach ([1, 2] as $_) {
        $screen->call('openLawCreate');
        fillLaw($screen, lawForm(['lawTitle' => 'Même titre']))->call('saveLaw')->assertHasNoErrors();
    }

    expect(Law::query()->where('title', 'Même titre')->orderBy('id')->pluck('slug')->all())->toBe(['meme-titre', 'meme-titre-2']);
});

it('valide strictement le formulaire : obligatoires, longueurs, texte simple et lien http(s)', function (): void {
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawCreate');

    $screen->call('saveLaw')->assertHasErrors(['lawTitle' => 'required', 'lawCategory' => 'required', 'lawSummary' => 'required']);

    fillLaw($screen, lawForm(['lawTitle' => str_repeat('t', 201), 'lawSubtitle' => str_repeat('s', 201), 'lawCategory' => str_repeat('c', 81), 'lawSummary' => str_repeat('r', 2001), 'lawSimple' => str_repeat('v', 2001)]))
        ->call('saveLaw')->assertHasErrors(['lawTitle' => 'max', 'lawSubtitle' => 'max', 'lawCategory' => 'max', 'lawSummary' => 'max', 'lawSimple' => 'max']);

    fillLaw($screen, lawForm(['lawTitle' => '<b>Titre</b>', 'lawSummary' => 'Résumé <script>alert(1)</script>']))
        ->call('saveLaw')->assertHasErrors(['lawTitle' => 'not_regex', 'lawSummary' => 'not_regex']);

    foreach (['javascript:alert(1)', 'ftp://sgg.gouv.bj/x', 'pas une adresse', '/relatif'] as $url) {
        fillLaw($screen, lawForm(['lawUrl' => $url]))->call('saveLaw')->assertHasErrors(['lawUrl']);
    }

    expect(Law::query()->count())->toBe(0);
});

it('nettoie le contenu côté serveur : balises retirées, typographie française, rien d’invalide enregistré', function (): void {
    $actor = lawAdmin();
    $manage = app(ManageLaws::class);
    $data = ['title' => "<b>Droit d'asile</b>", 'subtitle' => '', 'category' => 'Pénal', 'summary' => "<script>x</script>Que dit la loi ?\t  Rien.", 'simple_summary' => '', 'official_url' => '', 'is_published' => true];

    $law = $manage->save(null, $data, $actor);
    expect($law->title)->toBe('Droit d’asile')->and($law->summary)->toBe("xQue dit la loi\u{00A0}? Rien.")->and($law->subtitle)->toBeNull();

    expect(fn () => $manage->save(null, [...$data, 'title' => '<i></i>'], $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->save(null, [...$data, 'official_url' => 'javascript:alert(1)'], $actor))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->save(null, [...$data, 'category' => str_repeat('c', 81)], $actor))->toThrow(ContentRefused::class)
        ->and(Law::query()->count())->toBe(1);
});

it('refuse de dépasser 255 textes', function (): void {
    foreach (range(1, ManageLaws::LAWS_MAX) as $i) {
        Law::query()->create(['slug' => 'l-'.$i, 'title' => 'Texte '.$i, 'summary' => 'Résumé', 'category' => 'X', 'position' => min($i, 255)]);
    }

    expect(fn () => app(ManageLaws::class)->save(null, ['title' => 'Un de trop', 'subtitle' => '', 'category' => 'X', 'summary' => 'Y', 'simple_summary' => '', 'official_url' => '', 'is_published' => true], lawAdmin()))
        ->toThrow(ContentRefused::class);
    expect(Law::query()->count())->toBe(ManageLaws::LAWS_MAX);
});

it('modifie tous les champs d’un texte sans toucher à sa clé ni à sa position, et /lois suit', function (): void {
    $law = Law::factory()->create(['slug' => 'loi-a', 'title' => 'Ancien titre', 'position' => 3, 'category' => 'Pénal', 'simple_summary' => null, 'official_url' => null]);
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawEdit', $law->id)
        ->assertSet('lawTitle', 'Ancien titre')->assertSet('lawCategory', 'Pénal')->assertSet('lawSimple', '')->assertSet('lawUrl', '');

    $screen->set('lawTitle', '')->call('saveLaw')->assertHasErrors(['lawTitle']);
    fillLaw($screen, lawForm(['lawTitle' => 'Nouveau titre', 'lawCategory' => 'Santé', 'lawPublished' => true]))->call('saveLaw')->assertHasNoErrors();

    $law->refresh();
    expect($law->slug)->toBe('loi-a')->and($law->position)->toBe(3)->and($law->title)->toBe('Nouveau titre')->and($law->category)->toBe('Santé')
        ->and($law->subtitle)->toBe('Protection des personnes')->and($law->summary)->toBe('Pénalise les violences et protège les victimes.')
        ->and($law->simple_summary)->toBe('La loi vous protège.')->and($law->official_url)->toBe('https://sgg.gouv.bj/doc/loi-2026-01')
        ->and(AuditLog::query()->where('action', 'ccc.law_updated')->exists())->toBeTrue();

    $this->get('/lois')->assertSee('Nouveau titre')->assertSee('La loi vous protège.')->assertDontSee('Ancien titre');

    $screen->call('openLawEdit', $law->id)->set('lawSubtitle', '')->set('lawSimple', '')->set('lawUrl', '')->call('saveLaw')->assertHasNoErrors();
    expect($law->fresh()->subtitle)->toBeNull()->and($law->fresh()->official_url)->toBeNull();
});

it('supprime un texte après confirmation, renumérote l’ordre et le retire de /lois', function (): void {
    $a = Law::factory()->create(['title' => 'Texte A', 'position' => 1]);
    $b = Law::factory()->create(['title' => 'Texte B', 'position' => 2]);
    $c = Law::factory()->create(['title' => 'Texte C', 'position' => 7]);
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawDelete', $b->id)->assertSet('lawId', $b->id);

    expect(Law::query()->count())->toBe(3);
    $screen->call('deleteLaw')->assertHasNoErrors()->assertDispatched('toast')->assertSet('lawId', null);

    expect(Law::query()->whereKey($b->id)->exists())->toBeFalse()->and(Law::query()->ordered()->pluck('position', 'id')->all())->toBe([$a->id => 1, $c->id => 2])
        ->and(AuditLog::query()->where('action', 'ccc.law_deleted')->value('detail'))->toBe('Texte B');
    $this->get('/lois')->assertDontSee('Texte B')->assertSee('Texte C');

    expect(fn () => $screen->call('openLawDelete', $b->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    $screen->call('deleteLaw')->assertNotFound();
});

it('réordonne les textes avec « Monter » et « Descendre » : ordre stable et repris par /lois, même à rangs égaux', function (): void {
    foreach (['Texte A', 'Texte B', 'Texte C'] as $title) {
        Law::factory()->create(['title' => $title, 'position' => 1]);
    }
    [$a, $b, $c] = Law::query()->ordered()->get()->all();
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')
        ->assertSeeHtml('id="xmLawUp'.$a->id.'"')->assertSeeHtml('id="xmLawDown'.$c->id.'"');

    $screen->call('moveLaw', $c->id, -1)->assertHasNoErrors()->assertDispatched('xm-ccc-focus');
    expect(lawTitles())->toBe(['Texte A', 'Texte C', 'Texte B'])->and(Law::query()->ordered()->pluck('position')->all())->toBe([1, 2, 3]);

    $screen->call('moveLaw', $a->id, 1);
    expect(lawTitles())->toBe(['Texte C', 'Texte A', 'Texte B']);

    $screen->call('moveLaw', $c->id, -1)->assertDispatched('toast');
    expect(lawTitles())->toBe(['Texte C', 'Texte A', 'Texte B'])->and(AuditLog::query()->where('action', 'ccc.law_moved')->count())->toBe(2);
});

it('conserve publier / masquer et le fait suivre à /lois', function (): void {
    $law = Law::factory()->create(['title' => 'Texte visible', 'is_published' => true]);
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('toggleLaw', $law->id);

    expect($law->fresh()->is_published)->toBeFalse()->and(AuditLog::query()->where('action', 'ccc.law_unpublished')->exists())->toBeTrue();
    $this->get('/lois')->assertDontSee('Texte visible');
    $screen->call('toggleLaw', $law->id);
    $this->get('/lois')->assertSee('Texte visible');
});

it('verrouille lawId : il ne se modifie pas depuis le navigateur', function (): void {
    $law = Law::factory()->create();
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawEdit', $law->id);

    expect(fn () => $screen->set('lawId', $law->id + 1))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('vérifie la permission à chaque action, pas seulement à l’affichage', function (string $action, array $arguments): void {
    $law = Law::factory()->create(['title' => 'Texte protégé', 'position' => 1]);
    $other = Law::factory()->create(['position' => 2]);
    $admin = lawAdmin();
    $this->actingAs($admin);
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawEdit', $law->id);

    $admin->syncRoles(['membre']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...array_map(static fn ($v) => $v === '@id' ? $law->id : $v, $arguments))->assertForbidden();
    expect(Law::query()->count())->toBe(2)->and($law->fresh()->title)->toBe('Texte protégé')->and($law->fresh()->position)->toBe(1)->and($law->fresh()->is_published)->toBeTrue()
        ->and($other->fresh()->position)->toBe(2);
})->with([
    'ouvrir la création' => ['openLawCreate', []],
    'ouvrir la modification' => ['openLawEdit', ['@id']],
    'ouvrir la suppression' => ['openLawDelete', ['@id']],
    'enregistrer' => ['saveLaw', []],
    'supprimer' => ['deleteLaw', []],
    'monter' => ['moveLaw', ['@id', -1]],
    'descendre' => ['moveLaw', ['@id', 1]],
    'publier' => ['toggleLaw', ['@id']],
]);

it('exige aussi `laws.manage` : sans cette permission, chaque action sur les textes est refusée', function (string $action, array $arguments): void {
    $law = Law::factory()->create(['title' => 'Texte protégé', 'position' => 1]);
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawEdit', $law->id);

    Role::findByName('admin')->revokePermissionTo('laws.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...array_map(static fn ($v) => $v === '@id' ? $law->id : $v, $arguments))->assertForbidden();
    expect(Law::query()->count())->toBe(1)->and($law->fresh()->title)->toBe('Texte protégé')->and($law->fresh()->is_published)->toBeTrue();
})->with([
    'créer' => ['openLawCreate', []],
    'enregistrer' => ['saveLaw', []],
    'supprimer' => ['deleteLaw', []],
    'monter' => ['moveLaw', ['@id', -1]],
    'publier' => ['toggleLaw', ['@id']],
]);

it('journalise sans jamais écrire le résumé ni la version simple', function (): void {
    $law = Law::factory()->create(['title' => 'Texte journalisé', 'position' => 1]);
    $this->actingAs(lawAdmin());
    $screen = Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawCreate');
    fillLaw($screen, lawForm(['lawSummary' => 'RÉSUMÉ SECRET', 'lawSimple' => 'SIMPLE SECRET', 'lawUrl' => 'https://secret.example/doc']))->call('saveLaw')->assertHasNoErrors();
    $screen->call('openLawEdit', $law->id)->set('lawSummary', 'AUTRE RÉSUMÉ SECRET')->call('saveLaw')->call('moveLaw', $law->id, 1)->call('openLawDelete', $law->id)->call('deleteLaw');

    $rows = AuditLog::query()->where('action', 'like', 'ccc.law_%')->get();
    expect($rows->pluck('action')->all())->toContain('ccc.law_created', 'ccc.law_updated', 'ccc.law_moved', 'ccc.law_deleted')
        ->and($rows->pluck('detail')->implode(' | '))->not->toContain('SECRET')->and($rows->pluck('detail')->implode(' | '))->not->toContain('secret.example');
});

it('affiche les libellés des champs liés à leurs erreurs (aria-describedby, aria-invalid)', function (): void {
    $this->actingAs(lawAdmin());

    Livewire::test(CccScreen::class)->call('setTab', 'lois')->call('openLawCreate')->call('saveLaw')
        ->assertSeeHtml('for="xmLawTitle"')->assertSeeHtml('aria-describedby="xmLawTitleError"')->assertSeeHtml('id="xmLawTitleError"')
        ->assertSeeHtml('aria-invalid="true"')->assertSeeHtml('list="xmLawCategories"');
});
