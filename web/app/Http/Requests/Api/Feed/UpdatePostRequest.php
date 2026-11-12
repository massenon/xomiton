<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Post;
use App\Models\PostMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /api/v1/posts/{ulid} (P7-02) : `{body?, tag?, visibility?, remove_media?: int[]}` (positions des **images** à retirer,
 * les autres gardent leur ordre ; jamais la vidéo : on supprime la publication ; la publication garde un texte ou un média ;
 * une vidéo en relecture ne se modifie pas : 403) par l'auteur·rice seulement (403 sinon ; 404 si la
 * publication n'est pas visible). Le texte d'une publication reste obligatoire (facultatif pour un sondage) ; l'étiquette
 * ne passe jamais vers ou depuis « sondage » (question et réponses ne se modifient pas après publication) ; une
 * publication anonyme ne passe jamais en « abonné·es » (422, Q72).
 */
final class UpdatePostRequest extends FormRequest
{
    use FeedRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('update', $this->visiblePost());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->route('post');
        $isPoll = $post instanceof Post && $post->isPoll();

        return [
            'body' => ['sometimes', $isPoll ? 'nullable' : 'required', 'string', 'max:'.(int) config('xomiton.feed.post_max_length', Post::BODY_MAX_LENGTH)],
            'tag' => ['sometimes', 'string', Rule::enum(PostTag::class)],
            'visibility' => ['sometimes', 'string', Rule::enum(PostVisibility::class)],
            'remove_media' => ['sometimes', 'array', 'max:20'],
            'remove_media.*' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['body.required' => (string) __('feed.errors.text_required')];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $post = $this->route('post');
            $tag = $this->input('tag');

            if (! $post instanceof Post) {
                return;
            }

            $this->validateRemovedMedia($validator, $post);

            if ($post->is_anonymous && $this->input('visibility') === PostVisibility::Followers->value) {
                $validator->errors()->add('visibility', (string) __('feed.api.anonymous_followers'));
            }

            // Publication de groupe (P8-02) : le groupe est l'audience, la visibilité reste `public`.
            if ($post->group_id !== null && $this->has('visibility') && $this->input('visibility') !== PostVisibility::Public->value) {
                $validator->errors()->add('visibility', (string) __('feed.api.group_visibility'));
            }

            if (! is_string($tag)) {
                return;
            }

            if ($post->isPoll() && $tag !== PostTag::Poll->value) {
                $validator->errors()->add('tag', (string) __('feed.api.tag_change_poll'));
            } elseif (! $post->isPoll() && $tag === PostTag::Poll->value) {
                $validator->errors()->add('tag', (string) __('feed.api.tag_poll'));
            }
        }];
    }

    /**
     * Positions à retirer : des images existantes seulement, et la publication garde un texte ou au moins un média.
     */
    private function validateRemovedMedia(Validator $validator, Post $post): void
    {
        $positions = $this->input('remove_media');

        if (! is_array($positions) || $positions === []) {
            return;
        }

        $images = PostMedia::query()->where('post_id', $post->getKey())->where('kind', PostMedia::KIND_IMAGE)->pluck('position')->map(static fn (mixed $position): int => (int) $position)->all();
        $wanted = array_values(array_unique(array_map(static fn (mixed $position): int => (int) $position, $positions)));

        if (array_diff($wanted, $images) !== []) {
            $validator->errors()->add('remove_media', (string) __('feed.api.media_unknown'));

            return;
        }

        $body = $this->has('body') ? $this->input('body') : $post->body;
        $remaining = PostMedia::query()->where('post_id', $post->getKey())->count() - count($wanted);

        if ($remaining < 1 && (! is_string($body) || trim($body) === '') && ! $post->isPoll()) {
            $validator->errors()->add('remove_media', (string) __('feed.api.media_last_required'));
        }
    }

    /**
     * @return array{body?: string|null, tag?: PostTag, visibility?: PostVisibility, remove_media?: list<int>}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('body')) {
            $body = $this->validated('body');
            $changes['body'] = is_string($body) ? $body : null;
        }

        if (is_string($this->validated('tag'))) {
            $changes['tag'] = PostTag::from((string) $this->validated('tag'));
        }

        if (is_string($this->validated('visibility'))) {
            $changes['visibility'] = PostVisibility::from((string) $this->validated('visibility'));
        }

        $remove = $this->validated('remove_media');

        if (is_array($remove) && $remove !== []) {
            $changes['remove_media'] = array_values(array_unique(array_map(static fn (mixed $position): int => (int) $position, $remove)));
        }

        return $changes;
    }
}
