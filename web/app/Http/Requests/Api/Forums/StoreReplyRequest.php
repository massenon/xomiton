<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Forums;

use App\Http\Requests\Api\Forums\Concerns\ForumRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST forums/topics/{ulid}/replies (P8-05 ; forum-topic.php l.13 : réponse ≥ 2 car.) : `{body (2–2000),
 * is_anonymous?}`. Sujet invisible → 404 ; sans `comments.create` ou sujet masqué → 403 ; sujet fermé → 422
 * `topic_locked` (cas d'usage). E-mail confirmé (`verified`).
 */
final class StoreReplyRequest extends FormRequest
{
    use ForumRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('reply', $this->visibleTopic());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:'.(int) config('xomiton.forums.reply_min', 2), 'max:'.(int) config('xomiton.forums.reply_max', 2000)],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => (string) __('community.forums.reply.empty'),
            'body.min' => (string) __('community.forums.reply.min', ['min' => (int) config('xomiton.forums.reply_min', 2)]),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }

    public function body(): string
    {
        return (string) $this->validated('body');
    }

    public function anonymous(): bool
    {
        return $this->boolean('is_anonymous');
    }
}
