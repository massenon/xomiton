<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Models\ModerationReport;

/**
 * Résultat d'un signalement de contenu : le signalement (nouveau ou celui déjà déposé par la même personne, sans double
 * compte) et le masquage automatique éventuel (RG-16).
 */
final readonly class ReportOutcome
{
    public function __construct(
        public ModerationReport $report,
        public bool $created,
        public bool $hidden,
    ) {}
}
