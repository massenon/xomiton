<?php

declare(strict_types=1);

namespace App\Application\Cases\Data;

use App\Enums\FollowupOutcome;
use App\Models\CaseFollowup;

/**
 * Étape de suivi à ajouter, validée par CaseRules::forFollowupCreate().
 */
final readonly class FollowupInput
{
    public function __construct(
        public string $date,
        public string $step,
        public FollowupOutcome $outcome,
        public ?string $nextOn = null,
        public ?string $comment = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $comment = trim((string) ($validated['comment'] ?? ''));

        return new self(
            date: (string) $validated['date'],
            step: (string) $validated['step'],
            outcome: FollowupOutcome::from((string) $validated['outcome']),
            nextOn: isset($validated['next_on']) ? (string) $validated['next_on'] : null,
            comment: $comment === '' ? null : $comment,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'date' => $this->date,
            'step' => $this->step,
            'outcome' => $this->outcome,
            'next_on' => $this->nextOn,
            'comment' => $this->comment,
        ];
    }

    /** L'étape enregistrée porte-t-elle exactement cette saisie ? Rejeu d'un même `client_uuid` (AddFollowup). */
    public function matches(CaseFollowup $followup): bool
    {
        return $followup->date?->format('Y-m-d') === $this->date
            && $followup->step === $this->step
            && $followup->outcome === $this->outcome
            && $followup->next_on?->format('Y-m-d') === $this->nextOn
            && $followup->comment === $this->comment;
    }
}
