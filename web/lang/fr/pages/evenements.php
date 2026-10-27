<?php

declare(strict_types=1);

/*
 * Événements publics `/evenements`, `/evenements/{ulid}` pour les visiteur·ses (P8-04, Q35 a ; prototype events.php,
 * event-details.php). Registre : vous (site public institutionnel, VOICE_AND_TONE.md §3, Q36). Seuls les événements
 * ouverts au public (`events.is_public`) sont listés, sans adresse exacte ni lien de connexion (réservés aux
 * participant·es confirmé·es) ; les membres connecté·es voient la page Vue de l'espace membre (lang/fr/community.php).
 */
return [

    'title' => 'Événements',
    'intro' => 'Ateliers, causeries, dépistages et formations ouverts à toutes et tous, près de chez vous ou en ligne.',
    'og_alt' => 'Événements Xomiton : ateliers, causeries et dépistages ouverts à la communauté',
    'filters_label' => 'Filtrer par catégorie',
    'filter_all' => 'Tous',
    'list_title' => 'Prochains événements publics',
    'image_alt' => 'Visuel de l’événement : :title',
    'empty' => 'Aucun événement public à venir pour le moment.',
    'empty_category' => 'Aucun événement public à venir dans cette catégorie.',
    'show_all' => 'Voir tous les événements',
    'participants' => '{0} Aucune inscription pour le moment|{1} :count personne inscrite|[2,*] :count personnes inscrites',
    'details' => 'Voir l’événement',
    'details_label' => 'Voir l’événement « :title »',
    'load_more' => 'Charger plus',

    // Encart « Rejoindre » (liste et fiche)
    'join_title' => 'Participer à un événement',
    'join_text' => 'Créez un compte gratuit ou connectez-vous pour répondre « Je participe », recevoir l’adresse exacte et un rappel la veille.',
    'join_login' => 'Se connecter',
    'join_register' => 'Créer un compte',
    'members_more' => 'D’autres rendez-vous sont réservés aux membres de la communauté.',

    // Fiche
    'description' => 'Description',
    'no_description' => 'Aucune description renseignée.',
    'organizer' => 'Organisé par :name',
    'address_hidden' => 'Pour la sécurité de chacune et chacun, l’adresse exacte et le lien de connexion sont communiqués aux seules personnes inscrites.',
    'charter' => 'L’événement se déroule dans le respect de la charte Xomiton : confidentialité, bienveillance et consentement pour toute photo.',
    'past_badge' => 'Événement passé',
    'participate' => 'Je participe',
    'others' => 'Autres événements',
    'back' => 'Tous les événements',
    'share' => 'Partager',
    'share_copied' => 'Lien copié.',
    'share_failed' => 'Impossible de partager le lien. Copiez l’adresse de la page.',
];
