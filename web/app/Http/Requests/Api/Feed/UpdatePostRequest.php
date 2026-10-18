<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /api/v1/posts/{ulid} (P7-02) : `{body?, tag?, visibility?}` par l'auteur·rice seulement (403 sinon ; 404 si la
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
     * @return array{body?: string|null, tag?: PostTag, visibility?: PostVisibility}
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

        return $changes;
    }
}
