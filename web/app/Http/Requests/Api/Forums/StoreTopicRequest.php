<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Forums;

use App\Application\Community\Data\TopicDraft;
use App\Http\Requests\Api\Forums\Concerns\ForumRequest;
use App\Models\Forum;
use App\Models\ForumTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST forums/topics (P8-05 ; modale « Nouveau sujet » de forums.php l.103-110) : `{forum (clé d'un forum actif),
 * title (5–140), body (10–5000), is_anonymous?}` ; `posts.create` (403 sinon), e-mail confirmé (`verified`).
 */
final class StoreTopicRequest extends FormRequest
{
    use ForumRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', ForumTopic::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'forum' => ['required', 'string', 'max:10', Rule::exists('forums', 'key')->where('is_active', true)],
            'title' => ['required', 'string', 'min:'.self::limit('title_min', 5), 'max:'.self::limit('title_max', 140)],
            'body' => ['required', 'string', 'min:'.self::limit('body_min', 10), 'max:'.self::limit('body_max', 5000)],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $title = ['min' => self::limit('title_min', 5)];
        $body = ['min' => self::limit('body_min', 10)];

        return [
            'forum.required' => (string) __('community.forums.create.forum_required'),
            'forum.exists' => (string) __('community.forums.errors.forum'),
            'title.required' => (string) __('community.forums.create.title_min', $title),
            'title.min' => (string) __('community.forums.create.title_min', $title),
            'body.required' => (string) __('community.forums.create.body_min', $body),
            'body.min' => (string) __('community.forums.create.body_min', $body),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'body'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }
    }

    public function toDraft(): TopicDraft
    {
        return new TopicDraft(
            forum: Forum::query()->active()->forKey((string) $this->validated('forum'))->firstOrFail(),
            title: (string) $this->validated('title'),
            body: (string) $this->validated('body'),
            anonymous: $this->boolean('is_anonymous'),
        );
    }

    private static function limit(string $key, int $default): int
    {
        return (int) config('xomiton.forums.'.$key, $default);
    }
}
