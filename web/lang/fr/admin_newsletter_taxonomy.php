<?php

declare(strict_types=1);

// Onglet « Tags et centres d'intérêt » de /admin/newsletter (P14-02) : vouvoiement (administration).
return [
    'intro' => 'Les tags servent à segmenter les abonné·es (campagnes, filtres, import) ; les centres d’intérêt sont aussi proposés aux membres dans leur profil et sur le formulaire d’inscription. L’identifiant se déduit du libellé à la création et ne change plus. Un élément utilisé ne peut pas être supprimé.',
    'empty_text' => 'Ajoutez-en un pour pouvoir le choisir dans les segments et les fiches d’abonné·es.',

    'kinds' => [
        'tag' => [
            'title' => 'Tags',
            'new' => 'Ajouter un tag',
            'title_new' => 'Ajouter un tag',
            'title_edit' => 'Renommer le tag',
            'caption' => 'Tags de segmentation des abonné·es',
            'empty' => 'Aucun tag.',
        ],
        'interest' => [
            'title' => 'Centres d’intérêt',
            'new' => 'Ajouter un centre d’intérêt',
            'title_new' => 'Ajouter un centre d’intérêt',
            'title_edit' => 'Renommer le centre d’intérêt',
            'caption' => 'Centres d’intérêt des abonné·es',
            'empty' => 'Aucun centre d’intérêt.',
        ],
    ],

    'columns' => [
        'label' => 'Libellé',
        'slug' => 'Identifiant',
        'usage' => 'Utilisation',
        'actions' => 'Actions',
    ],

    'usage' => [
        'none' => 'Inutilisé',
        'summary' => ':subscribers abonné·es · :profiles profils · :campaigns campagnes à venir',
    ],

    'actions' => [
        'rename' => 'Renommer',
        'delete' => 'Supprimer',
        'rename_of' => 'Renommer « :label »',
        'delete_of' => 'Supprimer « :label »',
    ],

    'fields' => ['label' => 'Libellé'],

    'form' => [
        'hint_new' => '2 à 60 caractères. L’identifiant en sera déduit et ne pourra plus changer.',
        'hint_edit' => 'Seul le libellé change ; l’identifiant reste le même pour les abonné·es et les segments.',
        'save' => 'Enregistrer',
    ],

    'delete' => [
        'title' => 'Supprimer cet élément ?',
        'text' => '« :label » sera supprimé. Il n’est utilisé nulle part.',
        'refused' => '« :label » ne peut pas être supprimé : il est utilisé (:usage). Retirez-le d’abord des abonné·es et des campagnes concernés.',
        'confirm' => 'Supprimer',
    ],

    'toasts' => [
        'created' => '« :label » ajouté.',
        'updated' => '« :label » renommé.',
        'deleted' => '« :label » supprimé.',
        'in_use' => '« :label » est utilisé : suppression refusée.',
    ],

    'errors' => [
        'not_found' => 'Cet élément n’existe plus.',
        'label_length' => 'Le libellé doit faire entre :min et :max caractères.',
        'label_empty' => 'Le libellé doit contenir des lettres ou des chiffres.',
        'label_taken' => 'Ce libellé existe déjà.',
    ],
];
