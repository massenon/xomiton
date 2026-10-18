<?php

declare(strict_types=1);

/*
 * Ziggy (P6-02) : routes nommées Laravel lisibles par l'espace Vue (`route('fil')`, resources/js/app/routes.ts).
 * Seul le groupe « member » est écrit dans la page (`@routes('member')`, resources/views/app.blade.php) : jamais la
 * liste de l'administration, de l'API, des outils de débogage ni des liens signés (newsletter, médias privés).
 */
return [

    'groups' => [
        'member' => [
            // Espace membre et pair (routes/web.php, groupe auth + Inertia).
            'fil', 'fil.*', 'messages', 'groupes.*', 'pages.*', 'evenements.*', 'forums.*', 'qui-suivre', 'profil',
            'parametres', 'notifications', 'assistant', 'annuaire', 'medias', 'ccc', 'espace-pair', 'collecte',
            'deconnexion', 'design-system', 'design-system.*',
            // Parcours de crise et pages publiques atteintes depuis l'espace connecté.
            'home', 'sos', 'signaler', 'signaler.suivi', 'urgences', 'thematiques.*', 'ressources.index', 'ressources.show',
            'violences', 'lois', 'do-glegbe', 'aide.index', 'aide.show', 'actualites.*', 'confidentialite', 'connexion',
        ],
    ],

];
