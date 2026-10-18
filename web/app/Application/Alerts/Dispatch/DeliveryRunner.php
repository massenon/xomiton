<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\DeliveryBudget;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PoolablePushSender;
use App\Domain\Notifications\Contracts\PoolableSmsSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Models\AlertNotification;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Remises externes bornées dans le temps (D7, P5-03) : les pilotes « poolables » (contrats Poolable*Sender) partent
 * ensemble dans un seul `Http::pool` (délai HTTP = délai fournisseur borné par le budget restant) ; les autres sont
 * appelés en séquence par fournisseur (SMS directs, puis Web Push, puis FCM), chaque groupe fermé après
 * `provider_timeout_seconds` et tous après le budget global : ce qui n'est pas tenté reste en attente pour le cron.
 * Une exception de pilote devient un échec (message masqué). Le résultat push est appliqué à l'abonnement
 * (PushSubscription::recordDelivery : suppression sur 404 / 410). Les pilotes restent les seuls à parler aux fournisseurs.
 * Limite : la fenêtre d'un groupe séquentiel n'est vérifiée qu'entre deux `send()` ; un `send()` bloquant n'est pas
 * borné (deux fournisseurs push lents : ≈ 7 s, sous le budget de 8 s, D13, au-delà des « < 4 s » visés). Tout pilote
 * HTTP à venir implémente donc PoolablePushSender / PoolableSmsSender, ou applique lui-même
 * `xomiton.alerts.provider_timeout_seconds` à sa requête (contrats PushSender, FcmSender, SmsSender).
 */
final class DeliveryRunner
{
    /** Ordre des groupes séquentiels (le canal le plus sûr pour la personne d'abord). */
    public const SEQUENCE = [PlannedDelivery::SMS, PlannedDelivery::WEBPUSH, PlannedDelivery::FCM];

    /** @var array<string, string> */
    private array $providerNames = [];

    public function __construct(
        private readonly PushSender $webPush,
        private readonly FcmSender $fcm,
        private readonly SmsSender $sms,
    ) {}

    /**
     * @param  list<PlannedDelivery>  $deliveries
     * @return list<DeliveryOutcome> dans l'ordre des remises reçues
     */
    public function run(array $deliveries, DeliveryBudget $budget): array
    {
        $outcomes = [];
        $pooled = [];
        $sequential = [];

        foreach ($deliveries as $index => $delivery) {
            $spec = $this->describe($delivery);

            if ($spec !== null) {
                $pooled[$index] = [$delivery, $spec];
            } else {
                $sequential[$delivery->provider][$index] = $delivery;
            }
        }

        if ($pooled !== []) {
            $this->runPool($pooled, $budget, $outcomes);
        }

        foreach (self::SEQUENCE as $provider) {
            if (isset($sequential[$provider])) {
                $this->runSequence($provider, $sequential[$provider], $budget, $outcomes);
            }
        }

        ksort($outcomes);

        foreach ($outcomes as $outcome) {
            if ($outcome->attempted && $outcome->result !== null && $outcome->delivery->subscription !== null) {
                $outcome->delivery->subscription->recordDelivery($outcome->result);
            }
        }

        return array_values($outcomes);
    }

    /** Nom effectif du pilote d'un fournisseur (`log`, `vapid`, `firebase`, fournisseur SMS) : `alert_notifications.provider`. */
    public function providerName(string $provider): string
    {
        return $this->providerNames[$provider] ??= match ($provider) {
            PlannedDelivery::WEBPUSH => NotificationDrivers::effective('webpush', (string) config('xomiton.notifications.push.webpush.driver', NotificationDrivers::LOG), NotificationDrivers::WEBPUSH),
            PlannedDelivery::FCM => NotificationDrivers::effective('fcm', (string) config('xomiton.notifications.push.fcm.driver', NotificationDrivers::LOG), NotificationDrivers::FCM),
            default => NotificationDrivers::effective('sms', (string) config('xomiton.sms.provider', NotificationDrivers::LOG), NotificationDrivers::SMS),
        };
    }

    private function sender(string $provider): PushSender|FcmSender|SmsSender
    {
        return match ($provider) {
            PlannedDelivery::WEBPUSH => $this->webPush,
            PlannedDelivery::FCM => $this->fcm,
            default => $this->sms,
        };
    }

