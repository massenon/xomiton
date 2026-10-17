<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Auth\ConnectedDevices;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appareil connecté (`GET me/sessions`) : jeton Sanctum (`token-<id>`) ou session web (`web-<empreinte>`, jamais
 * l'identifiant de session lui-même). `current` marque le jeton ou la session de la requête.
 *
 * @property string $id
 * @property string $kind
 * @property string|null $name
 * @property string|null $ip
 * @property bool $current
 * @property \Illuminate\Support\Carbon|null $last_active_at
 * @property \Illuminate\Support\Carbon|null $created_at
 */
final class SessionResource extends JsonResource
{
    public const KIND_TOKEN = ConnectedDevices::KIND_TOKEN;

    public const KIND_WEB = ConnectedDevices::KIND_WEB;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->resource;

        return [
            'id' => $row['id'],
            'kind' => $row['kind'],
            'name' => $row['name'],
            'ip' => $row['ip'] ?? null,
            'current' => (bool) ($row['current'] ?? false),
            'last_active_at' => $row['last_active_at']?->toIso8601String(),
            'created_at' => $row['created_at']?->toIso8601String(),
        ];
    }
}
