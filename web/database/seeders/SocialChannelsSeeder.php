<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SocialNetwork;
use App\Models\GatewayTemplate;
use App\Models\SocialChannel;
use Illuminate\Database\Seeder;

/**
 * Passerelles du lot 9 (DATABASE_DICTIONARY.md §8), reprises du prototype : les six réseaux de
 * x_social_gateways() (prototype_social/inc/data.php : libellé, icône, couleur) et les trois messages clés
 * de prototype_social/data/gateway_templates.json. Tous les réseaux sont semés déconnectés : les connexions
 * du prototype (Facebook, Telegram) étaient simulées et aucun jeton réel n'existe.
 * Idempotent : libellé, icône, couleur et API sont réalignés à chaque exécution ; l'état de connexion et les
 * jetons posés par l'administration sont conservés ; les modèles gardent `is_active` s'il a été modifié.
 */
class SocialChannelsSeeder extends Seeder
{
    /** @var list<array{0: SocialNetwork, 1: string, 2: string, 3: string}> réseau, libellé, icône, couleur (x_social_gateways()) */
    private const NETWORKS = [
        [SocialNetwork::Facebook, 'Facebook', 'bi-facebook', '#1877F2'],
        [SocialNetwork::TikTok, 'TikTok', 'bi-tiktok', '#000000'],
        [SocialNetwork::Romeo, 'Roméo', 'bi-heart-fill', '#E4007C'],
        [SocialNetwork::GanXo, 'GanXo', 'bi-chat-heart-fill', '#7A2BBE'],
        [SocialNetwork::Telegram, 'Telegram', 'bi-telegram', '#229ED9'],
        [SocialNetwork::Tinder, 'Tinder', 'bi-fire', '#FF5864'],
    ];

    /** @var list<SocialNetwork> réseaux disposant d'une API de publication (les autres : publication manuelle assistée) */
    private const API_NETWORKS = [SocialNetwork::Facebook, SocialNetwork::Telegram, SocialNetwork::TikTok];

    /** @var list<array{title: string, body: string, category: string}> data/gateway_templates.json, catégorie déduite du titre */
    private const TEMPLATES = [
        [
            'title' => 'Prévention – consentement',
            'body' => 'Le consentement est libre, enthousiaste et réversible. Besoin d’en parler ? Xomiton est là, sans jugement.',
            'category' => GatewayTemplate::CATEGORY_PREVENTION,
        ],
        [
            'title' => 'Prévention – dépistage',
            'body' => 'Se faire dépister, c’est prendre soin de soi. Gratuit et confidentiel dans nos centres partenaires.',
            'category' => GatewayTemplate::CATEGORY_DEPISTAGE,
        ],
        [
            'title' => 'Aide – SOS',
            'body' => 'Tu es en danger ou tu connais quelqu’un qui l’est ? Utilise le bouton SOS Xomiton : une personne de confiance te répond.',
            'category' => GatewayTemplate::CATEGORY_SOS,
        ],
    ];

    public function run(): void
    {
        $this->seedChannels();
        $this->seedTemplates();
    }

    private function seedChannels(): void
    {
        foreach (self::NETWORKS as [$network, $label, $icon, $color]) {
            $existing = SocialChannel::query()->ofNetwork($network)->first();

            SocialChannel::query()->updateOrCreate(
                ['network' => $network->value],
                [
                    'label' => $label,
                    'icon' => $icon,
                    'color' => $color,
                    'supports_api' => in_array($network, self::API_NETWORKS, true),
                    // État de connexion et jetons : jamais écrasés au rejeu, déconnecté à la création.
                    'is_connected' => $existing?->is_connected ?? false,
                    'account_handle' => $existing?->account_handle,
                    'connected_at' => $existing?->connected_at,
                    'connected_by' => $existing?->connected_by,
                ],
            );
        }
    }

    private function seedTemplates(): void
    {
        $position = 0;

        foreach (self::TEMPLATES as $template) {
            $position++;

            $existing = GatewayTemplate::query()->where('title', $template['title'])->first();

            GatewayTemplate::query()->updateOrCreate(
                ['title' => $template['title']],
                [
                    'body' => $template['body'],
                    'category' => $template['category'],
                    'position' => $position,
                    'is_active' => $existing?->is_active ?? true,
                ],
            );
        }
    }
}
