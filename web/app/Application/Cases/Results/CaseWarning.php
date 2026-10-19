<?php

declare(strict_types=1);

namespace App\Application\Cases\Results;

/**
 * Avertissement non bloquant renvoyé après l'enregistrement d'un dossier (`meta.warnings`) : date de plus de
 * `max_past_years` ans (RG-07, `date_old`), doublon suspect (`duplicate_suspected`, codes des dossiers visibles
 * du compte seulement).
 */
final readonly class CaseWarning
{
    public const DATE_OLD = 'date_old';

    public const DUPLICATE_SUSPECTED = 'duplicate_suspected';

    /**
     * @param  list<string>  $codes
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $codes = [],
    ) {}

    /**
     * @return array{code: string, message: string, codes: list<string>}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'message' => $this->message, 'codes' => $this->codes];
    }
}
