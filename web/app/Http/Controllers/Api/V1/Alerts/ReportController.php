<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Alerts;

use App\Application\Alerts\Queries\FindAlertByTrackingCode;
use App\Application\Alerts\Queries\ListAlertsOfUser;
use App\Application\Alerts\SubmitReport;
use App\Domain\Alerts\Exceptions\TrackingCodeNotFound;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Alerts\ListMyAlertsRequest;
use App\Http\Requests\Alerts\StoreReportRequest;
use App\Http\Resources\Alerts\AlertResource;
use App\Http\Resources\Alerts\AlertTrackingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

/**
 * Signalements et besoins (P5-06, docs/API.md §6.1-§6.3) : `POST signalements` (anonyme ou connecté·e, `idempotent:required,
 * limit=report`), `GET signalements/mine` (`auth:sanctum`, alertes non anonymes du compte), `GET signalements/{code}`
 * (suivi sans compte, `throttle:tracking`, réponse neutre). Même charge utile que le formulaire `/signaler` (Q11 a).
 */
final class ReportController extends ApiController
{
    use ResolvesAlertSource;

    /** Réponses propres à la personne : jamais en cache partagé. */
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function store(StoreReportRequest $request, SubmitReport $submit): JsonResponse
    {
        $files = array_values(array_filter((array) $request->file('attachments', []), static fn (mixed $file): bool => $file instanceof UploadedFile));
        $source = $this->alertSource($request);

        $result = $submit($files, static fn (array $stored) => $request->toData($source, $stored));
        $alert = $result->alert->loadMissing('routedContact');

        return $this->created(AlertResource::make($alert), route('api.signalements.show', ['code' => $alert->tracking_code]))
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function mine(ListMyAlertsRequest $request, ListAlertsOfUser $alerts): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->paginated($alerts($user, $request->alertType(), $request->perPage()), AlertResource::class)
            ->withHeaders(self::PRIVATE_HEADERS);
    }

    public function show(string $code, FindAlertByTrackingCode $find): JsonResponse
    {
        $alert = $find($code) ?? throw new TrackingCodeNotFound;

        return $this->respond(AlertTrackingResource::make($alert), headers: self::PRIVATE_HEADERS);
    }
}
