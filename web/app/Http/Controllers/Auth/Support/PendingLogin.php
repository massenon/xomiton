<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Support;

use App\Application\Auth\OtpService;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Connexion en attente d'un code OTP `login` (Q13 b) : le mot de passe est vérifié, la session n'est pas encore
 * ouverte. L'état vit dans la session, le temps de validité du code, et ne contient jamais le mot de passe.
 */
final class PendingLogin
{
    public const KEY = 'auth.pending_login';

    public function __construct(private readonly Session $session) {}

    public function remember(User $user, bool $remember, ?string $next): void
    {
        $this->session->put(self::KEY, [
            'user_id' => $user->getKey(),
            'remember' => $remember,
            'next' => $next,
            'expires_at' => now()->addMinutes(OtpService::ttlMinutes())->getTimestamp(),
        ]);
    }

    public function user(): ?User
    {
        $state = $this->state();

        return $state === null ? null : User::query()->find((int) $state['user_id']);
    }

    public function remembers(): bool
    {
        return (bool) ($this->state()['remember'] ?? false);
    }

    public function next(): ?string
    {
        $next = $this->state()['next'] ?? null;

        return is_string($next) ? $next : null;
    }

    public function forget(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return array{user_id: int, remember: bool, next: ?string, expires_at: int}|null
     */
    private function state(): ?array
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state) || ! isset($state['user_id'], $state['expires_at'])) {
            return null;
        }

        if ((int) $state['expires_at'] <= now()->getTimestamp()) {
            $this->forget();

            return null;
        }

        /** @var array{user_id: int, remember: bool, next: ?string, expires_at: int} $state */
        return $state;
    }
}
