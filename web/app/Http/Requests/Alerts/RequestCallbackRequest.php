<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Application\Alerts\Data\CallbackData;
use App\Enums\ContactChannel;
use App\Http\Requests\Alerts\Concerns\ReadsAlertPayload;
use App\Rules\PhoneOrEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Modale « Demander à être rappelé·e » de l'annuaire membre (annuaire.php l.122-138, P8-12) : membre connecté·e avec
 * `directory.view`, moyen de contact obligatoire (≤ `alerts.callback_contact_max`), message ≤ `alerts.callback_message_max`,
 * pseudo non transmis par défaut.
 */
final class RequestCallbackRequest extends FormRequest
{
    use ReadsAlertPayload;

    public function authorize(): bool
    {
        return $this->currentUser()?->can('directory.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAlertPayload(['anonymous'], ['anonymous' => true]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'contact' => ['required', 'string', 'max:'.(int) config('xomiton.alerts.callback_contact_max', 190), new PhoneOrEmail],
            'preferred_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'message' => ['nullable', 'string', 'max:'.(int) config('xomiton.alerts.callback_message_max', 1000)],
            'anonymous' => ['boolean'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    public function toData(int $contactId): CallbackData
    {
        return new CallbackData(
            contactId: $contactId,
            userId: (int) $this->currentUser()?->getKey(),
            contact: (string) $this->stringOrNull('contact'),
            idempotencyKey: (string) $this->input('idempotency_key'),
            preferredChannel: ContactChannel::tryFrom((string) $this->input('preferred_channel')),
            message: $this->stringOrNull('message'),
            anonymous: $this->boolean('anonymous', true),
        );
    }
}
