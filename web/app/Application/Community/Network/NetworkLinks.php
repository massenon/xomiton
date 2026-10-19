<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Adresses de /qui-suivre (P8-06) : onglets du prototype (`?tab=recues`, `?tab=envoyees`, who-to-follow.php l.50) et
 * invitation préparée depuis la carte « Qui suivre » du fil (`?invite=<id>`, inc/render.php l.80). Repli sur le chemin
 * quand la route nommée n'existe pas (tests unitaires, console).
 */
final class NetworkLinks
{
    /** Onglet interne → valeur du paramètre `tab` de l'adresse (prototype). */
    public const TABS = ['suggestions' => null, 'received' => 'recues', 'sent' => 'envoyees'];

    private function __construct() {}

    public static function index(?string $tab = null): string
    {
        $query = array_filter(['tab' => self::TABS[$tab ?? 'suggestions'] ?? null]);

        return self::url($query);
    }

    public static function invite(int $userId): string
    {
        return self::url(['invite' => $userId]);
    }

    /**
     * Profil public d'un·e membre (`/membres/{pseudo}`, P8-07) ; null tant que la route n'existe pas.
     */
    public static function profile(User $user): ?string
    {
        return Route::has('membres.show') ? route('membres.show', [(string) $user->pseudo]) : null;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private static function url(array $query): string
    {
        $base = Route::has('qui-suivre') ? route('qui-suivre') : url('/qui-suivre');

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }
}
