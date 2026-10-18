<?php

declare(strict_types=1);

/*
 * Mon compte côté API (lot api-me : P6-06, P6-07, P6-09, docs/API.md §3 et §7). Espace connecté : tutoiement
 * (VOICE_AND_TONE.md §3, Q36). Les libellés des écrans de paramètres restent dans lang/fr/profile.php (settings.*).
 */
return [

    'security' => [
        'pin_never_sent' => 'Ton code PIN reste sur ton appareil : ne l’envoie jamais au serveur.',
    ],

    'notifications' => [
        'filter' => 'Filtre',
        'category' => 'Catégorie',
    ],

    'devices' => [
        'revoked' => 'Cet appareil a été déconnecté de ton compte. Réinstalle ou réinitialise l’application pour l’enregistrer à nouveau.',
        'taken' => 'Cet identifiant d’appareil est déjà utilisé. Réinitialise l’application pour en générer un nouveau.',
        'fields' => [
            'device_uuid' => 'Identifiant de l’appareil',
            'label' => 'Nom de l’appareil',
            'platform' => 'Système',
            'app_version' => 'Version de l’application',
        ],
    ],

    'push' => [
        'fields' => [
            'kind' => 'Type d’abonnement',
            'endpoint' => 'Adresse d’abonnement',
            'p256dh' => 'Clé publique',
            'auth' => 'Clé d’authentification',
            'token' => 'Jeton de l’appareil',
            'device_label' => 'Nom de l’appareil',
        ],
        'endpoint_host' => 'Cette adresse d’abonnement ne vient pas d’un service de notifications reconnu par ton navigateur.',
    ],

    'newsletter' => [
        'subscribed' => 'Recevoir la newsletter',
        'bounced' => 'Nos e-mails n’arrivent plus à ton adresse. Vérifie-la ou écris-nous pour réactiver la newsletter.',
        'email_missing' => 'Ajoute une adresse e-mail à ton compte pour recevoir la newsletter.',
        'not_subscribed' => 'Abonne-toi d’abord à la newsletter pour choisir tes centres d’intérêt.',
    ],

    'export' => [
        'expiry' => '{1} :hours heure|[2,*] :hours heures',
        // Lien de l'e-mail `export-donnees` expiré ou altéré (GET /mes-donnees/export/{token}).
        'link_invalid' => [
            'title' => 'Lien expiré',
            'text' => 'Ce lien de téléchargement n’est plus valable : il ne dure que :expiry, ou il a été modifié. Redemande un export depuis tes paramètres, tu recevras un nouveau lien par e-mail.',
            'action' => 'Redemander un export',
        ],
    ],

    // Suppression du compte (DELETE me) : motif enregistré sur l'abonnement newsletter désactivé.
    'deletion' => [
        'newsletter_reason' => 'Compte supprimé',
    ],

];
