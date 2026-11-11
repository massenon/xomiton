<?php

declare(strict_types=1);

use App\Application\Shared\Referentials;
use App\Livewire\Admin\Referentials\Index as ReferentialsScreen;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\Need;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * Référentiels administrables (décision client du 11/11/2026) : /admin/referentiels (Livewire), permission
 * `referentials.manage` (admin et superadmin) revérifiée à chaque action, CRUD des zones (de tout pays) et communes, types de
 * violence, besoins, populations : validation, activation, ordre, suppression refusée tant que l'élément est utilisé, journal,
 * cache des référentiels et API.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function refUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function refScreen(string $role = 'admin', array $query = [])
{
    test()->actingAs(refUser($role));

    return Livewire::withQueryParams($query)->test(ReferentialsScreen::class);
}

it('réserve /admin/referentiels à l’admin et au superadmin : 403 pour le modérateur, le pair et le membre', function (string $role, int $status): void {
    $this->actingAs(refUser($role))->get('/admin/referentiels')->assertStatus($status);
})->with([['admin', 200], ['superadmin', 200], ['moderateur', 403], ['pair', 403], ['membre', 403]]);

it('affiche l’entrée « Référentiels » du menu à l’admin seulement', function (): void {
    $this->actingAs(refUser())->get('/admin/referentiels')->assertOk()->assertSee('Référentiels')->assertSee('Zones et communes');
    $this->actingAs(refUser('moderateur'))->get('/admin')->assertOk()->assertDontSee(route('admin.referentiels'));
});

it('revérifie la permission à chaque action Livewire : retirée en cours de route, l’action est refusée', function (): void {
    $screen = refScreen();
    Permission::findByName('referentials.manage', 'web')->roles()->detach();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $screen->call('create')->assertForbidden();
});

it('refuse l’écriture à un compte sans la permission, même en contournant la route', function (): void {
    $moderator = refUser('moderateur');
    $this->actingAs($moderator);

    Livewire::test(ReferentialsScreen::class)->assertForbidden();
});

it('verrouille l’identifiant de l’élément modifié : il ne se change pas depuis le navigateur', function (): void {
    $zone = Zone::factory()->create();

    expect(fn () => refScreen()->set('itemId', (int) $zone->id))->toThrow('Cannot update locked property');
});

// --- Zones -------------------------------------------------------------------------------------------------------------

it('crée une zone dans un pays hors Bénin : code déduit du libellé, position propre au pays, journal sans donnée sensible', function (): void {
    $screen = refScreen('admin', ['pays' => 'CM']);

    $screen->call('create')->set('form.label', 'Littoral')->call('save')->assertHasNoErrors();
    $screen->call('create')->set('form.label', 'Centre')->call('save')->assertHasNoErrors();

    $littoral = Zone::query()->inCountry('CM')->where('slug', 'littoral')->firstOrFail();
    $centre = Zone::query()->inCountry('CM')->where('slug', 'centre')->firstOrFail();
    expect($littoral->country_code)->toBe('CM')->and($littoral->position)->toBe(1)->and($centre->position)->toBe(2)->and($littoral->is_active)->toBeTrue();

    $log = AuditLog::query()->where('action', 'referentials.zone_created')->latest('id')->firstOrFail();
    expect($log->detail)->toContain('Centre')->toContain('centre')->toContain('CM');
});

it('accepte le même nom dans deux pays mais le refuse dans un même pays', function (): void {
    Zone::factory()->inCountry('BJ')->create(['name' => 'Littoral', 'slug' => 'littoral']);

    refScreen('admin', ['pays' => 'CM'])->call('create')->set('form.label', 'Littoral')->call('save')->assertHasNoErrors();
    expect(Zone::query()->where('slug', 'littoral')->count())->toBe(2);

    refScreen('admin', ['pays' => 'BJ'])->call('create')->set('form.label', 'Littoral')->call('save')->assertHasErrors(['form.label']);
});

it('valide la zone : libellé obligatoire, pays inconnu refusé, code mal formé refusé', function (): void {
    $screen = refScreen();

    $screen->call('create')->set('form.label', '')->call('save')->assertHasErrors(['form.label']);
    $screen->set('form.label', 'Savane')->set('form.country_code', 'ZZ')->call('save')->assertHasErrors(['form.country_code']);
    $screen->set('form.country_code', 'BJ')->set('form.slug', 'Savane Nord')->call('save')->assertHasErrors(['form.slug']);

    expect(Zone::query()->where('name', 'Savane')->exists())->toBeFalse();
});

