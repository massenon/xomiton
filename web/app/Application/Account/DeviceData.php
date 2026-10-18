<?php

declare(strict_types=1);

namespace App\Application\Account;

/**
 * Appareil déclaré par l'application (POST me/devices, P6-09) : UUID généré à l'installation, libellé lisible
 * (« Pixel 7 »), plateforme (« Android 13 · Redmi 10 ») et version de l'application.
 */
final readonly class DeviceData
{
    public function __construct(
        public string $uuid,
        public string $label,
        public ?string $platform = null,
        public ?string $appVersion = null,
    ) {}
}
