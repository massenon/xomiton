<?php

declare(strict_types=1);

namespace App\Application\Feed\Data;

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use Illuminate\Http\UploadedFile;

/**
 * Publication à créer (POST /api/v1/posts, P7-02), validée par App\Http\Requests\Api\Feed\StorePostRequest :
 * texte (≤ 3000), étiquette, visibilité, pseudonyme, images facultatives (`images`, ordre conservé, jusqu'à
 * `feed.max_images` ; `imagesAlt` aligné index par index, MediaUploader, collection `images`) ou **une** vidéo avec sa
 * description (Q96 : jamais avec des images ni dans un sondage, relue par la modération avant parution) et, pour un
 * sondage, son PollDraft (l'étiquette est alors `sondage`) ; `groupId` : publication dans un groupe (P8-02 : le
 * groupe est l'audience, visibilité `public`).
 */
final readonly class PostDraft
{
    /**
     * @param  list<UploadedFile>  $images
     * @param  list<string|null>  $imagesAlt
     */
    public function __construct(
        public ?string $body,
        public PostTag $tag,
        public PostVisibility $visibility,
        public bool $anonymous,
        public array $images = [],
        public array $imagesAlt = [],
        public ?PollDraft $poll = null,
        public ?int $groupId = null,
        public ?UploadedFile $video = null,
        public ?string $videoDescription = null,
    ) {}
}
