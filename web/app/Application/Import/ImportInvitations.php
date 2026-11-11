<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\InvitationStatus;
use App\Models\Follow;
use App\Models\Invitation;

/**
 * invitations.json → invitations (from_user / to_user → FK, status, date → created_at) ; une invitation
 * acceptée crée aussi le lien dans follows. Clé naturelle : le couple (from_user_id, to_user_id).
 */
final class ImportInvitations extends AbstractImporter
{
    public static function collection(): string
    {
        return 'invitations';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $fromId = $this->ctx->userId($row['from_user'] ?? null)
            ?? $this->reject(sprintf('expéditeur·rice #%s inconnu·e', (string) ($row['from_user'] ?? '?')));
        $toId = $this->ctx->userId($row['to_user'] ?? null)
            ?? $this->reject(sprintf('destinataire #%s inconnu·e', (string) ($row['to_user'] ?? '?')));

        if ($fromId === $toId) {
            $this->reject('une personne ne peut pas s’inviter elle-même');
        }

        $status = InvitationStatus::tryFrom((string) ($row['status'] ?? ''))
            ?? $this->reject(sprintf('statut inconnu « %s »', (string) ($row['status'] ?? '')));
        $createdAt = $this->ctx->dateTime($row['date'] ?? null) ?? now();

        $invitation = Invitation::query()->firstOrNew(['from_user_id' => $fromId, 'to_user_id' => $toId]);
        $invitation->status = $status;

        if (! $status->isPending()) {
            $invitation->responded_at ??= $createdAt;
        }

        $invitation->created_at = $createdAt;
        $invitation->save();

        if ($status === InvitationStatus::Accepted) {
            Follow::query()->firstOrCreate(['follower_id' => $fromId, 'followed_id' => $toId]);
        }

        $this->track($invitation);
    }
}
