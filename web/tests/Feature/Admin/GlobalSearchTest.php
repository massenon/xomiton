<?php

declare(strict_types=1);

use App\Application\Admin\Search\GlobalSearch;
use App\Enums\DirectoryRole;
use App\Livewire\Admin\Search\Index as Search;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\Event;
use App\Models\Group;
use App\Models\Thematique;
use App\Models\User;
use App\Models\Zone;
use App\Support\ClearColumns;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event as EventFacade;
use Livewire\Livewire;

/*
 * P10-08 : Recherche globale /admin/recherche — accès, résultats groupés, groupes limités aux permissions et aux
 * périmètres (AlertScope, CaseRecordScope), aucune recherche sur une colonne chiffrée (récit d'alerte, notes de dossier,
 * adresse d'événement), terme ni journalisé ni mémorisé en session, bornes (2 caractères, 60 recherches par minute).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function searchUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function groupKeys(array $groups): array
{
    return array_column($groups, 'key');
}

it('sert la page à tout compte d’administration et la refuse au membre et au visiteur', function (): void {
    foreach (['admin', 'superadmin', 'moderateur'] as $role) {
        $html = $this->actingAs(searchUser($role))->get(route('admin.recherche'))
            ->assertOk()->assertSeeLivewire(Search::class)->assertSee('Rechercher dans toute l’administration')->getContent();
        expect($html)->toMatch('/data-xm-nav="recherche"\s+aria-current="page"/');
    }

    $this->actingAs(searchUser('membre'))->get(route('admin.recherche'))->assertForbidden();
    auth()->logout();
    $this->get(route('admin.recherche'))->assertRedirect();
});

it('groupe les résultats par famille et compte le total de chaque groupe', function (): void {
    $this->actingAs(searchUser('admin'));
    $zone = Zone::factory()->create(['name' => 'Xqzgnanado']);
    User::factory()->create(['name' => 'Xqzdrine Ahouansou', 'email' => 'xqzdrine@exemple.bj', 'pseudo' => 'xqzdrine']);
    CaseRecord::factory()->vbg()->create(['code' => 'VBG-XQZ-01', 'zone_id' => $zone->id, 'referral' => 'Centre d’écoute', 'collected_by' => User::factory()->create(['pseudo' => 'collecteur-fixe'])->id]);
    Alert::factory()->create(['zone_id' => $zone->id, 'category' => 'violence']);
    DirectoryContact::factory()->create(['name' => 'Xqzdro Conseil', 'role' => DirectoryRole::Juriste, 'org' => 'ONG Xqz']);
    Thematique::factory()->create(['title' => 'Xqz : comprendre vos droits']);
    Event::factory()->create(['title' => 'Atelier Xqz']);
    Group::factory()->create(['name' => 'Groupe Xqz']);
    AuditLog::factory()->create(['action' => 'xqz.test', 'actor' => 'agent']);

    $groups = Livewire::test(Search::class)->set('q', 'xqz')->assertHasNoErrors()->viewData('groups');

    expect(groupKeys($groups))->toBe(['users', 'cases', 'alerts', 'directory', 'thematiques', 'network', 'journal']);
    $totals = collect($groups)->pluck('total', 'key');
    expect($totals['users'])->toBe(1)->and($totals['cases'])->toBe(1)->and($totals['network'])->toBe(2)->and($totals['journal'])->toBe(1);

    Livewire::test(Search::class)->set('q', 'xqz')->assertSee('Utilisateur·rices')->assertSee('Xqzdrine Ahouansou')->assertSee('VBG-XQZ-01')->assertSee('Annuaire');

    // La fonction se cherche par son libellé : « point focal » retrouve le code `point_focal`.
    DirectoryContact::factory()->focalNational()->create(['name' => 'Ayaba Hounkpatin']);
    expect(groupKeys(app(GlobalSearch::class)->search(searchUser('admin'), 'point focal')))->toBe(['directory']);
});

it('n’ouvre à chaque rôle que les groupes dont il a la permission', function (): void {
    User::factory()->create(['name' => 'Qzkassi Zita']);
    CaseRecord::factory()->vbg()->create(['code' => 'VBG-QZK-01', 'collected_by' => User::factory()->create(['name' => 'Qzkassi Pair'])->id]);
    Event::factory()->create(['title' => 'Qzkassi en fête']);
    Thematique::factory()->create(['title' => 'Qzkassi']);
    AuditLog::factory()->create(['action' => 'qzk.test']);

    $search = fn (User $user): array => groupKeys(app(GlobalSearch::class)->search($user, 'qzk'));

    expect($search(searchUser('admin')))->toContain('users', 'cases', 'thematiques', 'network', 'journal')
        // Modérateur : Utilisateurs et Réseau social seulement (Q14, P12-09).
        ->and($search(searchUser('moderateur')))->toBe(['users', 'network'])
        ->and($search(searchUser('membre')))->toBe([]);
});

it('respecte le périmètre des alertes : un compte à alerts.view_routed ne voit que les siennes', function (): void {
    $agent = User::factory()->create();
    $agent->givePermissionTo(['admin.access', 'alerts.view_routed']);
    $contact = DirectoryContact::factory()->linkedTo($agent)->create();
    $zone = Zone::factory()->create(['name' => 'Mono-Couffo']);
    $mine = Alert::factory()->create(['zone_id' => $zone->id, 'routed_to' => $contact->id]);
    $other = Alert::factory()->create(['zone_id' => $zone->id, 'routed_to' => DirectoryContact::factory()->create()->id]);

    $groups = app(GlobalSearch::class)->search($agent, 'Mono-Couffo');

    expect(groupKeys($groups))->toBe(['alerts'])
        ->and($groups[0]['total'])->toBe(1)
        ->and($groups[0]['rows'][0]['title'])->toContain((string) $mine->id);

    // Recherche par numéro d'alerte (#id) : même périmètre.
    expect(app(GlobalSearch::class)->search($agent, '#'.$other->id))->toBe([]);
    $admin = searchUser('admin');
    expect(app(GlobalSearch::class)->search($admin, '#'.$other->id)[0]['total'])->toBe(1);
});

it('ne cherche jamais dans une colonne chiffrée : récit, notes, adresse, position, contact', function (): void {
    $this->actingAs(searchUser('admin'));
    $zone = Zone::factory()->create();
    Alert::factory()->located()->create(['zone_id' => $zone->id, 'category' => 'harcèlement', 'description' => 'RECIT-SECRET-ALPHA', 'contact' => 'CONTACT-SECRET-BETA']);
    CaseRecord::factory()->vbg()->create(['zone_id' => $zone->id, 'collected_by' => User::factory()->create()->id, 'notes' => 'NOTES-SECRETES-GAMMA']);
    Event::factory()->withAddress('ADRESSE-SECRETE-DELTA')->create(['title' => 'Atelier ouvert']);

    foreach (['RECIT-SECRET-ALPHA', 'CONTACT-SECRET-BETA', 'NOTES-SECRETES-GAMMA', 'ADRESSE-SECRETE-DELTA'] as $secret) {
        Livewire::test(Search::class)->set('q', $secret)->assertViewHas('groups', [])->assertViewHas('total', 0)->assertSee('Aucun résultat');
    }

    // Les colonnes en clair, elles, répondent.
    expect(app(GlobalSearch::class)->search(searchUser('admin'), 'harcèlement')[0]['key'])->toBe('alerts');

    // Contrat : aucune colonne interrogée n'est chiffrée, et ClearColumns refuse les colonnes chiffrées.
    foreach (GlobalSearch::COLUMNS as $model => $columns) {
        ClearColumns::assert(new $model, $columns);
    }
    expect(fn () => ClearColumns::assert(new Alert, ['description']))->toThrow(LogicException::class)
        ->and(fn () => ClearColumns::assert(new CaseRecord, ['notes']))->toThrow(LogicException::class)
        ->and(fn () => ClearColumns::assert(new Event, ['address']))->toThrow(LogicException::class)
        ->and(fn () => ClearColumns::assert(new Alert, ['alerts.lat']))->toThrow(LogicException::class);

    $source = (string) file_get_contents(app_path('Application/Admin/Search/GlobalSearch.php'));
    foreach (config('xomiton.encrypted_columns') as $table => $columns) {
        foreach ($columns as $column) {
            // Aucune requête ne nomme `table.colonne` ni `'colonne'` d'une colonne chiffrée.
            expect($source)->not->toContain("'".$table.'.'.$column."'");
            if (in_array($column, ['description', 'notes', 'contact', 'address', 'lat', 'lng', 'body', 'comment'], true)) {
                expect($source)->not->toMatch("/clause\\(['\"]([a-z_]+\\.)?{$column}['\"]/");
            }
        }
    }
});

it('ne journalise ni ne mémorise le terme cherché', function (): void {
    $admin = searchUser('admin');
    $this->actingAs($admin);
    User::factory()->create(['name' => 'Mot-Unique-Recherche']);
    $logged = [];
    EventFacade::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.json_encode($log->context);
    });
    $auditBefore = AuditLog::query()->count();

    $response = $this->get(route('admin.recherche', ['q' => 'Mot-Unique-Recherche']))->assertOk()->assertSee('Mot-Unique-Recherche');
    Livewire::test(Search::class)->set('q', 'Mot-Unique-Recherche')->assertSee('Mot-Unique-Recherche');

    expect(AuditLog::query()->count())->toBe($auditBefore)
        ->and(implode('|', $logged))->not->toContain('Mot-Unique-Recherche')
        ->and(json_encode(session()->all()))->not->toContain('Mot-Unique-Recherche')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin');
});

it('exige deux caractères, normalise les espaces et neutralise les jokers SQL', function (): void {
    $this->actingAs(searchUser('admin'));
    User::factory()->create(['name' => 'Awa Dossou']);
    User::factory()->create(['name' => '100% Solidaire']);

    Livewire::test(Search::class)->set('q', 'a')->assertSee('Saisissez au moins 2 caractères.')->assertViewHas('groups', []);
    Livewire::test(Search::class)->set('q', '   ')->assertSee('Saisissez au moins 2 caractères pour lancer la recherche.');
    expect(GlobalSearch::normalize("  Awa \n  Dossou  "))->toBe('Awa Dossou')
        ->and(GlobalSearch::normalize(str_repeat('é', 200)))->toHaveLength(GlobalSearch::MAX_LENGTH)
        ->and(GlobalSearch::normalize('x'))->toBe('');

    expect(Livewire::test(Search::class)->set('q', 'Awa  Dossou')->viewData('total'))->toBe(1)
        // « % » et « _ » sont cherchés tels quels, pas comme jokers.
        ->and(Livewire::test(Search::class)->set('q', '%%')->viewData('total'))->toBe(0)
        ->and(Livewire::test(Search::class)->set('q', '0% S')->viewData('total'))->toBe(1)
        ->and(Livewire::test(Search::class)->set('q', '__')->viewData('total'))->toBe(0);
});

it('limite le nombre de recherches par minute et par compte', function (): void {
    config(['xomiton.rate_limits.admin_search_per_minute' => 2]);
    $this->actingAs(searchUser('admin'));
    $component = Livewire::test(Search::class);

    $component->set('q', 'aa')->set('q', 'ab')->assertDontSee('Trop de recherches');
    $component->set('q', 'ac')->assertSee('Trop de recherches')->assertViewHas('groups', []);

    $this->actingAs(searchUser('admin'));
    Livewire::test(Search::class)->set('q', 'aa')->assertDontSee('Trop de recherches');
});

it('propose le lien vers l’écran du groupe au-delà de six résultats', function (): void {
    $this->actingAs(searchUser('admin'));
    foreach (range(1, 8) as $i) {
        User::factory()->create(['name' => 'Personne Lambda '.$i]);
    }

    $groups = app(GlobalSearch::class)->search(searchUser('admin'), 'Lambda');

    expect($groups[0]['total'])->toBe(8)->and($groups[0]['rows'])->toHaveCount(GlobalSearch::PER_GROUP);
    Livewire::test(Search::class)->set('q', 'Lambda')->assertSee('Voir les 8 résultats dans');
});

it('ignore un paramètre ?q= mal formé', function (): void {
    $this->actingAs(searchUser('admin'));

    $this->get(route('admin.recherche', ['q' => ['a', 'b']]))->assertOk();
    $this->get(route('admin.recherche', ['q' => str_repeat('x', 5000)]))->assertOk();
});
