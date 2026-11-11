<?php

declare(strict_types=1);

use App\Application\Alerts\Support\SosOwnerToken;
use App\Domain\Alerts\Dispatch\NotifiedState;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\DirectoryRole;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\IdempotencyKey;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;

/*
 * P5-06 : API SOS — POST sos (201, jamais de 429, rejeu → même 201 et même jeton), GET sos/{id} (statut + personnes
 * prévenues issues d'alert_notifications, compte propriétaire ou X-Sos-Token, 403 sinon), cancel (repentir 60 s), safe.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

function postSos(array $payload = [], ?string $key = null): Illuminate\Testing\TestResponse
{
    return test()->withHeader(HandleIdempotencyKey::HEADER, $key ?? (string) Str::uuid())->postJson('/api/v1/sos', $payload);
}

function sosHeaders(?string $token): array
{
    return $token === null ? [] : [SosOwnerToken::HEADER => $token];
}

it('crée un SOS anonyme : 201, critique, jeton X-Sos-Token, Location, polling 10 s', function (): void {
    $response = postSos(['zone_id' => AlertFixtures::zone('Littoral')->id, 'message' => 'Je suis enfermée.'])->assertCreated();
    $alert = Alert::query()->sole();

    $response->assertHeader('Location', route('api.sos.show', ['alert' => $alert->ulid]))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.id', $alert->ulid)
        ->assertJsonPath('data.type', 'sos')
        ->assertJsonPath('data.priority', AlertPriority::Critical->value)
        ->assertJsonPath('data.sos_token', SosOwnerToken::for($alert))
        ->assertJsonPath('data.notified', [])
        ->assertJsonPath('meta.poll_interval_seconds', 10)
        ->assertJsonMissingPath('data.description');

    expect($alert->is_anonymous)->toBeTrue()->and($response->json('data.can_cancel_until'))->not->toBeNull();
});

it('rejoue un SOS avec la même clé : même 201, même jeton, aucune nouvelle alerte', function (): void {
    $key = (string) Str::uuid();

    $first = postSos([], $key)->assertCreated();
    $again = postSos([], $key)->assertCreated()->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($again->json())->toBe($first->json())
        ->and($again->json('data.sos_token'))->not->toBeNull()
        ->and(Alert::query()->count())->toBe(1);
});

it('n’oppose jamais de 429 à un SOS, même au-delà des limiteurs', function (): void {
    config(['xomiton.rate_limits.api_per_minute' => 1, 'xomiton.rate_limits.sos_log_per_minute_per_ip' => 1]);

    foreach (range(1, 4) as $ignored) {
        postSos()->assertCreated();
    }

    expect(Alert::query()->sos()->count())->toBe(4);
});

it('répond 422 sans clé d’idempotence ou avec une charge invalide', function (): void {
    test()->postJson('/api/v1/sos')->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_required');

    postSos(['lat' => 6.36, 'message' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['lng', 'message']);

    expect(Alert::query()->count())->toBe(0);
});

it('réserve le statut d’un SOS anonyme au porteur du jeton (403 sans jeton ou jeton faux)', function (): void {
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $token = $created->json('data.sos_token');

    test()->flushHeaders()->getJson("/api/v1/sos/{$id}")->assertForbidden()->assertJsonPath('code', 'forbidden');
    test()->getJson("/api/v1/sos/{$id}", sosHeaders('deviné'))->assertForbidden();
    test()->getJson("/api/v1/sos/{$id}", sosHeaders($token))->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.status', 'nouveau')
        ->assertJsonMissingPath('data.sos_token')
        ->assertJsonPath('meta.poll_interval_seconds', 10);

    test()->getJson('/api/v1/sos/01JAAAAAAAAAAAAAAAAAAAAAAA', sosHeaders($token))->assertNotFound();
});

it('réserve le statut d’un SOS identifié à son compte', function (): void {
    $owner = AlertFixtures::member();
    Sanctum::actingAs($owner);
    $created = postSos()->assertCreated()->assertJsonMissingPath('data.sos_token');
    $id = $created->json('data.id');

    test()->getJson("/api/v1/sos/{$id}")->assertOk()->assertJsonPath('data.id', $id);

    app('auth')->forgetGuards();
    Sanctum::actingAs(AlertFixtures::member());
    test()->getJson("/api/v1/sos/{$id}")->assertForbidden();

    // Un signalement n'est pas un SOS : rien à suivre ici, même pour sa propriétaire.
    $report = Alert::factory()->fromMember($owner)->create();
    app('auth')->forgetGuards();
    Sanctum::actingAs($owner);
    test()->getJson("/api/v1/sos/{$report->ulid}")->assertNotFound();
});

it('liste les personnes prévenues (NotifiedPeople, P5-03) et le contact principal, sans coordonnées masquées', function (): void {
    $zone = AlertFixtures::zone('Littoral');
    $owner = AlertFixtures::member();
    $focal = DirectoryContact::factory()->role(DirectoryRole::PointFocal)->inZone($zone)->create(['name' => 'HOUNKPATIN Chantal', 'is_public' => true]);
    $psy = DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($zone)->hidden()->create();
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->sos()->fromMember($owner)->routedTo($focal)->create(['zone_id' => $zone->id]);
    $rows = AlertNotification::factory()->forAlert($alert);

    $rows->toContact($focal, NotificationChannel::Sms)->sent()->seen()->create();
    $rows->toContact($psy, NotificationChannel::Sms)->sent()->create();
    $rows->toUser($admin)->failed()->create();
    // Retour d'accusé à l'auteur·e : ce n'est pas une personne prévenue.
    $rows->toUser($owner)->purpose(NotificationPurpose::SosAcknowledged)->sent()->create();

    Sanctum::actingAs($owner);
    $response = test()->getJson("/api/v1/sos/{$alert->ulid}")->assertOk();

    expect($response->json('data.notified.*.kind'))->toBe([RecipientKind::Focal->value, RecipientKind::Contact->value, RecipientKind::Team->value])
        ->and($response->json('data.notified.*.state'))->toBe([NotifiedState::Seen->value, NotifiedState::SmsSent->value, NotifiedState::Pending->value])
        ->and($response->json('data.notified.0.label'))->toBe('HOUNKPATIN Chantal')
        ->and($response->json('data.notified.0.phone'))->toBe($focal->phone)
        ->and($response->json('data.notified.1.phone'))->toBeNull()
        ->and($response->json('data.primary_contact.label'))->toBe('HOUNKPATIN Chantal')
        ->and((string) $response->getContent())->not->toContain((string) $psy->phone)->not->toContain((string) $psy->name)
        ->not->toContain((string) $admin->email)->not->toContain('user:')->not->toContain('contact:')->not->toContain('trusted:')
        ->and($response->json('data.notified.*.key'))->each->toMatch('/^p[0-9a-f]{24}$/');

    // Clé stable d'un sondage à l'autre (le composant xmNotified s'en sert pour repérer « a vu l'alerte »).
    expect(test()->getJson("/api/v1/sos/{$alert->ulid}")->json('data.notified.*.key'))->toBe($response->json('data.notified.*.key'));
});

it('annule un SOS dans les 60 s avec le jeton, puis refuse au-delà (422) ou sans jeton (403)', function (): void {
    $this->freezeSecond();
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $token = $created->json('data.sos_token');

    test()->flushHeaders()->postJson("/api/v1/sos/{$id}/cancel")->assertForbidden();

    test()->postJson("/api/v1/sos/{$id}/cancel", [], sosHeaders($token))->assertOk()
        ->assertJsonPath('data.cancelled', true)
        ->assertJsonPath('data.status', AlertStatus::Closed->value)
        ->assertJsonPath('meta.poll_interval_seconds', null);

    // Déjà annulé : idempotent.
    test()->postJson("/api/v1/sos/{$id}/cancel", [], sosHeaders($token))->assertOk()->assertJsonPath('data.cancelled', true);

    $late = postSos()->assertCreated();
    $this->travel(61)->seconds();
    test()->postJson('/api/v1/sos/'.$late->json('data.id').'/cancel', [], sosHeaders($late->json('data.sos_token')))
        ->assertUnprocessable()->assertJsonPath('code', 'cancel_window_expired');
});

it('enregistre « Je suis en sécurité » et arrête le sondage', function (): void {
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');

    test()->flushHeaders()->postJson("/api/v1/sos/{$id}/safe")->assertForbidden();

    test()->postJson("/api/v1/sos/{$id}/safe", [], sosHeaders($created->json('data.sos_token')))->assertOk()
        ->assertJsonPath('data.safe', true)
        ->assertJsonPath('data.status', AlertStatus::Handled->value)
        ->assertJsonPath('meta.poll_interval_seconds', null);

    expect(Alert::query()->sole()->safe_at)->not->toBeNull();
});

it('limite les actions répétées sur un même SOS (limiteur sos_owner)', function (): void {
    config(['xomiton.rate_limits.sos_owner_per_minute' => 2]);
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $headers = sosHeaders($created->json('data.sos_token'));

    test()->postJson("/api/v1/sos/{$id}/safe", [], $headers)->assertOk();
    test()->postJson("/api/v1/sos/{$id}/safe", [], $headers)->assertOk();
    test()->postJson("/api/v1/sos/{$id}/safe", [], $headers)->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
});

it('ne bloque jamais le suivi, le repentir ni « Je suis en sécurité » par le limiteur global api (CGNAT)', function (): void {
    config(['xomiton.rate_limits.api_per_minute' => 1]);
    $first = postSos()->assertCreated();
    $second = postSos()->assertCreated();
    $id = $first->json('data.id');
    $headers = sosHeaders($first->json('data.sos_token'));

    foreach (range(1, 3) as $ignored) {
        test()->getJson("/api/v1/sos/{$id}", $headers)->assertOk();
    }

    test()->postJson("/api/v1/sos/{$id}/safe", [], $headers)->assertOk()->assertJsonPath('data.safe', true);
    test()->postJson('/api/v1/sos/'.$second->json('data.id').'/cancel', [], sosHeaders($second->json('data.sos_token')))
        ->assertOk()->assertJsonPath('data.cancelled', true);

    // Le quota global est bien épuisé pour le reste de l'API.
    test()->getJson('/api/v1/urgences')->assertOk();
    test()->getJson('/api/v1/urgences')->assertTooManyRequests();
});

it('borne le sondage d’un même SOS par son propre limiteur (sos_status)', function (): void {
    config(['xomiton.rate_limits.sos_status_per_minute' => 2]);
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $headers = sosHeaders($created->json('data.sos_token'));

    test()->getJson("/api/v1/sos/{$id}", $headers)->assertOk();
    test()->getJson("/api/v1/sos/{$id}", $headers)->assertOk();
    test()->getJson("/api/v1/sos/{$id}", $headers)->assertTooManyRequests();

    // Autre alerte, autre compteur.
    $other = postSos()->assertCreated();
    test()->getJson('/api/v1/sos/'.$other->json('data.id'), sosHeaders($other->json('data.sos_token')))->assertOk();
});

it('refuse le jeton d’un SOS sur un autre SOS, et le jeton seul sur un SOS identifié (403)', function (): void {
    $a = postSos()->assertCreated();
    $b = postSos()->assertCreated();
    $tokenA = sosHeaders($a->json('data.sos_token'));

    test()->getJson('/api/v1/sos/'.$b->json('data.id'), $tokenA)->assertForbidden();
    test()->postJson('/api/v1/sos/'.$b->json('data.id').'/cancel', [], $tokenA)->assertForbidden();
    test()->postJson('/api/v1/sos/'.$b->json('data.id').'/safe', [], $tokenA)->assertForbidden();

    $identified = Alert::factory()->sos()->fromMember(AlertFixtures::member())->create();
    $forged = sosHeaders(SosOwnerToken::for($identified));

    test()->getJson("/api/v1/sos/{$identified->ulid}", $forged)->assertForbidden();
    test()->postJson("/api/v1/sos/{$identified->ulid}/cancel", [], $forged)->assertForbidden();
    expect($identified->fresh()?->isCancelled())->toBeFalse();
});

it('refuse « Je suis en sécurité » sur un SOS annulé (422 alert_closed) et l’annulation d’un signalement (422 not_sos)', function (): void {
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $headers = sosHeaders($created->json('data.sos_token'));

    test()->postJson("/api/v1/sos/{$id}/cancel", [], $headers)->assertOk();
    test()->postJson("/api/v1/sos/{$id}/safe", [], $headers)->assertUnprocessable()->assertJsonPath('code', 'alert_closed');

    $owner = AlertFixtures::member();
    $report = Alert::factory()->fromMember($owner)->create();
    Sanctum::actingAs($owner);
    test()->postJson("/api/v1/sos/{$report->ulid}/cancel")->assertUnprocessable()->assertJsonPath('code', 'not_sos');
    expect($report->fresh()?->isCancelled())->toBeFalse();
});

it('arrête le sondage après sos_poll_minutes (poll_interval_seconds null)', function (): void {
    config(['xomiton.alerts.sos_poll_minutes' => 30]);
    $created = postSos()->assertCreated();
    $id = $created->json('data.id');
    $headers = sosHeaders($created->json('data.sos_token'));

    $this->travel(29)->minutes();
    test()->getJson("/api/v1/sos/{$id}", $headers)->assertOk()->assertJsonPath('meta.poll_interval_seconds', 10);

    $this->travel(2)->minutes();
    test()->getJson("/api/v1/sos/{$id}", $headers)->assertOk()
        ->assertJsonPath('data.status', 'nouveau')
        ->assertJsonPath('meta.poll_interval_seconds', null);
});

it('répond 202 processing, jamais 429, quand le même SOS est encore en cours d’envoi', function (): void {
    config(['xomiton.idempotency.wait_seconds' => 0]);
    $key = (string) Str::uuid();

    postSos([], $key)->assertCreated();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null]);

    postSos([], $key)->assertStatus(202)
        ->assertHeader('Retry-After')
        ->assertJsonPath('data.status', 'processing');

    expect(Alert::query()->count())->toBe(1);
});

it('exige le jeton XSRF sur le chemin stateful des pages du site (419 sans)', function (): void {
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    test()->withHeaders(['Referer' => config('app.url').'/sos']);
    postSos()->assertStatus(419)->assertJsonPath('code', 'session_expired');

    expect(Alert::query()->count())->toBe(0);
});