it('modifie le libellé d’une zone sans toucher à son code, même si le formulaire est falsifié', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create(['name' => 'Nord', 'slug' => 'nord']);

    refScreen('admin', ['pays' => 'CM'])->call('edit', $zone->id)
        ->assertSet('form.label', 'Nord')
        ->set('form.label', 'Grand Nord')->set('form.slug', 'autre-code')
        ->call('save')->assertHasNoErrors();

    expect($zone->fresh()->name)->toBe('Grand Nord')->and($zone->fresh()->slug)->toBe('nord');
    expect(AuditLog::query()->where('action', 'referentials.zone_updated')->exists())->toBeTrue();
});

it('change le pays d’une zone inutilisée mais le refuse dès qu’elle a des communes ou des références', function (): void {
    $free = Zone::factory()->inCountry('BJ')->create();
    $busy = Zone::factory()->inCountry('BJ')->create();
    Commune::factory()->create(['zone_id' => $busy->id]);

    $screen = refScreen();
    $screen->call('edit', $free->id)->set('form.country_code', 'TG')->call('save')->assertHasNoErrors();
    expect($free->fresh()->country_code)->toBe('TG');

    // L'écran fige le champ (et ignore la valeur falsifiée) ; le cas d'usage refuse aussi, sans passer par l'écran.
    $screen->call('edit', $busy->id)->assertSet('form.country_code', 'BJ')->set('form.country_code', 'TG')->call('save');
    expect($busy->fresh()->country_code)->toBe('BJ');

    $used = Zone::factory()->inCountry('BJ')->create();
    User::factory()->create(['zone_id' => $used->id]);
    $screen->call('edit', $used->id)->set('form.country_code', 'TG')->call('save');
    expect($used->fresh()->country_code)->toBe('BJ');

    expect(fn () => app(App\Application\Admin\Referentials\ManageReferentials::class)->saveZone($used, ['label' => $used->name, 'country_code' => 'TG', 'is_active' => true], refUser()))
        ->toThrow(App\Domain\Referentials\Exceptions\ReferentialRefused::class);
});

it('liste les zones du pays choisi seulement, dans leur ordre', function (): void {
    Zone::factory()->inCountry('CM')->create(['name' => 'Ouest', 'slug' => 'ouest', 'position' => 2]);
    Zone::factory()->inCountry('CM')->create(['name' => 'Est', 'slug' => 'est', 'position' => 1]);
    Zone::factory()->inCountry('BJ')->create(['name' => 'Alibori', 'slug' => 'alibori']);

    $rows = refScreen('admin', ['pays' => 'CM'])->instance()->rows();

    expect(array_column($rows, 'label'))->toBe(['Est', 'Ouest']);
});

it('désactive une zone : elle disparaît de la liste de saisie de l’API mais reste lisible sur l’existant', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create();
    $user = User::factory()->create(['zone_id' => $zone->id]);

    $this->getJson('/api/v1/zones')->assertJsonFragment(['id' => $zone->id, 'country_code' => 'CM']);

    refScreen('admin', ['pays' => 'CM'])->call('toggle', $zone->id)->assertDispatched('toast', type: 'success');

    expect($zone->fresh()->is_active)->toBeFalse();
    $this->getJson('/api/v1/zones')->assertJsonMissing(['id' => $zone->id]);
    expect($user->fresh()->zone->name)->toBe($zone->name);
    expect(AuditLog::query()->where('action', 'referentials.zone_deactivated')->exists())->toBeTrue();
});

it('invalide le cache des référentiels et change l’ETag de l’API à chaque écriture', function (): void {
    $before = app(Referentials::class)->version();
    $etag = $this->getJson('/api/v1/zones')->headers->get('ETag');

    refScreen('admin', ['pays' => 'CM'])->call('create')->set('form.label', 'Adamaoua')->call('save')->assertHasNoErrors();

    expect(app(Referentials::class)->version())->toBeGreaterThan($before)
        ->and($this->getJson('/api/v1/zones')->headers->get('ETag'))->not->toBe($etag);
    $this->getJson('/api/v1/zones')->assertJsonFragment(['slug' => 'adamaoua', 'country_code' => 'CM']);
});

