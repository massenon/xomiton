<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\SocialNetwork;

/** Canal Telegram de l'organisation (Bot API, `sendMessage`) : le transport réel est un point d'extension (P14-04). */
final class TelegramPublisher extends ApiPublisher
{
    protected function network(): SocialNetwork
    {
        return SocialNetwork::Telegram;
    }
}
