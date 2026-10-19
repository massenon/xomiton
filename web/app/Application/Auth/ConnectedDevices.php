<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * « Appareils connectés » (P2-06) : jetons Sanctum (`token-<id>`) et sessions web en base (`web-<empreinte>`,
 * SESSION_DRIVER=database, D7). Une session web n'est jamais désignée par son identifiant (il ouvrirait la session)
 * mais par une empreinte dérivée d'APP_KEY. Rien ici ne touche un autre compte que celui passé en paramètre.
 */
final class ConnectedDevices
{
    public const KIND_TOKEN = 'token';

    public const KIND_WEB = 'web';

    /**
     * Appareils de la personne, du plus récemment actif au plus ancien.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function list(User $user, ?int $currentTokenId = null, ?string $currentSessionId = null): Collection
    {
        // toBase() : sans jeton, map() rend une collection Eloquent vide, dont merge() appellerait getKey() sur les
        // lignes de sessions web (500 sur me/sessions et /parametres pour un compte web seulement).
        return $this->tokens($user, $currentTokenId)->toBase()
            ->merge($this->webSessions($user, $currentSessionId))
            ->sortByDesc(fn (array $row): int => ($row['last_active_at'] ?? $row['created_at'])?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Révoque un jeton ou une session web de la personne ; false si l'identifiant ne lui correspond pas.
     */
    public function revoke(User $user, string $id): bool
    {
        if (Str::startsWith($id, self::KIND_TOKEN.'-')) {
            return $this->revokeToken($user, (int) Str::after($id, self::KIND_TOKEN.'-'));
        }

        if (Str::startsWith($id, self::KIND_WEB.'-')) {
            return $this->revokeWebSession($user, Str::after($id, self::KIND_WEB.'-'));
        }

        return false;
    }

    /**
     * Déconnecte tous les autres appareils (P8-09 : « Déconnecter tous les autres appareils », changement de mot de
     * passe) : jetons Sanctum sauf le jeton courant (abonnements push supprimés en cascade, P6-09) et sessions web sauf
     * la session courante. Rend le nombre d'accès fermés ; journal `auth.sessions_revoked_others` (nombre seulement).
     */
    public function revokeOthers(User $user, ?int $currentTokenId = null, ?string $currentSessionId = null): int
    {
        $revoked = $user->tokens()
            ->when($currentTokenId !== null, static fn ($query) => $query->whereKeyNot($currentTokenId))
            ->delete();

        if (self::hasSessionsTable()) {
            $revoked += DB::table('sessions')->where('user_id', $user->getKey())
                ->when($currentSessionId !== null, static fn ($query) => $query->where('id', '!=', $currentSessionId))
                ->delete();
        }

        if ($revoked > 0) {
            Audit::log('auth.sessions_revoked_others', $user, 'acces='.$revoked, $user);
        }

        return (int) $revoked;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tokens(User $user, ?int $currentTokenId): Collection
    {
        return $user->tokens()->orderByDesc('last_used_at')->orderByDesc('id')->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => self::KIND_TOKEN.'-'.$token->getKey(),
                'kind' => self::KIND_TOKEN,
                'name' => (string) $token->name,
                'ip' => null,
                'current' => $currentTokenId !== null && (int) $token->getKey() === $currentTokenId,
                'last_active_at' => $token->last_used_at,
                'created_at' => $token->created_at,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function webSessions(User $user, ?string $currentSessionId): Collection
    {
        if (! self::hasSessionsTable()) {
            return collect();
        }

        return DB::table('sessions')->where('user_id', $user->getKey())->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): array => [
                'id' => self::KIND_WEB.'-'.self::fingerprint((string) $row->id),
                'kind' => self::KIND_WEB,
                'name' => Str::limit((string) ($row->user_agent ?? __('auth.api.web_session')), 100, '…'),
                'ip' => $row->ip_address,
                'current' => $currentSessionId !== null && hash_equals((string) $row->id, $currentSessionId),
                'last_active_at' => Carbon::createFromTimestamp((int) $row->last_activity),
                'created_at' => null,
            ]);
    }

    private function revokeToken(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->whereKey($tokenId)->first();

        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        $token->delete();
        Audit::log('auth.session_revoked', $user, sprintf('jeton=%d ; appareil=%s', $tokenId, (string) $token->name), $user);

        return true;
    }

    private function revokeWebSession(User $user, string $fingerprint): bool
    {
        if (! self::hasSessionsTable()) {
            return false;
        }

        foreach (DB::table('sessions')->where('user_id', $user->getKey())->pluck('id') as $sessionId) {
            if (! hash_equals(self::fingerprint((string) $sessionId), $fingerprint)) {
                continue;
            }

            DB::table('sessions')->where('id', $sessionId)->delete();
            Audit::log('auth.session_revoked', $user, 'session_web='.$fingerprint, $user);

            return true;
        }

        return false;
    }

    private static function hasSessionsTable(): bool
    {
        return Schema::hasTable('sessions');
    }

    /** Empreinte publique d'une session web : jamais l'identifiant lui-même. */
    private static function fingerprint(string $sessionId): string
    {
        return substr(hash('sha256', $sessionId.'|'.(string) config('app.key')), 0, 32);
    }
}
