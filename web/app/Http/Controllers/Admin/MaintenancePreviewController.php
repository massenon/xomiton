<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceSnapshot;
use App\Application\Platform\MaintenanceStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Aperçu « voir comme un visiteur » (P13-06) : la page publique de maintenance telle qu'elle s'affiche (même vue, mêmes
 * données que EnsureNotInMaintenance), maintenance active ou non. `?message=` montre un brouillon du message public,
 * `?minutes=` une durée estimée. Statut 200, jamais indexé ni mis en cache, sans vérification de réouverture.
 */
final class MaintenancePreviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $snapshot = MaintenanceStatus::snapshot();
        $draft = EnableMaintenance::clean($request->string('message')->toString(), MaintenanceStatus::MESSAGE_MAX_LENGTH);
        $minutes = $request->integer('minutes');

        if ($draft !== null || $minutes > 0 || ! $snapshot->active) {
            $startsAt = $snapshot->active ? $snapshot->startsAt : now();
            $endsAt = $minutes > 0 ? Carbon::now()->addMinutes(min($minutes, 72 * 60)) : $snapshot->endsAt;
            $snapshot = new MaintenanceSnapshot(true, $snapshot->window, $draft ?? $snapshot->message, $startsAt, $endsAt);
        }

        return response()
            ->view(MaintenancePage::VIEW, MaintenancePage::data($snapshot, preview: true))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
