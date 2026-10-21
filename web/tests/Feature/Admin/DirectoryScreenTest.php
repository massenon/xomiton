<?php

declare(strict_types=1);

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Application\Alerts\RouteAlert;
use App\Enums\DirectoryRole;
use App\Livewire\Admin\Directory\Index as Directory;
use App\Livewire\Admin\Directory\RoutingPreview;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\Need;
use App\Models\User;
use App\Models\Zone;
use App\Support\CsvExport;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P11-04 : Annuaire dynamique /admin/annuaire (Livewire, WithDataTable) — accès `directory.manage`, recherche sur colonnes
 * en clair (jamais le téléphone), CRUD par les règles du catalogue (phone_bj, email, commune_in_zone), activer / désactiver
 * (pris en compte par le routage RG-04), drapeaux public / urgence / point focal national, lien vers un compte, import CSV
 * (rapport de rejets, rejouable), export CSV avec coordonnées (export « complet » réservé à `cases.export`), aperçu du
 * routage identique à RouteAlert.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, ValidationRulesSeeder::class]);
});

function directoryUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** @param  array<string, mixed>  $overrides */
function directoryContact(array $overrides = []): DirectoryContact
{
    return DirectoryContact::factory()->create(array_replace([
        'name' => 'KPADE Awa',
        'role' => DirectoryRole::Juriste,
        'zone_id' => Zone::query()->where('name', 'Littoral')->value('id'),
        'commune_id' => null,
    ], $overrides));
}

/** @return array<string, mixed> */
function directoryForm(array $overrides = []): array
{
    $zone = Zone::query()->where('name', 'Littoral')->firstOrFail();

    return array_replace([
        'name' => 'DOSSOU Maître',
        'role' => 'juriste',
        'org' => 'Barreau',
        'availability' => '9 h à 17 h',
        'phone' => '01 97 38 88 47',
        'email' => 'Maitre@Barreau.BJ',
        'whatsapp' => '',
        'zone_id' => (string) $zone->id,
        'commune_id' => (string) Commune::query()->where('zone_id', $zone->id)->value('id'),
        'user_ref' => '',
        'is_active' => true,
        'is_public' => true,
        'is_emergency' => false,
        'is_national_focal' => false,
    ], $overrides);
}

function directoryCsv(array $lines, string $delimiter = ';'): UploadedFile
{
    $header = ['Nom', 'Rôle', 'Organisation', 'Téléphone', 'E-mail', 'WhatsApp', 'Département', 'Commune', 'Disponibilité', 'Actif'];
    $out = fopen('php://memory', 'r+');
    fwrite($out, "\u{FEFF}");
    fputcsv($out, $header, $delimiter, '"', '');

    foreach ($lines as $line) {
        fputcsv($out, $line, $delimiter, '"', '');
    }

    rewind($out);

    return UploadedFile::fake()->createWithContent('annuaire.csv', (string) stream_get_contents($out));
}

it('sert l’annuaire à l’admin et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(directoryUser($role))->get(route('admin.annuaire'))
            ->assertOk()->assertSeeLivewire(Directory::class)->assertSeeLivewire(RoutingPreview::class)->assertSee('Annuaire dynamique')->getContent();
        expect($html)->toMatch('/data-xm-nav="annuaire"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(directoryUser($role))->get(route('admin.annuaire'))->assertForbidden();
    }

    Livewire::actingAs(directoryUser('moderateur'))->test(Directory::class)->assertForbidden();
    Livewire::actingAs(directoryUser('moderateur'))->test(RoutingPreview::class)->assertForbidden();
});

it('liste, filtre et trie les contacts ; la recherche ignore le téléphone', function (): void {
    $this->actingAs(directoryUser('admin'));
    $littoral = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $borgou = Zone::query()->where('name', 'Borgou')->firstOrFail();
    directoryContact(['name' => 'ADJOVI Paul', 'org' => 'Clinique Sainte-Marie', 'email' => 'paul@clinique.bj', 'phone' => '+229 01 55 00 11 22', 'role' => DirectoryRole::Gynecologue]);
    directoryContact(['name' => 'BIO Rachida', 'zone_id' => $borgou->id, 'role' => DirectoryRole::Psychologue, 'is_active' => false]);
    directoryContact(['name' => 'CODJO Léa', 'zone_id' => $littoral->id, 'role' => DirectoryRole::PointFocal]);

    Livewire::test(Directory::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('name')->all() === ['ADJOVI Paul', 'BIO Rachida', 'CODJO Léa'])
        ->set('search', 'sainte-marie')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', 'paul@clinique')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', '55 00 11')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', '')
        ->set('role', 'psychologue')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('name')->all() === ['BIO Rachida'])
        ->set('role', '')->set('zone', (string) $littoral->id)->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->set('zone', '')->set('status', 'inactif')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('name')->all() === ['BIO Rachida'])
        ->set('status', 'actif')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->set('status', '')->call('sortBy', 'nom')->assertViewHas('rows', fn ($rows): bool => $rows->first()->name === 'CODJO Léa')
        ->call('sortBy', 'telephone')
        ->assertSee(['Contacts', 'Actifs', 'Points focaux', 'Juristes · Psy · Gynéco']);
});

