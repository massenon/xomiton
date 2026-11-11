<?php

declare(strict_types=1);

use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Enums\AlertStatus;
use App\Enums\DirectoryRole;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use Illuminate\Support\Facades\Event;
use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * API `pair/alerts` (P9-02, docs/API.md §22 ; ROLE_ET_PERMISSIONS.md §4 l.72 « Pair : routées ») : seulement les
 * alertes routées vers les fiches d'annuaire du compte (même pour un compte `alerts.view`), détail pour répondre
 * (récit, contact, position déchiffrés, no-store), accusé de réception (AcknowledgeAlert) et statuts
 * (ChangeAlertStatus : prendre en charge, traiter, clore) ; 403 sur une alerte non routée ; aucune réattribution.
 */

beforeEach(function (): void {
    C::seed();
    Event::fake([AlertAcknowledged::class, AlertStatusChanged::class]);
    $this->peer = C::peer();
    $this->contact = DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->linkedTo($this->peer)->create();
    $this->elsewhere = DirectoryContact::factory()->linkedTo(C::peer())->create();
});

it('liste les seules alertes routées vers mes fiches, ouvertes par défaut', function (): void {
    $new = Alert::factory()->routedTo($this->contact)->create(['created_at' => now()->subMinutes(5)]);
    $taken = Alert::factory()->routedTo($this->contact)->acknowledged()->withStatus(AlertStatus::InProgress)->create(['created_at' => now()->subMinutes(9)]);
    $closed = Alert::factory()->routedTo($this->contact)->withStatus(AlertStatus::Closed)->create();
    $foreign = Alert::factory()->routedTo($this->elsewhere)->create();

    $open = F::as($this->peer)->getJson('/api/v1/pair/alerts')->assertOk()
        ->assertJsonPath('meta.state', 'open')
        ->assertJsonPath('meta.new', 1);

    expect(array_column($open->json('data'), 'id'))->toBe([$new->ulid, $taken->ulid])
        ->and($open->json('data.0'))->not->toHaveKeys(['description', 'contact'])
        ->and($open->headers->get('Cache-Control'))->toContain('no-store');

    $all = F::as($this->peer)->getJson('/api/v1/pair/alerts?state=all')->assertOk();

    expect(array_column($all->json('data'), 'id'))->toContain($closed->ulid)->not->toContain($foreign->ulid);
});

it('ne montre à l’administration, dans l’espace pair, que les alertes routées vers ses propres fiches', function (): void {
    $admin = F::member('admin');
    $own = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($admin)->create())->create();
    Alert::factory()->routedTo($this->contact)->create();

    $response = F::as($admin)->getJson('/api/v1/pair/alerts')->assertOk();

    expect(array_column($response->json('data'), 'id'))->toBe([$own->ulid]);
});

it('détaille une alerte routée vers moi pour y répondre, en no-store', function (): void {
    $alert = Alert::factory()->sos()->routedTo($this->contact)->withContact('+229 01 97 38 88 47')->located(6.37, 2.39)
        ->create(['description' => 'Menaces répétées par un voisin depuis une semaine.']);

    $response = F::as($this->peer)->getJson('/api/v1/pair/alerts/'.$alert->ulid)->assertOk()
        ->assertJsonPath('data.id', $alert->ulid)
        ->assertJsonPath('data.type', 'sos')
        ->assertJsonPath('data.description', 'Menaces répétées par un voisin depuis une semaine.')
        ->assertJsonPath('data.contact', '+229 01 97 38 88 47')
        ->assertJsonPath('data.position.lat', 6.37)
        ->assertJsonPath('data.can.acknowledge', true)
        ->assertJsonPath('data.can.close', true);

    expect($response->json('data'))->not->toHaveKeys(['tracking_code', 'idempotency_key', 'user_id'])
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('ETag'))->toBeNull();
});

