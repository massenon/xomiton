<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Application\Assistant\EscalationData;
use App\Enums\AlertSource;
use App\Enums\ContactChannel;
use App\Http\Requests\Alerts\Concerns\ReadsAlertPayload;
use App\Rules\PhoneOrEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/assistant/escalate (P8-11, « Parler à un humain ») : compte connecté (`auth:sanctum`), `Idempotency-Key`
 * obligatoire (ADR-32, rejeu de la boîte d'envoi), moyen de contact obligatoire (téléphone ou e-mail, comme la demande
 * de rappel de l'annuaire), canal préféré et message (≤ `xomiton.assistant.escalation_message_max`) facultatifs, pseudo
 * non transmis par défaut (`anonymous` vrai), conversation enregistrée facultative (ignorée si `anonymous` est vrai).
 */
final class EscalateToHumanRequest extends FormRequest
{
    use ReadsAlertPayload;

    public function authorize(): bool
    {
        return $this->currentUser() !== null;
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
            'contact' => ['required', 'string', 'max:190', new PhoneOrEmail],
            'preferred_channel' => ['nullable', Rule::enum(ContactChannel::class)],
            'message' => ['nullable', 'string', 'max:'.self::messageMax()],
            'anonymous' => ['boolean'],
            'conversation_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'contact' => (string) __('assistant.escalation.contact'),
            'preferred_channel' => (string) __('assistant.escalation.channel'),
            'message' => (string) __('assistant.escalation.message'),
            'anonymous' => (string) __('assistant.escalation.share_pseudo'),
            'conversation_id' => (string) __('assistant.page.conversation'),
            'idempotency_key' => 'Idempotency-Key',
        ];
    }

    public static function messageMax(): int
    {
        return max(1, (int) config('xomiton.assistant.escalation_message_max', 1000));
    }

    public function toData(AlertSource $source, ?int $deviceId = null): EscalationData
    {
        return new EscalationData(
            userId: (int) $this->currentUser()?->getKey(),
            contact: (string) $this->stringOrNull('contact'),
            idempotencyKey: (string) $this->input('idempotency_key'),
            source: $source,
            preferredChannel: ContactChannel::tryFrom((string) $this->input('preferred_channel')),
            message: $this->stringOrNull('message'),
            anonymous: $this->boolean('anonymous', true),
            conversationId: $this->intOrNull('conversation_id'),
            deviceId: $deviceId,
        );
    }
}
