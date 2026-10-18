<?php

declare(strict_types=1);

namespace App\Application\Feed\Data;

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use Illuminate\Http\UploadedFile;

/**
 * Publication à créer (POST /api/v1/posts, P7-02), validée par App\Http\Requests\Api\Feed\StorePostRequest :
 * texte (≤ 3000), étiquette, visibilité, pseudonyme, image facultative (MediaUploader, collection `images`) et, pour
 * un sondage, son PollDraft (l'étiquette est alors `sondage`).
 */
final readonly class PostDraft
{
    public function __construct(
        public ?string $body,
        public PostTag $tag,
        public PostVisibility $visibility,
        public bool $anonymous,
        public ?UploadedFile $image = null,
        public ?string $imageAlt = null,
        public ?PollDraft $poll = null,
    ) {}
}