it('refuse (403) le détail et chaque geste sur une alerte non routée vers moi', function (): void {
    $foreign = Alert::factory()->routedTo($this->elsewhere)->create();
    $as = fn () => F::as($this->peer);

    $as()->getJson('/api/v1/pair/alerts/'.$foreign->ulid)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/acknowledge')->assertForbidden();
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/status', ['status' => 'en_cours'])->assertForbidden();
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/status', ['status' => 'clos'])->assertForbidden();

    expect($foreign->fresh()->status)->toBe(AlertStatus::New)
        ->and($foreign->fresh()->acknowledged_at)->toBeNull();

    $as()->getJson('/api/v1/pair/alerts/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
});

it('refuse (403) à l’administration, dans l’espace pair, le détail et chaque geste sur une alerte non routée vers ses fiches', function (): void {
    $admin = F::member('admin');
    $foreign = Alert::factory()->routedTo($this->elsewhere)->create();
    $as = fn () => F::as($admin);

    $as()->getJson('/api/v1/pair/alerts/'.$foreign->ulid)->assertForbidden()->assertJsonPath('code', 'forbidden');
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/acknowledge')->assertForbidden();
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/status', ['status' => 'en_cours'])->assertForbidden();
    $as()->postJson('/api/v1/pair/alerts/'.$foreign->ulid.'/status', ['status' => 'inconnu'])->assertForbidden();

    expect($foreign->fresh()->status)->toBe(AlertStatus::New)
        ->and($foreign->fresh()->acknowledged_at)->toBeNull();

    $own = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($admin)->create())->create();
    $as()->getJson('/api/v1/pair/alerts/'.$own->ulid)->assertOk();
});

it('accuse réception une fois (P5-02), puis rend l’accusé existant sans réécrire', function (): void {
    $alert = Alert::factory()->routedTo($this->contact)->create();

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/acknowledge')->assertOk()
        ->assertJsonPath('data.status', 'pris_en_charge')
        ->assertJsonPath('data.acknowledged', true)
        ->assertJsonPath('meta.acknowledged_now', true);

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/acknowledge')->assertOk()
        ->assertJsonPath('meta.already_acknowledged', true);

    expect($alert->fresh()->acknowledged_by)->toBe($this->peer->id);
    Event::assertDispatchedTimes(AlertAcknowledged::class, 1);
});

it('prend en charge puis clôt une alerte routée, avec historique (ChangeAlertStatus)', function (): void {
    $alert = Alert::factory()->routedTo($this->contact)->create();

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/status', ['status' => 'en_cours'])->assertOk()
        ->assertJsonPath('data.status', 'en_cours')
        ->assertJsonPath('data.acknowledged', true);

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/status', ['status' => 'clos'])->assertOk()
        ->assertJsonPath('data.status', 'clos')
        ->assertJsonPath('data.can.close', false)
        ->assertJsonPath('data.history.1.status', 'clos');

    expect($alert->fresh()->closed_at)->not->toBeNull();

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/status', ['status' => 'en_cours'])->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_transition');
});

it('n’offre ni réattribution, ni note, ni export au pair', function (): void {
    $alert = Alert::factory()->routedTo($this->contact)->create();

    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/reassign', ['contact_id' => $this->elsewhere->id])->assertNotFound();
    F::as($this->peer)->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/notes', ['body' => 'Note'])->assertNotFound();
    F::as($this->peer)->getJson('/api/v1/pair/alerts/export')->assertNotFound();

    expect((int) $alert->fresh()->routed_to)->toBe($this->contact->id);
});

it('refuse les alertes routées à un compte pair sans alerts.view_routed', function (): void {
    Spatie\Permission\Models\Role::findByName('pair', 'web')->revokePermissionTo('alerts.view_routed');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $alert = Alert::factory()->routedTo($this->contact)->create();

    F::as($this->peer->fresh())->getJson('/api/v1/pair/alerts')->assertForbidden();
    F::as($this->peer->fresh())->postJson('/api/v1/pair/alerts/'.$alert->ulid.'/status', ['status' => 'clos'])->assertForbidden();
});

it('dit dans meta.on_duty si le pair est de garde comme point focal (plage focal en cours)', function (): void {
    F::as($this->peer)->getJson('/api/v1/pair/alerts')->assertOk()->assertJsonPath('meta.on_duty', false);

    OnCallShift::factory()->forUser($this->peer)->focal()->create();
    OnCallShift::factory()->forUser($this->elsewhere->user)->focal()->between(now()->addDay(), now()->addDays(2))->create();

    F::as($this->peer)->getJson('/api/v1/pair/alerts')->assertOk()->assertJsonPath('meta.on_duty', true);
    F::as($this->elsewhere->user)->getJson('/api/v1/pair/alerts')->assertOk()->assertJsonPath('meta.on_duty', false);
});
