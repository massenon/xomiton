<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Network;

use App\Http\Requests\Api\Network\Concerns\NetworkRequest;
use App\Models\Follow;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST follows {user_id} (P8-06) : identifiant entier ; l'existence et la possibilité de suivre sont jugées par
 * App\Application\Community\Network\FollowMember (422 neutre `follow_unavailable`, pas de règle `exists` ici : la réponse
 * ne doit pas dire si un compte existe).
 */
final class StoreFollowRequest extends FormRequest
{
    use NetworkRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Follow::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function targetId(): int
    {
        return (int) $this->validated('user_id');
    }
}
