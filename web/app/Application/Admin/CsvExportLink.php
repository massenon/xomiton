<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Lien d'export CSV d'un écran d'administration (P10-02, ADR-33, revue admin-socle) : URL signée de courte durée
 * (`xomiton.exports.link_minutes`) dont l'unique paramètre `t` est chiffré (écran, compte, export complet ou non, état
 * des filtres : la recherche n'apparaît donc ni dans l'URL ni dans les journaux du serveur). Le lien est lié au compte
 * qui l'a demandé ; le contrôleur rejoue les mêmes contrôles d'accès que l'action Livewire.
 */
final class CsvExportLink
{
    public const ROUTE = 'admin.export';

    private function __construct() {}

    /**
     * @param  class-string<Component>  $screen
     * @param  array<string, mixed>  $state
     */
    public static function make(string $screen, User $user, array $state, bool $full): string
    {
        $token = Crypt::encryptString((string) json_encode([
            'c' => $screen,
            'u' => (string) $user->getKey(),
            'f' => $full,
            's' => $state,
        ], JSON_THROW_ON_ERROR));

        return URL::temporarySignedRoute(self::ROUTE, now()->addMinutes(max(1, (int) config('xomiton.exports.link_minutes', 2))), ['t' => $token]);
    }

    /**
     * Charge utile du lien si elle est lisible, destinée à ce compte et vise un écran WithDataTable ; sinon null.
     *
     * @return array{screen: class-string<Component>, full: bool, state: array<string, mixed>}|null
     */
    public static function read(mixed $token, User $user): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($payload) || ($payload['u'] ?? null) !== (string) $user->getKey()) {
            return null;
        }

        $screen = $payload['c'] ?? null;

        if (! is_string($screen) || ! class_exists($screen) || ! is_subclass_of($screen, Component::class)
            || ! in_array(WithDataTable::class, class_uses_recursive($screen), true)) {
            return null;
        }

        return ['screen' => $screen, 'full' => ($payload['f'] ?? false) === true, 'state' => is_array($payload['s'] ?? null) ? $payload['s'] : []];
    }
}
