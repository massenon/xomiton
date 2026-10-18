<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Enums\PostStatus;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/posts/{ulid}/comments (P7-02 ; prototype post-details.php) : `{body, parent_id?, is_anonymous?}` — texte
 * 2 à 1000 car., réponse à un commentaire publié **de la même publication**. `comments.create` et publication visible
 * (404 sinon), e-mail confirmé (middleware `verified`).
 */
final class StoreCommentRequest extends FormRequest
{
    use FeedRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('comment', $this->visiblePost());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->visiblePost();

        return [
            'body' => ['required', 'string', 'min:2', 'max:'.Comment::BODY_MAX_LENGTH],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('comments', 'id')
                ->where('post_id', $post->getKey())->where('status', PostStatus::Published->value)->whereNull('deleted_at')],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => (string) __('feed.comments.empty_error'),
            'body.min' => (string) __('feed.comments.empty_error'),
            'parent_id.exists' => (string) __('feed.api.parent_invalid'),
        ];
    }

    public function parent(): ?Comment
    {
        $id = $this->validated('parent_id');

        return is_numeric($id) ? Comment::query()->find((int) $id) : null;
    }
}
