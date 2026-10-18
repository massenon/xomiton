<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Abonnements push du compte (`push_subscriptions`, ADR-25, P6-09) : Web Push (endpoint + clés `p256dh` / `auth` du
 * navigateur) ou FCM (jeton d'appareil, rangé dans `endpoint`). L'empreinte `endpoint_hash` (SHA-256) est unique :
 * un même endpoint ne sert qu'un compte, celui qui vient de s'abonner — sur un appareil partagé, l'abonnement passe
 * au compte connecté et l'ancien n'y est plus notifié. Suppression à la déconnexion et sur `onTokenRefresh` (P15-08) ;
 * l'abonnement garde le jeton Sanctum (`token_id`) et l'appareil (`sync_device_id`, `X-Device-Id`) qui l'ont
 * enregistré : révoquer ce jeton (DELETE me/sessions/{id}, cascade) ou cet appareil (DELETE me/devices/{uuid}) le
 * supprime — un téléphone perdu ou partagé n'est plus notifié.
 * Journal (`push.subscribed`, `push.unsubscribed`, `Log`) : type et empreinte tronquée seulement, **jamais l'endpoint
 * ni le jeton en clair**.
 */
final class PushSubscriptions
{
    /**
     * @return array{0: PushSubscription, 1: bool} l'abonnement et vrai s'il vient d'être créé pour ce compte
     */
    public function save(User $user, string $kind, string $endpoint, ?string $publicKey = null, ?string $authToken = null, ?string $label = null, ?int $tokenId = null, ?int $deviceId = null): array
    {
        $endpoint = trim($endpoint);
        $hash = PushSubscription::hashEndpoint($endpoint);
        $attributes = [
            'user_id' => $user->getKey(),
            'token_id' => $tokenId,
            'sync_device_id' => $deviceId,
            'kind' => $kind,
            'endpoint' => $endpoint,
            'public_key' => $kind === PushSubscription::KIND_WEBPUSH ? $publicKey : null,
            'auth_token' => $kind === PushSubscription::KIND_WEBPUSH ? $authToken : null,
            'device_label' => $label === null || trim($label) === '' ? null : Str::limit(trim($label), 80, ''),
        ];

        $subscription = PushSubscription::query()->where('endpoint_hash', $hash)->first();

        if ($subscription === null) {
            try {
                $subscription = PushSubscription::query()->create($attributes);
            } catch (UniqueConstraintViolationException) {
                $subscription = PushSubscription::query()->where('endpoint_hash', $hash)->firstOrFail();
            }

            if ($subscription->wasRecentlyCreated) {
                $this->trace('push.subscribed', $user, $subscription);

                return [$subscription, true];
            }
        }

        $transferred = (int) $subscription->user_id !== (int) $user->getKey();
        $subscription->forceFill($attributes)->save();

        if ($transferred) {
            Log::info('[push] Abonnement repris par le compte connecté (appareil partagé).', ['kind' => $kind, 'subscription' => self::fingerprint($subscription)]);
            $this->trace('push.subscribed', $user, $subscription);
        }

        return [$subscription, $transferred];
    }

    /**
     * Supprime l'abonnement de ce compte portant cet endpoint ou ce jeton ; celui d'un autre compte n'est jamais touché.
     */
    public function remove(User $user, string $endpoint, ?string $kind = null): bool
    {
        $query = PushSubscription::query()->forEndpoint($endpoint)->where('user_id', $user->getKey());

        if ($kind !== null) {
            $query->ofKind($kind);
        }

        $subscription = $query->first();

        if ($subscription === null) {
            return false;
        }

        $subscription->delete();
        $this->trace('push.unsubscribed', $user, $subscription);

        return true;
    }

    /** Empreinte courte journalisable (12 premiers caractères du SHA-256 de l'endpoint). */
    public static function fingerprint(PushSubscription $subscription): string
    {
        return substr((string) $subscription->endpoint_hash, 0, 12);
    }

    private function trace(string $action, User $user, PushSubscription $subscription): void
    {
        Audit::log($action, $user, sprintf('type=%s ; empreinte=%s', $subscription->kind, self::fingerprint($subscription)), $user);
    }
}
