<?php

declare(strict_types=1);

// Planning de garde `/admin/garde` (P1-17) : vouvoiement (administration).
return [
    'title' => 'Planning de garde',
    'intro' => 'Ce planning décide qui reçoit les SOS et les alertes routées, puis l’escalade. Sans plage en cours, la plateforme se rabat sur tous les comptes admin habilités (garde admin) ou sur la fiche d’annuaire du point focal. Les heures sont celles du Bénin.',
    'new' => 'Ajouter une garde',
    'national' => 'Nationale',
    'until' => 'jusqu’au :date',
    'range_week' => 'Semaine du :from au :to',
    'empty' => 'Aucune garde sur cette période.',
    'empty_text' => 'Ajoutez une garde pour désigner qui est prévenu·e en premier lors d’un SOS.',
    'table_caption' => 'Gardes de la période : :range',

    'kinds' => [
        'admin' => 'Admin de garde',
        'focal' => 'Point focal remplaçant',
    ],

    'kpi' => [
        'now' => 'De garde en ce moment',
        'next' => 'Prochaine garde',
        'period' => 'Gardes sur la période',
    ],

    'now' => [
        'title' => 'En ce moment',
        'none' => 'Aucune garde en cours : les SOS sont envoyés à tous les comptes admin habilités.',
        'no_admin' => 'Aucun·e admin de garde : les SOS sont envoyés à tous les comptes admin habilités.',
        'next' => 'Prochaine garde : :name, à partir du :date.',
    ],

    'list' => ['title' => 'Gardes : :range'],

    'view' => ['label' => 'Affichage', 'week' => 'Semaine', 'month' => 'Mois'],
    'nav' => ['label' => 'Période', 'previous' => 'Période précédente', 'today' => 'Aujourd’hui', 'next' => 'Période suivante'],

    'columns' => [
        'person' => 'Personne',
        'kind' => 'Type',
        'zone' => 'Zone',
        'starts' => 'Début',
        'ends' => 'Fin',
        'state' => 'État',
        'actions' => 'Actions',
    ],

    'state' => ['active' => 'En cours', 'upcoming' => 'À venir', 'finished' => 'Terminée'],

    'actions' => [
        'edit' => 'Modifier',
        'delete' => 'Supprimer',
        'edit_of' => 'Modifier la garde de :label',
        'delete_of' => 'Supprimer la garde de :label',
    ],

    'fields' => [
        'kind' => 'Type de garde',
        'user_id' => 'Personne de garde',
        'zone_id' => 'Zone couverte',
        'starts_at' => 'Début',
        'ends_at' => 'Fin',
        'notes' => 'Note interne (facultatif)',
    ],

    'form' => [
        'title_new' => 'Ajouter une garde',
        'title_edit' => 'Modifier la garde',
        'choose_person' => 'Choisir une personne',
        'kind_hint_admin' => 'Compte admin ou super-admin : prévenu en premier lors d’un SOS, partout au Bénin.',
        'kind_hint_focal' => 'Compte lié à une fiche d’annuaire active de point focal : remplace le point focal de la zone (ou national) pendant la plage.',
        'timezone_hint' => 'Heures saisies dans le fuseau :timezone. La fin n’est pas incluse : une garde peut commencer à l’heure exacte où la précédente se termine.',
        'save' => 'Enregistrer',
    ],

    'delete' => [
        'title' => 'Supprimer cette garde ?',
        'text' => 'La garde de :name, du :from au :to, sera supprimée.',
        'warning_admin_others' => 'Cette garde est en cours : :name ne recevra plus les SOS dès maintenant. Les autres admins de garde restent prévenus.',
        'warning_admin_alone' => 'Cette garde est en cours et c’est la seule : les SOS seront envoyés à tous les comptes admin habilités (:roles) dès maintenant.',
        'warning_focal' => 'Cette garde est en cours : le routage reviendra à la fiche d’annuaire du point focal dès maintenant.',
        'confirm' => 'Supprimer la garde',
    ],

    'toasts' => [
        'created' => 'Garde ajoutée.',
        'updated' => 'Garde modifiée.',
        'deleted' => 'Garde supprimée.',
        'deleted_active' => 'Garde en cours supprimée : le routage des SOS est déjà mis à jour.',
    ],

    'errors' => [
        'not_found' => 'Cette garde n’existe plus.',
        'finished' => 'Cette garde est terminée : elle ne peut plus être modifiée.',
        'invalid_date' => 'Cette date n’est pas valide.',
        'order' => 'La fin doit être après le début.',
        'too_long' => 'Une garde dure :days jours au plus.',
        'past' => 'La fin de la garde doit être dans le futur.',
        'not_eligible_admin' => 'Choisissez un compte actif de rôle admin ou super-admin.',
        'not_eligible_focal' => 'Choisissez un compte actif lié à une fiche d’annuaire active de point focal.',
        'overlap_admin' => ':name est déjà de garde du :from au :to : cette plage la chevauche.',
        'overlap_focal' => 'Un point focal (:name) est déjà de garde sur cette zone du :from au :to : une seule personne peut l’être à la fois.',
    ],
];
