<?php

declare(strict_types=1);

namespace App\Rules\Concerns;

use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\ValidationRuleKey;
use Illuminate\Database\QueryException;

/**
 * Interrupteur du catalogue des règles de validation (P9-01, P11-05) : une règle désactivée dans Administration ›
 * Règles de validation ne contrôle plus la saisie. Une règle absente du catalogue ou verrouillée reste active
 * (ValidationCatalog::isActive) : on ne relâche jamais un contrôle par oubli.
 */
trait ConsultsCatalog
{
    protected static function catalogAllows(ValidationRuleKey $key): bool
    {
        try {
            return app(ValidationCatalog::class)->isActive($key);
        } catch (QueryException) {
            // Catalogue illisible (base indisponible, table absente) : le contrôle reste actif.
            return true;
        }
    }
}
