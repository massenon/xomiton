<?php

declare(strict_types=1);

namespace App\Application\Community\Data;

use App\Models\Forum;

/**
 * Sujet de forum à ouvrir (POST /api/v1/forums/topics, P8-05 ; modale « Nouveau sujet » de forums.php l.103-110), validé
 * par App\Http\Requests\Api\Forums\StoreTopicRequest : forum actif, titre (5–140), message (10–5000), anonyme ou non.
 */
final readonly class TopicDraft
{
    public function __construct(
        public Forum $forum,
        public string $title,
        public string $body,
        public bool $anonymous,
    ) {}
}
