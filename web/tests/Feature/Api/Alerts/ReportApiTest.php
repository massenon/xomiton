<?php

declare(strict_types=1);

use App\Application\Shared\MediaUploader;
use App\Domain\Alerts\Events\AlertCreated;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\AlertAttachment;
use App\Models\IdempotencyKey;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;

/*
 * P5-06 : API des signalements — POST signalements (anonyme, 201, Idempotency-Key obligatoire, rejeu → même 201,
 * limiteur `report` sans compter les rejeux), GET signalements/mine (Sanctum, non anonymes, {data, meta, links}),
 * GET signalements/{code} (sans compte, réponse neutre, limiteur `tracking`).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reportPayload(array $overrides = []): array
{
    return [
        'type' => 'report',
        'category' => 'physique',
        'description' => AlertFixtures::DESCRIPTION,
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        ...$overrides,
    ];
}

function postReport(array $payload, ?string $key = null): Illuminate\Testing\TestResponse
{
    return test()->withHeader(HandleIdempotencyKey::HEADER, $key ?? (string) Str::uuid())->postJson('/api/v1/signalements', $payload);
}

it('crée un signalement anonyme : 201, code de suivi, statut, Location, sans compte', function (): void {
    $response = postReport(reportPayload())->assertCreated();
    $alert = Alert::query()->sole();

    $response->assertHeader('Location', route('api.signalements.show', ['code' => $alert->tracking_code]))
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.id', $alert->ulid)
        ->assertJsonPath('data.tracking_code', $alert->tracking_code)
        ->assertJsonPath('data.tracking_code_display', 'SIG-'.$alert->tracking_code)
        ->assertJsonPath('data.type', 'report')
        ->assertJsonPath('data.status', AlertStatus::New->value)
        ->assertJsonPath('data.status_text', __('alerts.report.status_text.nouveau'))
        ->assertJsonMissingPath('data.sos_token')
        ->assertJsonMissingPath('data.description')
        ->assertJsonMissingPath('data.contact');

    expect($alert->is_anonymous)->toBeTrue()
        ->and($alert->user_id)->toBeNull()
        ->and($alert->type)->toBe(AlertType::Report)
        ->and($alert->source)->toBe(AlertSource::App)
        ->and(array_keys($response->json()))->toBe(['data']);
});

it('rejoue la même clé avec le même 201 et refuse un corps différent (ADR-32)', function (): void {
    $key = (string) Str::uuid();

    $first = postReport(reportPayload(), $key)->assertCreated();
    $again = postReport(reportPayload(), $key)->assertCreated()->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($again->json())->toBe($first->json())
        ->and($again->headers->get('Location'))->toBe($first->headers->get('Location'))
        ->and(Alert::query()->count())->toBe(1);

    postReport(reportPayload(['description' => 'Une autre histoire, envoyée avec la même clé.']), $key)
        ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_reused');

    expect(Alert::query()->count())->toBe(1);
});

it('répond 422 au format de l’API : charge invalide, clé absente ou mal formée', function (): void {
    postReport(reportPayload(['description' => 'trop court', 'zone_id' => null]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['description', 'zone_id']);

    postReport(reportPayload(['type' => 'need', 'category' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['needs']);

    test()->flushHeaders()->postJson('/api/v1/signalements', reportPayload())
        ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_required');

    postReport(reportPayload(), 'pas-un-uuid')->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_invalid');

    expect(Alert::query()->count())->toBe(0)->and(IdempotencyKey::query()->count())->toBe(0);
});

it('limite les signalements neufs par IP (429) sans compter les rejeux', function (): void {
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 2]);
    $key = (string) Str::uuid();

    postReport(reportPayload(), $key)->assertCreated();
    postReport(reportPayload(), $key)->assertCreated();
    postReport(reportPayload(), $key)->assertCreated();
    postReport(reportPayload())->assertCreated();

    postReport(reportPayload())
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');

    // Un rejeu reste servi au-delà du quota : la boîte d'envoi récupère sa réponse d'origine.
    postReport(reportPayload(), $key)->assertCreated();

    expect(Alert::query()->count())->toBe(2);
});

it('stocke les preuves sur le disque privé et rattache un signalement non anonyme au compte connecté', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $member = AlertFixtures::member();
    Sanctum::actingAs($member);
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    test()->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), 'Accept' => 'application/json'])
        ->post('/api/v1/signalements', reportPayload([
            'anonymous' => '0',
            'contactable' => '1',
            'contact' => '+229 01 97 00 00 01',
            'preferred_channel' => 'whatsapp',
            'attachments' => [UploadedFile::fake()->createWithContent('certificat.pdf', $pdf)],
        ]))
        ->assertCreated();

    $alert = Alert::query()->sole();
    $attachment = AlertAttachment::query()->sole();

    expect($alert->user_id)->toBe($member->id)
        ->and($alert->is_anonymous)->toBeFalse()
        ->and($attachment->alert_id)->toBe($alert->id)
        ->and($attachment->disk)->toBe(MediaUploader::privateDisk())
        ->and($attachment->kind)->toBe('documents');

    Storage::disk(MediaUploader::privateDisk())->assertExists($attachment->path);
});

it('ne laisse aucun fichier orphelin quand la création échoue', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    $pdf = "%PDF-1.4\n%%EOF\n";
    // Clé déjà prise par un SOS : CreateReport lève IdempotencyKeyConflict (409) après le téléversement.
    $key = (string) Str::uuid();
    Alert::factory()->sos()->create(['idempotency_key' => $key]);

    test()->withHeaders([HandleIdempotencyKey::HEADER => $key, 'Accept' => 'application/json'])
        ->post('/api/v1/signalements', reportPayload(['attachments' => [UploadedFile::fake()->createWithContent('preuve.pdf', $pdf)]]))
        ->assertStatus(409);

    expect(Storage::disk(MediaUploader::privateDisk())->allFiles())->toBe([])
        ->and(AlertAttachment::query()->count())->toBe(0);
});

it('retrouve les signalements non anonymes du compte connecté, paginés en { data, meta, links }', function (): void {
    $member = AlertFixtures::member();
    $other = AlertFixtures::member();

    Sanctum::actingAs($member);
    postReport(reportPayload(['anonymous' => false]))->assertCreated();
    postReport(reportPayload(['type' => 'need', 'category' => null, 'needs' => ['hebergement'], 'anonymous' => false]))->assertCreated();
    postReport(reportPayload(['anonymous' => true]))->assertCreated();
    app('auth')->forgetGuards();
    Sanctum::actingAs($other);
    postReport(reportPayload(['anonymous' => false]))->assertCreated();
    app('auth')->forgetGuards();

    $mine = Alert::query()->where('user_id', $member->id)->latestFirst()->pluck('tracking_code')->all();
    Sanctum::actingAs($member);

    $response = test()->getJson('/api/v1/signalements/mine?per_page=1')->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonStructure(['data' => [['id', 'tracking_code', 'type', 'status', 'created_at']], 'meta' => ['current_page', 'last_page', 'per_page', 'total'], 'links' => ['first', 'last', 'prev', 'next']])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.tracking_code', $mine[0]);

    expect($response->json('links.next'))->toContain('per_page=1')->toContain('page=2')
        ->and(count($mine))->toBe(2)
        ->and(Alert::query()->count())->toBe(4);

    test()->getJson('/api/v1/signalements/mine?type=need')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.type', 'need')
        ->assertJsonPath('data.0.history.0.status', AlertStatus::New->value);

    test()->getJson('/api/v1/signalements/mine?type=autre')->assertUnprocessable()->assertJsonValidationErrors('type');
});

it('exige un compte pour « mes signalements » (401), sans confondre « mine » avec un code de suivi', function (): void {
    test()->getJson('/api/v1/signalements/mine')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('suit un signalement par code sans compte, avec une réponse neutre pour un code inconnu', function (): void {
    $code = postReport(reportPayload())->assertCreated()->json('data.tracking_code');

    test()->getJson('/api/v1/signalements/'.strtolower('sig-'.$code))->assertOk()
        ->assertJsonPath('data.tracking_code_display', 'SIG-'.$code)
        ->assertJsonPath('data.status', 'nouveau')
        ->assertJsonPath('data.history.0.status', 'nouveau')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.tracking_code');

    $unknown = test()->getJson('/api/v1/signalements/SIG-AAAAAAAAAA')->assertNotFound()->assertJsonPath('code', 'not_found');
    $malformed = test()->getJson('/api/v1/signalements/n-importe-quoi')->assertNotFound();
    Alert::query()->update(['cancelled_at' => now()]);
    $cancelled = test()->getJson('/api/v1/signalements/'.$code)->assertNotFound();

    expect($unknown->json('message'))->toBe(__('alerts.report.tracking.not_found', ['days' => 90]))
        ->and($malformed->json())->toBe($unknown->json())
        ->and($cancelled->json())->toBe($unknown->json());
});

it('limite le suivi par code à 20 requêtes par heure et par IP', function (): void {
    config(['xomiton.rate_limits.tracking_per_hour_per_ip' => 2]);

    test()->getJson('/api/v1/signalements/SIG-AAAAAAAAAA')->assertNotFound();
    test()->getJson('/api/v1/signalements/SIG-AAAAAAAAAB')->assertNotFound();
    test()->getJson('/api/v1/signalements/SIG-AAAAAAAAAC')->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests');
});

it('reconnaît un envoi des pages du site comme source web', function (): void {
    test()->withHeader('Referer', config('app.url').'/signaler');
    postReport(reportPayload())->assertCreated();

    expect(Alert::query()->sole()->source)->toBe(AlertSource::Web)
        ->and(User::query()->count())->toBe(0);
});

it('garde les preuves d’une alerte enregistrée quand une erreur survient après le commit', function (): void {
    Storage::fake(MediaUploader::privateDisk());
    Event::listen(AlertCreated::class, static fn (): never => throw new RuntimeException('écouteur en échec'));
    $pdf = "%PDF-1.4\n%%EOF\n";

    test()->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid(), 'Accept' => 'application/json'])
        ->post('/api/v1/signalements', reportPayload(['attachments' => [UploadedFile::fake()->createWithContent('preuve.pdf', $pdf)]]))
        ->assertServerError();

    $attachment = AlertAttachment::query()->sole();

    expect(Alert::query()->count())->toBe(1);
    Storage::disk(MediaUploader::privateDisk())->assertExists($attachment->path);
});

it('exige le jeton XSRF sur le chemin stateful des pages du site (419 sans)', function (): void {
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    test()->withHeader('Referer', config('app.url').'/signaler');
    postReport(reportPayload())->assertStatus(419)->assertJsonPath('code', 'session_expired');

    expect(Alert::query()->count())->toBe(0);
});
