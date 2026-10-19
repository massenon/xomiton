<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\CaseRules;
use App\Application\Cases\Data\FollowupChanges;
use App\Http\Requests\Peer\Concerns\ResolvesVisibleCase;
use App\Models\CaseFollowup;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `PATCH pair/cases/{code}/followups/{followup}` (P9-02) : dossier visible et étape **de ce dossier**, sinon 404 ;
 * `CaseFollowupPolicy::update` sinon 403 ; champs envoyés seulement, date actuelle reprise pour « prochaine date ≥ date ».
 */
final class UpdateFollowupRequest extends FormRequest
{
    use ResolvesVisibleCase;

    private ?CaseFollowup $resolvedFollowup = null;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->followup()) ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return app(CaseRules::class)->forFollowupUpdate();
    }

    public function followup(): CaseFollowup
    {
        if ($this->resolvedFollowup !== null) {
            return $this->resolvedFollowup;
        }

        $id = $this->route('followup');
        $followup = is_numeric($id)
            ? CaseFollowup::query()->forCase($this->caseRecord())->whereKey((int) $id)->first()
            : null;

        if ($followup === null) {
            throw new NotFoundHttpException;
        }

        $followup->setRelation('case', $this->caseRecord());

        return $this->resolvedFollowup = $followup;
    }

    public function toChanges(): FollowupChanges
    {
        return FollowupChanges::fromValidated($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->replace(CaseRules::withCurrentDate($this->all(), $this->followup()));
    }
}
