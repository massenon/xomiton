<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Forums;

use App\Http\Requests\Api\Forums\Concerns\ForumRequest;
use App\Models\ForumTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET forums/topics?forum=f1&q=&page=&per_page= (P8-05, forums.php : forum par clé courte du prototype, recherche
 * ≤ 60 car. sur le titre et le message). Forum inconnu ou fermé → 422 (la page web, elle, revient à « Tous les forums »).
 */
final class ListTopicsRequest extends FormRequest
{
    use ForumRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', ForumTopic::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'forum' => ['sometimes', 'nullable', 'string', 'max:10', Rule::exists('forums', 'key')->where('is_active', true)],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.forums.search_max', 60)],
        ];
    }

    public function forumKey(): ?string
    {
        $key = $this->validated('forum');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function term(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
