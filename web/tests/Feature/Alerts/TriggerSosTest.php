<?php

declare(strict_types=1);

use App\Application\Alerts\CreateReport;
use App\Application\Alerts\TriggerSos;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\SosTriggered;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;
use App\Http\Requests\Alerts\StoreSosRequest;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\DirectoryContact;
use App\Models\SyncDevice;
use App\Models\UserSetting;
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
 * P5-02 : TriggerSos — critique, besoin `securite`, position chiffrée gardée seulement si partagée, routage RG-04
 * (sans département : point focal national), un seul événement SosTriggered, choix de partage mémorisé, idempotence.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

it('crée un SOS critique avec sa position chiffrée et n’émet que SosTriggered', function (): void {
    Event::fake([SosTriggered::class, AlertCreated::class]);

    $result = app(TriggerSos::class)(AlertFixtures::sos([
        'lat' => 6.36536, 'lng' => 2.41833, 'accuracy' => 18, 'shareLocation' => true,
        'needs' => ['soutien_medical', 'securite'], 'message' => '  Je suis devant le marché.  ', 'phone' => '0197388847',
    ]));
    $alert = $result->alert->fresh();
    $raw = DB::table('alerts')->where('id', $alert->id)->first();

    expect($alert->type)->toBe(AlertType::Sos)
        ->and($alert->priority)->toBe(AlertPriority::Critical)
        ->and($alert->status)->toBe(AlertStatus::New)
        ->and($alert->needs)->toBe(['securite', 'soutien_medical'])
        ->and($alert->coordinates())->toBe(['lat' => 6.36536, 'lng' => 2.41833])
        ->and($alert->location_accuracy)->toBe(18)
        ->and($raw->lat)->not->toContain('6.365')
        ->and($alert->description)->toBe('Je suis devant le marché.')
        ->and($alert->contact)->toBe('+229 01 97 38 88 47')
        ->and($alert->preferred_channel)->toBe(ContactChannel::Call)
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($alert->category)->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->count())->toBe(1);

    Event::assertDispatched(SosTriggered::class, fn (SosTriggered $event): bool => $event->alertId === $alert->id && $event->hasLocation && $event->isAnonymous());
    Event::assertNotDispatched(AlertCreated::class);
});

it('ignore la position quand la personne refuse de la partager et mémorise son choix', function (): void {
    $member = AlertFixtures::member();

    $alert = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $member->id, 'lat' => 6.3, 'lng' => 2.4, 'accuracy' => 30, 'shareLocation' => false]))->alert;
    $visitor = app(TriggerSos::class)(AlertFixtures::sos(['lat' => 6.3, 'lng' => 2.4]))->alert;

    expect($alert->lat)->toBeNull()
        ->and($alert->lng)->toBeNull()
        ->and($alert->location_accuracy)->toBeNull()
        ->and($alert->is_anonymous)->toBeFalse()
        ->and($alert->user_id)->toBe($member->id)
        ->and(UserSetting::query()->where('user_id', $member->id)->value('sos_share_gps'))->toBeFalse()
        ->and($visitor->is_anonymous)->toBeTrue()
        ->and($visitor->user_id)->toBeNull()
        ->and($visitor->hasLocation())->toBeTrue();
});

it('route un SOS sans département au point focal national et un SOS localisé au point focal du département', function (): void {
    $borgou = PrototypeDirectory::zone('Borgou');
    $national = DirectoryContact::factory()->focalNational()->create();
    $zoneFocal = DirectoryContact::factory()->focalZone($borgou)->create();

    $gpsOnly = app(TriggerSos::class)(AlertFixtures::sos(['lat' => 9.33, 'lng' => 2.63]))->alert;
    $located = app(TriggerSos::class)(AlertFixtures::sos(['zoneId' => $borgou->id]))->alert;

    expect($gpsOnly->routed_to)->toBe($national->id)
        ->and($gpsOnly->routing_fallback)->toBe(RoutingFallback::FocalNational)
        ->and($located->routed_to)->toBe($zoneFocal->id)
        ->and($located->routing_fallback)->toBe(RoutingFallback::FocalZone);
});

it('renvoie le SOS d’origine au rejeu de la même clé', function (): void {
    Event::fake([SosTriggered::class]);
    $key = (string) Str::uuid();

    $first = app(TriggerSos::class)(AlertFixtures::sos(['idempotencyKey' => $key]));
    $again = app(TriggerSos::class)(AlertFixtures::sos(['idempotencyKey' => $key, 'message' => 'autre']));

    expect($again->duplicate)->toBeTrue()
        ->and($again->alert->tracking_code)->toBe($first->alert->tracking_code)
        ->and(Alert::query()->count())->toBe(1);

    Event::assertDispatchedTimes(SosTriggered::class, 1);
});

it('crée quand même le SOS dont la clé appartient déjà à un signalement, puis dédoublonne ses rejeux', function (): void {
    Event::fake([SosTriggered::class]);
    $key = (string) Str::uuid();
    $report = app(CreateReport::class)(AlertFixtures::report(['idempotencyKey' => $key]))->alert;

    $sos = app(TriggerSos::class)(AlertFixtures::sos(['idempotencyKey' => $key]));
    $again = app(TriggerSos::class)(AlertFixtures::sos(['idempotencyKey' => $key]));

    expect($sos->duplicate)->toBeFalse()
        ->and($sos->alert->type)->toBe(AlertType::Sos)
        ->and($sos->alert->is($report))->toBeFalse()
        ->and($again->duplicate)->toBeTrue()
        ->and($again->alert->is($sos->alert))->toBeTrue()
        ->and($again->sosToken)->toBe($sos->sosToken)
        ->and(Alert::query()->sos()->count())->toBe(1)
        ->and($report->fresh()->type)->toBe(AlertType::Report);

    Event::assertDispatchedTimes(SosTriggered::class, 1);
});

it('n’enregistre l’appareil que d’un SOS rattaché à un compte et ne rend un jeton qu’au SOS anonyme', function (): void {
    $member = AlertFixtures::member();
    $device = SyncDevice::factory()->create(['user_id' => $member->id]);

    $anonymous = app(TriggerSos::class)(AlertFixtures::sos(['deviceId' => $device->id]));
    $identified = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $member->id, 'deviceId' => $device->id]));

    expect(DB::table('alerts')->where('id', $anonymous->alert->id)->value('device_id'))->toBeNull()
        ->and($anonymous->sosToken)->toBeString()->toHaveLength(64)
        ->and($identified->alert->fresh()->device_id)->toBe($device->id)
        ->and($identified->sosToken)->toBeNull();
});

it('valide un SOS sans aucun champ hormis la clé, et refuse un téléphone non béninois', function (): void {
    Route::post('/_test/sos', fn (StoreSosRequest $request) => response()->json(['share' => $request->toData()->shareLocation]))->middleware('api');

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/sos', [])->assertOk()->assertJson(['share' => null]);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/sos', ['share_location' => '0'])->assertOk()->assertJson(['share' => false]);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/sos', ['phone' => '+33 6 12 34 56 78'])->assertJsonValidationErrors('phone');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/sos', ['lat' => 6.3])->assertJsonValidationErrors('lng');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/_test/sos', ['message' => str_repeat('a', 1001)])->assertJsonValidationErrors('message');
    $this->flushHeaders()->postJson('/_test/sos', [])->assertJsonValidationErrors('idempotency_key');
});