it('crée un contact : coordonnées normalisées, audit sans coordonnées, observateur du cache des urgences', function (): void {
    $admin = directoryUser('admin');
    $this->actingAs($admin);

    Livewire::test(Directory::class)->call('openCreate')->set('form', directoryForm())->call('save')->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: __('admin_data.directory.toasts.created', ['name' => 'DOSSOU Maître']))
        ->assertDispatched('directory-changed')
        ->assertDispatched('xm-modal', id: 'xmDirectoryForm', action: 'hide');

    $contact = DirectoryContact::query()->sole();
    expect($contact->phone)->toBe('+229 01 97 38 88 47')
        ->and($contact->email)->toBe('maitre@barreau.bj')
        ->and($contact->role)->toBe(DirectoryRole::Juriste)
        ->and($contact->is_active)->toBeTrue();

    $audit = AuditLog::query()->where('action', 'directory.created')->sole();
    expect($audit->detail)->toBe('nom=DOSSOU Maître ; rôle=juriste');
});

it('applique les règles du catalogue : nom, rôle, téléphone, e-mail, commune, moyen de contact, point focal national', function (): void {
    $this->actingAs(directoryUser('admin'));
    $other = Zone::query()->where('name', 'Borgou')->firstOrFail();
    $strange = Commune::query()->where('zone_id', $other->id)->firstOrFail();

    $component = Livewire::test(Directory::class)->call('openCreate')
        ->set('form', directoryForm(['name' => 'ab', 'phone' => '12-AB', 'email' => 'awa@', 'commune_id' => (string) $strange->id, 'role' => 'inconnu']))
        ->call('save')->assertHasErrors(['form.name', 'form.phone', 'form.email', 'form.commune_id', 'form.role']);

    $component->set('form', directoryForm(['phone' => '', 'email' => '', 'whatsapp' => '']))->call('save')->assertHasErrors('form.phone');
    $component->set('form', directoryForm(['is_national_focal' => true]))->call('save')->assertHasErrors('form.is_national_focal');
    expect(DirectoryContact::query()->count())->toBe(0);

    // Règles `phone_bj` et `email` désactivées dans l'administration : le texte saisi est gardé tel quel.
    CaseFixtures::disable('phone_bj');
    CaseFixtures::disable('email');
    $component->set('form', directoryForm(['phone' => 'poste 14', 'email' => 'pas-un-mail']))->call('save')->assertHasNoErrors();
    expect(DirectoryContact::query()->sole())->phone->toBe('poste 14')->email->toBe('pas-un-mail');
});

it('lie un compte par pseudo ou e-mail et refuse un compte inconnu', function (): void {
    $this->actingAs(directoryUser('admin'));
    $member = User::factory()->create(['pseudo' => 'Awa-Pair', 'email' => 'awa@example.test']);

    $component = Livewire::test(Directory::class)->call('openCreate')
        ->set('form', directoryForm(['user_ref' => 'awa-pair']))->call('save')->assertHasNoErrors();
    expect(DirectoryContact::query()->sole()->user_id)->toBe($member->id);

    $component->call('openCreate')->set('form', directoryForm(['name' => 'Autre Contact', 'user_ref' => 'fantome']))->call('save')->assertHasErrors('form.user_ref');
    $component->call('openCreate')->set('form', directoryForm(['name' => 'Par Mail', 'phone' => '', 'email' => '', 'user_ref' => 'AWA@example.test']))->call('save')->assertHasNoErrors();
    expect(DirectoryContact::query()->count())->toBe(2);
});

