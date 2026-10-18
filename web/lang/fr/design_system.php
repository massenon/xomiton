<?php

declare(strict_types=1);

// Page catalogue /design-system (local et recette) : titres de sections et libellés d'états.
return [
    'title' => 'Design system',
    'heading' => 'Catalogue des composants Xomiton',
    'intro' => 'Chaque composant x-* dans chacun de ses états. Le HTML est celui du prototype prototype_social (thème Social + classes xm-*) ; les couleurs viennent des jetons --xm-* uniquement.',
    'theme' => 'Thème',
    'theme_light' => 'Clair',
    'theme_dark' => 'Sombre',
    'toc' => 'Sommaire',
    'states' => [
        'default' => 'Défaut',
        'hover' => 'Survol',
        'focus' => 'Focus',
        'active' => 'Actif',
        'disabled' => 'Désactivé',
        'loading' => 'Chargement',
        'error' => 'Erreur',
        'empty' => 'Vide',
    ],
    'hover_note' => 'Survol : :text (décrit, non simulable en capture).',
    'focus_note' => 'Focus : anneau 3 px --xm-focus-ring décalé de 2 px (simulé ici par la classe xm-demo-focus).',
    'sections' => [
        'tokens' => 'Jetons : couleurs et typographie',
        'buttons' => 'Boutons <x-button>',
        'badges' => 'Badges, statuts et urgences',
        'fields' => 'Champs <x-field> et résumé d’erreurs',
        'cards' => 'Cartes <x-card>',
        'stats' => 'Indicateurs <x-stat>',
        'feedback' => 'Alertes, toasts et états',
        'modals' => 'Modales <x-modal>',
        'navigation' => 'Navigation : fil d’Ariane, onglets, accordéon, étapes',
        'data' => 'Données : tableau, chronologie, squelettes',
        'bell' => 'Cloche <x-bell>',
        'media' => 'Avatars, images et logo',
        'sos' => 'Bouton SOS <x-sos-button>',
        'newsletter' => 'Newsletter <x-newsletter-form>',
    ],
];
