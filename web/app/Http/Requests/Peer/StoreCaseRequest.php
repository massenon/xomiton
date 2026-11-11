<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\CaseRules;
use App\Application\Cases\Data\CaseInput;
use App\Models\CaseRecord;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST pair/cases` (P9-02, collecte) : règles du catalogue activable (CaseRules::forCreate, mêmes que `sync/push` et
 * que `GET pair/validation-rules`) ; `client_uuid` facultatif (idempotence de la boîte d'envoi, ADR-32).
 */
final class StoreCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CaseRecord::class) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(CaseRules::class)->forCreate();
    }

    public function toInput(): CaseInput
    {
        return CaseInput::fromValidated($this->validated());
    }

    public function clientUuid(): ?string
    {
        $uuid = $this->validated('client_uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }
}
