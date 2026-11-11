<?php

declare(strict_types=1);

namespace App\Application\Moderation\Listeners;

use App\Domain\Moderation\Events\ContentReported;
use App\Enums\ModerationKind;
use App\Models\ModerationReport;
use App\Models\User;
use App\Notifications\ContentReported as ContentReportedNotification;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * ContentReported → cloche admin ContentReported pour les comptes actifs détenant `moderation.manage` (NOTIFICATIONS §3)
 * et journal `moderation.reported` sans l'extrait signalé (P3-08). Enregistré par AppServiceProvider::DOMAIN_LISTENERS.
 */
final class NotifyModerators
{
    public const PERMISSION = 'moderation.manage';

    public function handle(ContentReported $event): void
    {
        $report = ModerationReport::query()->find($event->reportId);

        if ($report === null) {
            return;
        }

        Audit::log('moderation.reported', $report, sprintf('nature=%s ; motif=%s ; signale_par=%s', $event->kind, $event->reason, $event->reporterUserId ?? 'anonyme'), null);

        $moderators = self::moderators();

        if ($moderators->isEmpty()) {
            return;
        }

        $kind = ModerationKind::tryFrom($event->kind);

        Notification::send($moderators, new ContentReportedNotification(
            reportId: (int) $report->getKey(),
            kindLabel: $kind?->label() ?? $event->kind,
            reason: $event->reason,
        ));
    }

    /**
     * @return Collection<int, User>
     */
    public static function moderators(): Collection
    {
        try {
            return User::query()->active()->permission(self::PERMISSION)->orderBy('id')->get();
        } catch (PermissionDoesNotExist $exception) {
            Log::warning('Modération : permission absente, aucun destinataire.', ['error' => $exception->getMessage()]);

            return new Collection;
        }
    }
}
