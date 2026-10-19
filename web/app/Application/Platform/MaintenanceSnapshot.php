<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\MaintenanceWindow;
use Illuminate\Support\Carbon;

/**
 * Photographie du mode maintenance à un instant donné (MaintenanceStatus::snapshot) : ce que la page 503 affiche.
 * `overdue` : une reprise avait été annoncée et elle est passée alors que la maintenance continue (activation
 * immédiate : la durée n'est qu'une estimation, Q85) — la page le dit au lieu d'afficher une heure dépassée.
 */
final readonly class MaintenanceSnapshot
{
    public function __construct(
        public bool $active,
        public ?MaintenanceWindow $window,
        public string $message,
        public ?Carbon $startsAt,
        public ?Carbon $endsAt,
        public bool $overdue = false,
    ) {}

    /**
     * Délai conseillé avant nouvelle tentative (en-tête Retry-After), en secondes : jusqu'à la reprise prévue,
     * borné entre 1 minute et 1 heure ; 10 minutes quand aucune reprise n'est annoncée.
     */
    public function retryAfterSeconds(): int
    {
        if ($this->endsAt === null) {
            return 600;
        }

        return (int) min(3600, max(60, now()->diffInSeconds($this->endsAt, false)));
    }

    /**
     * @return array{message: string, startsAt: ?Carbon, endsAt: ?Carbon, disconnected: bool}
     */
    public function toViewData(bool $disconnected = false): array
    {
        return [
            'message' => $this->message,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'disconnected' => $disconnected,
        ];
    }
}
