<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Models\SyncDevice;
use App\Models\User;

/**
 * Lot en cours : compte connecté, appareil enregistré (`X-Device-Id`, SyncDeviceResolver), lecteur des charges utiles
 * et quotas par élément.
 */
final readonly class PushContext
{
    public function __construct(
        public User $user,
        public SyncDevice $device,
        public ItemDecoder $decoder,
        public ItemLimiter $limiter,
    ) {}

    public function userId(): int
    {
        return (int) $this->user->getKey();
    }

    public function deviceId(): int
    {
        return (int) $this->device->getKey();
    }
}