it('modifie un contact, le désactive et le réactive : seules les fiches actives sont routées', function (): void {
    $this->actingAs(directoryUser('admin'));
    $zone = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $juriste = directoryContact(['zone_id' => $zone->id]);
    $fallback = directoryContact(['name' => 'Point focal national', 'role' => DirectoryRole::PointFocal, 'zone_id' => null, 'is_national_focal' => true]);

    $component = Livewire::test(Directory::class)->call('openEdit', $juriste->id);
    expect($component->get('form.name'))->toBe('KPADE Awa');
    $component->set('form.org', 'Nouveau cabinet')->call('save')->assertHasNoErrors();
    expect($juriste->fresh()->org)->toBe('Nouveau cabinet');
    expect(AuditLog::query()->where('action', 'directory.updated')->sole()->detail)->toBe('nom=KPADE Awa ; champs=org');

    expect(app(RouteAlert::class)($zone->id, ['soutien_juridique'])->contactId)->toBe($juriste->id);

    $component->call('toggle', $juriste->id)->assertDispatched('toast', type: 'success', message: __('admin_data.directory.toasts.deactivated', ['name' => 'KPADE Awa']));
    expect($juriste->fresh()->is_active)->toBeFalse()
        ->and(app(RouteAlert::class)($zone->id, ['soutien_juridique'])->contactId)->toBe($fallback->id);

    $component->call('toggle', $juriste->id)->assertDispatched('toast', type: 'success', message: __('admin_data.directory.toasts.activated', ['name' => 'KPADE Awa']));
    expect(app(RouteAlert::class)($zone->id, ['soutien_juridique'])->contactId)->toBe($juriste->id);
    expect(AuditLog::query()->whereIn('action', ['directory.activated', 'directory.deactivated'])->pluck('action')->all())->toBe(['directory.deactivated', 'directory.activated']);

    $component->call('openEdit', 99999)->assertDispatched('toast', type: 'error');
});

it('prévient quand plus aucun point focal national n’est actif', function (): void {
    $this->actingAs(directoryUser('admin'));
    $national = directoryContact(['name' => 'Focal National', 'role' => DirectoryRole::PointFocal, 'zone_id' => null, 'is_national_focal' => true]);

    $component = Livewire::test(Directory::class)->call('toggle', $national->id);
    $warnings = collect($component->effects['dispatches'] ?? [])->filter(fn (array $e): bool => $e['name'] === 'toast' && $e['params']['type'] === 'warning');

    expect($warnings)->toHaveCount(1)->and($warnings->first()['params']['message'])->toBe(__('admin_data.directory.toasts.no_national_focal'));
});

it('supprime un contact logiquement, avec audit, et il n’est plus routé', function (): void {
    $this->actingAs(directoryUser('admin'));
    $zone = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $contact = directoryContact(['zone_id' => $zone->id]);

    Livewire::test(Directory::class)->call('delete', $contact->id)
        ->assertDispatched('toast', type: 'success', message: __('admin_data.directory.toasts.deleted', ['name' => 'KPADE Awa']));

    expect(DirectoryContact::query()->count())->toBe(0)->and(DirectoryContact::withTrashed()->count())->toBe(1)
        ->and(app(RouteAlert::class)($zone->id, ['soutien_juridique'])->contactId)->toBeNull();
    expect(AuditLog::query()->where('action', 'directory.deleted')->sole()->detail)->toBe('nom=KPADE Awa ; rôle=juriste');
});

it('sert hors connexion une fiche publique cochée « urgence » (drapeau is_emergency)', function (): void {
    $this->actingAs(directoryUser('admin'));
    $contact = directoryContact(['name' => 'Référent Rapide', 'role' => DirectoryRole::PairAnimateur, 'is_public' => true, 'is_emergency' => false]);

    expect(app(ListEmergencyContacts::class)()->contacts->pluck('id')->all())->not->toContain($contact->id);

    Livewire::test(Directory::class)->call('openEdit', $contact->id)->set('form.is_emergency', true)->call('save')->assertHasNoErrors();

    expect(app(ListEmergencyContacts::class)()->contacts->pluck('id')->all())->toContain($contact->id);

    // Non public : jamais servi, même cochée.
    Livewire::test(Directory::class)->call('openEdit', $contact->id)->set('form.is_public', false)->call('save')->assertHasNoErrors();
    expect(app(ListEmergencyContacts::class)()->contacts->pluck('id')->all())->not->toContain($contact->id);
});

