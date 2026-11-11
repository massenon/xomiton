<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Application\Shared\MediaUploader;
use App\Domain\Messaging\ConversationRules;
use App\Domain\Messaging\Exceptions\ParticipantLimitReached;
use App\Domain\Messaging\Exceptions\RecipientUnavailable;
use App\Domain\Messaging\Exceptions\WrongConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Facades\DB;

/**
 * Gestes sur une conversation (P8-01) : lecture, sourdine, départ, blocage, titre et participant·es d'un groupe,
 * suppression d'un message pour tout le monde. Les droits sont vérifiés en amont (ConversationPolicy, MessagePolicy).
 *
 *  - quitter un groupe : `left_at` + `left_reason = left` (personne ne la réintègre ensuite) ; si plus aucun·e admin
 *    n'y reste, la personne présente depuis le plus longtemps le devient. Quitter une conversation privée la **masque** :
 *    elle revient au prochain message reçu ;
 *  - ajouter à un groupe : personnes joignables, sans blocage avec aucune personne présente ni entre elles, jamais une
 *    personne partie d'elle-même (une personne retirée par un·e admin peut l'être) ; elle ne lit que la suite
 *    (ConversationParticipant : `joined_after_message_id`) ;
 *  - bloquer : conversation privée seulement, `user_blocks` (FR-SOC-04, le blocage vaut partout : fil, profil) ;
 *  - supprimer pour tout le monde : corps effacé (chaîne vide chiffrée) et pièce jointe supprimée du disque ; **message
 *    signalé** (un `moderation_reports` le vise) : seulement masqué (`deleted_for_everyone_at`), corps chiffré et pièce
 *    jointe gardés comme preuve pour la file de modération (P12-06).
 */
final class ManageConversation
{
    public function __construct(
        private readonly MessagingContacts $contacts,
        private readonly MessageNotifier $notifier,
        private readonly MediaUploader $uploader,
    ) {}

    public function markRead(User $user, Conversation $conversation, ?int $messageId): ?int
    {
        $participant = $this->participant($user, $conversation);
        $latest = Message::query()->where('conversation_id', $conversation->getKey())->max('id');
        $cursor = ConversationRules::readCursor($participant->last_read_message_id, $messageId, $latest === null ? null : (int) $latest);

        if ($cursor !== $participant->last_read_message_id) {
            $participant->forceFill(['last_read_message_id' => $cursor])->save();
        }

        $this->notifier->markConversationRead($user, $conversation);

        return $cursor;
    }

    public function mute(User $user, Conversation $conversation, ?int $minutes): ConversationParticipant
    {
        $participant = $this->participant($user, $conversation);
        $participant->forceFill(['muted_until' => ConversationRules::mutedUntil($minutes, now())])->save();

        return $participant;
    }

    public function unmute(User $user, Conversation $conversation): ConversationParticipant
    {
        $participant = $this->participant($user, $conversation);
        $participant->forceFill(['muted_until' => null])->save();

        return $participant;
    }

    public function leave(User $user, Conversation $conversation): void
    {
        DB::transaction(function () use ($user, $conversation): void {
            $participant = $this->participant($user, $conversation);
            $participant->forceFill(['left_at' => now(), 'left_reason' => ConversationParticipant::LEFT_SELF])->save();
            $this->notifier->markConversationRead($user, $conversation);

            if ($conversation->isGroup()) {
                $this->ensureAdmin($conversation);
            }
        });
    }

    public function rename(Conversation $conversation, string $title): Conversation
    {
        $this->groupOnly($conversation);
        $conversation->forceFill(['title' => trim($title)])->save();

        return $conversation;
    }

