<?php

declare(strict_types=1);

use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\DirectoryRole;
use App\Enums\RoutingFallback;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Notifications\AlertRouted;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\FeedFixtures as F;
use Tests\Support\PrototypeDirectory;

/*
 * Annuaire membre (P8-12, docs/API.md §19) : `GET directory` — fiches actives selon la portée (publiques pour un·e membre
 * et un compte pair en attente, réseau compris pour pair·e validé·e, modération, administration), jamais notes ni compte
 * lié, filtres, recherche, carte par département, pagination, `no-store` ; `POST directory/{id}/callback` → RequestCallback
 * (alerte besoin `annuaire` routée vers le contact choisi sans RG-04, AlertRouted au compte du contact, rejeu sans
 * doublon, fiche non publique refusée).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    $this->zou = PrototypeDirectory::zone('Zou');
    $this->littoral = PrototypeDirectory::zone('Littoral');
    $this->abomey = Commune::query()->where('zone_id', $this->zou->id)->orderBy('position')->firstOrFail();
    $this->juriste = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou, $this->abomey)
        ->create(['name' => 'ADJOVI Rose', 'org' => 'Clinique juridique', 'notes' => 'Note interne']);
    $this->psy = DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($this->littoral)->create(['name' => 'BIO Clarisse', 'org' => 'Centre Espoir']);
    $this->national = DirectoryContact::factory()->focalNational()->create(['name' => 'CODJO Ange']);
    $this->reserved = DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->inZone($this->zou)->hidden()->create(['name' => 'DOSSOU Inès']);
    $this->inactive = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou)->inactive()->create(['name' => 'EZIN Inactive']);
    $this->deleted = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($this->zou)->create(['name' => 'FAGLA Supprimée']);
    $this->deleted->delete();
});

function directoryCallback(User $user, int $contactId, ?string $key = null, array $payload = ['contact' => '+229 01 97 38 88 47']): Illuminate\Testing\TestResponse
{
    return F::as($user)->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())
        ->postJson('/api/v1/directory/'.$contactId.'/callback', $payload);
}

function directoryNames(Illuminate\Testing\TestResponse $response): array
{
    return array_column($response->json('data'), 'name');
}

it('sert à un·e membre les fiches publiques actives, sans notes ni compte lié, en no-store', function (): void {
    $response = F::as(F::member())->getJson('/api/v1/directory')->assertOk();

    expect(directoryNames($response))->toBe(['ADJOVI Rose', 'BIO Clarisse', 'CODJO Ange'])
        ->and($response->json('meta.reach'))->toBe('public')
        ->and($response->json('meta.total'))->toBe(3)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $first = $response->json('data.0');
    expect($first)->toMatchArray([
        'id' => $this->juriste->id,
        'role' => 'juriste',
        'role_label' => 'Juriste',
        'role_color' => 'info',
        'org' => 'Clinique juridique',
        'zone' => ['id' => $this->zou->id, 'name' => 'Zou'],
        'commune' => ['id' => $this->abomey->id, 'name' => $this->abomey->name],
        'national' => false,
        'is_public' => true,
        'can' => ['callback' => true],
    ])
        ->and($first['tel'])->toBe('tel:'.preg_replace('/[^+0-9]/', '', (string) $this->juriste->phone))
        ->and($first['whatsapp_url'])->toBe('https://wa.me/'.preg_replace('/\D/', '', (string) $this->juriste->whatsapp))
        ->and($first)->not->toHaveKeys(['notes', 'user_id'])
        ->and($response->json('data.2.national'))->toBeTrue()
        // Rouge réservé à l'urgence (Q62) : la couleur du point focal est faite côté serveur, pour tous les clients.
        ->and($response->json('data.2.role_color'))->toBe('primary');
});

it('forme le lien WhatsApp depuis le téléphone quand le champ est vide, avec l’indicatif 229', function (): void {
    $this->psy->forceFill(['phone' => '01 97 38 88 47', 'whatsapp' => null])->save();
    $this->juriste->forceFill(['whatsapp' => '97 38 88 46'])->save();

    $data = collect(F::as(F::member())->getJson('/api/v1/directory')->json('data'))->keyBy('name');

    expect($data['BIO Clarisse']['whatsapp'])->toBeNull()
        ->and($data['BIO Clarisse']['whatsapp_url'])->toBe('https://wa.me/2290197388847')
        ->and($data['ADJOVI Rose']['whatsapp_url'])->toBe('https://wa.me/2290197388846');
});

it('ouvre les fiches réservées au réseau aux pair·es validé·es, à la modération et à l’administration seulement', function (string $role, UserStatus $status, string $reach): void {
    $user = F::member($role);
    $user->forceFill(['status' => $status])->save();

    $response = F::as($user)->getJson('/api/v1/directory')->assertOk();

    expect($response->json('meta.reach'))->toBe($reach)
        ->and(in_array('DOSSOU Inès', directoryNames($response), true))->toBe($reach === 'network');

    if ($reach === 'network') {
        $reserved = collect($response->json('data'))->firstWhere('name', 'DOSSOU Inès');
        expect($reserved['is_public'])->toBeFalse()->and($reserved['can']['callback'])->toBeFalse();
    }
})->with([
    'membre' => ['membre', UserStatus::Active, 'public'],
    'pair en attente (RG-03)' => ['pair', UserStatus::Pending, 'public'],
    'pair validé·e' => ['pair', UserStatus::Active, 'network'],
    'modération' => ['moderateur', UserStatus::Active, 'network'],
    'administration' => ['admin', UserStatus::Active, 'network'],
]);

it('refuse sans compte (401) et sans directory.view (403)', function (): void {
    $this->getJson('/api/v1/directory')->assertUnauthorized();

    F::as(User::factory()->withProfile()->create())->getJson('/api/v1/directory')->assertForbidden();
});

it('filtre par rôle, département, commune et recherche ; une valeur inconnue est ignorée', function (): void {
    $member = F::member();
    $other = Commune::query()->where('zone_id', $this->littoral->id)->firstOrFail();
    $gyneco = DirectoryContact::factory()->role(DirectoryRole::Gynecologue)->inZone($this->littoral)->create(['name' => 'GBAGUIDI Nadia', 'org' => 'Maternité']);

    expect(directoryNames(F::as($member)->getJson('/api/v1/directory?role=psychologue')))->toBe(['BIO Clarisse'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?zone='.$this->zou->id)))->toBe(['ADJOVI Rose'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?zone='.$this->zou->id.'&commune='.$this->abomey->id)))->toBe(['ADJOVI Rose'])
        // Commune d'un autre département : ignorée.
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?zone='.$this->zou->id.'&commune='.$other->id)))->toBe(['ADJOVI Rose'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q=espoir')))->toBe(['BIO Clarisse'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q=psycho')))->toBe(['BIO Clarisse'])
        // Libellé du rôle sans accents ni casse : « gyneco » trouve « Gynécologue ».
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q=GYNECO')))->toBe(['GBAGUIDI Nadia'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q=gynéco')))->toBe(['GBAGUIDI Nadia'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q='.urlencode($this->abomey->name))))->toBe(['ADJOVI Rose'])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?q=100%25')))->toBe([])
        ->and(directoryNames(F::as($member)->getJson('/api/v1/directory?role=inconnu&zone=999999')))->toHaveCount(4);

    expect(F::as($member)->getJson('/api/v1/directory?role=juriste&zone='.$this->zou->id)->json('meta.filters'))
        ->toBe(['q' => null, 'role' => 'juriste', 'zone' => $this->zou->id, 'commune' => null]);
});

it('compte les fiches par département pour la carte (mêmes filtres, hors lieu) et les fiches nationales à part', function (): void {
    $member = F::member();
    DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($this->zou)->create();

    $map = F::as($member)->getJson('/api/v1/directory?zone='.$this->littoral->id)->json('meta.map');
    $counts = collect($map['zones'])->pluck('count', 'slug');

    expect($counts['zou'])->toBe(2)
        ->and($counts['littoral'])->toBe(1)
        ->and($counts['borgou'])->toBe(0)
        ->and($map['zones'])->toHaveCount(12)
        ->and($map['national'])->toBe(1)
        ->and($map['total'])->toBe(4);

    $psy = collect(F::as($member)->getJson('/api/v1/directory?role=psychologue')->json('meta.map.zones'))->pluck('count', 'slug');
    expect($psy['zou'])->toBe(1)->and($psy['littoral'])->toBe(1);
});

it('pagine par page, dans l’ordre alphabétique', function (): void {
    config(['xomiton.directory.per_page' => 2]);
    $member = F::member();

    $first = F::as($member)->getJson('/api/v1/directory')->assertOk();
    $second = F::as($member)->getJson('/api/v1/directory?page=2')->assertOk();

    expect(directoryNames($first))->toBe(['ADJOVI Rose', 'BIO Clarisse'])
        ->and($first->json('meta.last_page'))->toBe(2)
        ->and($first->json('meta.map.total'))->toBe(3)
        ->and(directoryNames($second))->toBe(['CODJO Ange'])
        // « Charger plus » : pas de décompte de la carte après la page 1.
        ->and($second->json('meta'))->toHaveKey('map')
        ->and($second->json('meta.map'))->toBeNull();
});

it('demande un rappel : besoin « annuaire » routé vers le contact choisi, sans RG-04, rejeu sans doublon', function (): void {
    $contactUser = User::factory()->withProfile()->create();
    $this->juriste->forceFill(['user_id' => $contactUser->id])->save();
    $member = F::member();
    $key = (string) Str::uuid();

    $response = F::as($member)->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/directory/'.$this->juriste->id.'/callback', ['contact' => '+229 01 97 38 88 47', 'message' => 'Le soir, après 18 h.'])
        ->assertCreated();

    $alert = Alert::query()->sole();
    expect($alert->type)->toBe(AlertType::Need)
        ->and($alert->source)->toBe(AlertSource::Directory)
        ->and($alert->routed_to)->toBe($this->juriste->id)
        ->and($alert->routing_fallback)->toBe(RoutingFallback::Manual)
        ->and($alert->zone_id)->toBe($this->zou->id)
        ->and($alert->description)->toBe('Le soir, après 18 h.')
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($response->json('data.tracking_code'))->toBe($alert->tracking_code)
        ->and($response->json('data.type'))->toBe('need')
        ->and($response->json('data.routed'))->toBe(['role' => 'Juriste', 'name' => 'ADJOVI Rose', 'phone' => $this->juriste->phone, 'whatsapp' => $this->juriste->whatsapp])
        ->and($response->headers->get('Location'))->toBe(route('api.signalements.show', ['code' => $alert->tracking_code]))
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        // Onglet « Besoins » de /admin/alertes (P10) : « Routé vers » = contact choisi.
        ->and(Alert::query()->ofType(AlertType::Need)->routedTo($this->juriste)->pluck('id')->all())->toBe([$alert->id]);
    Notification::assertSentTo($contactUser, AlertRouted::class, fn (AlertRouted $notification): bool => $notification->alertId === $alert->id);

    F::as($member)->withHeader('Idempotency-Key', $key)
        ->postJson('/api/v1/directory/'.$this->juriste->id.'/callback', ['contact' => '+229 01 97 38 88 47', 'message' => 'Le soir, après 18 h.'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.tracking_code', $alert->tracking_code);
    expect(Alert::query()->count())->toBe(1);
});

it('transmet le pseudo sur demande : alerte liée au compte, journal au nom du compte', function (): void {
    $member = F::member();

    directoryCallback($member, $this->psy->id, null, ['contact' => 'awa@example.org', 'anonymous' => false])->assertCreated();

    $alert = Alert::query()->sole();
    $log = AuditLog::query()->where('action', 'alerts.callback_requested')->sole();
    expect($alert->is_anonymous)->toBeFalse()
        ->and($alert->user_id)->toBe($member->id)
        ->and($alert->pseudo)->toBe($member->pseudo)
        ->and($alert->routed_to)->toBe($this->psy->id)
        ->and($log->user_id)->toBe($member->id)
        ->and($log->subject_id)->toBe($alert->id)
        ->and($log->ip)->not->toBeNull();
});

it('refuse une fiche inactive ou supprimée (422 contact_unavailable) et sans directory.view (403)', function (): void {
    $member = F::member();

    directoryCallback($member, $this->inactive->id)->assertUnprocessable()->assertJsonPath('code', 'contact_unavailable');
    directoryCallback($member, $this->deleted->id)->assertUnprocessable()->assertJsonPath('code', 'contact_unavailable');
    directoryCallback(User::factory()->withProfile()->create(), $this->juriste->id)->assertForbidden();

    expect(Alert::query()->count())->toBe(0);
});

it('limite les demandes de rappel par compte (429), jamais par le quota `report` des signalements', function (): void {
    config(['xomiton.rate_limits.directory_callback_per_hour' => 2, 'xomiton.rate_limits.report_per_hour_per_ip' => 1]);
    $member = F::member();
    $key = (string) Str::uuid();

    directoryCallback($member, $this->juriste->id, $key)->assertCreated();
    directoryCallback($member, $this->psy->id)->assertCreated();
    // Un rejeu (même clé) ne consomme pas le quota et répond encore.
    directoryCallback($member, $this->juriste->id, $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    directoryCallback($member, $this->national->id)->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');

    // Même adresse IP, autre compte : quota à part (CGNAT mobile).
    directoryCallback(F::member(), $this->national->id)->assertCreated();
    expect(Alert::query()->count())->toBe(3);
});

it('refuse une fiche réservée au réseau, un moyen de contact manquant, un message trop long et l’absence de clé', function (): void {
    $peer = F::member('pair');

    F::as($peer)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/directory/'.$this->reserved->id.'/callback', ['contact' => '+229 01 97 38 88 47'])
        ->assertUnprocessable()->assertJsonPath('code', 'contact_unavailable');

    F::as($peer)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/directory/'.$this->juriste->id.'/callback', ['contact' => '', 'message' => str_repeat('a', 1001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['contact', 'message']);

    // Les en-têtes s'accumulent d'une requête à l'autre du test : clé et jeton retirés.
    $this->flushHeaders();
    F::as($peer)->postJson('/api/v1/directory/'.$this->juriste->id.'/callback', ['contact' => '+229 01 97 38 88 47'])
        ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_required');

    $this->flushHeaders();
    app('auth')->forgetGuards();
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/directory/'.$this->juriste->id.'/callback', ['contact' => '+229 01 97 38 88 47'])
        ->assertUnauthorized();

    expect(Alert::query()->count())->toBe(0);
});
