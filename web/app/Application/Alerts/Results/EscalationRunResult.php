<?php

declare(strict_types=1);

namespace App\Application\Alerts\Results;

/**
 * Bilan d'une exécution d'alerts:escalate : compteurs et une ligne par action (code, type, délai, cible en
 * identifiants seulement, jamais de nom ni de contenu).
 */
final class EscalationRunResult
{
    public int $examined = 0;

    public int $escalated = 0;

    public int $reminded = 0;

    public int $skipped = 0;

    /** @var list<array{code: string, action: string, type: string, minutes: int, target: string}> */
    public array $actions = [];

    /** @var list<array{ulid: string, error: string}> */
    public array $errors = [];

    public function __construct(public readonly bool $dryRun = false) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