    /**
     * @param  list<int>  $userIds
     */
    public function addParticipants(User $manager, Conversation $conversation, array $userIds): void
    {
        $this->groupOnly($conversation);
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $rows = ConversationParticipant::query()->where('conversation_id', $conversation->getKey())->get()->keyBy('user_id');
        $present = $rows->reject(fn (ConversationParticipant $p): bool => $p->hasLeft())->keys()->map(fn ($id) => (int) $id)->values()->all();
        $new = array_values(array_diff($ids, $present));
        $max = (int) config('xomiton.messaging.group_max_participants', 50);

        if ($new === []) {
            return;
        }

        if (count($present) + count($new) > $max) {
            throw new ParticipantLimitReached($max);
        }

        $users = User::query()->with('profile')->whereKey($new)->get();

        // Réponse neutre dans tous les cas : ni qui bloque qui, ni qui est parti·e d'elle-même.
        $leftOnTheirOwn = collect($new)->contains(fn (int $id): bool => $rows->has($id) && ! $rows->get($id)->canBeAddedBack());

        if ($users->count() !== count($new) || $leftOnTheirOwn
            || $users->contains(fn (User $user): bool => ! $this->contacts->canContact($manager, $user))
            || $this->contacts->blockAmong($new, $present)) {
            throw new RecipientUnavailable('user_ids');
        }

        DB::transaction(function () use ($conversation, $users): void {
            // Modèle (et non insert) : l'entrée pose `joined_after_message_id` (ConversationParticipant::booted).
            foreach ($users as $user) {
                ConversationParticipant::query()->updateOrCreate(
                    ['conversation_id' => $conversation->getKey(), 'user_id' => $user->getKey()],
                    ['left_at' => null, 'role' => ConversationParticipant::ROLE_MEMBER, 'muted_until' => null],
                );
            }
        });
    }

    public function removeParticipant(Conversation $conversation, User $user): void
    {
        $this->groupOnly($conversation);

        DB::transaction(function () use ($conversation, $user): void {
            ConversationParticipant::query()->active()->where('conversation_id', $conversation->getKey())
                ->where('user_id', $user->getKey())->update(['left_at' => now(), 'left_reason' => ConversationParticipant::LEFT_REMOVED]);
            $this->ensureAdmin($conversation);
        });
    }

    public function block(User $user, Conversation $conversation): User
    {
        $other = $this->otherOf($user, $conversation);
        UserBlock::query()->firstOrCreate(['user_id' => $user->getKey(), 'blocked_user_id' => $other->getKey()]);

        return $other;
    }

    public function unblock(User $user, Conversation $conversation): User
    {
        $other = $this->otherOf($user, $conversation);
        UserBlock::query()->where('user_id', $user->getKey())->where('blocked_user_id', $other->getKey())->delete();

        return $other;
    }

    public function deleteForEveryone(Message $message): Message
    {
        if ($message->isDeletedForEveryone()) {
            return $message;
        }

        if (ModerationReport::query()->forSubject($message)->exists()) {
            $message->forceFill(['deleted_for_everyone_at' => now()])->save();

            return $message;
        }

        $path = $message->media_path;
        $message->forceFill(['body' => '', 'media_path' => null, 'deleted_for_everyone_at' => now()])->save();

        if ($path !== null && $path !== '') {
            $this->uploader->delete($path);
        }

        return $message;
    }

    private function participant(User $user, Conversation $conversation): ConversationParticipant
    {
        return ConversationParticipant::query()->active()->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())->firstOrFail();
    }

    private function otherOf(User $user, Conversation $conversation): User
    {
        if ($conversation->isGroup()) {
            throw WrongConversationKind::privateOnly();
        }

        $other = ConversationParticipant::query()->with('user')->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $user->getKey())->first()?->user;

        if ($other === null) {
            throw new RecipientUnavailable;
        }

        return $other;
    }

    private function groupOnly(Conversation $conversation): void
    {
        if (! $conversation->isGroup()) {
            throw WrongConversationKind::groupOnly();
        }
    }

    /** Un groupe garde au moins un·e admin tant qu'il reste quelqu'un. */
    private function ensureAdmin(Conversation $conversation): void
    {
        $active = ConversationParticipant::query()->active()->where('conversation_id', $conversation->getKey());

        if ((clone $active)->admins()->exists()) {
            return;
        }

        $next = (clone $active)->orderBy('created_at')->orderBy('id')->first();
        $next?->forceFill(['role' => ConversationParticipant::ROLE_ADMIN])->save();
    }
}
