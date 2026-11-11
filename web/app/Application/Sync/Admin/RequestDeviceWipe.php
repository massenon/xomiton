<?php

declare(strict_types=1);

namespace App\Application\Sync\Admin;

use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Effacement à distance d'un appareil de l'application (P15-11, volet serveur ; permission `device.wipe`, admin et
 * superadmin). L'appareil est marqué « à effacer » (`wipe_requested_at`, motif de la liste fermée) et **révoqué** dans le
 * même geste : il n'obtient plus aucune donnée (403 `device_revoked` sur toute requête portant son `X-Device-Id`) et ses
 * abonnements push sont supprimés. Seuls `GET me`, `POST heartbeat` et `POST me/devices/{uuid}/wiped` lui répondent
 * encore, pour qu'il lise l'ordre (`device.wipe`) et l'accuse. Idempotent : un appareil déjà marqué n'est pas re-marqué
 * (la date et le motif d'origine restent) ; un appareil déjà effacé ne l'est plus. Journal `device.wipe_requested`
 * (libellé et motif, jamais l'UUID).
 *
 * Trois déclencheurs : l'action de l'administration (web Livewire et `admin/devices/{id}/wipe`), la personne elle-même pour
 * un de ses appareils (`POST me/devices/{uuid}/wipe`, mot de passe ressaisi) et le **retrait du rôle
 * pair** (`forAccount`, sans contrôle de permission : l'appelant a déjà été autorisé à retirer le rôle) qui marque tous les
 * appareils encore actifs de la personne.
 */
final class RequestDeviceWipe
{
    public const REASON_ADMIN = 'admin';

    public const REASON_PEER_ROLE_REMOVED = 'peer_role_removed';

    /** La personne efface elle-même un de ses appareils (téléphone perdu ou volé), depuis un autre appareil. */
    public const REASON_OWNER = 'owner';

    /** @var list<string> */
    public const REASONS = [self::REASON_ADMIN, self::REASON_PEER_ROLE_REMOVED, self::REASON_OWNER];

    /**
     * Action de l'administration sur un appareil.
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $by, SyncDevice $device): SyncDevice
    {
        if (! $by->can('device.wipe')) {
            throw new AuthorizationException;
        }

        $this->mark($device, self::REASON_ADMIN, $by);

        return $device->refresh();
    }

    /**
     * La personne efface un de SES appareils (téléphone perdu ou volé) : `POST me/devices/{uuid}/wipe`. Le mot de passe a
     * été ressaisi par l'appelant ; un appareil d'un autre compte n'est jamais touché (AuthorizationException).
     *
     * @throws AuthorizationException
     */
    public function byOwner(User $owner, SyncDevice $device): SyncDevice
    {
        if ((int) $device->user_id !== (int) $owner->getKey()) {
            throw new AuthorizationException;
        }

        $this->mark($device, self::REASON_OWNER, $owner);

        return $device->refresh();
    }

    /**
     * Marque tous les appareils actifs d'un compte (retrait du rôle pair).
     *
     * @return int nombre d'appareils nouvellement marqués
     */
    public function forAccount(User $target, string $reason, ?User $by = null): int
    {
        $marked = 0;

        $target->syncDevices()->active()->get()->each(function (SyncDevice $device) use ($reason, $by, &$marked): void {
            if ($this->mark($device, $reason, $by)) {
                $marked++;
            }
        });

        return $marked;
    }

    private function mark(SyncDevice $device, string $reason, ?User $by): bool
    {
        $changed = DB::transaction(function () use ($device, $reason): bool {
            $locked = SyncDevice::query()->lockForUpdate()->find($device->getKey());

            if ($locked === null || $locked->wiped_at !== null || $locked->wipe_requested_at !== null) {
                return false;
            }

            $locked->forceFill([
                'wipe_requested_at' => now(),
                'wipe_reason' => in_array($reason, self::REASONS, true) ? $reason : self::REASON_ADMIN,
                'is_revoked' => true,
            ])->save();

            PushSubscription::query()->where('sync_device_id', $locked->getKey())->delete();

            return true;
        });

        if ($changed) {
            Audit::log('device.wipe_requested', $device, sprintf('appareil=%s ; motif=%s', $device->label, $reason), $by);
        }

        return $changed;
    }
}
