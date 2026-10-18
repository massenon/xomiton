<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE conversations/{ulid}/messages/{id} (P8-01) : supprimer pour tout le monde — son propre message, ou tout message
 * pour la gestion d'un groupe (MessagePolicy::delete), 403 sinon ; message d'une autre conversation → 404.
 */
final class DeleteMessageRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('delete', $this->routeMessage());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
