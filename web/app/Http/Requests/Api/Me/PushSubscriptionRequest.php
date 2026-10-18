<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Models\PushSubscription;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * POST / DELETE /api/v1/me/push-subscriptions (P6-09, ADR-25).
 *  - Web Push : `{kind: "webpush", endpoint, keys: {p256dh, auth}, device_label?}` — forme de
 *    `PushSubscription.toJSON()` du navigateur (`public_key` / `auth_token` à plat acceptés) ;
 *  - FCM : `{kind: "fcm", token, device_label?}`.
 * DELETE : `endpoint` ou `token` seulement (`kind` facultatif).
 * L'endpoint Web Push doit appartenir à un service push connu (`xomiton.notifications.push.webpush.allowed_hosts`) :
 * le futur pilote VAPID ne postera jamais vers une adresse choisie par le client (SSRF).
 */
final class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [
                'kind' => ['sometimes', 'nullable', 'string', Rule::in(PushSubscription::KINDS)],
                'endpoint' => ['required_without:token', 'nullable', 'string', 'max:4096'],
                'token' => ['required_without:endpoint', 'nullable', 'string', 'max:4096'],
            ];
        }

        $webpush = PushSubscription::KIND_WEBPUSH;
        $fcm = PushSubscription::KIND_FCM;

        return [
            'kind' => ['required', 'string', Rule::in(PushSubscription::KINDS)],
            'endpoint' => ["required_if:kind,{$webpush}", 'nullable', 'string', 'max:2048', 'url:https', $this->knownPushHost()],
            'keys' => ['sometimes', 'nullable', 'array'],
            'keys.p256dh' => ["required_if:kind,{$webpush}", 'nullable', 'string', 'max:255'],
            'keys.auth' => ["required_if:kind,{$webpush}", 'nullable', 'string', 'max:255'],
            'token' => ["required_if:kind,{$fcm}", 'nullable', 'string', 'max:4096'],
            'device_label' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }

    /** Hôte de l'endpoint Web Push parmi les services connus (motifs `*.notify.windows.com` admis). */
    public static function isKnownPushHost(string $url): bool
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return false;
        }

        foreach ((array) config('xomiton.notifications.push.webpush.allowed_hosts', []) as $pattern) {
            if (is_string($pattern) && $pattern !== '' && Str::is(mb_strtolower($pattern), $host)) {
                return true;
            }
        }

        return false;
    }

    private function knownPushHost(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '' && ! self::isKnownPushHost($value)) {
                $fail(__('account.push.endpoint_host'));
            }
        };
    }

    /**
     * Clés à plat (`public_key`, `auth_token`) ramenées sous `keys.*`.
     */
    protected function prepareForValidation(): void
    {
        if ($this->isMethod('DELETE') || $this->has('keys')) {
            return;
        }

        if ($this->filled('public_key') || $this->filled('auth_token')) {
            $this->merge(['keys' => ['p256dh' => $this->input('public_key'), 'auth' => $this->input('auth_token')]]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'kind' => __('account.push.fields.kind'),
            'endpoint' => __('account.push.fields.endpoint'),
            'keys.p256dh' => __('account.push.fields.p256dh'),
            'keys.auth' => __('account.push.fields.auth'),
            'token' => __('account.push.fields.token'),
            'device_label' => __('account.push.fields.device_label'),
        ];
    }

    public function kind(): ?string
    {
        $kind = $this->validated('kind');

        if (is_string($kind) && $kind !== '') {
            return $kind;
        }

        return $this->filled('token') && ! $this->filled('endpoint') ? PushSubscription::KIND_FCM : null;
    }

    /** Endpoint Web Push ou jeton FCM (rangé dans `endpoint`). */
    public function target(): string
    {
        $kind = $this->kind();
        $endpoint = $this->validated('endpoint');
        $token = $this->validated('token');

        if ($kind === PushSubscription::KIND_FCM) {
            return trim((string) ($token ?? $endpoint));
        }

        return trim((string) ($endpoint ?? $token));
    }

    public function publicKey(): ?string
    {
        $value = $this->validated('keys.p256dh');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function authToken(): ?string
    {
        $value = $this->validated('keys.auth');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function deviceLabel(): ?string
    {
        $value = $this->validated('device_label');

        return is_string($value) ? $value : null;
    }
}
