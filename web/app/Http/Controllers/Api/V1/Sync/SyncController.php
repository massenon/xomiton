<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sync;

use App\Application\Cases\CaseValidator;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Application\Shared\SosBurstWatch;
use App\Application\Sync\Admin\ResolveSyncConflict;
use App\Application\Sync\Pull\PullChanges;
use App\Application\Sync\Pull\PullCursor;
use App\Application\Sync\Push\ItemOutcome;
use App\Application\Sync\Push\PushBatch;
use App\Application\Sync\Push\PushContext;
use App\Application\Sync\SyncDeviceResolver;
use App\Enums\ConflictStatus;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Sync\ListConflictsRequest;
use App\Http\Requests\Api\Sync\PullRequest;
use App\Http\Requests\Api\Sync\PushRequest;
use App\Http\Requests\Api\Sync\ResolveConflictRequest;
use App\Http\Requests\Api\Sync\SyncItemDecoder;
use App\Http\Requests\Api\Sync\SyncItemLimiter;
use App\Http\Resources\Sync\SyncConflictResource;
use App\Models\SyncConflict;
use App\Models\SyncOutboxReceipt;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Synchronisation hors connexion (P9-06, SOFTWARE_ARCHITECTURE.md §5, docs/API.md §21) — appareils enregistrés
 * seulement (`X-Device-Id`, ADR-26) : `POST sync/push` (lot de 50, `idempotent:required,nonblocking,limit=sync_push`,
 * réponse 200 par élément, quotas par élément SyncItemLimiter), `GET sync/pull` (deltas par collection, jeton de
 * reprise), `GET sync/conflicts` (conflits de l'appareil). Réponses `no-store, private` (notes de dossier, codes de
 * suivi).
 */
final class SyncController extends ApiController
{
    private const PRIVATE_HEADERS = ['Cache-Control' => 'no-store, private'];

    public function __construct(private readonly SyncDeviceResolver $devices) {}

    public function push(PushRequest $request, PushBatch $push, CaseValidator $cases, SosBurstWatch $sosWatch): JsonResponse
    {
        $user = $this->user($request);
        $device = $this->devices->resolve($user, $request);
        $this->devices->touch($device, synced: true, pending: $request->pending());

        $context = new PushContext($user, $device, new SyncItemDecoder($request, $cases), new SyncItemLimiter($request, $sosWatch));
        $outcomes = $push($context, $request->items());
        $count = static fn (string $result): int => count(array_filter($outcomes, static fn (ItemOutcome $o): bool => $o->result === $result));

        return $this->respond(
            ['items' => array_map(static fn (ItemOutcome $outcome): array => $outcome->toArray(), $outcomes)],
            200,
            [
                'created' => $count(SyncOutboxReceipt::RESULT_CREATED),
                'updated' => $count(SyncOutboxReceipt::RESULT_UPDATED),
                'duplicates' => $count(SyncOutboxReceipt::RESULT_DUPLICATE),
                'conflicts' => $count(SyncOutboxReceipt::RESULT_CONFLICT),
                'rejected' => $count(SyncOutboxReceipt::RESULT_REJECTED),
                'server_time' => now()->toIso8601String(),
            ],
            headers: self::PRIVATE_HEADERS,
        );
    }

    public function pull(PullRequest $request, PullChanges $pull): JsonResponse
    {
        $user = $this->user($request);
        $device = $this->devices->resolve($user, $request);
        $cursor = PullCursor::decode($request->since());

        $result = $pull($user, $device, $cursor, $request->collections(), $request);
        $this->devices->touch($device, synced: true);

        return $this->respond($result->collections, 200, [
            'cursor' => $result->cursor->encode(),
            'has_more' => $result->hasMore,
            'collections' => array_keys($result->collections),
            'until' => $result->until->toIso8601String(),
            'server_time' => now()->toIso8601String(),
            'emergency_numbers' => KnowledgeBaseAnswerer::emergencyNumbers(),
        ], headers: self::PRIVATE_HEADERS);
    }

    public function conflicts(ListConflictsRequest $request): JsonResponse
    {
        $device = $this->devices->resolve($this->user($request), $request);

        $conflicts = SyncConflict::query()->forDevice((int) $device->getKey())
            ->when($request->status() === 'open', fn ($query) => $query->where('status', ConflictStatus::Open->value))
            ->when($request->status() === 'resolved', fn ($query) => $query->where('status', ConflictStatus::Resolved->value))
            ->with('subject')
            ->latestFirst()
            ->paginate($this->perPage($request));

        return $this->paginated($conflicts, SyncConflictResource::class, [
            'open' => SyncConflict::query()->forDevice((int) $device->getKey())->open()->count(),
        ])->withHeaders(self::PRIVATE_HEADERS);
    }

    /**
     * POST sync/conflicts/{conflict}/resolve : le pair tranche lui-même un conflit 409 de ses appareils sur ses propres
     * dossiers (valeur du serveur ou de l'appareil ; SyncConflictPolicy `resolve`, audit `sync.conflict_resolved`) ;
     * l'administration peut aussi fusionner. Conflit d'un autre compte ou d'un dossier qui n'est pas à soi → 404 neutre ;
     * déjà tranché → 422 `conflict_already_resolved`.
     */
    public function resolve(ResolveConflictRequest $request, SyncConflict $conflict, ResolveSyncConflict $resolve): JsonResponse
    {
        $user = $this->user($request);
        $device = $this->devices->resolve($user, $request);
        $conflict->loadMissing(['device', 'subject']);

        // Hors périmètre (autre compte, autre dossier) : même réponse que pour un identifiant inconnu.
        abort_unless($user->can('resolve', $conflict), 404);

        $resolved = $resolve($user, $conflict, $request->resolution(), $request->merged());
        $this->devices->touch($device, synced: false);

        return $this->respond(new SyncConflictResource($resolved->load('subject')), 200, [
            'open' => SyncConflict::query()->forDevice((int) $conflict->device_id)->open()->count(),
        ], headers: self::PRIVATE_HEADERS);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
