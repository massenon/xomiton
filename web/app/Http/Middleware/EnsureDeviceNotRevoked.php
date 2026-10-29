<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Application\Account\DeviceRegistry;
use App\Domain\Account\Exceptions\DeviceRevoked;
use App\Models\SyncDevice;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appareil révoqué (par la personne, l'administration, `sync:prune` ou un ordre d'effacement à distance, P15-11) :
 * toute requête **authentifiée** qui porte son `X-Device-Id` répond 403 `device_revoked` (l'application purge ses
 * données locales et génère un nouvel UUID). Un en-tête absent, invalide, inconnu ou d'un autre compte ne déclenche rien
 * (la route concernée décide : `sync/*` exige un appareil enregistré, `device_required`). Les routes publiques (SOS,
 * signalement, urgences) ne passent pas ici : elles ne sont jamais bloquées (RG-01).
 *
 * Exceptions, pour qu'un appareil révoqué puisse se mettre en règle : l'accusé d'effacement, la déconnexion, le retrait
 * de son abonnement push et de l'appareil ; et, tant qu'un ordre d'effacement attend son accusé, `GET me` et `POST
 * heartbeat` (qui portent l'ordre, champ `wipe`). À poser après `auth:sanctum`.
 */
final class EnsureDeviceNotRevoked
{
    /** Toujours permis à un appareil révoqué. */
    private const ALWAYS = ['api.me.devices.wiped', 'api.me.devices.destroy', 'api.me.devices.store', 'api.auth.logout', 'api.me.push_subscriptions.destroy'];

    /** Permis tant qu'un effacement attend son accusé : l'appareil y lit l'ordre. */
    private const WHILE_WIPE_PENDING = ['api.me.show', 'api.heartbeat'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $uuid = trim((string) $request->header(DeviceRegistry::HEADER, ''));

        if (! $user instanceof User || ! Str::isUuid($uuid)) {
            return $next($request);
        }

        $device = SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->first();

        if ($device === null || ! $device->isRevoked()) {
            return $next($request);
        }

        $route = (string) $request->route()?->getName();

        if (in_array($route, self::ALWAYS, true) || ($device->wipePending() && in_array($route, self::WHILE_WIPE_PENDING, true))) {
            return $next($request);
        }

        throw new DeviceRevoked;
    }
}
