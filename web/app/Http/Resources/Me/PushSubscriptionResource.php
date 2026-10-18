<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Application\Account\PushSubscriptions;
use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Abonnement push (P6-09) : jamais l'endpoint, le jeton FCM ni les clés — seulement l'empreinte courte qui permet au
 * client de reconnaître son abonnement.
 *
 * @mixin PushSubscription
 */
final class PushSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PushSubscription $subscription */
        $subscription = $this->resource;

        return [
            'id' => (int) $subscription->getKey(),
            'kind' => $subscription->kind,
            'fingerprint' => PushSubscriptions::fingerprint($subscription),
            'device_label' => $subscription->device_label,
            'last_used_at' => $subscription->last_used_at?->toIso8601String(),
            'created_at' => $subscription->created_at?->toIso8601String(),
        ];
    }
}
