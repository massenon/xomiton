<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST conversations/{ulid}/participants `{user_ids[]}` · DELETE conversations/{ulid}/participants/{user} (P8-01) :
 * gestion d'un groupe (créateur·rice ou admin, 403 sinon). On ne se retire pas soi-même (→ `leave`), et la personne
 * qui a créé le groupe ne peut pas en être retirée.
 */
final class ManageParticipantsRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        $conversation = $this->conversation();

        // Conversation privée : aucune gestion (ConversationPolicy) ; une participante reçoit le 422 explicite
        // `conversation_group_only` de l'action plutôt qu'un 403.
        return $this->viewer()->can($conversation->isGroup() ? 'manage' : 'view', $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->isMethod('DELETE')) {
            return [];
        }

        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('xomiton.messaging.group_max_participants', 50))],
            'user_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('user_ids', [])));
    }

    public function removed(): User
    {
        $user = $this->route('user');
        $conversation = $this->conversation();

        if (! $user instanceof User) {
            throw new NotFoundHttpException;
        }

        if ($user->is($this->viewer()) || ($conversation->created_by !== null && (int) $conversation->created_by === (int) $user->getKey())) {
            abort(403);
        }

        return $user;
    }
}
