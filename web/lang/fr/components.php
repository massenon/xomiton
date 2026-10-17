<?php

declare(strict_types=1);

// Textes des composants Blade x-* (MICROCOPY.md, COMPOSANTS.md). Les libellés transverses (Annuler, Fermer, Envoi…) sont dans common.php.
return [
    'avatar' => [
        'alt' => 'Avatar de :name',
    ],

    'badge' => [
        'status_prefix' => 'Statut :',
    ],

    'bell' => [
        'label' => 'Notifications',
        'unread' => '{0} notification non lue|{1} notification non lue|[2,*] notifications non lues',
        'unread_item' => '(non lue)',
        'mark_all_read' => 'Tout marquer comme lu',
        'filter' => 'Filtrer les notifications',
        'tabs' => [
            'unread' => 'Non lus',
            'read' => 'Lues',
            'all' => 'Toutes les notifications',
        ],
        'empty' => 'Aucune notification dans cette liste.',
        'see_all' => 'Voir toutes les notifications',
    ],

    'breadcrumb' => [
        'label' => 'Fil d’Ariane',
    ],

    'datatable' => [
        'empty' => 'Aucun résultat.',
        'sort' => 'Trier par :column',
    ],

    'error_summary' => [
        'title' => '{1} :count erreur à corriger|[2,*] :count erreurs à corriger',
    ],

    'field' => [
        'optional' => '(facultatif)',
        'show_password' => 'Afficher le mot de passe',
        'hide_password' => 'Masquer le mot de passe',
        'counter' => ':count / :max',
    ],

    'img' => [
        'missing_alt' => '<x-img> exige un attribut alt (utiliser alt="" pour une image décorative).',
    ],

    'logo' => [
        'alt' => 'Xomiton',
        'home' => 'Xomiton, accueil',
    ],

    'modal' => [
        'confirm_word_label' => 'Pour confirmer, saisis le mot suivant :',
        'delete_action' => 'Supprimer définitivement',
        'dirty_guard' => 'Abandonner les modifications ?',
    ],

    'skeleton' => [
        'loading' => 'Chargement…',
    ],

    'sos' => [
        'label' => 'SOS',
        'help' => 'Maintiens le bouton 2 secondes, ou appuie sur Entrée puis confirme. Tu pourras annuler pendant 5 secondes.',
        'confirm_title' => 'Envoyer une alerte SOS ?',
        'confirm_text' => 'Ta position et ton message seront envoyés à la personne de l’annuaire la plus proche.',
        'countdown_title' => 'Envoi de ton alerte…',
        'countdown' => 'Envoi de ton alerte dans :s… Tu peux encore annuler.',
        'cancel' => 'Annuler l’alerte',
        'cancelled' => 'Alerte annulée.',
        'send' => 'Envoyer',
        'sent' => 'Ton alerte est partie.',
        'sent_text' => 'Reste si possible dans un lieu public ou éclairé. La personne contactée va te rappeler.',
        'safe' => 'Je suis en sécurité',
    ],

    'stat' => [
        'trend_up' => 'en hausse',
        'trend_down' => 'en baisse',
        'trend_flat' => 'stable',
    ],

    'stepper' => [
        'label' => 'Étapes',
        'done' => 'terminée',
        'current' => 'en cours',
        'todo' => 'à venir',
    ],

    'tabs' => [
        'label' => 'Onglets',
    ],

    'timeline' => [
        'label' => 'Chronologie du dossier',
        'empty' => 'Aucune étape enregistrée pour ce dossier.',
        'next' => 'Prochain suivi : :date',
        'late' => 'En retard',
        'kinds' => [
            'internal' => 'Note interne',
            'shared' => 'Note partagée',
            'status' => 'Changement de statut',
        ],
    ],

    'urgency' => [
        'prefix' => 'Urgence :',
        'levels' => [
            'low' => 'Faible',
            'medium' => 'Moyenne',
            'high' => 'Élevée',
            'critical' => 'Critique',
        ],
    ],

    // Statuts prêts à l'emploi pour <x-badge scope="…" :status="…"> (couleur = sens, toujours un libellé en toutes lettres).
    'status' => [
        'case' => [
            'new' => ['label' => 'Nouveau', 'color' => 'primary', 'icon' => 'stars'],
            'assigned' => ['label' => 'Assigné', 'color' => 'violet', 'icon' => 'person-check'],
            'in_progress' => ['label' => 'En cours', 'color' => 'info', 'icon' => 'arrow-repeat'],
            'pending_victim_response' => ['label' => 'En attente de réponse', 'color' => 'warning', 'icon' => 'hourglass-split'],
            'resolved' => ['label' => 'Résolu', 'color' => 'success', 'icon' => 'check-circle'],
            'closed' => ['label' => 'Clos', 'color' => 'neutral', 'icon' => 'archive'],
            'unresolved' => ['label' => 'Non résolu', 'color' => 'danger', 'icon' => 'x-octagon'],
        ],
        'alert' => [
            'nouveau' => ['label' => 'Nouveau', 'color' => 'danger', 'icon' => 'bell-fill'],
            'en_cours' => ['label' => 'En cours', 'color' => 'warning', 'icon' => 'hourglass-split'],
            'traite' => ['label' => 'Traité', 'color' => 'success', 'icon' => 'check2-circle'],
        ],
        'priority' => [
            'critique' => ['label' => 'Critique', 'color' => 'danger', 'icon' => 'exclamation-octagon-fill'],
            'haute' => ['label' => 'Haute', 'color' => 'warning', 'icon' => 'exclamation-triangle'],
            'normale' => ['label' => 'Normale', 'color' => 'neutral', 'icon' => 'dash-circle'],
        ],
        'alert_type' => [
            'denonciation' => ['label' => 'Signalement', 'color' => 'primary', 'soft' => true, 'icon' => 'megaphone'],
            'besoin' => ['label' => 'Besoin', 'color' => 'info', 'soft' => true, 'icon' => 'life-preserver'],
            'sos' => ['label' => 'SOS', 'color' => 'danger', 'icon' => 'geo-alt-fill'],
        ],
    ],
];
