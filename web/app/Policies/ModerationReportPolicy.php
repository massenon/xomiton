<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ModerationReport;
use App\Models\User;

/**
 * Signalements de contenu : tout membre du réseau signale (`social.access`) ; le traitement relève de `moderation.manage`.
 */
final class ModerationReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('moderation.manage');
    }

    public function view(User $user, ModerationReport $report): bool
    {
        if ($user->can('moderation.manage')) {
            return true;
        }

        return $report->reporter_user_id !== null && (int) $report->reporter_user_id === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function handle(User $user, ModerationReport $report): bool
    {
        return $user->can('moderation.manage');
    }

    public function update(User $user, ModerationReport $report): bool
    {
        return $user->can('moderation.manage');
    }

    public function delete(User $user, ModerationReport $report): bool
    {
        return $user->can('moderation.manage');
    }
}
