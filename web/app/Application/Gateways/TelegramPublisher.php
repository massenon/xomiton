<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\SocialNetwork;

/**
 * Canal Telegram de l'organisation (Bot API : `sendMediaGroup`, `sendPhoto` ou `sendMessage`, TelegramBotApi ; pilote
 * `http`). La cible est l'identifiant du canal (`external_id`, ex. `-1001234567890`), sinon son nom public `@canal`.
 */
final class TelegramPublisher extends ApiPublisher
{
    protected function network(): SocialNetwork
    {
        return SocialNetwork::Telegram;
    }
}
