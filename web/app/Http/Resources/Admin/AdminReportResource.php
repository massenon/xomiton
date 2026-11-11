<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Application\Moderation\ContentVisibility;
use App\Models\ModerationReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Signalement de contenu en file de modération (`admin/moderation/reports`, P15-16) : nature, motif, extrait court du
 * contenu (200 caractères), auteur·rice signalé·e par son pseudonyme, nombre de signalements et de personnes distinctes
 * pour ce contenu, état et gestes permis (masquer, rendre visible, ignorer, avertir). Jamais l'identité de la personne qui signale.
 *
 * @property ModerationReport $resource
 */
final class AdminReportResource extends JsonResource
{
    /**
     * @param  array{reports: int, people: int}|null  $siblings
     */
    public function __construct($resource, private readonly ?array $siblings = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $report = $this->resource;
        $subject = $report->subject;
        $hidden = ContentVisibility::isHidden($subject);

        return [
            'id' => (int) $report->getKey(),
            'kind' => $report->kind->value,
            'reason' => $report->reason,
            'excerpt' => $report->excerpt === null ? null : Str::limit((string) $report->excerpt, 200),
            'reported_user' => $report->reportedUser === null ? null : ['id' => (int) $report->reportedUser->getKey(), 'pseudo' => $report->reportedUser->pseudo],
            'status' => $report->status->value,
            'action' => $report->action,
            'hidden' => $hidden,
            'reports' => $this->siblings['reports'] ?? 1,
            'people' => $this->siblings['people'] ?? 1,
            'created_at' => $report->created_at?->toIso8601String(),
            'handled_at' => $report->handled_at?->toIso8601String(),
            'can' => [
                'hide' => $report->isPending() && ContentVisibility::hideable($subject) && ! $hidden,
                'restore' => $hidden,
                'dismiss' => $report->isPending(),
                'warn' => $report->isPending() && $report->reportedUser !== null,
            ],
        ];
    }
}