it('réordonne les zones d’un pays avec Monter et Descendre sans toucher aux autres pays', function (): void {
    $a = Zone::factory()->inCountry('CM')->create(['name' => 'A', 'slug' => 'a', 'position' => 1]);
    $b = Zone::factory()->inCountry('CM')->create(['name' => 'B', 'slug' => 'b', 'position' => 2]);
    $c = Zone::factory()->inCountry('CM')->create(['name' => 'C', 'slug' => 'c', 'position' => 3]);
    $other = Zone::factory()->inCountry('BJ')->create(['name' => 'Z', 'slug' => 'z', 'position' => 7]);

    $screen = refScreen('admin', ['pays' => 'CM']);
    $screen->call('move', $c->id, 'up')->assertDispatched('toast', type: 'info');
    expect(array_column($screen->instance()->rows(), 'label'))->toBe(['A', 'C', 'B']);

    $screen->call('move', $a->id, 'up');
    $screen->call('move', $b->id, 'down');
    expect(array_column($screen->instance()->rows(), 'label'))->toBe(['A', 'C', 'B'])->and($other->fresh()->position)->toBe(7);
    expect(AuditLog::query()->where('action', 'referentials.zone_moved')->count())->toBe(1);
});

it('refuse de supprimer une zone qui a des communes, puis une zone utilisée, et propose de la désactiver', function (): void {
    $withCommunes = Zone::factory()->inCountry('CM')->create(['name' => 'Sud']);
    Commune::factory()->create(['zone_id' => $withCommunes->id]);
    $used = Zone::factory()->inCountry('CM')->create(['name' => 'Est']);
    Alert::factory()->create(['zone_id' => $used->id]);

    $screen = refScreen('admin', ['pays' => 'CM']);
    $screen->call('delete', $withCommunes->id)->assertDispatched('toast', type: 'error', message: __('admin_referentials.errors.zone_has_communes', ['label' => 'Sud']));
    $screen->call('delete', $used->id)->assertDispatched('toast', type: 'error', message: __('admin_referentials.errors.in_use', ['label' => 'Est']));

    expect(Zone::query()->whereKey([$withCommunes->id, $used->id])->count())->toBe(2)
        ->and(__('admin_referentials.errors.in_use', ['label' => 'Est']))->toContain('Désactivez-le');
});

it('refuse de supprimer une zone référencée par un compte, une garde, l’annuaire ou un dossier', function (string $reference): void {
    $zone = Zone::factory()->inCountry('CM')->create();
    match ($reference) {
        'compte' => User::factory()->create(['zone_id' => $zone->id]),
        'annuaire' => DirectoryContact::factory()->create(['zone_id' => $zone->id]),
        'dossier' => CaseRecord::factory()->create(['zone_id' => $zone->id]),
        'garde' => App\Models\OnCallShift::factory()->create(['zone_id' => $zone->id]),
    };

    refScreen('admin', ['pays' => 'CM'])->call('delete', $zone->id)->assertDispatched('toast', type: 'error');

    expect(Zone::query()->whereKey($zone->id)->exists())->toBeTrue();
})->with(['compte', 'annuaire', 'dossier', 'garde']);

it('supprime une zone vide et inutilisée, et journalise la suppression', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create(['name' => 'Vide', 'slug' => 'vide']);

    refScreen('admin', ['pays' => 'CM'])->call('delete', $zone->id)->assertDispatched('toast', type: 'warning');

    expect(Zone::query()->whereKey($zone->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'referentials.zone_deleted')->exists())->toBeTrue();
});

// --- Communes ----------------------------------------------------------------------------------------------------------

it('gère les communes d’une zone : création, code unique par zone, modification, ordre, activation', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create(['name' => 'Centre']);
    $other = Zone::factory()->inCountry('CM')->create(['name' => 'Ouest']);
    Commune::factory()->create(['zone_id' => $other->id, 'name' => 'Yaoundé', 'slug' => 'yaounde']);

    $screen = refScreen('admin', ['pays' => 'CM', 'zone' => (string) $zone->id]);
    expect($screen->instance()->kind())->toBe('commune');

    $screen->call('create')->set('form.label', 'Yaoundé')->call('save')->assertHasNoErrors();
    $screen->call('create')->set('form.label', 'Yaoundé')->call('save')->assertHasErrors(['form.slug']);
    $screen->set('form.label', 'Mbalmayo')->set('form.slug', '')->call('save')->assertHasNoErrors();

    $yaounde = Commune::query()->where('zone_id', $zone->id)->where('slug', 'yaounde')->firstOrFail();
    $mbalmayo = Commune::query()->where('zone_id', $zone->id)->where('slug', 'mbalmayo')->firstOrFail();
    expect($yaounde->position)->toBe(1)->and($mbalmayo->position)->toBe(2);

    $screen->call('edit', $mbalmayo->id)->set('form.label', 'Mbalmayo ville')->call('save')->assertHasNoErrors();
    expect($mbalmayo->fresh()->name)->toBe('Mbalmayo ville')->and($mbalmayo->fresh()->slug)->toBe('mbalmayo');

    $screen->call('move', $mbalmayo->id, 'up');
    expect(array_column($screen->instance()->rows(), 'label'))->toBe(['Mbalmayo ville', 'Yaoundé']);

    $screen->call('toggle', $yaounde->id);
    expect($yaounde->fresh()->is_active)->toBeFalse();
    $communes = collect($this->getJson('/api/v1/zones')->json('data'))->firstWhere('id', $zone->id)['communes'];
    expect(array_column($communes, 'slug'))->toBe(['mbalmayo']);
});

