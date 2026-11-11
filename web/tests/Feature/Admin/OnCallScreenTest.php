<?php

declare(strict_types=1);

use App\Application\Alerts\OnCallRoster;
use App\Application\Alerts\RouteAlert;
use App\Enums\DirectoryRole;
use App\Livewire\Admin\OnCall\Index as OnCall;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

/*
 * P1-17 : écran /admin/garde (`on_call.manage`) — liste semaine / mois, garde en cours et prochaine, création, modification
 * et suppression d'une plage, heures saisies dans le fuseau d'affichage et stockées en UTC, règles alignées sur OnCallRoster
 * (admin : plusieurs personnes de garde en même temps, jamais deux plages de la même personne qui se chevauchent ; point
 * focal : une personne par zone, liée à une fiche d'annuaire active), journal sans les notes, permission rejouée à chaque
 * action, identifiants verrouillés, et test de bout en bout : ce que l'écran écrit change le routage lu par OnCallRoster.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
});

function ocUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** Valeur d'un champ datetime-local (fuseau d'affichage) pour un instant. */
function ocInput(CarbonInterface $at): string
{
    return $at->copy()->timezone('Africa/Porto-Novo')->format('Y-m-d\TH:i');
}

/** @return array<string, string> */
function ocForm(User $person, array $overrides = []): array
{
    return array_replace([
        'kind' => 'admin',
        'user_id' => (string) $person->id,
        'zone_id' => '',
        'starts_at' => ocInput(now()->addDay()->startOfHour()),
        'ends_at' => ocInput(now()->addDay()->startOfHour()->addHours(8)),
        'notes' => '',
    ], $overrides);
}

function ocSave(User $by, array $form, int $editId = 0): \Livewire\Features\SupportTesting\Testable
{
    $screen = Livewire::actingAs($by)->test(OnCall::class);

    if ($editId !== 0) {
        $screen->call('openEdit', $editId);
    }

    return $screen->set('form', $form)->call('save');
}

/** Contact de point focal lié à un compte. */
function ocFocal(?Zone $zone = null): array
{
    $user = ocUser('pair');
    $contact = DirectoryContact::factory()->focalZone($zone)->create(['user_id' => $user->id, 'zone_id' => $zone?->id, 'name' => 'Focal test']);

    return [$user, $contact];
}

it('réserve /admin/garde à on_call.manage : 403 pour le modérateur, le pair et le membre, accès pour admin et superadmin', function (): void {
    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(ocUser($role))->get(route('admin.garde'))->assertForbidden();
    }

    Livewire::actingAs(ocUser('moderateur'))->test(OnCall::class)->assertForbidden();
    $this->actingAs(ocUser('admin'))->get(route('admin.garde'))->assertOk()->assertSee('Planning de garde');
    $this->actingAs(ocUser('superadmin'))->get(route('admin.garde'))->assertOk();
});

it('ajoute l’entrée « Planning de garde » au menu de l’admin', function (): void {
    $this->actingAs(ocUser('admin'))->get(route('admin.dashboard'))->assertSee(route('admin.garde'), false);
    $this->actingAs(ocUser('moderateur'))->get(route('admin.dashboard'))->assertDontSee(route('admin.garde'), false);
});

it('liste les gardes de la semaine, la garde en cours et la prochaine, puis navigue par semaine et par mois', function (): void {
    $admin = ocUser('admin');
    $now = OnCallShift::factory()->forUser(ocUser('admin'))->create();
    $next = OnCallShift::factory()->forUser(ocUser('admin'))->between(now()->addDays(2), now()->addDays(2)->addHours(4))->create();
    $far = OnCallShift::factory()->forUser(ocUser('admin'))->between(now()->addDays(20), now()->addDays(20)->addHours(4))->withNotes('REPERE-LOINTAIN')->create();

    $screen = Livewire::actingAs($admin)->test(OnCall::class)
        ->assertSee($now->user->pseudo)
        ->assertSee($next->user->pseudo)
        ->assertDontSee('REPERE-LOINTAIN')
        ->assertSee('En ce moment');

    expect($screen->html())->toContain('Semaine du');

    $screen->call('setView', 'mois')->assertSet('view', 'mois');
    $screen->call('next')->call('next')->call('next');
    $screen->call('today')->assertSet('day', '');

    // Mois : la garde éloignée (+20 j) apparaît dans le mois courant ou le suivant ; on la retrouve en avançant.
    $found = false;

    foreach ([0, 1] as $step) {
        if (str_contains(Livewire::actingAs($admin)->test(OnCall::class)->set('view', 'mois')->set('day', now()->addMonths($step)->format('Y-m-d'))->html(), 'REPERE-LOINTAIN')) {
            $found = true;
        }
    }

    expect($found)->toBeTrue();
});

