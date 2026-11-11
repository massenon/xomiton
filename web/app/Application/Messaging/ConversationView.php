<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Une conversation telle que la voit une personne (P8-01) : sa participation, le dernier message qu'elle peut lire, ses
 * non lus, l'autre personne d'une conversation privée et ce qu'elle peut y faire. Construite par ConversationDirectory,
 * rendue par App\Http\Resources\Messaging\ConversationResource (aucun attribut calculé posé sur le modèle).
 * `participants` : participant·es présent·es, soi compris.
 */
final readonly class ConversationView
{
    /**
     * @param  Collection<int, ConversationParticipant>  $participants
     */
    public function __construct(
        public Conversation $conversation,
        public User $viewer,
        public ?ConversationParticipant $me,
        public Collection $participants,
        public ?User $other,
        public ?Message $lastMessage,
        public int $unread,
        public bool $previewVisible,
        public bool $canSend,
        public bool $blockedByMe,
        public bool $canManage,
    ) {}

    public function isGroup(): bool
    {
        return $this->conversation->isGroup();
    }

    public function isMuted(): bool
    {
        return $this->me?->isMuted() ?? false;
    }
}
