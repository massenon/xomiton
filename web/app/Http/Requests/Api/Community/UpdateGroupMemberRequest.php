<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Enums\GroupRole;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH groups/{ulid}/members/{user} `{role: membre | moderateur | admin}` (P8-02) ; droits vérifiés par le contrôleur
 * (GroupPolicy::changeRole) et App\Domain\Community\GroupMembershipRules.
 */
final class UpdateGroupMemberRequest extends FormRequest
{
    use CommunityRequest;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['role' => ['required', 'string', Rule::enum(GroupRole::class)]];
    }

    public function role(): GroupRole
    {
        return GroupRole::from((string) $this->validated('role'));
    }
}
