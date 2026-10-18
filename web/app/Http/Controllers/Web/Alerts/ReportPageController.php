<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Alerts;

use App\Application\Alerts\Queries\ReportFormOptions;
use App\Application\Alerts\SubmitReport;
use App\Application\Alerts\Support\ReportReplay;
use App\Enums\AlertSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Alerts\StoreReportRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Page publique `/signaler` (P5-04, gabarit sobre Q18 a, tu Q47) : formulaire en six étapes (Q11 a), envoyé en POST
 * classique avec @csrf (fonctionne sans JavaScript ; Alpine n'ajoute que le pas à pas, le brouillon local et la
 * cascade département → commune). Même charge utile et même cas d'usage que `POST /api/v1/signalements`
 * (StoreReportRequest, SubmitReport) ; limiteur `report` (10/h par IP), aucune IP enregistrée.
 * Le code de suivi est remis une seule fois, par la session (jamais dans l'URL), sur `/signaler/confirmation` ; une
 * même clé renvoyée avec un autre contenu revient au formulaire (ReportReplay). Session chiffrée, sans IP pour un·e
 * invité·e (GuestPrivateDatabaseSessionHandler).
 */
final class ReportPageController extends Controller
{
    /** Clé de session (flash) de la confirmation. */
    public const FLASH = 'signaler.sent';

    /** Réponses propres à la personne : jamais en cache partagé. */
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function create(Request $request, ReportFormOptions $options): Response
    {
        return response()->view('public.signaler.create', [
            'options' => $options(),
            // `?tab=besoin` (accueil, assistant, prototype signalement.php) : « Exprimer un besoin » présélectionné.
            'initialType' => $request->query('tab') === 'besoin' ? 'need' : null,
            'idempotencyKey' => (string) Str::uuid(),
            'seo' => [
                'title' => __('seo.pages.signaler.title'),
                'description' => __('seo.pages.signaler.description'),
                'breadcrumb' => [[__('alerts.report.title'), route('signaler')]],
            ],
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    public function store(StoreReportRequest $request, SubmitReport $submit): RedirectResponse
    {
        $files = array_values(array_filter((array) $request->file('attachments', []), static fn (mixed $file): bool => $file instanceof UploadedFile));

        $result = $submit($files, static fn (array $stored) => $request->toData(AlertSource::Web, $stored));

        // Même clé, contenu modifié (réponse perdue puis formulaire corrigé) : la correction n'est jamais avalée par
        // « déjà reçu » ; le formulaire revient avec une clé neuve (old() sans idempotency_key), comme le 422 de l'API.
        if ($result->duplicate && ! ReportReplay::matches($result->alert, $request->toData(AlertSource::Web), count($files))) {
            return redirect()->route('signaler', status: 303)
                ->withInput($request->except('idempotency_key'))
                ->withErrors(['idempotency_key' => __('alerts.report.changed_after_send', ['code' => $result->alert->displayCode()])]);
        }

        return redirect()->route('signaler.confirmation', status: 303)->with(self::FLASH, [
            'code' => $result->alert->displayCode(),
            'type' => $result->alert->type->value,
            'duplicate' => $result->duplicate,
            'routed' => $result->routedPayload(),
        ]);
    }

    public function confirmation(Request $request): View|RedirectResponse|Response
    {
        $sent = $request->session()->get(self::FLASH);

        if (! is_array($sent) || ! is_string($sent['code'] ?? null)) {
            return redirect()->route('signaler.suivi');
        }

        return response()->view('public.signaler.confirmation', [
            'sent' => $sent,
            'seo' => ['title' => __('alerts.report.done.title'), 'index' => false],
        ])->withHeaders(self::PRIVATE_HEADERS);
    }
}
