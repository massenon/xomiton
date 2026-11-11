<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Application\Steering\Reports\ReportStorage;
use App\Http\Controllers\Controller;
use App\Models\GeneratedReport;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement d'un rapport généré (P10-07) par lien signé de 5 minutes, lié au compte qui l'a demandé (`u`) et à la
 * permission `reports.generate` (et `cases.export` pour un registre détaillé). Un rapport en cours, en échec ou expiré, ou dont le fichier a disparu, répond 404.
 * Le téléchargement est journalisé ; la réponse n'est jamais mise en cache.
 */
final class ReportDownloadController extends Controller
{
    public function __invoke(Request $request, GeneratedReport $report): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless((string) $request->query('u') === (string) $user->getKey(), 403);
        abort_unless($report->status->value === 'done' && $report->hasFile(), 404);
        // Un registre détaillé exige aussi la permission d'export des dossiers, quelle que soit la personne qui l'a produit.
        abort_if($report->hasDetail() && ! $user->can('cases.export'), 403);

        $disk = ReportStorage::disk();
        abort_unless($disk->exists((string) $report->file_path), 404);

        Audit::log('report.downloaded', $report, $report->format->value.' · '.$report->periodLabel(), $user);

        return $disk->download((string) $report->file_path, ReportStorage::downloadName($report), [
            'Content-Type' => (string) $report->format->mimeType(),
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
