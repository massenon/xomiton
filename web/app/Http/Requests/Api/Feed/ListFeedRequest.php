<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Enums\PostTag;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/feed · feed/saved · posts/{ulid}/comments (P7-02) : `?tag=` (étiquette, fil seulement), `?cursor=`
 * (opaque, `meta.next_cursor`), `?per_page=` (≤ xomiton.api.max_per_page).
 */
final class ListFeedRequest extends FormRequest
{
    use FeedRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Post::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tag' => ['sometimes', 'nullable', 'string', Rule::enum(PostTag::class)],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('xomiton.api.max_per_page', 50)],
        ];
    }

    public function tag(): ?PostTag
    {
        $tag = $this->validated('tag');

        return is_string($tag) && $tag !== '' ? PostTag::from($tag) : null;
    }
}
