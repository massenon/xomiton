<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Domain\Messaging\Exceptions\ParticipantLimitReached;
use App\Domain\Messaging\Exceptions\RecipientUnavailable;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * POST conversations (P8-01) : conversation privée ou de groupe.
 *
 *  - privée : **une seule par paire** ; si elle existe déjà, elle est rendue (et réaffichée pour qui l'avait quittée),
 *    sans nouveau contrôle (un blocage s'y lit par `can_send`) ; sinon la personne doit être joignable
 *    (MessagingContacts::canContact), réponse neutre `recipient_unavailable` dans le cas contraire. Verrou par paire
 *    (cache fichier, D7) contre deux créations simultanées ;
 *  - groupe : titre, autres participant·es (distinct·es, toutes joignables, au plus `group_max_participants` en tout,
 *    **aucun blocage entre deux d'entre elles**, pas seulement avec la créatrice), la personne qui crée en est admin. Le rattachement à un groupe de la communauté (`group_id`) revient à P8-02.
 */
final class StartConversation
{
    private const LOCK_SECONDS = 5;

    public function __construct(private readonly MessagingContacts $contacts) {}

    /**
     * @return array{0: Conversation, 1: bool} conversation, vrai si elle vient d'être créée
     */
    public function private(User $viewer, User $other): array
    {
        if ($other->is($viewer)) {
            throw new RecipientUnavailable;
        }

        $pair = [min((int) $viewer->getKey(), (int) $other->getKey()), max((int) $viewer->getKey(), (int) $other->getKey())];

        return Cache::lock('messaging:private:'.implode('-', $pair), self::LOCK_SECONDS)
            ->block(self::LOCK_SECONDS, function () use ($viewer, $other): array {
                $existing = $this->existingPrivate($viewer, $other);

                if ($existing !== null) {
                    ConversationParticipant::query()->where('conversation_id', $existing->getKey())
                        ->where('user_id', $viewer->getKey())->whereNotNull('left_at')->update(['left_at' => null]);

                    return [$existing, false];
                }

                if (! $this->contacts->canContact($viewer, $other)) {
                    throw new RecipientUnavailable;
                }

                return [DB::transaction(fn (): Conversation => $this->create($viewer, ConversationKind::Private, null, [$other])), true];
            });
    }

    /**
     * @param  list<int>  $userIds
     */
    public function group(User $viewer, string $title, array $userIds): Conversation
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn (int $id): bool => $id !== (int) $viewer->getKey())));
        $max = (int) config('xomiton.messaging.group_max_participants', 50);

        if ($ids === []) {
            throw new RecipientUnavailable('user_ids');
        }

        if (count($ids) + 1 > $max) {
            throw new ParticipantLimitReached($max);
        }

        $users = User::query()->with('profile')->whereKey($ids)->get();

        if ($users->count() !== count($ids) || $users->contains(fn (User $user): bool => ! $this->contacts->canContact($viewer, $user))
            || $this->contacts->blockAmong($ids, [(int) $viewer->getKey()])) {
            throw new RecipientUnavailable('user_ids');
        }

        return DB::transaction(fn (): Conversation => $this->create($viewer, ConversationKind::Group, trim($title), $users->all()));
    }

    public function existingPrivate(User $a, User $b): ?Conversation
    {
        return Conversation::query()->private()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a->getKey()))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b->getKey()))
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<int, User>  $others
     */
    private function create(User $viewer, ConversationKind $kind, ?string $title, array $others): Conversation
    {
        $conversation = Conversation::query()->create([
            'kind' => $kind,
            'title' => $title === '' ? null : $title,
            'created_by' => $viewer->getKey(),
        ]);

        $now = now();
        $rows = [[
            'conversation_id' => $conversation->getKey(),
            'user_id' => $viewer->getKey(),
            'role' => $kind->isGroup() ? ConversationParticipant::ROLE_ADMIN : ConversationParticipant::ROLE_MEMBER,
            'created_at' => $now,
            'updated_at' => $now,
        ]];

        foreach ($others as $other) {
            $rows[] = [
                'conversation_id' => $conversation->getKey(),
                'user_id' => $other->getKey(),
                'role' => ConversationParticipant::ROLE_MEMBER,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ConversationParticipant::query()->insert($rows);

        return $conversation;
    }
}
