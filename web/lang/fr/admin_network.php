<?php

declare(strict_types=1);

/*
 * Administration, Réseau social `/admin/reseau` (P12-05 ; prototype admin/reseau.php). Registre : vous, neutre et précis
 * (VOICE_AND_TONE.md §3, administration). Aucune identité d’auteur·rice anonyme, aucun récit.
 */
return [

    'title' => 'Réseau social',
    'intro' => 'Groupes, pages d’organisations, événements, sujets de forum et publications. Une page d’organisation n’est visible du public qu’une fois approuvée ; « En avant » place une page ou un groupe en tête de sa liste. Masquer est réversible, supprimer ne l’est pas.',
    'tabs_label' => 'Sections du réseau social',
    'tabs' => ['groupes' => 'Groupes', 'pages' => 'Pages', 'evenements' => 'Événements', 'forums' => 'Sujets de forum', 'publications' => 'Publications', 'gestion' => 'Gestion des forums'],
    'tab_pending' => ':count à approuver',
    'search_help' => 'La recherche porte sur les noms, titres et textes en clair.',
    'hidden_reason' => 'Masquée par l’équipe de modération.',
    'kpi' => [
        'groups' => 'Groupes',
        'pages' => 'Pages',
        'pending' => '{1} 1 en attente d’approbation|[2,*] :count en attente d’approbation',
        'events' => 'Événements publiés',
        'posts' => 'Publications visibles',
    ],
    'columns' => [
        'group' => 'Groupe', 'page' => 'Page', 'event' => 'Événement', 'topic' => 'Sujet', 'post' => 'Publication', 'privacy' => 'Confidentialité',
        'members' => 'Membres', 'followers' => 'Abonné·es', 'category' => 'Catégorie', 'date' => 'Date', 'going' => 'Participant·es', 'replies' => 'Réponses',
        'reports' => 'Signalements', 'state' => 'État',
    ],
    'states' => ['all' => 'Tous les états', 'visible' => 'Visibles', 'hidden' => 'Masqués', 'pending' => 'À approuver'],
    'badges' => ['approved' => 'Approuvée', 'pending' => 'En attente', 'hidden' => 'Masqué', 'featured' => 'En avant', 'pinned' => 'Épinglé', 'locked' => 'Verrouillé'],
    'actions' => ['view' => 'Voir', 'hide' => 'Masquer', 'show' => 'Rendre visible', 'delete' => 'Supprimer', 'approve' => 'Approuver', 'unapprove' => 'Retirer l’approbation', 'feature' => 'En avant', 'pin' => 'Épingler', 'lock' => 'Verrouiller'],
    'confirm' => [
        'hide' => 'Masquer « :name » ? Cet élément disparaît du site tant que vous ne le rendez pas visible.',
        'delete' => 'Supprimer « :name » ? Cette action ne peut pas être annulée.',
        'unapprove' => 'Retirer l’approbation de « :name » ? La page ne sera plus visible que de sa propriétaire et de la modération.',
        'hide_post' => 'Masquer cette publication ? Elle disparaît du fil tant que vous ne la rendez pas visible.',
        'delete_post' => 'Supprimer cette publication ? Cette action ne peut pas être annulée.',
    ],
    'toasts' => [
        'hidden' => 'Masqué.',
        'shown' => 'Rendu visible.',
        'deleted' => 'Supprimé.',
        'approved' => 'Page « :name » approuvée.',
        'unapproved' => 'Approbation de « :name » retirée.',
        'featured' => 'Mis en avant.',
        'unfeatured' => 'Fin de la mise en avant.',
        'pinned' => 'Sujet épinglé en tête de son forum.',
        'unpinned' => 'Sujet désépinglé.',
        'locked' => 'Sujet verrouillé : plus de réponse possible.',
        'unlocked' => 'Sujet déverrouillé.',
    ],
    'owner' => 'propriétaire : :name',
    'by' => 'par :name',
    'no_zone' => 'Sans zone',
    'online' => 'En ligne',
    'post_sr' => 'publication n° :id',
    'likes_comments' => ':likes mention(s) « J’aime » · :comments commentaire(s)',

    'groupes' => ['search_placeholder' => 'Nom, description ou adresse…', 'caption' => 'Groupes : nom, confidentialité, membres, état et actions.', 'empty' => 'Aucun groupe'],
    'pages' => ['search_placeholder' => 'Nom, catégorie ou description…', 'caption' => 'Pages d’organisations : nom, catégorie, abonné·es, état et actions.', 'empty' => 'Aucune page'],
    'evenements' => ['search_placeholder' => 'Titre, lieu ou organisateur·rice…', 'caption' => 'Événements : titre, date, participant·es, état et actions.', 'empty' => 'Aucun événement'],
    'forums' => ['search_placeholder' => 'Titre du sujet…', 'caption' => 'Sujets de forum : titre, réponses, date, état et actions.', 'empty' => 'Aucun sujet'],
    'publications' => ['search_placeholder' => 'Texte ou étiquette…', 'caption' => 'Publications : extrait, date, signalements, état et actions.', 'empty' => 'Aucune publication'],

    // P18-10 : gestion des forums (onglet « Gestion des forums », permission `forums.manage`).
    'manage' => [
        'intro' => 'Créez, renommez et ordonnez les forums. Un forum archivé reste lisible mais n’accepte plus de nouveau sujet. L’ordre choisi ici est celui de la page Forums des membres et de l’application.',
        'caption' => 'Forums : ordre, nom, sujets, état et actions.',
        'create' => 'Créer un forum',
        'create_title' => 'Nouveau forum',
        'edit_title' => 'Modifier « :name »',
        'save' => 'Enregistrer',
        'empty' => 'Aucun forum',
        'list_label' => 'Ordre des forums',
        'position' => ':position sur :total',
        'columns' => ['order' => 'Ordre', 'forum' => 'Forum', 'topics' => 'Sujets', 'state' => 'État'],
        'fields' => ['name' => 'Nom', 'description' => 'Description', 'icon' => 'Icône'],
        'help' => [
            'name' => 'Entre :min et :max caractères, visible des membres.',
            'description' => 'Une phrase, :max caractères au plus.',
            'icon' => 'Pictogramme affiché sur la carte du forum.',
        ],
        'key' => 'Clé : :key (ne change jamais)',
        'icons' => [
            'chat-heart' => 'Bulle et cœur', 'chat-square-text' => 'Bulle de discussion', 'bank' => 'Bâtiment public', 'heart-pulse' => 'Cœur et pouls',
            'people' => 'Personnes', 'graph-up-arrow' => 'Courbe montante', 'shield-check' => 'Bouclier', 'book' => 'Livre',
            'mortarboard' => 'Toque d’étudiant·e', 'briefcase' => 'Mallette', 'house-heart' => 'Maison et cœur', 'flower1' => 'Fleur',
            'stars' => 'Étoiles', 'megaphone' => 'Mégaphone', 'globe2' => 'Globe', 'lightbulb' => 'Ampoule',
        ],
        'states' => ['open' => 'Ouvert', 'archived' => 'Archivé', 'closed' => 'Fermé'],
        'actions' => ['edit' => 'Modifier', 'up' => 'Monter', 'down' => 'Descendre', 'archive' => 'Archiver', 'restore' => 'Réactiver'],
        'confirm_archive' => 'Archiver « :name » ? Les sujets restent lisibles, mais personne ne pourra en ouvrir de nouveau.',
        'moved' => '« :name » est maintenant en position :position sur :total.',
        'toasts' => [
            'created' => 'Forum « :name » créé.',
            'updated' => 'Forum enregistré.',
            'archived' => 'Forum « :name » archivé.',
            'restored' => 'Forum « :name » réactivé.',
        ],
    ],
];