it('ignore une date de repère mal formée', function (): void {
    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->set('day', 'pas-une-date')->assertOk();
    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->set('day', '2026-02-31')->assertOk();
});

it('crée une garde admin : heures du Bénin converties en UTC, journal sans les notes', function (): void {
    $admin = ocUser('admin');
    $person = ocUser('admin');
    $start = now()->addDay()->startOfHour();

    ocSave($admin, ocForm($person, ['notes' => 'Numéro perso 0197000000']))->assertHasNoErrors();

    $shift = OnCallShift::query()->firstOrFail();
    expect($shift->user_id)->toBe($person->id)
        ->and($shift->kind)->toBe('admin')
        ->and($shift->zone_id)->toBeNull()
        ->and($shift->starts_at->equalTo($start))->toBeTrue()
        ->and($shift->ends_at->equalTo($start->copy()->addHours(8)))->toBeTrue()
        ->and($shift->notes)->toBe('Numéro perso 0197000000');

    $log = AuditLog::query()->where('action', 'on_call.created')->firstOrFail();
    expect($log->user_id)->toBe($admin->id)
        ->and($log->detail)->toContain('compte='.$person->id)->toContain('type=admin')
        ->and($log->detail)->not->toContain('0197000000');
});

it('stocke bien un décalage UTC+1 : 08 h saisies = 07 h UTC', function (): void {
    $person = ocUser('admin');
    $day = now()->addDays(3)->setTimezone('Africa/Porto-Novo')->format('Y-m-d');

    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => $day.'T08:00', 'ends_at' => $day.'T16:00']))->assertHasNoErrors();

    expect(OnCallShift::query()->firstOrFail()->starts_at->utc()->format('H:i'))->toBe('07:00');
});

it('efface la zone d’une garde admin (le routage l’ignore)', function (): void {
    $zone = Zone::query()->where('name', 'Littoral')->firstOrFail();

    ocSave(ocUser('admin'), ocForm(ocUser('superadmin'), ['zone_id' => (string) $zone->id]))->assertHasNoErrors();

    expect(OnCallShift::query()->firstOrFail()->zone_id)->toBeNull();
});

it('crée une garde de point focal pour un département, puis au niveau national', function (): void {
    $zone = Zone::query()->where('name', 'Littoral')->firstOrFail();
    [$user] = ocFocal($zone);

    ocSave(ocUser('admin'), ocForm($user, ['kind' => 'focal', 'zone_id' => (string) $zone->id]))->assertHasNoErrors();
    ocSave(ocUser('admin'), ocForm($user, ['kind' => 'focal', 'zone_id' => '']))->assertHasNoErrors();

    expect(OnCallShift::query()->forKind('focal')->forZone($zone)->count())->toBe(1)
        ->and(OnCallShift::query()->forKind('focal')->forZone(null)->count())->toBe(1);
});

it('valide les champs : type, personne, dates', function (array $override, string $field): void {
    $form = ocForm(ocUser('admin'), $override);

    ocSave(ocUser('admin'), $form)->assertHasErrors(['form.'.$field]);

    expect(OnCallShift::query()->count())->toBe(0);
})->with([
    'type inconnu' => [['kind' => 'chef'], 'kind'],
    'personne absente' => [['user_id' => ''], 'user_id'],
    'personne inexistante' => [['user_id' => '999999'], 'user_id'],
    'début absent' => [['starts_at' => ''], 'starts_at'],
    'fin illisible' => [['ends_at' => 'demain'], 'ends_at'],
    'zone inexistante' => [['kind' => 'focal', 'zone_id' => '999999'], 'zone_id'],
    'note trop longue' => [['notes' => str_repeat('a', 201)], 'notes'],
]);

