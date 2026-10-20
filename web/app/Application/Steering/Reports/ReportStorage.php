<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Models\GeneratedReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fichiers des rapports : disque `xomiton.reports.disk` (hors racine web), morceaux temporaires du registre détaillé
 * (`reports/tmp/<id>/part-<n>.ndjson`, une ligne JSON par dossier) et nom de téléchargement.
 */
final class ReportStorage
{
    public static function disk(): Filesystem
    {
        return Storage::disk((string) config('xomiton.reports.disk', 'local'));
    }

    public static function partPath(GeneratedReport $report, int $part): string
    {
        return 'reports/tmp/'.$report->getKey().'/part-'.$part.'.ndjson';
    }

    public static function finalPath(GeneratedReport $report): string
    {
        return 'reports/'.now()->format('Y').'/'.$report->getKey().'-'.Str::slug($report->title).'.'.$report->format->extension();
    }

    public static function forgetParts(GeneratedReport $report): void
    {
        self::disk()->deleteDirectory('reports/tmp/'.$report->getKey());
    }

    /** Nom du fichier téléchargé : « rapport-mensuel_2026-09-01_2026-09-30.xlsx ». */
    public static function downloadName(GeneratedReport $report): string
    {
        return Str::slug($report->title).'_'.$report->period_start->toDateString().'_'.$report->period_end->toDateString().'.'.$report->format->extension();
    }
}
