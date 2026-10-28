<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Jeton d'accès OAuth2 Google d'un compte de service (scope Firebase Cloud Messaging), sans librairie : le JWT RS256
 * est signé avec OpenSSL (`google/auth` n'a pas pu être installé, voir RUNBOOK). Le jeton (≈ 1 h) est mis en cache
 * dans le store `file` : une requête sur des centaines n'appelle Google qu'une fois par heure (cPanel, pas de worker).
 * Le fichier JSON est lu hors racine web ; la clé privée ne quitte jamais ce processus et n'est jamais journalisée.
 */
final class GoogleAccessToken
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const CACHE_KEY = 'fcm.google_access_token';

    /** @var array{client_email: string, private_key: string, project_id?: string, token_uri?: string}|null */
    private ?array $account = null;

    public function __construct(
        private readonly ?string $credentialsPath,
        private readonly string $cacheStore = 'file',
    ) {}

    public static function fromConfig(): self
    {
        $path = config('xomiton.notifications.push.fcm.credentials');

        return new self(is_string($path) && $path !== '' ? $path : null, (string) config('xomiton.notifications.push.fcm.token_cache_store', 'file'));
    }

    public function isConfigured(): bool
    {
        try {
            $this->account();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function projectId(): ?string
    {
        $configured = config('xomiton.notifications.push.fcm.project_id');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        try {
            return $this->account()['project_id'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @throws RuntimeException compte de service illisible ou échange refusé (message sans secret)
     */
    public function get(): string
    {
        $cache = Cache::store($this->cacheStore);
        $cached = $cache->get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $account = $this->account();
        $uri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        $response = Http::asForm()
            ->timeout((float) config('xomiton.alerts.provider_timeout_seconds', 3))
            ->post($uri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->assertion($account, $uri),
            ]);

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Google OAuth: token exchange refused (HTTP '.$response->status().')');
        }

        $ttl = max(60, (int) $response->json('expires_in', 3600) - 300);
        $cache->put(self::CACHE_KEY, $token, $ttl);

        return $token;
    }

    /** Oublie le jeton (réponse 401 de FCM) : le prochain envoi en redemande un. */
    public function forget(): void
    {
        Cache::store($this->cacheStore)->forget(self::CACHE_KEY);
    }

    /**
     * @return array{client_email: string, private_key: string, project_id?: string, token_uri?: string}
     */
    private function account(): array
    {
        if ($this->account !== null) {
            return $this->account;
        }

        if ($this->credentialsPath === null || ! is_file($this->credentialsPath) || ! is_readable($this->credentialsPath)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS missing or unreadable');
        }

        $json = json_decode((string) file_get_contents($this->credentialsPath), true);

        if (! is_array($json) || ! is_string($json['client_email'] ?? null) || ! is_string($json['private_key'] ?? null)) {
            throw new RuntimeException('FIREBASE_CREDENTIALS is not a service account JSON');
        }

        return $this->account = $json;
    }

    /**
     * @param  array{client_email: string, private_key: string}  $account
     */
    private function assertion(array $account, string $audience): string
    {
        $now = time();
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => $audience,
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];

        $signature = '';

        if (! openssl_sign(implode('.', $segments), $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Service account private key unusable');
        }

        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
