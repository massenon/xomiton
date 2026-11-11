<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\SocialNetwork;

/** Page Facebook de l'organisation (Graph API, `POST /{page}/feed`) : le transport réel est un point d'extension (P14-04). */
final class FacebookPublisher extends ApiPublisher
{
    protected function network(): SocialNetwork
    {
        return SocialNetwork::Facebook;
    }
}
