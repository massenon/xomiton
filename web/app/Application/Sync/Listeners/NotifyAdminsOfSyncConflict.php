<?php

declare(strict_types=1);

namespace App\Application\Sync\Listeners;

use App\Domain\Sync\Events\SyncConflictDetected;
use App\Models\SyncConflict;
use App\Models\User;
use App\Notifications\SyncConflict as SyncConflictNotification;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * SyncConflictDetected → cloche admin SyncConflict pour les comptes actifs détenant `sync.manage` (NOTIFICATIONS §3) et
 * journal `sync.conflict_detected` sans les valeurs en conflit (P3-08). Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class NotifyAdminsOfSyncConflict
{
    public const PERMISSION = 'sync.manage';

    public function handle(SyncConflictDetected $event): void
    {
        $conflict = SyncConflict::query()->find($event->conflictId);

        if ($conflict === null) {
            return;
        }

        $subjectLabel = class_basename($event->subjectType).' #'.$event->subjectId;

        Audit::log('sync.conflict_detected', $conflict, sprintf('appareil=%d ; sujet=%s ; champ=%s', $event->deviceId, $subjectLabel, $event->field), null);

        $admins = self::admins();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new SyncConflictNotification(
            conflictId: (int) $conflict->getKey(),
            subjectLabel: $subjectLabel,
            field: $event->field,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    public static function admins(): Collection
    {
        try {
            return User::query()->active()->permission(self::PERMISSION)->orderBy('id')->get();
        } catch (PermissionDoesNotExist $exception) {
            Log::warning('Synchronisation : permission absente, aucun destinataire.', ['error' => $exception->getMessage()]);

            return new Collection;
        }
    }
}
