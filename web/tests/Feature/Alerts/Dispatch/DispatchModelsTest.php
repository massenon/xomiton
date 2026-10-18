<?php

declare(strict_types=1);

use App\Application\Mail\EmailTemplateService;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\SmsText;
use App\Enums\DeliveryStatus;
use App\Enums\MailTransport;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\EmailTemplate;
use App\Models\MailOutbox;
use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\SosTriggered;
use Database\Seeders\EmailTemplatesSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Notifications\FakePushSender;

/*
 * P5-03 : consignation (`alert_notifications` : objet, fournisseur, vu, références), abonnement push nettoyé par une
 * seule règle, dépôt d'e-mail sans SMTP, textes du lot.
 */

it('consigne objet, fournisseur et état « vu », avec des références seulement', function (): void {
    $user = User::factory()->create();
    $subscription = PushSubscription::factory()->forUser($user)->create();
    $row = AlertNotification::factory()->toSubscription($subscription)->create();

    expect($row->fresh()->purpose)->toBe(NotificationPurpose::SosTriggered)
        ->and($row->personKey())->toBe('user:'.$user->id)
        ->and($row->recipientUserId())->toBe($user->id);

    $row->markFailed('HTTP 500 pour +229 01 97 38 88 47', 'log', now()->addMinute());
    expect($row->fresh()->attempts)->toBe(1)->and($row->fresh()->status)->toBe(DeliveryStatus::Failed);

    $row->markSent('log');
    $row->markSeen(now()->subMinute());
    $first = $row->fresh()->seen_at;
    $row->markSeen(now());

    expect($row->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($row->fresh()->attempts)->toBe(2)
        ->and($row->fresh()->last_error)->toBeNull()
        ->and($row->fresh()->provider)->toBe('log')
        ->and($row->fresh()->seen_at->equalTo($first))->toBeTrue()
        ->and(AlertNotification::query()->seen()->count())->toBe(1);

    foreach ([AlertNotification::factory()->create(), AlertNotification::factory()->channel(NotificationChannel::Mail)->create(), AlertNotification::factory()->channel(NotificationChannel::Database)->create()] as $made) {
        expect($made->recipient)->toMatch('/^(user|contact|trusted|subscription):\d+$/');
    }
});

it('ne rejoue ni les lignes abandonnées ni les objets non rejouables', function (): void {
    $due = AlertNotification::factory()->failed()->create();
    AlertNotification::factory()->skipped()->create();
    AlertNotification::factory()->purpose(NotificationPurpose::SosSafe)->failed()->create();

    expect(AlertNotification::query()->retryable()->pluck('id')->all())->toBe([$due->id])
        ->and(AlertNotification::query()->dueForRetry()->count())->toBe(2);
});

it('supprime un abonnement disparu et date un abonnement servi, par une seule règle partagée avec PushChannel', function (): void {
    $gone = PushSubscription::factory()->create();
    $ok = PushSubscription::factory()->create();
    $failed = PushSubscription::factory()->create();

    $gone->recordDelivery(DeliveryResult::gone());
    $ok->recordDelivery(DeliveryResult::sent());
    $failed->recordDelivery(DeliveryResult::failed('HTTP 500'));

    expect(PushSubscription::query()->find($gone->id))->toBeNull()
        ->and($ok->fresh()->last_used_at)->not->toBeNull()
        ->and($failed->fresh()->last_used_at)->toBeNull();

    $user = User::factory()->withProfile()->create();
    $subscription = PushSubscription::factory()->forUser($user)->create();
    $this->app->instance(PushSender::class, (new FakePushSender)->willReturn(DeliveryResult::gone('HTTP 410')));
    Notification::sendNow($user, new SosTriggered(1, 'SIG-ABC', 'Cotonou', 'Littoral'), ['webpush']);

    expect(PushSubscription::query()->find($subscription->id))->toBeNull();
});

it('dépose l’e-mail dans la boîte d’envoi sans jamais appeler le mailer', function (): void {
    $this->seed(EmailTemplatesSeeder::class);
    config(['mail.default' => 'array']);
    Mail::fake();

    $log = EmailTemplateService::enqueue('alerte-routee', 'contact@exemple.bj', ['alert_reference' => 'SIG-ABC', 'alert_zone' => 'Littoral'], 'alerte_routee');

    expect($log->ok)->toBeTrue()
        ->and($log->transport)->toBe(MailTransport::Outbox)
        ->and(MailOutbox::query()->where('to', 'contact@exemple.bj')->where('template_slug', 'alerte-routee')->exists())->toBeTrue();
    Mail::assertNothingSent();

    $invalid = EmailTemplateService::enqueue('alerte-routee', 'pas-une-adresse');
    EmailTemplate::query()->where('slug', 'alerte-routee')->update(['is_active' => false]);
    $inactive = EmailTemplateService::enqueue('alerte-routee', 'contact@exemple.bj');

    expect($invalid->ok)->toBeFalse()->and($inactive->ok)->toBeFalse()->and(MailOutbox::query()->count())->toBe(1);
});

it('ne tronque jamais le lien final d’un SMS', function (): void {
    $url = 'https://xomiton.bj/alertes/01J9ZQ8V7W4K2M3N5P6R7S8T9V/accuser?contact=12&expires=1792345678&signature='.str_repeat('a', 64);
    $sms = (new SosTriggered(1, 'SIG-A7K2M9P4QX', 'Abomey-Calavi', 'Atlantique', null, $url))->toSms(Alert::factory()->make());

    expect($sms)->toEndWith($url)
        ->and(SmsText::isGsm7($sms))->toBeTrue()
        ->and(SmsText::segments($sms))->toBeLessThanOrEqual(2)
        ->and($sms)->toStartWith('Xomiton - Alerte SOS SIG-A7K2M9P4QX');
});

it('a tous les textes du lot, non vides, avec une espace insécable avant les deux-points', function (): void {
    $keys = [
        'alerts.notified.title', 'alerts.notified.state.seen', 'alerts.notified.state.sms_sent', 'alerts.notified.state.sent',
        'alerts.notified.state.pending', 'alerts.notified.badge_seen', 'alerts.notified.badge_sms', 'alerts.notified.kind.trusted',
        'alerts.notified.kind.team', 'alerts.notified.kind.team_role', 'alerts.notified.offline', 'alerts.notified.updated',
        'notifications.sos_acknowledged.title', 'notifications.sos_acknowledged.body', 'notifications.sos_acknowledged.discreet',
        'notifications.sos_cancelled.sms', 'notifications.sos_safe.sms', 'notifications.alert_routed.reminder_title',
        'enums/alerts.delivery_status.skipped', 'notifications.sos_triggered.sms_copy',
        ...array_map(static fn (string $code): string => 'alerts.delivery_reason.'.$code, [
            AlertNotification::SKIP_GONE, AlertNotification::SKIP_NO_PHONE, AlertNotification::SKIP_NO_ADDRESS,
            AlertNotification::SKIP_UNAVAILABLE, AlertNotification::SKIP_HANDLED, AlertNotification::SKIP_CANCELLED,
            AlertNotification::SKIP_EXHAUSTED, AlertNotification::ERROR_NO_RESPONSE, AlertNotification::ERROR_MAIL_NOT_SENT,
        ]),
    ];

    foreach ([...$keys, ...array_map(static fn (NotificationPurpose $p): string => 'enums/alerts.notification_purpose.'.$p->value, NotificationPurpose::cases())] as $key) {
        $text = __($key);
        expect($text)->toBeString()->not->toBe($key)->not->toBe('')
            ->and(preg_match('/[^\x{00A0}] [:;?!](\s|$)/u', $text))->toBe(0, $key);
    }

    // Textes SMS : une fois ramenés par SmsText, alphabet GSM-7 seulement (sinon UCS-2, 70 caractères par segment).
    foreach (['sos_triggered.sms', 'sos_triggered.sms_no_position', 'sos_triggered.sms_copy', 'sos_triggered.sms_trusted', 'sos_triggered.sms_trusted_unnamed', 'sos_unacknowledged.sms', 'sos_cancelled.sms', 'sos_safe.sms'] as $key) {
        $sms = (string) __('notifications.'.$key);
        // Aucune lettre à translittérer : le texte reçu reste du français correct.
        expect(preg_match('/[êâîôûç·]/u', $sms))->toBe(0, $key)
            ->and(SmsText::isGsm7(SmsText::normalize($sms)))->toBeTrue($key);
    }

    foreach (['seen', 'sms_sent', 'sent', 'pending'] as $state) {
        expect(__('enums/alerts.notified_state.'.$state))->not->toStartWith('enums/');
    }

    foreach (['trusted', 'focal', 'team', 'contact', 'author'] as $kind) {
        expect(__('enums/alerts.recipient_kind.'.$kind))->not->toStartWith('enums/');
    }
});