it('refuse une fin avant ou égale au début, une plage de plus de 31 jours et une plage déjà terminée', function (): void {
    $person = ocUser('admin');
    $start = now()->addDay()->startOfHour();

    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput($start), 'ends_at' => ocInput($start)]))->assertHasErrors(['form.ends_at']);
    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput($start), 'ends_at' => ocInput($start->copy()->subHour())]))->assertHasErrors(['form.ends_at']);
    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput($start), 'ends_at' => ocInput($start->copy()->addDays(40))]))->assertHasErrors(['form.ends_at']);
    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput(now()->subDays(3)), 'ends_at' => ocInput(now()->subDays(2))]))->assertHasErrors(['form.ends_at']);

    expect(OnCallShift::query()->count())->toBe(0);
});

it('refuse une personne qui n’est pas éligible : membre, compte suspendu, point focal sans fiche d’annuaire', function (): void {
    ocSave(ocUser('admin'), ocForm(ocUser('membre')))->assertHasErrors(['form.user_id']);
    ocSave(ocUser('admin'), ocForm(ocUser('pair')))->assertHasErrors(['form.user_id']);

    $suspended = User::factory()->suspended()->create();
    $suspended->syncRoles(['admin']);
    ocSave(ocUser('admin'), ocForm($suspended))->assertHasErrors(['form.user_id']);

    // Point focal : un admin sans fiche liée, ou lié à une fiche inactive ou d'un autre rôle, est refusé.
    $adminWithoutContact = ocUser('admin');
    ocSave(ocUser('admin'), ocForm($adminWithoutContact, ['kind' => 'focal']))->assertHasErrors(['form.user_id']);

    [$inactiveUser, $contact] = ocFocal();
    $contact->update(['is_active' => false]);
    ocSave(ocUser('admin'), ocForm($inactiveUser, ['kind' => 'focal']))->assertHasErrors(['form.user_id']);

    $lawyer = ocUser('pair');
    DirectoryContact::factory()->create(['user_id' => $lawyer->id, 'role' => DirectoryRole::Juriste]);
    ocSave(ocUser('admin'), ocForm($lawyer, ['kind' => 'focal']))->assertHasErrors(['form.user_id']);

    expect(OnCallShift::query()->count())->toBe(0);
});

it('refuse deux plages admin de la même personne qui se chevauchent, accepte deux personnes ou des plages qui se touchent', function (): void {
    $person = ocUser('admin');
    $other = ocUser('admin');
    $start = now()->addDay()->startOfHour();
    OnCallShift::factory()->forUser($person)->between($start, $start->copy()->addHours(8))->create();

    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput($start->copy()->addHours(4)), 'ends_at' => ocInput($start->copy()->addHours(12))]))
        ->assertHasErrors(['form.starts_at']);

    // Deux admins de garde en même temps : les deux sont prévenus (OnCallRoster::adminsOnCall), cohérent.
    ocSave(ocUser('admin'), ocForm($other, ['starts_at' => ocInput($start->copy()->addHours(4)), 'ends_at' => ocInput($start->copy()->addHours(12))]))
        ->assertHasNoErrors();

    // Fin exclue : la plage suivante peut commencer à l'heure exacte où l'autre se termine.
    ocSave(ocUser('admin'), ocForm($person, ['starts_at' => ocInput($start->copy()->addHours(8)), 'ends_at' => ocInput($start->copy()->addHours(16))]))
        ->assertHasNoErrors();

    expect(OnCallShift::query()->count())->toBe(3);
});

it('refuse deux gardes de point focal qui se chevauchent dans la même zone (une seule serait retenue), accepte une autre zone', function (): void {
    $littoral = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $atlantique = Zone::query()->where('id', '!=', $littoral->id)->firstOrFail();
    [$first] = ocFocal($littoral);
    [$second] = ocFocal($littoral);
    $start = now()->addDay()->startOfHour();
    OnCallShift::factory()->forUser($first)->focal($littoral)->between($start, $start->copy()->addHours(8))->create();
    $overlap = ['kind' => 'focal', 'starts_at' => ocInput($start->copy()->addHours(2)), 'ends_at' => ocInput($start->copy()->addHours(6))];

    ocSave(ocUser('admin'), ocForm($second, $overlap + ['zone_id' => (string) $littoral->id]))->assertHasErrors(['form.starts_at']);
    ocSave(ocUser('admin'), ocForm($second, $overlap + ['zone_id' => (string) $atlantique->id]))->assertHasNoErrors();
    ocSave(ocUser('admin'), ocForm($second, $overlap + ['zone_id' => '']))->assertHasNoErrors();

    expect(OnCallShift::query()->count())->toBe(3);
});

