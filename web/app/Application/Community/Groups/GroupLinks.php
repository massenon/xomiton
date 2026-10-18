<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Route;

/**
 * Adresses d'un groupe (ADR-27 : par ULID, jamais par id) : fiche `/groupes/{ulid}` et onglet (`?tab=members` pour
 * l'équipe qui reçoit une demande d'adhésion).
 */
final class GroupLinks
{
    private function __construct() {}

    public static function show(Group $group, ?string $tab = null): string
    {
        $parameters = ['group' => $group->ulid] + ($tab !== null ? ['tab' => $tab] : []);

        return Route::has('groupes.show') ? route('groupes.show', $parameters) : url('/groupes/'.$group->ulid.($tab !== null ? '?tab='.$tab : ''));
    }
}
