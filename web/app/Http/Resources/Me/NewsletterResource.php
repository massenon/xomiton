<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Enums\SubscriberStatus;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Newsletter de la personne connectée (GET / PATCH me/newsletter, P6-06) : abonnement, statut, centres d'intérêt
 * choisis et liste des centres d'intérêt proposés (pour les puces du client). Ressource `null` = jamais abonné·e.
 */
final class NewsletterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscriber = $this->resource instanceof NewsletterSubscriber ? $this->resource : null;
        $status = $subscriber?->status;

        return [
            'subscribed' => $status === SubscriberStatus::Active,
            'status' => $status?->value,
            'pending_confirmation' => $status === SubscriberStatus::Pending,
            'interests' => array_values(array_filter((array) ($subscriber->interests ?? []), 'is_string')),
            'available_interests' => NewsletterInterest::query()->ordered()->get(['slug', 'label'])
                ->map(static fn (NewsletterInterest $interest): array => ['slug' => $interest->slug, 'label' => $interest->label])
                ->values()
                ->all(),
            'confirmed_at' => $subscriber?->confirmed_at?->toIso8601String(),
        ];
    }
}
