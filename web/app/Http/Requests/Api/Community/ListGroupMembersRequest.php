<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Enums\MembershipStatus;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET groups/{ulid}/members?status=active|requested&page=&per_page= (P8-02) : `requested` (demandes en attente) est
 * réservé à l'équipe du groupe (contrôleur : 403).
 */
final class ListGroupMembersRequest extends FormRequest
{
    use CommunityRequest;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REQUESTED = 'requested';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', 'in:'.self::STATUS_ACTIVE.','.self::STATUS_REQUESTED],
        ];
    }

    public function status(): MembershipStatus
    {
        return $this->validated('status') === self::STATUS_REQUESTED ? MembershipStatus::Requested : MembershipStatus::Active;
    }
}
