<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\SocialNetwork;
use App\Models\SocialChannel;

/**
 * gateways.json → social_channels (name → network, connected → is_connected, since → connected_at,
 * account → account_handle). Déjà semés : réalignés par réseau ; libellé, icône, couleur et API viennent
 * du seeder ou de l'enum. Aucun jeton n'existe dans le prototype (connexions simulées).
 */
final class ImportSocialChannels extends AbstractImporter
{
    public static function collection(): string
    {
        return 'gateways';
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $this->required($row['name'] ?? null, 'name');
        $network = SocialNetwork::fromPrototype($name) ?? $this->reject(sprintf('réseau inconnu « %s »', $name));

        $channel = SocialChannel::query()->firstOrNew(['network' => $network->value]);
        $connected = (bool) ($row['connected'] ?? false);

        if (! $channel->exists) {
            $channel->fill([
                'label' => $network->label(),
                'icon' => $network->defaultIcon(),
                'color' => $network->defaultColor(),
                'supports_api' => $network->supportsApi(),
            ]);
        }

        $channel->fill([
            'is_connected' => $connected,
            'account_handle' => $connected ? $this->text($row['account'] ?? null, 60) : null,
            'connected_at' => $connected ? ($this->ctx->dateTime($row['since'] ?? null) ?? $channel->connected_at ?? now()) : null,
        ]);

        $channel->save();
        $this->track($channel);
    }
}
