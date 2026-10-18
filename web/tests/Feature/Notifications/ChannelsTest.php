<?php

declare(strict_types=1);

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Infrastructure\Notifications\LogFcmSender;
use App\Infrastructure\Notifications\LogPushSender;
use App\Infrastructure\Notifications\LogSmsSender;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\InvitationReceived;
use App\Notifications\SosTriggered;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Log;

/*
 * P3-02, ADR-25, Q7 : canaux webpush / fcm / sms derrière les interfaces du domaine, pilotes `log` par défaut tant que les
 * paquets ne sont pas installés, repli sur `log` pour un pilote inconnu ou absent, abonnement disparu supprimé, canal mail
 * standard conservé pour une notification Laravel classique.
 */

/**
 * Pilote push factice : enregistre les remises et renvoie le résultat voulu.
 */
function fakePushSender(DeliveryResult $result): object
{
    return new class($result) implements PushSender, FcmSender
    {
        /** @var list<array{0: int, 1: PushMessage}> */
        public array $sent = [];

        public function __construct(private readonly DeliveryResult $result) {}

        public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
        {
            $this->sent[] = [(int) $subscription->getKey(), $message];

            return $this->result;
        }
    };
}

it('lie les pilotes log par défaut et retombe sur log pour un pilote inconnu ou non installé', function (): void {
    expect(app(PushSender::class))->toBeInstanceOf(LogPushSender::class)
        ->and(app(FcmSender::class))->toBeInstanceOf(LogFcmSender::class)
        ->and(app(SmsSender::class))->toBeInstanceOf(LogSmsSender::class);

    Log::spy();

    expect(NotificationDrivers::effective('webpush', 'vapid', NotificationDrivers::WEBPUSH))->toBe('log')
        ->and(NotificationDrivers::effective('fcm', 'firebase', NotificationDrivers::FCM))->toBe('log')
        ->and(NotificationDrivers::effective('sms', 'inconnu', NotificationDrivers::SMS))->toBe('log')
        ->and(NotificationDrivers::effective('sms', 'LOG', NotificationDrivers::SMS))->toBe('log');

    Log::shouldHaveReceived('warning')->times(3);
});

it('consigne la charge push et le SMS dans le journal avec les pilotes log, sans endpoint ni numéro en clair', function (): void {
    Log::spy();
    $user = User::factory()->withProfile()->create(['phone' => '+229 01 97 38 88 47']);
    $subscription = PushSubscription::factory()->for($user)->create();

    $user->notify(new InvitationReceived(1, 'Mariam A.'));
    // SMS de secours seulement sans abonnement push (P5-03) : SOS adressé à un compte sans abonnement.
    User::factory()->withProfile()->create(['phone' => '+229 01 97 38 88 47'])
        ->notify(new SosTriggered(1, 'CODE', 'Cotonou', 'Littoral'));

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => str_starts_with($message, '[webpush:log]')
        && $context['subscription'] === $subscription->endpoint_hash
        && ! str_contains(json_encode($context), $subscription->endpoint)
        && $context['payload']['data']['type'] === 'invitation')->once();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => str_starts_with($message, '[sms:log]')
        && $context['to'] === LogSmsSender::maskNumber('+229 01 97 38 88 47')
        && ! str_contains($context['to'], '229019738')
        && str_contains($context['text'], 'CODE'))->once();

    expect($subscription->fresh()->last_used_at)->not->toBeNull()
        ->and(LogSmsSender::maskNumber('+229 01 97 38 88 47'))->toBe('*********8847');
});

it('remet le push à chaque abonnement Web Push et FCM de la personne par le pilote du canal', function (): void {
    $user = User::factory()->withProfile()->create();
    PushSubscription::factory()->for($user)->count(2)->create();
    PushSubscription::factory()->for($user)->fcm()->create();

    $webPush = fakePushSender(DeliveryResult::sent());
    $fcm = fakePushSender(DeliveryResult::sent());
    $this->app->instance(PushSender::class, $webPush);
    $this->app->instance(FcmSender::class, $fcm);

    $user->notify(new InvitationReceived(1, 'Mariam A.'));

    expect($webPush->sent)->toHaveCount(2)
        ->and($fcm->sent)->toHaveCount(1)
        ->and($fcm->sent[0][1]->title)->toBe('Nouvelle invitation')
        ->and($fcm->sent[0][1]->data)->toBe(['type' => 'invitation', 'id' => 1, 'url' => route('qui-suivre'), 'category' => 'communaute'])
        ->and($user->pushSubscriptions()->whereNull('last_used_at')->count())->toBe(0);
});

it('supprime un abonnement disparu (404 / 410, jeton désinscrit) et journalise un échec rejouable sans le remonter', function (): void {
    Log::spy();
    $user = User::factory()->withProfile()->create();
    $gone = PushSubscription::factory()->for($user)->create();
    $this->app->instance(PushSender::class, fakePushSender(DeliveryResult::gone('410 Gone')));

    $user->notify(new InvitationReceived(1, 'Mariam A.'));

    expect(PushSubscription::query()->whereKey($gone->getKey())->exists())->toBeFalse()
        ->and($user->notifications()->count())->toBe(1);

    $kept = PushSubscription::factory()->for($user)->fcm()->create();
    $this->app->instance(FcmSender::class, fakePushSender(DeliveryResult::failed('timeout')));

    $user->notify(new InvitationReceived(2, 'Nadia'));

    expect(PushSubscription::query()->whereKey($kept->getKey())->exists())->toBeTrue()
        ->and($kept->fresh()->last_used_at)->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_starts_with($message, '[fcm]'))->once();
});

it('envoie le SMS de secours au numéro routé, borné à 160 caractères', function (): void {
    $sms = new class implements SmsSender
    {
        /** @var list<array{0: string, 1: string}> */
        public array $sent = [];

        public function send(string $to, string $text): DeliveryResult
        {
            $this->sent[] = [$to, $text];

            return DeliveryResult::sent();
        }
    };
    $this->app->instance(SmsSender::class, $sms);
    $user = User::factory()->withProfile()->create(['phone' => '+229 01 97 38 88 47']);

    $user->notify(new SosTriggered(1, 'ABCDEFGH12', 'Cotonou', 'Littoral'));

    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0][0])->toBe('+229 01 97 38 88 47')
        ->and($sms->sent[0][1])->toContain('ABCDEFGH12')
        ->and(mb_strlen($sms->sent[0][1]))->toBeLessThanOrEqual(160);

    // Sans téléphone : pas de canal SMS, et rien ne casse.
    $silent = User::factory()->withProfile()->create(['phone' => null]);
    $silent->notify(new SosTriggered(2, 'CODE', 'Cotonou', 'Littoral'));
    expect($sms->sent)->toHaveCount(1)->and($silent->notifications()->count())->toBe(1);
});

it('confie une notification Laravel classique (MailMessage) au canal mail standard', function (): void {
    $this->mock(MailChannel::class)->shouldReceive('send')->once();
    $user = User::factory()->withProfile()->create();

    $user->notify(new class extends LaravelNotification
    {
        public function via(object $notifiable): array
        {
            return ['mail'];
        }

        public function toMail(object $notifiable): MailMessage
        {
            return (new MailMessage)->subject('Test')->line('Ligne');
        }
    });
});
