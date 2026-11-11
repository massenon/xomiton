<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\DeliveryRunner;
use App\Application\Alerts\Dispatch\PlannedDelivery;
use App\Domain\Alerts\Dispatch\DeliveryBudget;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\PushMessage;
use App\Models\AlertNotification;
use App\Models\PushSubscription;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\Notifications\FakePoolablePushSender;
use Tests\Support\Notifications\FakePoolableSmsSender;
use Tests\Support\Notifications\FakeSmsSender;

/*
 * P5-03 (D7) : les pilotes HTTP « poolables » partent ensemble dans un Http::pool ; les autres en séquence bornée ;
 * une exception de pilote devient un échec au message masqué ; 404 / 410 supprime l'abonnement.
 */

function runner(PushSender&FcmSender $push, SmsSender $sms): DeliveryRunner
{
    app()->instance(PushSender::class, $push);
    app()->instance(FcmSender::class, $push);
    app()->instance(SmsSender::class, $sms);

    return app(DeliveryRunner::class);
}

function message(): PushMessage
{
    return new PushMessage('Alerte SOS', 'Une personne a besoin d’aide.', '/admin/alertes', ['type' => 'sos', 'id' => 1, 'url' => '/admin/alertes', 'category' => 'sos']);
}

function budget(): DeliveryBudget
{
    return DeliveryBudget::start(3.0, 8.0);
}

it('envoie les remises poolables en une seule passe Http::pool et interprète chaque réponse', function (): void {
    Http::fake([
        'push.test/*' => Http::response('', 201),
        'sms.test/*' => Http::response('', 500),
    ]);
    $subscriptions = PushSubscription::factory()->count(2)->create();
    $deliveries = [
        ...$subscriptions->map(fn (PushSubscription $s): PlannedDelivery => PlannedDelivery::push(AlertNotification::factory()->toSubscription($s)->create(), $s, message()))->all(),
        PlannedDelivery::sms(AlertNotification::factory()->create(), '+229 01 97 38 88 47', 'Xomiton – Alerte SOS'),
    ];

    $outcomes = runner(new FakePoolablePushSender, new FakePoolableSmsSender)->run($deliveries, budget());

    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://sms.test/send' && $request['to'] === '+229 01 97 38 88 47');
    expect($outcomes[0]->ok())->toBeTrue()
        ->and($outcomes[1]->ok())->toBeTrue()
        ->and($outcomes[2]->ok())->toBeFalse()
        ->and($outcomes[2]->error())->toBe('HTTP 500')
        ->and($outcomes[0]->provider)->toBe('log')
        ->and($subscriptions[0]->fresh()->last_used_at)->not->toBeNull();
});

it('supprime l’abonnement sur 410 et consigne une coupure réseau comme un échec', function (): void {
    $gone = PushSubscription::factory()->create();
    $down = PushSubscription::factory()->create();
    Http::fake([
        'push.test/'.$gone->id => Http::response('', 410),
        'push.test/'.$down->id => Http::failedConnection('timeout'),
    ]);

    $outcomes = runner(new FakePoolablePushSender, new FakeSmsSender)->run([
        PlannedDelivery::push(AlertNotification::factory()->toSubscription($gone)->create(), $gone, message()),
        PlannedDelivery::push(AlertNotification::factory()->toSubscription($down)->create(), $down, message()),
    ], budget());

    expect($outcomes[0]->gone())->toBeTrue()
        ->and(PushSubscription::query()->find($gone->id))->toBeNull()
        ->and($outcomes[1]->ok())->toBeFalse()
        ->and($outcomes[1]->error())->toBe('ConnectionException : timeout')
        ->and(PushSubscription::query()->find($down->id))->not->toBeNull();
});

it('envoie en séquence par send() une remise que le pilote ne sait pas pooler', function (): void {
    Http::fake();
    $push = (new FakePoolablePushSender)->poolable(false);
    $subscription = PushSubscription::factory()->create();

    $outcomes = runner($push, new FakeSmsSender)->run([PlannedDelivery::push(AlertNotification::factory()->toSubscription($subscription)->create(), $subscription, message())], budget());

    Http::assertNothingSent();
    expect($push->calls)->toHaveCount(1)->and($outcomes[0]->ok())->toBeTrue();
});

it('transforme l’exception d’un pilote en échec au message masqué', function (): void {
    $sms = (new FakeSmsSender)->willReturn(new RuntimeException('Numéro +229 01 97 38 88 47 refusé'));

    $outcomes = runner(new FakePoolablePushSender, $sms)->run([PlannedDelivery::sms(AlertNotification::factory()->create(), '+229 01 97 38 88 47', 'Texte')], budget());

    expect($outcomes[0]->attempted)->toBeTrue()
        ->and($outcomes[0]->ok())->toBeFalse()
        ->and($outcomes[0]->error())->toBe('RuntimeException : Numéro [masqué] refusé');
});

it('ne tente rien une fois le budget épuisé', function (): void {
    Http::fake();
    $now = 0.0;
    $budget = DeliveryBudget::start(3.0, 1.0, static function () use (&$now): float {
        return $now;
    });
    $now = 2.0;
    $subscription = PushSubscription::factory()->create();

    $outcomes = runner(new FakePoolablePushSender, new FakeSmsSender)->run([
        PlannedDelivery::push(AlertNotification::factory()->toSubscription($subscription)->create(), $subscription, message()),
        PlannedDelivery::sms(AlertNotification::factory()->create(), '+229 01 97 38 88 47', 'Texte'),
    ], $budget);

    Http::assertNothingSent();
    expect($outcomes[0]->attempted)->toBeFalse()->and($outcomes[1]->attempted)->toBeFalse();
});
