<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostMedia;
use ValueError;

/**
 * posts.json → posts (text → body, likes → likes_count, comments → comments_count, ts → published_at,
 * tag → PostTag, visibility « friends » → followers, user 0 → is_anonymous), image → post_media et
 * poll → polls / poll_options / poll_votes éclatés (votes {utilisateur: [options]}).
 * Clé naturelle : auteur·rice (ou anonymat), date de publication et corps.
 */
final class ImportPosts extends AbstractImporter
{
    public static function collection(): string
    {
        return 'posts';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $prototypeId = $this->prototypeId($row, $index);
        $body = $this->required($row['text'] ?? null, 'text', Post::BODY_MAX_LENGTH);
        $publishedAt = $this->ctx->dateTime($row['ts'] ?? null) ?? $this->reject('horodatage « ts » absent ou invalide');

        try {
            $tag = PostTag::fromLabel((string) ($row['tag'] ?? 'Témoignage'));
        } catch (ValueError $error) {
            $this->reject($error->getMessage());
        }

        $authorPrototypeId = (int) ($row['user'] ?? 0);
        $isAnonymous = $authorPrototypeId === 0 || ! empty($row['anonymous']);
        $userId = $authorPrototypeId > 0 ? $this->ctx->userId($authorPrototypeId) : null;

        if ($authorPrototypeId > 0 && $userId === null) {
            $this->reject(sprintf('auteur·rice #%d inconnu·e', $authorPrototypeId));
        }

        $post = Post::withTrashed()
            ->where('body', $body)
            ->where('published_at', $publishedAt)
            ->where('user_id', $userId)
            ->first() ?? new Post;

        $post->fill([
            'user_id' => $userId,
            'is_anonymous' => $isAnonymous,
            'body' => $body,
            'tag' => $tag,
            'visibility' => $this->visibility($row['visibility'] ?? null),
            'likes_count' => max(0, (int) ($row['likes'] ?? 0)),
            'comments_count' => max(0, (int) ($row['comments'] ?? 0)),
            'status' => PostStatus::Published,
            'published_at' => $publishedAt,
        ]);
        $post->created_at = $publishedAt;
        $post->deleted_at = null;
        $post->save();

        $this->syncImage($post, $row['image'] ?? null);

        if (is_array($row['poll'] ?? null)) {
            $this->syncPoll($post, $row['poll'], $prototypeId);
        }

        $this->ctx->remember('posts', $prototypeId, (int) $post->getKey());
        $this->track($post);
    }

    /** `friends` du prototype → `followers` (table de résolution du dictionnaire). */
    private function visibility(mixed $value): PostVisibility
    {
        $value = mb_strtolower(trim((string) $value));

        return match ($value) {
            'friends', 'followers' => PostVisibility::Followers,
            'me' => PostVisibility::Me,
            default => PostVisibility::Public,
        };
    }

    private function syncImage(Post $post, mixed $image): void
    {
        $path = $this->imagePath($image);

        if ($path === null) {
            return;
        }

        PostMedia::query()->firstOrCreate(
            ['post_id' => $post->getKey(), 'path' => $path],
            ['kind' => PostMedia::KIND_IMAGE, 'alt' => null, 'position' => 0],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPoll(Post $post, array $data, string|int $prototypeId): void
    {
        $question = $this->text($data['question'] ?? null, 200);

        if ($question === null) {
            $this->ctx->note(sprintf('publication #%s : sondage sans question, ignoré', (string) $prototypeId));

            return;
        }

        $poll = Poll::query()->updateOrCreate(
            ['post_id' => $post->getKey()],
            [
                'question' => $question,
                'multiple' => (bool) ($data['multiple'] ?? false),
                'results_before_vote' => (bool) ($data['results_before_vote'] ?? false),
                'ends_at' => $this->ctx->dateTime($data['ends'] ?? null),
            ],
        );

        /** @var array<string, PollOption> $options identifiant d'option du prototype (`o1`) => option */
        $options = [];

        foreach (array_values(is_array($data['options'] ?? null) ? $data['options'] : []) as $position => $option) {
            $label = $this->text(is_array($option) ? ($option['label'] ?? null) : $option, 80);

            if ($label === null) {
                continue;
            }

            $key = is_array($option) ? (string) ($option['id'] ?? 'o'.($position + 1)) : 'o'.($position + 1);
            $options[$key] = PollOption::query()->updateOrCreate(
                ['poll_id' => $poll->getKey(), 'label' => $label],
                ['position' => $position],
            );
        }

        $votes = $data['votes'] ?? [];

        foreach (is_array($votes) ? $votes : [] as $voterPrototypeId => $chosen) {
            $voterId = $this->ctx->userId($voterPrototypeId);

            if ($voterId === null) {
                $this->ctx->note(sprintf('publication #%s : vote de l’utilisateur·rice #%s ignoré (compte inconnu)', (string) $prototypeId, (string) $voterPrototypeId));

                continue;
            }

            $chosen = array_values(array_filter(array_map('strval', (array) $chosen)));
            $chosenOptionIds = [];

            foreach ($chosen as $optionKey) {
                if (! isset($options[$optionKey])) {
                    $this->ctx->note(sprintf('publication #%s : option « %s » inconnue, vote ignoré', (string) $prototypeId, $optionKey));

                    continue;
                }

                $chosenOptionIds[] = (int) $options[$optionKey]->getKey();
            }

            if ($chosenOptionIds === []) {
                continue;
            }

            if (! $poll->multiple) {
                $chosenOptionIds = [$chosenOptionIds[0]];
            }

            // Un seul choix par personne en sondage simple : les anciens votes sur d'autres options sont retirés.
            PollVote::query()->where('poll_id', $poll->getKey())->where('user_id', $voterId)
                ->whereNotIn('poll_option_id', $chosenOptionIds)->delete();

            foreach ($chosenOptionIds as $optionId) {
                PollVote::query()->firstOrCreate(
                    ['poll_option_id' => $optionId, 'user_id' => $voterId],
                    ['poll_id' => $poll->getKey()],
                );
            }
        }

        $this->recount($poll);
    }

    private function recount(Poll $poll): void
    {
        foreach (PollOption::query()->where('poll_id', $poll->getKey())->get() as $option) {
            $option->forceFill(['votes_count' => PollVote::query()->where('poll_option_id', $option->getKey())->count()])->save();
        }

        $votes = PollVote::query()->where('poll_id', $poll->getKey());

        $poll->forceFill([
            'votes_count' => $votes->count(),
            'voters_count' => $votes->distinct()->count('user_id'),
        ])->save();
    }
}
