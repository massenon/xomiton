<?php

declare(strict_types=1);

namespace App\Application\Admin\OnCall;

use App\Models\OnCallShift;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Création et modification d'une plage de garde (P1-17, `on_call.manage`). Les heures arrivent déjà en UTC ; la zone
 * n'a de sens que pour un point focal (OnCallRoster ignore la zone d'une garde admin) et est donc effacée sinon.
 * Journal « on_call.created » / « on_call.updated » : compte de garde, type, zone, plage UTC ; jamais les notes.
 */
final class SaveOnCallShift
{
    /**
     * @param  array{kind: string, user_id: int|string, zone_id?: int|string|null, starts_at: CarbonInterface, ends_at: CarbonInterface, notes?: string|null}  $data
     */
    public function __invoke(User $by, array $data, ?OnCallShift $shift = null): OnCallShift
    {
        $shift === null
            ? Gate::forUser($by)->authorize('create', OnCallShift::class)
            : Gate::forUser($by)->authorize('update', $shift);

        $isFocal = $data['kind'] === OnCallShift::KIND_FOCAL;
        $creating = $shift === null;
        $shift ??= new OnCallShift;
        $shift->fill([
            'kind' => $data['kind'],
            'user_id' => (int) $data['user_id'],
            'zone_id' => $isFocal && ($data['zone_id'] ?? null) !== null && $data['zone_id'] !== '' ? (int) $data['zone_id'] : null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ]);
        $shift->save();

        Audit::log($creating ? 'on_call.created' : 'on_call.updated', $shift, self::detail($shift), $by);

        return $shift;
    }

    public static function detail(OnCallShift $shift): string
    {
        return sprintf(
            'compte=%d ; type=%s ; zone=%s ; du %s au %s (UTC)',
            (int) $shift->user_id,
            $shift->kind,
            $shift->zone_id === null ? 'nationale' : (string) $shift->zone_id,
            $shift->starts_at->copy()->utc()->format('Y-m-d H:i'),
            $shift->ends_at->copy()->utc()->format('Y-m-d H:i'),
        );
    }
}
