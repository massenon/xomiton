<?php

declare(strict_types=1);

/*
 * Libellés des enums du domaine « passerelles réseaux sociaux » (lot 9, D4-bis) : app/Enums/SocialNetwork,
 * OutboundKind, OutboundStatus, GatewayDeliveryStatus.
 */
return [

    'social_network' => [
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'romeo' => 'Roméo',
        'ganxo' => 'GanXo',
        'telegram' => 'Telegram',
        'tinder' => 'Tinder',
    ],

    'outbound_kind' => [
        'bd' => 'BD SSR',
        'template' => 'Message clé',
        'custom' => 'Personnalisé',
    ],

    'outbound_status' => [
        'planifie' => 'Planifiée',
        'publie' => 'Publiée',
        'annule' => 'Annulée',
        'echec' => 'Échec',
    ],

    'gateway_delivery_status' => [
        'en_attente' => 'En attente',
        'publie' => 'Publiée',
        'echec' => 'Échec',
        'manuel' => 'Publiée à la main',
    ],

];
