<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\CaseChanges;
use App\Application\Cases\Data\CaseInput;
use App\Application\Cases\Data\FollowupInput;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Validation d'une charge utile hors Form Request — éléments `case` / `case_followup` de `sync/push` (P9-06) — avec
 * **les mêmes règles** que `pair/cases` (CaseRules, catalogue activable). Lève ValidationException (422, erreurs
 * par champ) ; messages et noms de champs de lang/fr/validation.php.
 */
final class CaseValidator
{
    public function __construct(private readonly CaseRules $rules) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function newCase(array $payload): CaseInput
    {
        return CaseInput::fromValidated(Validator::make($payload, $this->rules->forCreate())->validate());
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function changes(CaseRecord $case, User $by, array $payload): CaseChanges
    {
        $data = $this->rules->withCurrentLocation($payload, $case);

        return CaseChanges::fromValidated(Validator::make($data, $this->rules->forUpdate($by))->validate());
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function newFollowup(array $payload): FollowupInput
    {
        return FollowupInput::fromValidated(Validator::make($payload, $this->rules->forFollowupCreate())->validate());
    }
}