it('importe un fichier CSV : lignes valides créées, lignes invalides rejetées avec leur motif, rejouable sans doublon', function (): void {
    $admin = directoryUser('admin');
    $this->actingAs($admin);
    Commune::query()->where('name', 'Cotonou')->firstOrFail();

    $lines = [];

    for ($i = 1; $i <= 100; $i++) {
        $lines[] = ["NOM{$i} Prénom{$i}", 'Juriste', 'Barreau', sprintf('+229 01 97 %02d %02d 00', intdiv($i, 100) + 10, $i % 100), "contact{$i}@mail.bj", '', 'Littoral', 'Cotonou', '9 h à 17 h', $i % 2 === 0 ? 'oui' : 'non'];
    }

    // Lignes à rejeter : téléphone, e-mail, rôle, département, commune, nom court, aucun moyen de contact.
    $lines[] = ['MAUVAIS Tel', 'Juriste', '', '12-AB', 'a@mail.bj', '', 'Littoral', '', '', 'oui'];
    $lines[] = ['MAUVAIS Mail', 'Juriste', '', '+229 01 97 00 00 01', 'awa@', '', 'Littoral', '', '', 'oui'];
    $lines[] = ['ROLE Inconnu', 'Sorcier', '', '+229 01 97 00 00 02', 'b@mail.bj', '', 'Littoral', '', '', 'oui'];
    $lines[] = ['ZONE Inconnue', 'Juriste', '', '+229 01 97 00 00 03', 'c@mail.bj', '', 'Atlantide', '', '', 'oui'];
    $lines[] = ['COMMUNE Mauvaise', 'Juriste', '', '+229 01 97 00 00 04', 'd@mail.bj', '', 'Littoral', 'Parakou', '', 'oui'];
    $lines[] = ['ab', 'Juriste', '', '+229 01 97 00 00 05', 'e@mail.bj', '', 'Littoral', '', '', 'oui'];
    $lines[] = ['SANS Contact', 'Juriste', '', '', '', '', 'Littoral', '', '', 'oui'];

    $component = Livewire::test(Directory::class)->call('openImport')
        ->set('csv', directoryCsv($lines))->call('import')->assertHasNoErrors();

    $report = $component->get('importReport');
    expect($report['created'])->toBe(100)->and($report['updated'])->toBe(0)->and($report['rejected'])->toHaveCount(7);
    expect(DirectoryContact::query()->count())->toBe(100)
        ->and(DirectoryContact::query()->where('is_active', false)->count())->toBe(50)
        ->and(collect($report['rejected'])->pluck('line')->all())->toBe(range(102, 108));
    expect(collect($report['rejected'])->pluck('reasons')->flatten()->implode(' '))
        ->toContain('Rôle inconnu', 'Département inconnu', 'Commune inconnue', 'au moins un moyen de joindre');

    // Un seul audit pour tout le fichier, sans coordonnées.
    $audit = AuditLog::query()->where('action', 'directory.imported')->sole();
    expect($audit->detail)->toBe('lignes=107 ; créées=100 ; mises à jour=0 ; rejetées=7');

    // Rejouer le même fichier met à jour, sans doublon.
    $component->call('openImport')->set('csv', directoryCsv($lines))->call('import');
    expect(DirectoryContact::query()->count())->toBe(100)->and($component->get('importReport')['updated'])->toBe(100);
});

it('lit un CSV Windows-1252 à virgules, des colonnes dans un autre ordre et refuse les fichiers sans colonnes attendues', function (): void {
    $this->actingAs(directoryUser('admin'));
    $content = mb_convert_encoding("Rôle,Nom,Téléphone\nPsychologue,MÉLIA Zoé,+229 01 97 11 22 33\n", 'Windows-1252', 'UTF-8');

    $component = Livewire::test(Directory::class)->call('openImport')
        ->set('csv', UploadedFile::fake()->createWithContent('latin.csv', $content))->call('import')->assertHasNoErrors();
    expect(DirectoryContact::query()->sole())->name->toBe('MÉLIA Zoé')->role->toBe(DirectoryRole::Psychologue);

    $component->call('openImport')->set('csv', UploadedFile::fake()->createWithContent('mauvais.csv', "Foo;Bar\n1;2\n"))->call('import')->assertHasErrors('csv');
    $component->call('openImport')->set('csv', UploadedFile::fake()->create('trop-gros.csv', 2048, 'text/csv'))->call('import')->assertHasErrors('csv');
    expect(DirectoryContact::query()->count())->toBe(1);
});

