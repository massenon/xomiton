<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Alerts;

use App\Application\Alerts\Queries\FindAlertByTrackingCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alerts\TrackReportRequest;
use Illuminate\Http\Response;

/**
 * Suivi d'un signalement par son code sur `/suivi` (P5-04, FR-ALT-01), sans compte : GET affiche la saisie, POST
 * (limiteur `tracking`, code hors URL) affiche le statut lu par FindAlertByTrackingCode, la requête du suivi
 * `GET /api/v1/signalements/{code}`. Code inconnu, annulé ou clos depuis longtemps : un seul message neutre (404).
 * Du contact routé, le rôle seulement (comme AlertTrackingResource). Page non indexée.
 */
final class ReportTrackingController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'no-store, private'];

    public function show(): Response
    {
        return response()->view('public.signaler.suivi', self::page())->withHeaders(self::HEADERS);
    }

    public function lookup(TrackReportRequest $request, FindAlertByTrackingCode $find): Response
    {
        $alert = $find($request->code());

        return response()
            ->view('public.signaler.suivi', [...self::page(), 'alert' => $alert, 'searched' => true, 'code' => $request->code()], $alert === null ? 404 : 200)
            ->withHeaders(self::HEADERS);
    }

    /**
     * @return array<string, mixed>
     */
    private static function page(): array
    {
        return [
            'alert' => null,
            'searched' => false,
            'code' => '',
            'seo' => ['title' => __('alerts.report.tracking.title'), 'description' => __('alerts.report.tracking.intro'), 'index' => false],
        ];
    }
}
