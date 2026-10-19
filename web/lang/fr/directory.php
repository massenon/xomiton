<?php

declare(strict_types=1);

/*
 * Annuaire membre `/annuaire` (P8-12, lot annuaire-membre ; prototype annuaire.php), espace connecté : tutoiement
 * (VOICE_AND_TONE.md §3). Textes de la modale de rappel complétés par `alerts.callback.*` (P5-02). Exporté vers
 * vue-i18n par `php artisan lang:export`.
 */
return [
    'title' => 'Annuaire',
    'heading' => 'Annuaire dynamique',
    'intro' => 'Points focaux, juristes, psychologues et responsables de réseaux près de chez toi.',
    'urgences' => 'Urgences',
    'network_notice' => 'Tu vois aussi les contacts réservés au réseau. Ne les partage pas en dehors de ton accompagnement.',

    'filters' => [
        'label' => 'Filtrer l’annuaire',
        'search' => 'Nom, organisation, commune…',
        'search_label' => 'Recherche',
        'role' => 'Rôle',
        'all_roles' => 'Rôle',
        'zone' => 'Département',
        'all_zones' => 'Département',
        'commune' => 'Commune',
        'all_communes' => 'Commune',
        'submit' => 'Filtrer',
        'reset' => 'Réinitialiser',
        'results' => '{0} Aucun résultat|{1} 1 résultat|[2,*] :count résultats',
    ],

    'map' => [
        'label' => 'Carte schématique du Bénin',
        'caption' => 'Bénin · par département',
        'pin' => ':zone : :count contact|:zone : :count contacts',
        'national' => 'National : :count contact|National : :count contacts',
        'hint' => 'Choisis un département sur la carte ou dans la liste.',
    ],

    'card' => [
        'national' => 'National',
        'reserved' => 'Réservé au réseau',
        'call' => 'Appeler',
        'whatsapp' => 'WhatsApp',
        'email' => 'E-mail',
        'callback' => 'Demander à être rappelé·e',
        'callback_unavailable' => 'Pas de demande de rappel pour un contact réservé au réseau.',
    ],

    'empty' => 'Aucun contact ne correspond à ces critères.',
    'empty_all' => 'Voir tout l’annuaire',
    'loaded' => '{0} Aucun contact de plus|{1} 1 contact de plus|[2,*] :count contacts de plus',

    'callback' => [
        'who' => 'Tu seras rappelé·e par :name (:role).',
        'only_them' => 'Ta demande est transmise comme un « besoin » à cette personne uniquement.',
        'contact_help' => 'Téléphone ou e-mail, utilisé uniquement pour ce rappel.',
        'placeholder' => '+229 01 …',
        'message_placeholder' => 'Que souhaites-tu dire ?',
        'anonymous' => 'Ne pas transmettre mon pseudo',
        'anonymous_help' => 'Coché : la personne ne voit que ton numéro ou ton adresse.',
        'submit' => 'Envoyer la demande',
        'errors_title' => 'Ta demande n’est pas encore partie :',
        'tracking' => 'Ton code de suivi : :code',
        'contact_required' => 'Indique un numéro ou une adresse pour être rappelé·e.',
        'message_max' => ':max caractères au plus.',
        'offline' => 'Pas de connexion : ta demande n’est pas partie. Réessaie quand le réseau revient, ou appelle directement.',
    ],
];
