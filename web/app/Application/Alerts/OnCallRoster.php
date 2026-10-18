<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/**
 * Référentiel de la garde (P1-17) : qui prévenir, à un instant donné, parmi les destinataires « admin de garde »,
 * « point focal de zone » et « point focal national » de NOTIFICATIONS_INSTRUCTION.md §2. Lecture seule, aucune
 * notification ici (`SosDispatcher` P5-03, `alerts:escalate` P5-07).
 *
 * - Admins de garde : comptes actifs des plages `on_call_shifts` de type `admin` en cours ; sans plage, repli
 *   sur les comptes actifs de rôle admin ou superadmin détenant la permission `alerts.take`.
 * - Points focaux : une plage de type `focal` en cours désigne un·e remplaçant·e (compte lié à une fiche
 *   d'annuaire active) ; sinon la fiche `directory_contacts` active du point focal (national : `is_national_focal`).
 */
final class OnCallRoster
{
    public const PERMISSION = 'alerts.take';

    /** @var list<string> */
    public const FALLBACK_ROLES = ['admin', 'superadmin'];

    /**
     * Admins de garde à l'instant donné (par défaut maintenant), ou repli sur les admins habilités.
     *
     * @return Collection<int, User>
     */
    public function adminsOnCall(?CarbonInterface $at = null): Collection
    {
        $at ??= now();

        $onCall = User::query()
            ->active()
            ->whereHas('onCallShifts', fn (Builder $shift) => $shift->active($at)->forKind(OnCallShift::KIND_ADMIN))
            ->orderBy('id')
            ->get();

        return $onCall->isNotEmpty() ? $onCall : $this->fallbackAdmins();
    }

    /**
     * Repli sans planning : comptes actifs admin ou superadmin détenant `alerts.take`.
     *
     * @return Collection<int, User>
     */
    public function fallbackAdmins(): Collection
    {
        try {
            return User::query()
                ->active()
                ->role(self::FALLBACK_ROLES)
                ->permission(self::PERMISSION)
                ->orderBy('id')
                ->get();
        } catch (PermissionDoesNotExist|RoleDoesNotExist $exception) {
            Log::warning('Garde : rôles ou permission absents, aucun admin de repli.', ['error' => $exception->getMessage()]);

            return new Collection;
        }
    }

    public function hasAdminOnCall(?CarbonInterface $at = null): bool
    {
        return OnCallShift::query()->active($at ?? now())->forKind(OnCallShift::KIND_ADMIN)
            ->whereHas('user', fn (Builder $user) => $user->active())
            ->exists();
    }

    /**
     * Point focal national (RG-05) : remplaçant·e de garde, sinon fiche d'annuaire `is_national_focal` active.
     */
    public function nationalFocal(?CarbonInterface $at = null): ?DirectoryContact
    {
        return $this->focalFromShift(null, $at ?? now())
            ?? DirectoryContact::query()->active()->nationalFocal()->ordered()->first();
    }

    /**
     * Point focal d'un département (RG-04) : remplaçant·e de garde, sinon fiche d'annuaire active de rôle
     * point focal dans la zone.
     */
    public function zoneFocal(Zone $zone, ?CarbonInterface $at = null): ?DirectoryContact
    {
        return $this->focalFromShift($zone, $at ?? now())
            ?? DirectoryContact::query()->active()->focal()->inZone($zone)->ordered()->first();
    }

    /**
     * Remplaçant·e de garde seulement (plage `focal` en cours), sans repli sur l'annuaire : le routage RG-04
     * (RouteAlert) applique lui-même son repli par identifiant croissant (parité `route_alert()`).
     */
    public function focalOnDuty(?Zone $zone, ?CarbonInterface $at = null): ?DirectoryContact
    {
        return $this->focalFromShift($zone, $at ?? now());
    }

    /**
     * Fiche d'annuaire active liée au compte d'une plage `focal` en cours (nationale si `$zone` est nul).
     */
    private function focalFromShift(?Zone $zone, CarbonInterface $at): ?DirectoryContact
    {
        $shifts = OnCallShift::query()
            ->active($at)
            ->forKind(OnCallShift::KIND_FOCAL)
            ->forZone($zone)
            ->whereHas('user', fn (Builder $user) => $user->active())
            ->chronological()
            ->get();

        foreach ($shifts as $shift) {
            $contact = DirectoryContact::query()->active()->linkedTo((int) $shift->user_id)->first();

            if ($contact !== null) {
                return $contact;
            }
        }

        return null;
    }
}
