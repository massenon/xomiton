<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Network;

use App\Http\Requests\Api\Network\Concerns\NetworkRequest;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST invitations {user_id} (P8-06, who-to-follow.php l.14-21, « Envoyer une invitation ») : identifiant entier ; qui
 * peut être invité·e est jugé par App\Application\Community\Network\ManageInvitations (422 neutre
 * `invitation_unavailable`, pas de règle `exists` : la réponse ne doit pas dire si un compte existe).
 */
final class StoreInvitationRequest extends FormRequest
{
    use NetworkRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Invitation::class);
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
