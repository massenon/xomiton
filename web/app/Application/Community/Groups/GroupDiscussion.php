<?php

declare(strict_types=1);

namespace App\Application\Community\Groups;

use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * « Discussion du groupe » (P8-02, group-details.php l.53 ; rattachement `conversations.group_id` laissé par P8-01) :
 * une seule conversation de groupe par groupe de la communauté, créée au premier clic d'une membre active (titre : nom
 * du groupe), sous verrou (cache fichier, D7) contre deux créations simultanées. Chaque membre y entre en ouvrant la
 * discussion (réintégrée si elle l'avait quittée) ; l'équipe du groupe (propriétaire, admins, modérateur·rices) y est
 * `admin`. Le rôle n'est fixé qu'à l'entrée ou au retour (un rôle posé ensuite par la messagerie n'est pas écrasé à
 * chaque clic) et suit les changements de rôle du groupe et la cession de propriété (`syncRole`). Quitter le groupe
 * (`left`) ou en être retirée (`removed`) fait quitter la discussion, par le modèle (ConversationParticipant::booted).
 * Plafond `messaging.group_max_participants` non appliqué : la discussion suit les membres du groupe.
 */
final class GroupDiscussion
{
    private const LOCK_SECONDS = 5;

    /**
     * @return array{0: Conversation, 1: bool} conversation, vrai si elle vient d'être créée
     */
    public function open(User $user, Group $group): array
    {
        return Cache::lock('messaging:community-group:'.$group->getKey(), self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, function () use ($user, $group): array {
                return DB::transaction(function () use ($user, $group): array {
                    $conversation = $this->find($group);
                    $created = $conversation === null;

                    $conversation ??= Conversation::query()->create([
                        'kind' => ConversationKind::Group,
                        'title' => (string) $group->name,
                        'group_id' => $group->getKey(),
                        'created_by' => $group->owner_id ?? $user->getKey(),
                    ]);

                    $participant = ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
                        ->where('user_id', $user->getKey())->lockForUpdate()->first();

                    if ($participant === null || $participant->hasLeft()) {
                        // Entrée ou retour : rôle selon l'équipe du groupe (sinon, celui de la messagerie est gardé).
                        ($participant ?? new ConversationParticipant(['conversation_id' => $conversation->getKey(), 'user_id' => $user->getKey()]))
                            ->forceFill(['left_at' => null, 'role' => $this->roleFor((int) $user->getKey(), $group)])
                            ->save();
                    }

                    return [$conversation, $created];
                });
            });
    }

    /**
     * Fait quitter la discussion (`left_reason` : ConversationParticipant::LEFT_SELF ou LEFT_REMOVED).
     */
    public function remove(Group $group, User $user, string $reason = ConversationParticipant::LEFT_SELF): void
    {
        $participant = $this->participant($group, (int) $user->getKey());

        $participant?->forceFill(['left_at' => now(), 'left_reason' => $reason])->save();
    }

    /**
     * Reporte le rôle du groupe sur la discussion (changement de rôle, cession de propriété) ; sans effet si la
     * personne n'y est pas (encore) entrée.
     */
    public function syncRole(Group $group, int $userId): void
    {
        $participant = $this->participant($group, $userId);
        $role = $this->roleFor($userId, $group);

        if ($participant !== null && $participant->role !== $role) {
            $participant->forceFill(['role' => $role])->save();
        }
    }

    public function find(Group $group): ?Conversation
    {
        return Conversation::query()->groups()->where('group_id', $group->getKey())->orderBy('id')->first();
    }

    private function participant(Group $group, int $userId): ?ConversationParticipant
    {
        $conversation = $this->find($group);

        if ($conversation === null) {
            return null;
        }

        return ConversationParticipant::query()->active()->where('conversation_id', $conversation->getKey())
            ->where('user_id', $userId)->first();
    }

    /** Propriétaire, admin ou modérateur·rice active du groupe : admin de la discussion ; sinon membre. */
    private function roleFor(int $userId, Group $group): string
    {
        if ((int) $group->owner_id === $userId) {
            return ConversationParticipant::ROLE_ADMIN;
        }

        $role = GroupMember::query()->where('group_id', $group->getKey())->where('user_id', $userId)->active()->value('role');

        return $role !== null && $role->canModerate() ? ConversationParticipant::ROLE_ADMIN : ConversationParticipant::ROLE_MEMBER;
    }
}