it('réserve l’import et le modèle à `directory.manage` et fournit un modèle téléchargeable', function (): void {
    $this->actingAs(directoryUser('admin'));

    Livewire::test(Directory::class)->call('downloadTemplate')->assertFileDownloaded('modele-import-annuaire.csv');

    $mod = directoryUser('moderateur');
    Livewire::actingAs($mod)->test(Directory::class)->assertForbidden();
});

it('exporte en CSV avec coordonnées uniquement pour `cases.export` (export complet journalisé), formules neutralisées', function (): void {
    $admin = directoryUser('admin');
    $this->actingAs($admin);
    directoryContact(['name' => '=CMD() piège', 'phone' => '+229 01 97 38 88 47', 'email' => 'piege@mail.bj']);
    directoryContact(['name' => 'Autre Contact', 'role' => DirectoryRole::Psychologue, 'is_active' => false]);

    $url = Livewire::withQueryParams(['st' => 'actif'])->test(Directory::class)->call('export')->effects['redirect'] ?? null;
    expect($url)->toBeString()->toContain('/admin/export?');

    $csv = $this->get($url)->assertOk()->assertDownload(CsvExport::filename('annuaire'))->streamedContent();
    expect($csv)->toStartWith("\u{FEFF}Nom;Rôle;Organisation;Téléphone;E-mail;WhatsApp;Département;Commune;Disponibilité;Actif;Public;\"Point focal national\";Urgence\n")
        ->and($csv)->toContain("'=CMD() piège")->toContain('+229 01 97 38 88 47')->not->toContain('Autre Contact');
    expect(AuditLog::query()->where('action', 'export.csv_full')->sole()->detail)->toBe('Export CSV complet annuaire, 1 ligne');

    // `directory.manage` sans `cases.export` : ni bouton, ni export.
    $limited = User::factory()->create();
    $limited->givePermissionTo(['admin.access', 'directory.manage']);
    $this->actingAs($limited);
    Livewire::test(Directory::class)->assertDontSee(__('admin_data.directory.export', ['count' => 2]))->call('export')->assertForbidden();
});

it('affiche un aperçu du routage identique à RouteAlert pour chaque département et chaque besoin', function (): void {
    $this->actingAs(directoryUser('admin'));
    $littoral = Zone::query()->where('name', 'Littoral')->firstOrFail();
    $borgou = Zone::query()->where('name', 'Borgou')->firstOrFail();
    directoryContact(['name' => 'Juriste Littoral', 'zone_id' => $littoral->id]);
    directoryContact(['name' => 'Focal Borgou', 'role' => DirectoryRole::PointFocal, 'zone_id' => $borgou->id]);
    directoryContact(['name' => 'Focal National', 'role' => DirectoryRole::PointFocal, 'zone_id' => null, 'is_national_focal' => true]);
    directoryContact(['name' => 'Psy Inactive', 'role' => DirectoryRole::Psychologue, 'zone_id' => $littoral->id, 'is_active' => false]);

    $route = app(RouteAlert::class);

    // Le tableau en une passe rend la décision de __invoke() pour chaque cellule.
    $scenarios = [];

    foreach (Zone::query()->active()->ordered()->pluck('id') as $zoneId) {
        foreach ([[], ['soutien_juridique'], ['soutien_psychologique'], ['soutien_medical']] as $needs) {
            $scenarios[] = [$zoneId, $needs];
        }
    }

    foreach ($route->decisions($scenarios) as $index => $decision) {
        [$zoneId, $needs] = $scenarios[$index];
        expect($decision)->toEqual($route($zoneId, $needs));
    }

    $component = Livewire::test(RoutingPreview::class)
        ->set('need', 'soutien_juridique')->set('zone', (string) $littoral->id)
        ->assertSee('Juriste Littoral')->assertSee('Focal National');

    // Besoin sans contact actif dans la zone : repli sur le point focal de la zone, puis le national.
    $component->set('need', 'soutien_psychologique')->set('zone', (string) $borgou->id)->assertSee('Focal Borgou')->assertSee('repli');
    $component->set('need', 'soutien_psychologique')->set('zone', (string) $littoral->id)->assertSee('Focal National');

    expect(Need::query()->where('slug', 'soutien_juridique')->exists())->toBeTrue();
});
