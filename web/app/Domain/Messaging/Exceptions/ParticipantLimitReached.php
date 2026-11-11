<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Conversation de groupe pleine (`xomiton.messaging.group_max_participants`).
 */
final class ParticipantLimitReached extends DomainException
{
    public function __construct(int $max)
    {
        parent::__construct('community.messages.api.participants_max', ['max' => $max]);
    }

    public function errorCode(): string
    {
        return 'participants_max';
    }

    public function errors(): array
    {
        return ['user_ids' => [$this->userMessage()]];
    }
}
