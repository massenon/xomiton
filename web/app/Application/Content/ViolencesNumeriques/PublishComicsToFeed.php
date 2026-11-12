<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\CccTool;
use App\Models\Post;
use App\Models\PostMedia;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publie chaque BD du pilote **une seule fois** dans le fil social, comme une actualité (P19-11 partiel, Q98 point 5) :
 * émetteur officiel (OfficialAccount), texte de partage (`share.text`), les planches en images (≤ `feed.max_images`,
 * chemins `assets/…` du disque public : PostLinks les sert tels quels) avec leur texte alternatif, puis le lien vers
 * `/bd/{key}` ; visibilité publique, étiquette « Ressource ».
 *
 * Idempotent : `ccc_tools.feed_post_id` garde la publication créée ; tant qu'elle existe (même supprimée par la
 * modération ou masquée), la BD n'est jamais republiée. Seules les BD publiées **et** publiques sont annoncées.
 * Aucune notification n'est envoyée : la publication est créée directement (sans PublishPost ni écouteur), le fil la
 * montre à la prochaine lecture — pas d'envoi massif aux membres.
 */
final class PublishComicsToFeed
{
    public function __construct(
        private readonly ViolencesNumeriquesSource $source,
        private readonly OfficialAccount $account,
    ) {}

    /**
     * @return array{published: list<string>, skipped: list<string>}
     */
    public function __invoke(): array
    {
        $report = ['published' => [], 'skipped' => []];
        $author = null;

        foreach ($this->source->categories() as $category) {
            foreach ($category['topics'] as $topic) {
                if ($topic['status'] !== ViolencesNumeriquesSource::STATUS_PILOT) {
                    continue;
                }

                $data = $this->source->topic($topic['slug']);
                $key = (string) $data['key'];
                $tool = CccTool::query()->where('key', $key)->first();

                if ($tool === null || ! $tool->is_published || ! $tool->is_public || $this->alreadyPublished($tool)) {
                    $report['skipped'][] = $key;

                    continue;
                }

                $author ??= $this->account->resolve();
                $this->publish($tool, (string) ($data['share']['text'] ?? ''), $author->getKey());
                $report['published'][] = $key;
            }
        }

        return $report;
    }

    private function alreadyPublished(CccTool $tool): bool
    {
        return $tool->feed_post_id !== null && Post::query()->withTrashed()->whereKey($tool->feed_post_id)->exists();
    }

    private function publish(CccTool $tool, string $text, mixed $authorId): void
    {
        $url = route('bd.show', ['key' => $tool->key]);
        $body = trim($text) === '' ? (string) $tool->title : trim($text);
        $body = Str::limit($body, 3000 - mb_strlen($url) - 2, '…')."\n\n".$url;
        $max = max(1, (int) config('xomiton.feed.max_images', 4));
        $pages = array_slice(array_values(array_filter((array) $tool->pages, 'is_array')), 0, $max);

        DB::transaction(function () use ($tool, $body, $authorId, $pages): void {
            $post = new Post;
            $post->forceFill([
                'ulid' => (string) Str::ulid(),
                'user_id' => $authorId,
                'is_anonymous' => false,
                'body' => $body,
                'tag' => PostTag::Resource,
                'visibility' => PostVisibility::Public,
                'status' => PostStatus::Published,
                'published_at' => now(),
            ])->save();

            foreach ($pages as $position => $page) {
                $path = ltrim((string) ($page['image'] ?? ''), '/');
                $file = public_path($path);

                PostMedia::query()->create([
                    'post_id' => $post->getKey(),
                    'kind' => PostMedia::KIND_IMAGE,
                    'path' => $path,
                    'mime' => str_ends_with($path, '.webp') ? 'image/webp' : null,
                    'alt' => Str::limit(trim((string) ($page['alt'] ?? '')), 500, ''),
                    'width' => MediaUrl::exists($path) ? self::dimension($file, 0) : null,
                    'height' => MediaUrl::exists($path) ? self::dimension($file, 1) : null,
                    'size' => is_file($file) ? (int) filesize($file) : null,
                    'position' => $position,
                ]);
            }

            $tool->forceFill(['feed_post_id' => $post->getKey()])->save();
        });
    }

    private static function dimension(string $file, int $index): ?int
    {
        $size = is_file($file) ? @getimagesize($file) : false;

        return is_array($size) ? (int) $size[$index] : null;
    }
}