it('ne modifie jamais une commune d’une autre zone que celle qui est ouverte', function (): void {
    $zone = Zone::factory()->create();
    $foreign = Commune::factory()->create();

    expect(fn () => refScreen('admin', ['zone' => (string) $zone->id])->call('edit', $foreign->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('refuse de supprimer une commune utilisée et supprime une commune libre', function (): void {
    $zone = Zone::factory()->create();
    $used = Commune::factory()->create(['zone_id' => $zone->id]);
    $free = Commune::factory()->create(['zone_id' => $zone->id]);
    User::factory()->create(['zone_id' => $zone->id, 'commune_id' => $used->id]);

    $screen = refScreen('admin', ['pays' => $zone->country_code, 'zone' => (string) $zone->id]);
    $screen->call('delete', $used->id)->assertDispatched('toast', type: 'error');
    $screen->call('delete', $free->id)->assertDispatched('toast', type: 'warning');

    expect(Commune::query()->whereKey($used->id)->exists())->toBeTrue()->and(Commune::query()->whereKey($free->id)->exists())->toBeFalse();
});

it('ignore une zone d’URL inconnue et revient à la liste des zones', function (): void {
    expect(refScreen('admin', ['zone' => '999999'])->instance()->kind())->toBe('zones');
});

// --- Types de violence, besoins, populations ---------------------------------------------------------------------------

it('crée, modifie, désactive et réordonne un type de violence ; le code reste fixe', function (): void {
    ViolenceType::factory()->create(['slug' => 'physique', 'position' => 1]);
    $screen = refScreen('admin', ['onglet' => 'violence_types']);

    $screen->call('create')->set('form.label', 'Violence institutionnelle')->set('form.description', 'Refus de service par une administration.')->set('form.icon', 'bi-bank')->call('save')->assertHasNoErrors();
    $type = ViolenceType::query()->where('label', 'Violence institutionnelle')->firstOrFail();
    expect($type->slug)->toBe('violence-institution')->and(strlen($type->slug))->toBeLessThanOrEqual(20);

    $screen->call('edit', $type->id)->set('form.label', 'Violence d’État')->set('form.slug', 'hack')->call('save')->assertHasNoErrors();
    expect($type->fresh()->label)->toBe('Violence d’État')->and($type->fresh()->slug)->toBe('violence-institution');

    $screen->call('create')->set('form.label', 'X')->call('save')->assertHasErrors(['form.label', 'form.description']);
    $screen->set('form.label', 'Violence légale')->set('form.description', 'Description.')->set('form.icon', 'rouge')->call('save')->assertHasErrors(['form.icon']);

    $screen->call('move', $type->id, 'up');
    expect($type->fresh()->position)->toBe(1);

    $screen->call('toggle', $type->id);
    expect($type->fresh()->is_active)->toBeFalse();
    $this->getJson('/api/v1/violence-types')->assertJsonMissing(['slug' => 'violence-institution']);
});

it('crée un besoin avec son rôle ciblé et son code à soulignés ; l’API le sert avec le rôle', function (): void {
    refScreen('admin', ['onglet' => 'needs'])->call('create')->set('form.label', 'Aide à la scolarité')->set('form.routed_role', 'juriste')->call('save')->assertHasNoErrors();

    $need = Need::query()->where('slug', 'aide_a_la_scolarite')->firstOrFail();
    expect($need->routed_role?->value)->toBe('juriste');
    $this->getJson('/api/v1/needs')->assertJsonFragment(['slug' => 'aide_a_la_scolarite', 'routed_role' => 'juriste']);

    refScreen('admin', ['onglet' => 'needs'])->call('create')->set('form.label', 'Autre aide')->set('form.routed_role', 'inconnu')->call('save')->assertHasErrors(['form.routed_role']);
});

it('crée une population, la désactive, et refuse sa suppression tant qu’un dossier l’utilise', function (): void {
    $screen = refScreen('admin', ['onglet' => 'populations']);
    $screen->call('create')->set('form.label', 'Femmes en situation de handicap')->call('save')->assertHasNoErrors();
    $population = Population::query()->where('label', 'Femmes en situation de handicap')->firstOrFail();

    $case = CaseRecord::factory()->create(['population_id' => $population->id]);
    $screen->call('delete', $population->id)->assertDispatched('toast', type: 'error');
    expect(Population::query()->whereKey($population->id)->exists())->toBeTrue();

    $screen->call('toggle', $population->id);
    expect($population->fresh()->is_active)->toBeFalse()->and($case->fresh()->population->label)->toBe('Femmes en situation de handicap');

    $case->forceDelete();
    $screen->call('delete', $population->id)->assertDispatched('toast', type: 'warning');
    expect(Population::query()->whereKey($population->id)->exists())->toBeFalse();
});

it('refuse de supprimer un type de violence ou un besoin cité par une alerte ou un dossier', function (): void {
    $type = ViolenceType::factory()->create(['slug' => 'harcelement']);
    $need = Need::factory()->create(['slug' => 'aide_x']);
    Alert::factory()->create(['category' => 'harcelement', 'needs' => ['aide_x']]);
    $caseType = ViolenceType::factory()->create();
    CaseRecord::factory()->create(['violence_type_id' => $caseType->id]);

    refScreen('admin', ['onglet' => 'violence_types'])->call('delete', $type->id)->assertDispatched('toast', type: 'error')->call('delete', $caseType->id)->assertDispatched('toast', type: 'error');
    refScreen('admin', ['onglet' => 'needs'])->call('delete', $need->id)->assertDispatched('toast', type: 'error');

    expect(ViolenceType::query()->whereKey([$type->id, $caseType->id])->count())->toBe(2)->and(Need::query()->whereKey($need->id)->exists())->toBeTrue();
});

it('supprime un besoin inutilisé sans toucher aux besoins voisins', function (): void {
    $keep = Need::factory()->create(['slug' => 'garde']);
    $drop = Need::factory()->create(['slug' => 'jetable']);
    Alert::factory()->create(['needs' => ['garde']]);

    refScreen('admin', ['onglet' => 'needs'])->call('delete', $drop->id)->assertDispatched('toast', type: 'warning');

    expect(Need::query()->whereKey($drop->id)->exists())->toBeFalse()->and(Need::query()->whereKey($keep->id)->exists())->toBeTrue();
});

it('un signalement sur un type désactivé reste lisible mais la saisie le refuse', function (): void {
    $type = ViolenceType::factory()->create(['slug' => 'ancien', 'is_active' => true]);
    $alert = Alert::factory()->create(['category' => 'ancien']);

    refScreen('admin', ['onglet' => 'violence_types'])->call('toggle', $type->id);

    expect($alert->fresh()->category)->toBe('ancien');
    expect(array_column(app(Referentials::class)->violenceTypes(), 'slug'))->not->toContain('ancien');
});

it('compte les utilisations par ligne dans le tableau', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create();
    User::factory()->count(2)->create(['zone_id' => $zone->id]);
    Commune::factory()->count(3)->create(['zone_id' => $zone->id]);

    $row = refScreen('admin', ['pays' => 'CM'])->instance()->rows()[0];

    expect($row['usage'])->toBe(2)->and($row['communes'])->toBe(3);
});

it('refuse un onglet inconnu et garde l’onglet courant', function (): void {
    refScreen()->call('setTab', 'needs')->assertSet('tab', 'needs')->call('setTab', 'pirate')->assertSet('tab', 'needs');
});

it('rend des champs étiquetés, des boutons Monter / Descendre nommés et un tableau légendé', function (): void {
    $zone = Zone::factory()->inCountry('CM')->create(['name' => 'Centre']);
    Commune::factory()->create(['zone_id' => $zone->id, 'name' => 'Yaoundé']);

    refScreen('admin', ['pays' => 'CM'])
        ->assertSee('Pays')->assertSee('Nouvelle zone')->assertSee('Monter Centre')->assertSee('Descendre Centre')
        ->assertSee('Zones du pays choisi, dans leur ordre d’affichage')
        ->call('openZone', $zone->id)
        ->assertSee("Communes de «\u{00A0}Centre\u{00A0}»")->assertSee('Yaoundé')->assertSee('Zones (Cameroun)')
        ->call('create')->assertSee('Libellé')->assertSee('Code');

    refScreen('admin', ['onglet' => 'needs'])->call('create')->assertSee('Rôle ciblé');
});