it('modifie une garde sans la compter comme chevauchement d’elle-même, et journalise', function (): void {
    $admin = ocUser('admin');
    $person = ocUser('admin');
    $shift = OnCallShift::factory()->forUser($person)->upcoming()->create();
    $end = $shift->ends_at->copy()->addHours(2);

    ocSave($admin, ocForm($person, ['starts_at' => ocInput($shift->starts_at), 'ends_at' => ocInput($end), 'notes' => 'prolongée']), $shift->id)->assertHasNoErrors();

    expect($shift->fresh()->ends_at->equalTo($end))->toBeTrue()
        ->and($shift->fresh()->notes)->toBe('prolongée')
        ->and(AuditLog::query()->where('action', 'on_call.updated')->where('subject_id', $shift->id)->exists())->toBeTrue();
});

it('préremplit le formulaire en heure d’affichage à l’ouverture d’une garde', function (): void {
    $shift = OnCallShift::factory()->upcoming()->create();

    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('openEdit', $shift->id)
        ->assertSet('editId', $shift->id)
        ->assertSet('form.starts_at', ocInput($shift->starts_at))
        ->assertSet('form.user_id', (string) $shift->user_id);
});

it('refuse de modifier une garde terminée ou disparue', function (): void {
    $past = OnCallShift::factory()->past()->create();

    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('openEdit', $past->id)->assertSet('editId', 0);
    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('openEdit', 999999)->assertSet('editId', 0);
});

it('supprime une garde après confirmation et journalise', function (): void {
    $admin = ocUser('admin');
    $shift = OnCallShift::factory()->upcoming()->create();

    $screen = Livewire::actingAs($admin)->test(OnCall::class)->call('confirmDelete', $shift->id)->assertSet('deleteId', $shift->id);
    expect(OnCallShift::query()->count())->toBe(1);

    $screen->call('delete')->assertSet('deleteId', 0);

    expect(OnCallShift::query()->count())->toBe(0);
    $log = AuditLog::query()->where('action', 'on_call.deleted')->firstOrFail();
    expect($log->detail)->not->toContain('en cours')->and($log->user_id)->toBe($admin->id);
});

it('avertit avant de supprimer une garde en cours, selon qu’elle est seule ou non, et le consigne', function (): void {
    $alone = OnCallShift::factory()->create();

    $screen = Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $alone->id);
    expect($screen->html())->toContain('c’est la seule');
    $screen->call('delete');

    expect(OnCallShift::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'on_call.deleted')->value('detail'))->toContain('en cours');

    $first = OnCallShift::factory()->create();
    OnCallShift::factory()->create();
    expect(Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $first->id)->html())
        ->toContain('Les autres admins de garde restent prévenus')->not->toContain('c’est la seule');

    $focal = OnCallShift::factory()->focal()->create();
    expect(Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $focal->id)->html())->toContain('fiche d’annuaire du point focal');

    // Garde à venir : aucun avertissement.
    $future = OnCallShift::factory()->upcoming()->create();
    expect(Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $future->id)->html())->not->toContain('dès maintenant');
});

it('gère une suppression d’une garde déjà supprimée', function (): void {
    $shift = OnCallShift::factory()->upcoming()->create();
    $screen = Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $shift->id);
    $shift->delete();

    $screen->call('delete')->assertSet('deleteId', 0);
    expect(AuditLog::query()->where('action', 'on_call.deleted')->exists())->toBeFalse();
});

