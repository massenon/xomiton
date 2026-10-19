<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\CaseRules;
use App\Application\Cases\Data\FollowupInput;
use App\Http\Requests\Peer\Concerns\ResolvesVisibleCase;
use App\Models\CaseFollowup;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST pair/cases/{code}/followups` (P9-02) : dossier visible sinon 404, `CaseFollowupPolicy::create` sinon 403 ;
 * `client_uuid` facultatif (idempotence, ADR-32).
 */
final class StoreFollowupRequest extends FormRequest
{
    use ResolvesVisibleCase;

    public function authorize(): bool
    {
        return $this->user()?->can('create', [CaseFollowup::class, $this->caseRecord()]) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(CaseRules::class)->forFollowupCreate();
    }

    public function toInput(): FollowupInput
    {
        return FollowupInput::fromValidated($this->validated());
    }

    public function clientUuid(): ?string
    {
        $uuid = $this->validated('client_uuid');

        return is_string($uuid) && $uuid !== '' ? $uuid : null;
    }

    protected function prepareForValidation(): void
    {
        $this->caseRecord();
    }
}