    private function describe(PlannedDelivery $delivery): ?HttpRequestSpec
    {
        $sender = $this->sender($delivery->provider);

        try {
            if ($delivery->isPush() && $sender instanceof PoolablePushSender && $delivery->subscription !== null && $delivery->message !== null) {
                return $sender->describeRequest($delivery->subscription, $delivery->message);
            }

            if (! $delivery->isPush() && $sender instanceof PoolableSmsSender) {
                return $sender->describeRequest((string) $delivery->to, (string) $delivery->text);
            }
        } catch (Throwable $e) {
            Log::warning('alerts.dispatch.describe_failed', ['provider' => $delivery->provider, 'error' => class_basename($e)]);
        }

        return null;
    }

    /**
     * @param  array<int, array{0: PlannedDelivery, 1: HttpRequestSpec}>  $pooled
     * @param  array<int, DeliveryOutcome>  $outcomes
     */
    private function runPool(array $pooled, DeliveryBudget $budget, array &$outcomes): void
    {
        if ($budget->exhausted()) {
            foreach ($pooled as $index => [$delivery]) {
                $outcomes[$index] = DeliveryOutcome::notAttempted($delivery, $this->providerName($delivery->provider));
            }

            Log::warning('alerts.dispatch.provider_budget_exceeded', ['provider' => 'pool', 'reason' => 'request', 'left' => count($pooled)]);

            return;
        }

        $timeout = $budget->httpTimeout();

        try {
            $responses = Http::pool(static function (Pool $pool) use ($pooled, $timeout): void {
                foreach ($pooled as $index => [, $spec]) {
                    $options = match (true) {
                        $spec->json !== null => ['json' => $spec->json],
                        $spec->body !== null => ['body' => $spec->body],
                        default => [],
                    };

                    $pool->as((string) $index)
                        ->timeout($timeout)
                        ->connectTimeout(min(1.0, $timeout))
                        ->withHeaders($spec->headers)
                        ->send(strtoupper($spec->method), $spec->url, $options);
                }
            });
        } catch (Throwable $e) {
            $error = DeliveryError::fromThrowable($e);

            foreach ($pooled as $index => [$delivery]) {
                $outcomes[$index] = new DeliveryOutcome($delivery, true, DeliveryResult::failed($error), $this->providerName($delivery->provider));
            }

            return;
        }

        foreach ($pooled as $index => [$delivery]) {
            /** @var PoolablePushSender|PoolableSmsSender $sender */
            $sender = $this->sender($delivery->provider);
            $response = $responses[$index] ?? $responses[(string) $index] ?? null;

            try {
                $result = match (true) {
                    $response instanceof Response => $sender->interpretResponse($response->status(), (string) $response->body()),
                    $response instanceof Throwable => $sender->interpretTransportError(DeliveryError::fromThrowable($response)),
                    default => DeliveryResult::failed(AlertNotification::ERROR_NO_RESPONSE),
                };
            } catch (Throwable $e) {
                $result = DeliveryResult::failed(DeliveryError::fromThrowable($e));
            }

            $outcomes[$index] = new DeliveryOutcome($delivery, true, $result, $this->providerName($delivery->provider));
        }
    }

    /**
     * @param  array<int, PlannedDelivery>  $group
     * @param  array<int, DeliveryOutcome>  $outcomes
     */
    private function runSequence(string $provider, array $group, DeliveryBudget $budget, array &$outcomes): void
    {
        $name = $this->providerName($provider);
        $sender = $this->sender($provider);
        $startedAt = $budget->now();
        $left = 0;
        $reason = null;

        foreach ($group as $index => $delivery) {
            if ($budget->exhausted() || ! $budget->providerWindowOpen($startedAt)) {
                $reason ??= $budget->exhausted() ? 'request' : 'provider';
                $outcomes[$index] = DeliveryOutcome::notAttempted($delivery, $name);
                $left++;

                continue;
            }

            try {
                $result = $delivery->isPush()
                    ? $sender->send($delivery->subscription, $delivery->message)
                    : $sender->send((string) $delivery->to, (string) $delivery->text);
            } catch (Throwable $e) {
                $result = DeliveryResult::failed(DeliveryError::fromThrowable($e));
            }

            $outcomes[$index] = new DeliveryOutcome($delivery, true, $result, $name);
        }

        if ($left > 0) {
            Log::warning('alerts.dispatch.provider_budget_exceeded', ['provider' => $provider, 'reason' => $reason, 'left' => $left]);
        }
    }
}
