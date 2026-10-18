<?php

declare(strict_types=1);

use App\Application\Alerts\Data\CallbackData;
use App\Application\Alerts\RequestCallback;
use App\Application\Alerts\RouteAlert;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Exceptions\ContactUnavailable;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\DirectoryRole;
use App\Enums\RoutingFallback;
use App\Http\Requests\Alerts\RequestCallbackRequest;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\DirectoryContact;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;
use Tests\Support\PrototypeDirectory;

/*
 * P5-02 : RequestCallback (annuaire.php l.9-27) — alerte besoin `source = annuaire` confiée au contact choisi sans
 * RG-04 (`manual`), besoin déduit du rôle, message par défaut, pseudo non transmis par défaut ; RequestCallbackRequest.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

function callback(DirectoryContact $contact, array $overrides = []): App\Application\Alerts\Results\CreatedAlert
{
    return app(RequestCallback::class)(new CallbackData(...[
        'contactId' => $contact->id,
        'userId' => AlertFixtures::member()->id,
        'contact' => '+229 01 97 38 88 47',
        'idempotencyKey' => (string) Str::uuid(),
        ...$overrides,
    ]));
}

it('crée une demande de rappel routée vers le contact choisi, sans passer par RG-04', function (): void {
    Event::fake([AlertCreated::class]);

    $zone = PrototypeDirectory::zone('Littoral');
    $commune = Commune::query()->where('zone_id', $zone->id)->first();
    $juriste = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($zone, $commune)->create();
    $social = DirectoryContact::factory()->role(DirectoryRole::TravailleurSocial)->inZone($zone)->create();
    // RG-04 aurait choisi ce point focal (premier niveau pourvu) : la demande de rappel ne l'appelle pas.
    $focal = DirectoryContact::factory()->focalZone($zone)->create();
    expect(app(RouteAlert::class)($zone->id, ['autre'])->contactId)->toBe($focal->id);

    $alert = callback($juriste)->alert;
    $other = callback($social, ['message' => '  Merci de me rappeler le soir.  ', 'contact' => 'Moi@Exemple.bj'])->alert;

    expect($alert->type)->toBe(AlertType::Need)
        ->and($alert->source)->toBe(AlertSource::Directory)
        ->and($alert->routed_to)->toBe($juriste->id)
        ->and($alert->routing_fallback)->toBe(RoutingFallback::Manual)
        ->and($alert->zone_id)->toBe($zone->id)
        ->and($alert->commune_id)->toBe($commune?->id)
        ->and($alert->needs)->toBe(['soutien_juridique'])
        ->and($alert->description)->toBe('Je souhaite être rappelé·e.')
        ->and($alert->preferred_channel)->toBe(ContactChannel::Call)
        ->and($alert->is_anonymous)->toBeTrue()
        ->and($alert->pseudo)->toBeNull()
        ->and($alert->user_id)->toBeNull()
        ->and($other->needs)->toBe(['autre'])
        ->and($other->description)->toBe('Merci de me rappeler le soir.')
        ->and($other->contact)->toBe('moi@exemple.bj')
        ->and($other->preferred_channel)->toBe(ContactChannel::Email);

    Event::assertDispatchedTimes(AlertCreated::class, 2);
    expect(AuditLog::query()->ofAction('alerts.callback_requested')->forSubject($alert)->sole())
        ->ip->toBeNull()->user_id->toBeNull()->detail->toContain('vers=#'.$juriste->id);
});

it('transmet le pseudo seulement si la personne le souhaite', function (): void {
    $member = AlertFixtures::member();
    $psy = DirectoryContact::factory()->role(DirectoryRole::Psychologue)->create();

    $alert = callback($psy, ['userId' => $member->id, 'anonymous' => false])->alert;

    expect($alert->is_anonymous)->toBeFalse()
        ->and($alert->user_id)->toBe($member->id)
        ->and($alert->pseudo)->toBe($member->pseudo)
        ->and(AuditLog::query()->ofAction('alerts.callback_requested')->forSubject($alert)->value('user_id'))->toBe($member->id);
});

it('refuse un contact inactif, masqué ou supprimé', function (string $state): void {
    $contact = match ($state) {
        'inactif' => DirectoryContact::factory()->inactive()->create(),
        'masqué' => DirectoryContact::factory()->hidden()->create(),
        default => tap(DirectoryContact::factory()->create(), fn (DirectoryContact $c) => $c->delete()),
    };

    expect(fn () => callback($contact))->toThrow(ContactUnavailable::class);
})->with(['inactif', 'masqué', 'supprimé']);

it('valide la demande : contact obligatoire, message de 1000 caractères au plus, membre avec directory.view', function (): void {
    Route::post('/_test/rappel', fn (RequestCallbackRequest $request) => response()->json(['anonymous' => $request->toData(1)->anonymous]))->middleware('api');
    $member = AlertFixtures::member();
    $headers = fn () => ['Idempotency-Key' => (string) Str::uuid()];

    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => '0197388847'])->assertForbidden();

    $this->actingAs($member);
    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => '0197388847'])->assertOk()->assertJson(['anonymous' => true]);
    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => '0197388847', 'anonymous' => false])->assertOk()->assertJson(['anonymous' => false]);
    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => ''])->assertJsonValidationErrors('contact');
    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => '0197388847', 'message' => str_repeat('a', 1001)])->assertJsonValidationErrors('message');
    $this->withHeaders($headers())->postJson('/_test/rappel', ['contact' => '0197388847', 'preferred_channel' => 'pigeon'])->assertJsonValidationErrors('preferred_channel');
});
