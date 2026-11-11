<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Sync;

use App\Application\Cases\ValidationCatalog;
use App\Application\Sync\SyncDeviceResolver;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Sync\HeartbeatRequest;
use App\Http\Resources\Me\DeviceResource;
use App\Models\SyncConflict;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * `POST heartbeat` (P9-06, docs/API.md §21.5 ; prototype api.php `ping`) : signal de vie de l'application — dernier
 * contact de l'appareil (`last_seen_at`), éléments en attente annoncés (`pending_count`), version ; rend l'heure du
 * serveur et le nombre de conflits ouverts de l'appareil. Liste blanche de maintenance (RG-01, P2-11).
 */
final class HeartbeatController extends ApiController
{
    public function __invoke(HeartbeatRequest $request, SyncDeviceResolver $devices, ValidationCatalog $rules): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $device = $devices->resolveWithWipe($user, $request);

        // Appareil révoqué par un ordre d'effacement (P15-11) : il lit l'ordre et rien d'autre, ni dernier contact ni données.
        if ($device->isRevoked()) {
            return $this->respond([
                'server_time' => now()->toIso8601String(),
                'wipe' => DeviceResource::wipe($device),
            ], headers: ['Cache-Control' => 'no-store, private']);
        }

        if ($request->appVersion() !== null) {
            $device->forceFill(['app_version' => Str::limit($request->appVersion(), 20, '')]);
        }

        $devices->touch($device, synced: false, pending: $request->pending());

        return $this->respond([
            'server_time' => now()->toIso8601String(),
            'device' => [
                'device_uuid' => $device->device_uuid,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                'pending_count' => (int) $device->pending_count,
            ],
            'open_conflicts' => SyncConflict::query()->forDevice((int) $device->getKey())->open()->count(),
            // Version des règles de validation (P15-12) : plus récente que celle en cache → relire `pair/validation-rules`.
            'rules_updated_at' => $rules->lastUpdatedAt(),
            // Ordre d'effacement à distance en attente d'accusé (P15-11) : null sinon. Un appareil actif n'en porte jamais.
            'wipe' => null,
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
