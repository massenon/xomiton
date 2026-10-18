<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use App\Models\ConversationParticipant;
use App\Models\User;

/**
 * Personne d'une conversation telle qu'elle sort de l'API (RG-02) : pseudonyme et avatar, jamais le nom réel ni
 * l'e-mail ; rôle dans la conversation (`admin` / `member`).
 */
final class ParticipantPayload
{
    private function __construct() {}

    /**
     * @return array{id: int, pseudo: string, avatar_url: string, role: string|null, is_me: bool}
     */
    public static function for(User $user, ?ConversationParticipant $participant = null, ?User $viewer = null): array
    {
        return [
            'id' => (int) $user->getKey(),
            'pseudo' => (string) $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'role' => $participant?->role,
            'is_me' => $viewer !== null && $user->is($viewer),
        ];
    }
}
