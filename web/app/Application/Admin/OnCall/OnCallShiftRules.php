<?php

declare(strict_types=1);

namespace App\Application\Admin\OnCall;

use App\Application\Alerts\OnCallRoster;
use App\Enums\DirectoryRole;
use App\Models\OnCallShift;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

/**
 * Règles d'une plage de garde (P1-17), calquées sur ce que lit OnCallRoster :
 * - `admin` : tous les comptes admin de garde à l'instant donné sont prévenus (la zone est ignorée) ; plages de
 *   personnes différentes qui se chevauchent = deux admins de garde, cohérent ; deux plages de la MÊME personne qui
 *   se chevauchent = doublon, refusé ;
 * - `focal` : une seule personne est retenue par zone (la plus ancienne plage en cours : `focalFromShift`) ; un
 *   chevauchement dans la même zone (ou au niveau national) rendrait la seconde plage muette, donc refusé. La
 *   personne doit être liée à une fiche d'annuaire active de rôle point focal, sinon la plage est ignorée au routage ;
 * - la plage est « début inclus, fin exclue » : une plage qui commence quand l'autre finit ne la chevauche pas.
 */
final class OnCallShiftRules
{
    /** Durée maximale d'une plage (jours) : au-delà, c'est une erreur de saisie plus qu'un tour de garde. */
    public const MAX_DAYS = 31;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(OnCallShift::KINDS)],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Comptes éligibles pour un type de plage.
     *
     * @return Collection<int, User>
     */
    public function eligibleUsers(string $kind): Collection
    {
        $query = User::query()->active()->select(['id', 'pseudo'])->orderBy('pseudo')->orderBy('id');

        if ($kind === OnCallShift::KIND_FOCAL) {
            return $query->whereHas('directoryContact', fn (Builder $contact) => $contact->active()->where('role', DirectoryRole::PointFocal))->get();
        }

        return $query->role(OnCallRoster::FALLBACK_ROLES)->get();
    }

    public function isEligible(User $user, string $kind): bool
    {
        return $this->eligibleUsers($kind)->contains(fn (User $candidate): bool => (int) $candidate->getKey() === (int) $user->getKey());
    }

    /**
     * Contrôles croisés après la validation des champs : ordre des heures, durée, plage terminée, éligibilité,
     * chevauchement. Renvoie champ => message (vide si tout est cohérent).
     *
     * @param  array<string, mixed>  $data  données validées, heures déjà converties en UTC (CarbonInterface)
     * @return array<string, string>
     */
    public function crossChecks(array $data, ?OnCallShift $current, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $start = $data['starts_at'] ?? null;
        $end = $data['ends_at'] ?? null;
        $kind = (string) ($data['kind'] ?? '');
        $userId = (int) ($data['user_id'] ?? 0);
        $zoneId = isset($data['zone_id']) && $data['zone_id'] !== null ? (int) $data['zone_id'] : null;
        $problems = [];

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
            return $problems;
        }

        if ($end->lessThanOrEqualTo($start)) {
            $problems['ends_at'] = (string) __('admin_oncall.errors.order');

            return $problems;
        }

        if ($start->diffInDays($end) > self::MAX_DAYS) {
            $problems['ends_at'] = (string) __('admin_oncall.errors.too_long', ['days' => self::MAX_DAYS]);
        } elseif ($end->lessThanOrEqualTo($now)) {
            $problems['ends_at'] = (string) __('admin_oncall.errors.past');
        }

        $user = User::query()->find($userId);

        if ($user === null || ! $this->isEligible($user, $kind)) {
            $problems['user_id'] = (string) __($kind === OnCallShift::KIND_FOCAL ? 'admin_oncall.errors.not_eligible_focal' : 'admin_oncall.errors.not_eligible_admin');
        }

        if ($problems === [] && ($clash = $this->overlapping($kind, $userId, $zoneId, $start, $end, $current)) !== null) {
            $problems['starts_at'] = (string) __($kind === OnCallShift::KIND_FOCAL ? 'admin_oncall.errors.overlap_focal' : 'admin_oncall.errors.overlap_admin', [
                'name' => (string) $clash->user->pseudo,
                'from' => $clash->starts_at->copy()->timezone((string) config('xomiton.display_timezone', 'UTC'))->format('d/m/Y H:i'),
                'to' => $clash->ends_at->copy()->timezone((string) config('xomiton.display_timezone', 'UTC'))->format('d/m/Y H:i'),
            ]);
        }

        return $problems;
    }

    /**
     * Plage qui entre en conflit avec celle proposée selon la règle du type (null : aucune).
     */
    public function overlapping(string $kind, int $userId, ?int $zoneId, CarbonInterface $start, CarbonInterface $end, ?OnCallShift $ignore = null): ?OnCallShift
    {
        $query = OnCallShift::query()
            ->with('user:id,pseudo')
            ->forKind($kind)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->chronological();

        if ($ignore !== null) {
            $query->whereKeyNot($ignore->getKey());
        }

        if ($kind === OnCallShift::KIND_FOCAL) {
            $query->forZone($zoneId);
        } else {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}
