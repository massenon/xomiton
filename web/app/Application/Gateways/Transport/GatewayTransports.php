<?php

declare(strict_types=1);

namespace App\Application\Gateways\Transport;

/**
 * Choisit le transport des connecteurs (P14-04) selon `xomiton.gateways.driver` (`XOMITON_GATEWAYS_DRIVER`, `log` par défaut).
 * Un pilote inconnu retombe sur `log` : jamais d'appel réseau par accident. Extension : voir GatewayTransport.
 */
final class GatewayTransports
{
    /** @var array<string, class-string<GatewayTransport>> pilotes disponibles */
    public const DRIVERS = ['log' => LogGatewayTransport::class];

    public static function make(): GatewayTransport
    {
        $class = self::DRIVERS[(string) config('xomiton.gateways.driver', 'log')] ?? LogGatewayTransport::class;

        return app($class);
    }
}
