<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Domain\Notifications\Contracts\PoolablePushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Faux pilote push HTTP « poolable » : requête vers https://push.test/<id> ; 2xx → sent, 404 / 410 → gone, autre →
 * failed. `poolable(false)` simule un abonnement que le pilote ne sait pas pooler (repli sur send()).
 */
class FakePoolablePushSender extends FakePushSender implements PoolablePushSender
{
    private bool $poolable = true;

    public function poolable(bool $poolable): static
    {
        $this->poolable = $poolable;

        return $this;
    }

    public function describeRequest(PushSubscription $subscription, PushMessage $message): ?HttpRequestSpec
    {
        return $this->poolable
            ? new HttpRequestSpec('POST', 'https://push.test/'.$subscription->getKey(), ['TTL' => '60'], json: $message->toArray())
            : null;
    }

    public function interpretResponse(int $status, string $body): DeliveryResult
    {
        return match (true) {
            $status >= 200 && $status < 300 => DeliveryResult::sent(),
            in_array($status, [404, 410], true) => DeliveryResult::gone('HTTP '.$status),
            default => DeliveryResult::failed('HTTP '.$status),
        };
    }

    public function interpretTransportError(string $error): DeliveryResult
    {
        return DeliveryResult::failed($error);
    }
}
