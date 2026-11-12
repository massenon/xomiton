<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\SocialNetwork;
use App\Models\SocialChannel;

/**
 * Page Facebook de l'organisation (Graph API : photos non publiées puis `POST /{page}/feed`, FacebookGraphApi ; pilote
 * `http`). La cible est l'identifiant numérique de la page (`external_id`), sinon le nom public sans « @ ».
 */
final class FacebookPublisher extends ApiPublisher
{
    protected function target(SocialChannel $channel): string
    {
        return ltrim(parent::target($channel), '@');
    }

    protected function network(): SocialNetwork
    {
        return SocialNetwork::Facebook;
    }
}
