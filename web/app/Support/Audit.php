<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Journal d'audit (DEVELOPMENT_STANDARDS.md §2, MASTER_PROMPT.md §4) : une ligne par action sensible.
 * Usage : `Audit::log('user.suspend', $user, 'Motif : propos haineux')`.
 * L'auteur·rice est le compte connecté par défaut ; sans compte, « anonyme » (requête HTTP) ou « système » (console).
 * Les champs sensibles (mots de passe, jetons, codes OTP, récits chiffrés…) sont masqués avant écriture.
 */
final class Audit
{
    public const MASK = '[masqué]';

    /** Taille maximale de `detail` (colonne TEXT MySQL, en octets). */
    public const DETAIL_MAX_BYTES = 65535;

    /**
     * Clés dont la valeur n'est jamais écrite en clair dans le journal (comparaison insensible à la casse,
     * sur la clé entière ou un de ses segments : `current_password`, `otp_code`, `smtp_password`…).
     *
     * @var list<string>
     */
    public const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'new_password', 'secret', 'token', 'remember_token',
        'api_key', 'api_secret', 'access_token', 'refresh_token', 'otp', 'otp_code', 'code_hash',
        'smtp_password', 'reset_token', 'description', 'notes', 'comment', 'body', 'details', 'contact', 'lat', 'lng',
        'gender_identity', 'sexual_orientation', 'trusted_contact_phone', 'authorization', 'cookie',
    ];

    private function __construct() {}

    /**
     * Écrit une entrée immuable dans `audit_logs` et la renvoie.
     */
    public static function log(string $action, ?Model $subject = null, ?string $detail = null, ?User $actor = null): AuditLog
    {
        $actor ??= self::currentUser();
        $request = self::currentRequest();

        return AuditLog::query()->create([
            'user_id' => $actor?->getKey(),
            'actor' => self::actorLabel($actor, $request),
            'action' => Str::limit($action, 80, ''),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'detail' => $detail === null ? null : self::redactString($detail),
            'ip' => $request?->ip(),
            'user_agent' => $request === null ? null : Str::limit((string) $request->userAgent(), 255, ''),
        ]);
    }

    /**
     * Entrée sans compte, sans IP ni user-agent (P5-02 : actions anonymes — alerte anonyme, accusé par lien signé,
     * annulation d'un SOS anonyme — et tâches planifiées, avec l'acteur « système »). Ne lit jamais la requête courante,
     * ce qui protège l'anonymat même quand une requête (ou la requête factice de la console) est liée.
     */
    public static function logWithoutRequest(string $action, ?Model $subject = null, ?string $detail = null, string $actor = AuditLog::ACTOR_ANONYMOUS): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => null,
            'actor' => Str::limit($actor, 190, ''),
            'action' => Str::limit($action, 80, ''),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'detail' => $detail === null ? null : self::redactString($detail),
            'ip' => null,
            'user_agent' => null,
        ]);
    }

    /**
     * Variante pratique : le détail est un tableau (changements, contexte) sérialisé après masquage.
     *
     * @param  array<string, mixed>  $context
     */
    public static function logWith(string $action, array $context, ?Model $subject = null, ?User $actor = null): AuditLog
    {
        $json = json_encode(self::redact($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return self::log($action, $subject, $json === false ? null : $json, $actor);
    }

    /**
     * Remplace les valeurs des clés sensibles par un masque, récursivement.
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = $value === null ? null : self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::redact($value);
            }
        }

        return $data;
    }

    public static function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);
        $segments = preg_split('/[^a-z0-9]+/', $normalized) ?: [];

        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === $sensitive || in_array($sensitive, $segments, true) || str_ends_with($normalized, '_'.$sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masque les paires `clé=valeur`, `clé: valeur` ou `"clé":"valeur"` d'un texte libre pour les clés sensibles.
     */
    public static function redactString(string $text): string
    {
        $keys = implode('|', array_map(static fn (string $key): string => preg_quote($key, '/'), self::SENSITIVE_KEYS));

        $masked = preg_replace_callback(
            '/\b((?:[a-z0-9_.-]*[_.-])?(?:'.$keys.'))(["\']?)(\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^"\'\s,;}\]]*)/i',
            static fn (array $m): string => $m[1].$m[2].$m[3].($m[2] === '' ? self::MASK : $m[2].self::MASK.$m[2]),
            $text,
        );

        // `audit_logs.detail` est un TEXT MySQL : la limite est en octets, pas en caractères (accents, « » …).
        return mb_strcut($masked ?? $text, 0, self::DETAIL_MAX_BYTES);
    }

    private static function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function currentRequest(): ?Request
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return null;
        }

        $request = app()->bound('request') ? app('request') : null;

        return $request instanceof Request ? $request : null;
    }

    private static function actorLabel(?User $actor, ?Request $request): string
    {
        if ($actor !== null) {
            return Str::limit((string) ($actor->email ?? $actor->pseudo ?? $actor->getKey()), 190, '');
        }

        return $request === null ? AuditLog::ACTOR_SYSTEM : AuditLog::ACTOR_ANONYMOUS;
    }
}
