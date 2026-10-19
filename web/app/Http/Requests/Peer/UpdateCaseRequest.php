<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\CaseRules;
use App\Application\Cases\Data\CaseChanges;
use App\Http\Requests\Peer\Concerns\ResolvesVisibleCase;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `PATCH pair/cases/{code}` (P9-02) : dossier visible sinon 404, `CaseRecordPolicy::update` sinon 403 ; champs envoyés
 * seulement (CaseRules::forUpdate), localisation complétée par celle du dossier (RG-08 sur le couple final).
 */
final class UpdateCaseRequest extends FormRequest
{
    use ResolvesVisibleCase;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->caseRecord()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return app(CaseRules::class)->forUpdate($user);
    }

    public function toChanges(): CaseChanges
    {
        return CaseChanges::fromValidated($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $case = $this->caseRecord();
        $this->replace(app(CaseRules::class)->withCurrentLocation($this->all(), $case));
    }
}
