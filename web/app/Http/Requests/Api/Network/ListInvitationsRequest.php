<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Network;

use App\Application\Community\Network\InvitationDirectory;
use App\Http\Requests\Api\Network\Concerns\NetworkRequest;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET invitations?box=received|sent&cursor=&per_page= (P8-06, onglets « Invitations reçues » et « Invitations envoyées »
 * de who-to-follow.php) : `received` par défaut.
 */
final class ListInvitationsRequest extends FormRequest
{
    use NetworkRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Invitation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'box' => ['sometimes', 'nullable', 'string', Rule::in(InvitationDirectory::BOXES)],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }

    public function box(): string
    {
        $box = $this->validated('box');

        return is_string($box) && $box !== '' ? $box : InvitationDirectory::RECEIVED;
    }
}
