<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PoolablePushSender;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\HttpRequestSpec;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pilote FCM HTTP v1 (`FCM_DRIVER=fcm`, Q7 : Firebase Cloud Messaging, gratuit). Un message par jeton d'appareil
 * (`push_subscriptions.endpoint`, `kind = fcm`), authentifié par le jeton OAuth2 du compte de service (GoogleAccessToken).
 *
 * Charge utile discrète : le titre et le corps sont ceux du PushMessage (déjà neutralisés si la personne masque les
 * aperçus, NOTIFICATIONS §4.1) ; les données se limitent à `type`, `id`, `category` (+ `url` relatif) : le détail est
 * relu dans l'application après authentification. Priorité haute et canal Android `sos_alerts` pour les catégories
 * urgentes (`sos`, `alertes`), canal `duty` (Astreinte) pour les types d'astreinte (`duty_sos`, `duty_alert`, `alert_routed`) ;
 * aucun bloc `notification` pour les types silencieux (`sync`, `rules`, P15-19). Jeton invalide ou désinscrit (UNREGISTERED, INVALID_ARGUMENT sur le jeton, 404) →
 * `DeliveryResult::gone()` : le canal supprime l'abonnement. Le jeton d'appareil n'est jamais journalisé.
 */
final class FirebaseFcmSender implements FcmSender, PoolablePushSender
{
    private readonly GoogleAccessToken $auth;

    public function __construct(?GoogleAccessToken $auth = null)
    {
        $this->auth = $auth ?? GoogleAccessToken::fromConfig();
    }

    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult
    {
        try {
            $spec = $this->describeRequest($subscription, $message);

            if ($spec === null) {
                return DeliveryResult::failed('fcm: not configured');
            }

            $response = Http::withHeaders($spec->headers)
                ->timeout((float) config('xomiton.alerts.provider_timeout_seconds', 3))
                ->post($spec->url, $spec->json ?? []);

            return $this->interpretResponse($response->status(), (string) $response->body());
        } catch (Throwable $e) {
            return $this->interpretTransportError($e::class);
        }
    }

    public function describeRequest(PushSubscription $subscription, PushMessage $message): ?HttpRequestSpec
    {
        $project = $this->auth->projectId();

        if ($project === null || ! $this->auth->isConfigured()) {
            return null;
        }

        return new HttpRequestSpec(
            method: 'POST',
            url: sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', rawurlencode($project)),
            headers: ['Authorization' => 'Bearer '.$this->auth->get(), 'Content-Type' => 'application/json'],
            json: ['message' => $this->payload((string) $subscription->endpoint, $message)],
        );
    }

    public function interpretResponse(int $status, string $body): DeliveryResult
    {
        if ($status >= 200 && $status < 300) {
            return DeliveryResult::sent();
        }

        if ($status === 401) {
            $this->auth->forget();

            return DeliveryResult::failed('fcm: unauthorized (token refreshed)');
        }

        $error = json_decode($body, true)['error'] ?? [];
        $codes = [(string) ($error['status'] ?? '')];

        foreach ((array) ($error['details'] ?? []) as $detail) {
            $codes[] = (string) ($detail['errorCode'] ?? '');
        }

        if ($status === 404 || in_array('UNREGISTERED', $codes, true)) {
            return DeliveryResult::gone('fcm: UNREGISTERED');
        }

        // 400 INVALID_ARGUMENT portant sur le jeton (mal formé) → inutilisable ; tout autre 400 reste un échec rejouable.
        if ($status === 400 && in_array('INVALID_ARGUMENT', $codes, true) && (str_contains($body, 'message.token') || str_contains($body, 'registration token'))) {
            return DeliveryResult::gone('fcm: INVALID_ARGUMENT');
        }

        return DeliveryResult::failed(sprintf('fcm: HTTP %d %s', $status, trim(implode(' ', array_filter($codes)))));
    }

    public function interpretTransportError(string $error): DeliveryResult
    {
        return DeliveryResult::failed('fcm: transport error '.mb_substr($error, 0, 80));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(string $token, PushMessage $message): array
    {
        $urgent = $message->isUrgent();
        $data = [
            'type' => (string) $message->data['type'],
            'id' => (string) ($message->data['id'] ?? ''),
            'category' => (string) $message->data['category'],
        ];

        $url = $message->data['url'] ?? $message->url;

        if (is_string($url) && str_starts_with($url, '/')) {
            $data['url'] = $url;
        }

        if ($message->silent) {
            // Données seules (types `sync`, `rules`) : rien n'est affiché par le système, l'application agit. Réveil en
            // priorité normale (économie de batterie), regroupé par type : seul le dernier ordre compte.
            return [
                'token' => $token,
                'data' => $data,
                'android' => ['priority' => 'NORMAL', 'collapse_key' => $data['type']],
                'apns' => [
                    'headers' => ['apns-priority' => '5', 'apns-push-type' => 'background'],
                    'payload' => ['aps' => ['content-available' => 1]],
                ],
            ];
        }

        return [
            'token' => $token,
            'notification' => ['title' => $message->title, 'body' => $message->body],
            'data' => $data,
            'android' => [
                'priority' => $urgent ? 'HIGH' : 'NORMAL',
                'notification' => ['channel_id' => $message->channel()],
            ],
            'apns' => ['headers' => ['apns-priority' => $urgent ? '10' : '5']],
        ];
    }
}
