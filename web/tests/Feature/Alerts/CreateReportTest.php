<?php

declare(strict_types=1);

use App\Application\Alerts\CreateReport;
use App\Application\Alerts\TriggerSos;
use App\Application\Shared\StoredMedia;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Exceptions\IdempotencyKeyConflict;
use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;
use App\Http\Requests\Alerts\StoreReportRequest;
use App\Models\Alert;
use App\Models\AlertAttachment;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\SyncDevice;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;
use Tests\Support\PrototypeDirectory;

/*
 * P5-02 : CreateReport — anonymat RG-01 / RG-02, historique, code de suivi, idempotence ADR-32, routage RG-04 sur
 * l'annuaire du prototype, contact découplé de l'anonymat, pièces jointes, journal sans IP ; StoreReportRequest.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

function createReport(array $overrides = []): App\Application\Alerts\Results\CreatedAlert
{
    return app(CreateReport::class)(AlertFixtures::report($overrides));
}

it('crée un signalement anonyme, son historique et son code de suivi', function (): void {
    $result = createReport();
    $alert = $result->alert->fresh();

    expect($result->duplicate)->toBeFalse()
        ->and($alert->type)->toBe(AlertType::Report)
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($alert->user_id)->toBeNull()
        ->and($alert->pseudo)->toBeNull()
        ->and($alert->status)->toBe(AlertStatus::New)
        ->and($alert->priority)->toBe(AlertPriority::Normal)
        ->and($alert->source)->toBe(AlertSource::Web)
        ->and($alert->tracking_code)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/')
        ->and($alert->displayCode())->toBe('SIG-'.$alert->tracking_code)
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->get(['from_status', 'to_status', 'user_id'])->toArray())
        ->toBe([['from_status' => null, 'to_status' => 'nouveau', 'user_id' => null]]);
});

it('renvoie l’alerte d’origine au rejeu de la même clé, sans nouvelle ligne ni nouvel événement', function (): void {
    Event::fake([AlertCreated::class]);
    $key = (string) Str::uuid();

    $first = createReport(['idempotencyKey' => $key]);
    $again = createReport(['idempotencyKey' => $key]);
    $other = createReport(['idempotencyKey' => $key, 'description' => 'Une tout autre histoire, mais même clé d’envoi.', 'needs' => ['hebergement']]);

    expect($again->duplicate)->toBeTrue()
        ->and($other->duplicate)->toBeTrue()
        ->and($again->alert->tracking_code)->toBe($first->alert->tracking_code)
        ->and($other->alert->is($first->alert))->toBeTrue()
        ->and($other->alert->type)->toBe(AlertType::Report)
        ->and($other->alert->description)->toBe(AlertFixtures::DESCRIPTION)
        ->and(Alert::query()->count())->toBe(1)
        ->and(AlertStatusChange::query()->count())->toBe(1);

    Event::assertDispatchedTimes(AlertCreated::class, 1);
});

it('refuse en 409 une clé déjà prise par une alerte d’un autre type, comme le middleware idempotent', function (): void {
    $key = (string) Str::uuid();
    $sos = app(TriggerSos::class)(AlertFixtures::sos(['idempotencyKey' => $key]))->alert;

    expect(fn () => createReport(['idempotencyKey' => $key]))->toThrow(IdempotencyKeyConflict::class);

    createReport(['idempotencyKey' => $other = (string) Str::uuid()]);

    try {
        createReport(['idempotencyKey' => $other, 'type' => AlertType::Need, 'needs' => ['hebergement']]);
        $this->fail('Conflit attendu.');
    } catch (IdempotencyKeyConflict $conflict) {
        expect($conflict->status())->toBe(409)->and($conflict->errorCode())->toBe('idempotency_key_reused');
    }

    expect(Alert::query()->count())->toBe(2)
        ->and($sos->fresh()->type)->toBe(AlertType::Sos);
});

it('n’enregistre jamais l’appareil d’une alerte anonyme, seulement celui d’un compte identifié', function (): void {
    $member = AlertFixtures::member();
    $device = SyncDevice::factory()->create(['user_id' => $member->id]);

    $anonymous = createReport(['userId' => $member->id, 'anonymous' => true, 'deviceId' => $device->id])->alert;
    $guest = createReport(['deviceId' => $device->id])->alert;
    $identified = createReport(['userId' => $member->id, 'anonymous' => false, 'deviceId' => $device->id])->alert;

    expect(DB::table('alerts')->where('id', $anonymous->id)->value('device_id'))->toBeNull()
        ->and(DB::table('alerts')->where('id', $guest->id)->value('device_id'))->toBeNull()
        ->and($identified->fresh()->device_id)->toBe($device->id);
});

it('refuse une preuve hors du disque privé et un nombre de preuves au-delà du maximum, sans rien créer', function (): void {
    $public = new StoredMedia('public', 'images/2026/10/01jabc.webp', 'image/webp', 1200, 800, 600, 'photo.jpg');
    $private = new StoredMedia('local', 'images/2026/10/01jabd.webp', 'image/webp', 1200, 800, 600, 'photo.jpg');
    config(['xomiton.alerts.attachments_max' => 2]);

    expect(fn () => createReport(['attachments' => [$public]]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => createReport(['attachments' => [$private, $private, $private]]))->toThrow(InvalidArgumentException::class)
        ->and(Alert::query()->count())->toBe(0)
        ->and(AlertAttachment::query()->count())->toBe(0);
});

it('route sur l’annuaire du prototype comme route_alert()', function (array $needs, ?string $zone, ?int $expected, ?string $fallback, ?Closure $prepare = null): void {
    $directory = PrototypeDirectory::create();

    if ($prepare !== null) {
        $prepare($directory);
    }

    $alert = createReport(['needs' => $needs, 'zoneId' => $zone === null ? null : PrototypeDirectory::zone($zone)->id])->alert;

    expect($alert->routed_to)->toBe($expected)
        ->and($alert->routing_fallback?->value)->toBe($fallback);
})->with([
    'R1 juriste du Littoral' => [['soutien_juridique'], 'Littoral', 2, 'role_zone'],
    'psychologue du Zou' => [['soutien_psychologique'], 'Zou', 8, 'role_zone'],
    'point focal de l’Ouémé' => [['hebergement'], 'Ouémé', 4, 'focal_zone'],
    'R5 juriste inactive → premier point focal' => [['soutien_juridique'], 'Littoral', 4, 'focal_national', fn (array $d) => $d[2]->update(['is_active' => false])],
    'R10 point focal national' => [['soutien_medical'], 'Zou', 11, 'focal_national', fn () => DirectoryContact::factory()->focalNational()->create(['id' => 11])],
    'R12 sans point focal → premier actif' => [['soutien_medical'], 'Zou', 1, 'first_active', fn (array $d) => DirectoryContact::query()->whereIn('id', [4, 5])->delete()],
    'R13 annuaire inactif' => [['soutien_juridique'], 'Littoral', null, null, fn () => DirectoryContact::query()->update(['is_active' => false])],
]);

it('valide la charge utile : besoins exigés pour un besoin, faits passés, récit de 20 à 4000 caractères', function (array $payload, ?string $error): void {
    Route::post('/_test/signalements', fn (StoreReportRequest $request) => response()->json(['type' => $request->toData()->type->value]))->middleware('api');

    $base = [
        'type' => 'report',
        'category' => 'physique',
        'description' => AlertFixtures::DESCRIPTION,
        'zone_id' => PrototypeDirectory::zone('Littoral')->id,
    ];

    $response = $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/signalements', [...$base, ...$payload]);

    $error === null ? $response->assertOk() : $response->assertJsonValidationErrors($error);
})->with([
    'besoin sans besoins' => [['type' => 'need', 'needs' => []], 'needs'],
    'besoin avec besoins' => [['type' => 'need', 'needs' => ['hebergement']], null],
    'signalement sans besoins' => [[], null],
    'signalement sans type de violence' => [['category' => null], 'category'],
    'faits de demain' => [['occurred_on' => now()->addDay()->toDateString()], 'occurred_on'],
    'faits d’hier' => [['occurred_on' => now()->subDay()->toDateString()], null],
    'récit de 19 caractères' => [['description' => str_repeat('a', 19)], 'description'],
    'récit de 4001 caractères' => [['description' => str_repeat('a', 4001)], 'description'],
    'contactable sans contact' => [['contactable' => true, 'preferred_channel' => 'sms'], 'contact'],
    'contact invalide' => [['contactable' => true, 'contact' => 'pas un numéro', 'preferred_channel' => 'sms'], 'contact'],
    'contact ignoré si non contactable' => [['contactable' => false, 'contact' => 'pas un numéro'], null],
    'besoin inconnu' => [['needs' => ['inconnu']], 'needs.0'],
    'type sos refusé' => [['type' => 'sos'], 'type'],
]);

it('exige une clé d’idempotence UUID', function (): void {
    Route::post('/_test/signalements', fn (StoreReportRequest $request) => response()->json(['ok' => true]))->middleware('api');

    $this->postJson('/_test/signalements', ['type' => 'report'])->assertJsonValidationErrors('idempotency_key');
    $this->flushHeaders()->withHeader('Idempotency-Key', 'pas-un-uuid')->postJson('/_test/signalements', ['type' => 'report'])->assertJsonValidationErrors('idempotency_key');
});

it('relie un signalement au compte seulement si le membre ne reste pas anonyme', function (): void {
    $member = AlertFixtures::member();

    $identified = createReport(['userId' => $member->id, 'anonymous' => false])->alert;
    $anonymous = createReport(['userId' => $member->id, 'anonymous' => true])->alert;

    expect($identified->user_id)->toBe($member->id)
        ->and($identified->pseudo)->toBe($member->pseudo)
        ->and($identified->is_anonymous)->toBeFalse()
        ->and($anonymous->user_id)->toBeNull()
        ->and($anonymous->pseudo)->toBeNull()
        ->and($anonymous->is_anonymous)->toBeTrue();
});

it('garde un contact chiffré même sans compte quand la personne accepte d’être recontactée', function (): void {
    $contactable = createReport(['contactable' => true, 'contact' => '01 97 38 88 47', 'preferredChannel' => ContactChannel::Whatsapp])->alert;
    $silent = createReport(['contactable' => false, 'contact' => '+229 01 97 38 88 47'])->alert;
    $guessed = createReport(['contactable' => true, 'contact' => 'Nom@Exemple.bj'])->alert;

    expect(DB::table('alerts')->where('id', $contactable->id)->value('contact'))->not->toContain('97 38')
        ->and($contactable->fresh()->contact)->toBe('+229 01 97 38 88 47')
        ->and($contactable->preferred_channel)->toBe(ContactChannel::Whatsapp)
        ->and($contactable->is_anonymous)->toBeTrue()
        ->and($silent->contact)->toBeNull()
        ->and($silent->preferred_channel)->toBeNull()
        ->and($guessed->fresh()->contact)->toBe('nom@exemple.bj')
        ->and($guessed->preferred_channel)->toBe(ContactChannel::Email);
});

it('enregistre les preuves sur le disque privé, sans nom d’origine, et n’en ajoute pas au rejeu', function (): void {
    $key = (string) Str::uuid();
    $media = [
        new StoredMedia('local', 'images/2026/10/01jabc.webp', 'image/webp', 1200, 800, 600, 'photo-de-moi.jpg'),
        new StoredMedia('local', 'documents/2026/10/01jdef.pdf', 'application/pdf', 5400, null, null, 'certificat-medical.pdf'),
    ];

    $alert = createReport(['idempotencyKey' => $key, 'attachments' => $media])->alert;
    createReport(['idempotencyKey' => $key, 'attachments' => $media]);

    $rows = AlertAttachment::query()->where('alert_id', $alert->id)->orderBy('id')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('kind')->all())->toBe(['images', 'documents'])
        ->and($rows->pluck('disk')->unique()->all())->toBe(['local'])
        ->and(json_encode(DB::table('alert_attachments')->get()))->not->toContain('photo-de-moi')->not->toContain('certificat')
        ->and($rows->first()->toArray())->not->toHaveKeys(['disk', 'path'])
        ->and($alert->attachments()->count())->toBe(2);
});

it('journalise la création anonyme sans IP, sans compte et sans le récit', function (): void {
    $member = AlertFixtures::member();
    $this->actingAs($member);

    $alert = createReport(['userId' => $member->id, 'anonymous' => true])->alert;
    $log = AuditLog::query()->ofAction('alerts.created')->forSubject($alert)->sole();

    expect($log->ip)->toBeNull()
        ->and($log->user_agent)->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->actor)->toBe(AuditLog::ACTOR_ANONYMOUS)
        ->and($log->detail)->not->toContain(AlertFixtures::DESCRIPTION)
        ->and($log->detail)->toContain('type=denonciation');
});

it('n’accepte jamais un SOS dans ReportData', function (): void {
    expect(fn () => AlertFixtures::report(['type' => AlertType::Sos]))->toThrow(InvalidArgumentException::class);
});

it('route vers le contact par défaut et l’annonce au contact routé et à l’admin de garde', function (): void {
    Event::fake([AlertCreated::class]);
    $juriste = DirectoryContact::factory()->role(App\Enums\DirectoryRole::Juriste)->inZone(PrototypeDirectory::zone('Littoral'))->create();

    $result = createReport(['needs' => ['soutien_juridique']]);

    expect($result->routedContact?->is($juriste))->toBeTrue()
        ->and($result->routing?->fallback)->toBe(RoutingFallback::RoleZone)
        ->and($result->routedPayload())->toBe(['role' => 'Juriste', 'name' => $juriste->name, 'phone' => $juriste->phone, 'whatsapp' => $juriste->whatsapp]);

    $juriste->update(['is_public' => false]);

    expect((new App\Application\Alerts\Results\CreatedAlert($result->alert, false, $juriste->fresh()))->routedPayload())->toBe(['role' => 'Juriste']);

    Event::assertDispatched(AlertCreated::class, fn (AlertCreated $event): bool => $event->alertId === $result->alert->id
        && $event->routedToContactId === $juriste->id && $event->routingFallback === 'role_zone' && $event->type === 'denonciation');
});