it('verrouille les identifiants de la garde modifiée et supprimée', function (): void {
    $screen = Livewire::actingAs(ocUser('admin'))->test(OnCall::class);

    expect(fn () => $screen->set('editId', 5))->toThrow(CannotUpdateLockedPropertyException::class);
    expect(fn () => $screen->set('deleteId', 5))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejoue la permission à chaque action : 403 après le retrait du rôle', function (string $action, array $arguments): void {
    $admin = ocUser('admin');
    $shift = OnCallShift::factory()->upcoming()->create();
    $screen = Livewire::actingAs($admin)->test(OnCall::class);

    $admin->syncRoles(['moderateur']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call($action, ...array_map(static fn ($v) => $v === '@id' ? $shift->id : $v, $arguments))->assertForbidden();

    expect(OnCallShift::query()->count())->toBe(1)->and(AuditLog::query()->where('action', 'like', 'on_call.%')->count())->toBe(0);
})->with([
    'ouvrir la création' => ['openCreate', []],
    'ouvrir la modification' => ['openEdit', ['@id']],
    'enregistrer' => ['save', []],
    'confirmer la suppression' => ['confirmDelete', ['@id']],
    'supprimer' => ['delete', []],
    'changer la vue' => ['setView', ['mois']],
]);

it('refuse l’enregistrement quand la permission disparaît entre l’ouverture et la sauvegarde', function (): void {
    $admin = ocUser('admin');
    $person = ocUser('admin');
    $screen = Livewire::actingAs($admin)->test(OnCall::class)->call('openCreate')->set('form', ocForm($person));

    $admin->syncRoles(['membre']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $screen->call('save')->assertForbidden();

    expect(OnCallShift::query()->count())->toBe(0);
});

it('vide la personne quand on change de type et qu’elle n’est plus éligible', function (): void {
    $admin = ocUser('admin');

    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)
        ->set('form.user_id', (string) $admin->id)
        ->set('form.kind', 'focal')
        ->assertSet('form.user_id', '');
});

it('propose seulement les comptes éligibles dans la liste de personnes', function (): void {
    $admin = ocUser('admin');
    $member = ocUser('membre');

    $html = Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->html();

    expect($html)->toContain($admin->pseudo)->not->toContain('>'.$member->pseudo.'<');
});

// --- Bout en bout : ce que l'écran écrit est ce que lit OnCallRoster ----------------------------------------------------

it('une garde admin créée par l’écran change les destinataires lus par OnCallRoster, sa suppression rétablit le repli', function (): void {
    $actor = ocUser('admin');
    $night = ocUser('admin');
    $roster = app(OnCallRoster::class);

    // Sans plage : repli sur tous les admins habilités.
    expect($roster->adminsOnCall()->pluck('id')->all())->toContain($actor->id, $night->id);

    ocSave($actor, ocForm($night, ['starts_at' => ocInput(now()->subHour()), 'ends_at' => ocInput(now()->addHours(7))]))->assertHasNoErrors();
    expect($roster->adminsOnCall()->pluck('id')->all())->toBe([$night->id])
        ->and($roster->alertTeam()->pluck('id')->all())->toContain($night->id)->not->toContain($actor->id);

    $shift = OnCallShift::query()->firstOrFail();
    Livewire::actingAs($actor)->test(OnCall::class)->call('confirmDelete', $shift->id)->call('delete');

    expect($roster->adminsOnCall()->pluck('id')->all())->toContain($actor->id, $night->id);
});

it('une garde de point focal créée par l’écran change le contact retenu par le routage RG-04', function (): void {
    $littoral = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $permanent = DirectoryContact::factory()->focalZone($littoral)->create(['name' => 'Permanent']);
    [$user, $replacement] = ocFocal($littoral);
    DirectoryContact::query()->whereKey($replacement->id)->update(['name' => 'Remplaçant']);
    $route = app(RouteAlert::class);

    expect($route($littoral->id, [])->contactId)->toBe($permanent->id);

    ocSave(ocUser('admin'), ocForm($user, [
        'kind' => 'focal',
        'zone_id' => (string) $littoral->id,
        'starts_at' => ocInput(now()->subHour()),
        'ends_at' => ocInput(now()->addHours(7)),
    ]))->assertHasNoErrors();

    expect($route($littoral->id, [])->contactId)->toBe($replacement->id)
        ->and(app(OnCallRoster::class)->isFocalOnDuty($user))->toBeTrue();

    $shift = OnCallShift::query()->firstOrFail();
    Livewire::actingAs(ocUser('admin'))->test(OnCall::class)->call('confirmDelete', $shift->id)->call('delete');

    expect($route($littoral->id, [])->contactId)->toBe($permanent->id);
});

it('affiche, dans Paramètres, un lien vers le planning de garde', function (): void {
    $this->actingAs(ocUser('admin'))->get(route('admin.parametres'))->assertSee(route('admin.garde'), false);
});
