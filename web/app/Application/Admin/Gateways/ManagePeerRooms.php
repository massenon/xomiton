<?php

declare(strict_types=1);

namespace App\Application\Admin\Gateways;

use App\Models\PeerRoom;
use App\Models\User;
use App\Support\Audit;

/**
 * Salons de discussion entre pair·es (P12-08, permission `gateways.publish`) : réglages d'un salon (modération par mots
 * interdits, participation sous pseudonyme, taille de 5 à 500, actif). Le fil de discussion (conversation de groupe) est
 * rattaché au salon par le lot de messagerie des pair·es ; un salon ne se supprime pas, on le désactive.
 */
final class ManagePeerRooms
{
    /**
     * @param  array{name: string, description: ?string, is_moderated: bool, allow_anonymous: bool, max_members: int, is_active: bool}  $data
     */
    public function save(?PeerRoom $room, array $data, User $actor): PeerRoom
    {
        $room ??= new PeerRoom;
        $creating = ! $room->exists;

        $room->fill([
            'name' => trim($data['name']),
            'description' => $data['description'] !== null && trim($data['description']) !== '' ? trim($data['description']) : null,
            'is_moderated' => $data['is_moderated'],
            'allow_anonymous' => $data['allow_anonymous'],
            'max_members' => $data['max_members'],
            'is_active' => $data['is_active'],
        ]);

        if ($creating) {
            $room->created_by = $actor->getKey();
        }

        $room->save();
        Audit::log($creating ? 'gateway.room_created' : 'gateway.room_updated', $room, $room->name, $actor);

        return $room;
    }

    public function toggle(PeerRoom $room, User $actor): PeerRoom
    {
        $room->forceFill(['is_active' => ! $room->is_active])->save();
        Audit::log($room->is_active ? 'gateway.room_activated' : 'gateway.room_deactivated', $room, $room->name, $actor);

        return $room;
    }
}
