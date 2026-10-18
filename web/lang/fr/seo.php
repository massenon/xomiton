<?php

declare(strict_types=1);

// Métadonnées par défaut (SEO_CONTENT_GUIDE_INSTRUCTION.md, HOME_PAGE_COPY.md §Méta).
return [
    'site_name' => 'Xomiton',
    'default_title' => 'Espace safe d’apprentissage et d’appui pour les filles et femmes LBTQ+ au Bénin',
    'default_description' => 'Xomiton : votre espace safe d’apprentissage et d’appui holistique pour les filles et femmes LBTQ+ au Bénin. Ressources, communauté, signalement anonyme.',
    'default_image_alt' => 'Xomiton – espace safe d’apprentissage et d’appui holistique',
    'home' => 'Accueil',

    // --- Compléments P3-05 (P4-11 : title 50–60 car. « {Sujet} – Xomiton », description 140–160 car., vous) --------
    'title_suffix' => ' – Xomiton',
    'title_pattern' => ':title – Xomiton',
    'locale' => 'fr_FR',
    'in_language' => 'fr-BJ',
    'organization_description' => 'Espace safe d’apprentissage et d’appui holistique pour les filles et femmes LBTQ+ au Bénin.',
    'search_action' => 'Rechercher une ressource',
    'breadcrumb_home' => 'Accueil',

    // Pages publiques indexées. L'accueil est la seule exception « marque en tête » (Q36).
    'pages' => [
        'home' => [
            'title' => 'Xomiton – Espace safe LBTQ+, soutien et ressources au Bénin',
            'description' => 'Xomiton : votre espace safe d’apprentissage et d’appui pour les filles et femmes LBTQ+ au Bénin. Ressources fiables, communauté, signalement anonyme.',
        ],
        'thematiques' => [
            'title' => 'Thématiques : santé, droits, sécurité et bien-être – Xomiton',
            'description' => 'Fiches claires sur la santé sexuelle, les droits, la sécurité numérique et le bien-être des filles et femmes LBTQ+ au Bénin. Lecture possible hors connexion.',
        ],
        'ressources' => [
            'title' => 'Ressources, guides et actualités de la communauté – Xomiton',
            'description' => 'Guides pratiques, vidéos, fiches et actualités pour s’informer, se protéger et trouver de l’aide. Contenus vérifiés par des expert·es, mis à jour régulièrement.',
        ],
        'do_glegbe' => [
            'title' => 'Do Glegbe : règles, hygiène menstruelle et santé – Xomiton',
            'description' => 'Comprendre ses règles, gérer son hygiène menstruelle et savoir quand consulter : Do Glegbe donne des réponses simples et fiables aux filles et femmes du Bénin.',
        ],
        'aide' => [
            'title' => 'Centre d’aide : questions fréquentes et contacts – Xomiton',
            'description' => 'Réponses aux questions fréquentes sur l’inscription, l’anonymat, le signalement, le SOS et vos données. Une équipe formée vous répond sous 24 h.',
        ],
        'signaler' => [
            'title' => 'Signaler une violence, sans compte et anonymement – Xomiton',
            'description' => 'Signale une violence ou exprime un besoin d’aide, sans compte ni adresse IP enregistrée. Une personne formée lira ton message sous 24 h et t’orientera.',
        ],
        'sos' => [
            'title' => 'SOS : envoyer une alerte avec sa position GPS – Xomiton',
            'description' => 'En danger ? Envoyez une alerte SOS avec votre position à la personne de l’annuaire la plus proche : point focal, juriste ou psychologue. Même hors connexion.',
        ],
        'urgences' => [
            'title' => 'Contacts d’urgence au Bénin : 117, écoute, juriste – Xomiton',
            'description' => 'Numéros d’urgence (117, 118), ligne d’écoute, psychologues et juristes joignables, lieux d’accueil et plan de sécurité à imprimer : agir vite, en sécurité.',
        ],
        'connexion' => [
            'title' => 'Se connecter : retrouver mon fil, mes messages – Xomiton',
            'description' => 'Connectez-vous à votre espace Xomiton pour retrouver votre fil, vos messages, vos groupes et vos ressources. Accès sécurisé par e-mail ou pseudonyme et code.',
        ],
        'inscription' => [
            'title' => 'Créer un compte : rejoindre la communauté – Xomiton',
            'description' => 'Créez votre compte en une étape avec un pseudonyme : votre vrai nom n’est jamais demandé. Communauté bienveillante, ressources fiables et soutien confidentiel.',
        ],
        'actualites' => [
            'title' => 'Actualités de la communauté et des partenaires – Xomiton',
            'description' => 'Événements, ateliers, campagnes et nouvelles des organisations partenaires de Xomiton au Bénin. Suivez la vie de la communauté et participez près de chez vous.',
        ],
        'confidentialite' => [
            'title' => 'Confidentialité, charte et conditions d’usage – Xomiton',
            'description' => 'Comment Xomiton protège vos données : anonymat, chiffrement, droit à l’effacement, charte de la communauté et conditions d’utilisation, en langage clair.',
        ],
        'newsletter' => [
            'title' => 'Newsletter : actualités et ressources par e-mail – Xomiton',
            'description' => 'Recevez une à deux fois par mois les actualités, ressources et événements de la communauté Xomiton. Inscription confirmée par e-mail, désinscription en un clic.',
        ],
        'evenements' => [
            'title' => 'Événements : ateliers, causeries et dépistages – Xomiton',
            'description' => 'Ateliers, causeries, dépistages et formations ouverts à la communauté, au Bénin ou en ligne. Dates, lieux et inscriptions pour participer en toute sécurité.',
        ],
    ],

    // Pages non indexées (robots noindex, nofollow) : titre seul.
    'noindex' => [
        'otp' => 'Code de vérification – Xomiton',
        'search' => 'Résultats de recherche – Xomiton',
        'thanks' => 'Merci – Xomiton',
        'member' => 'Mon espace – Xomiton',
        'admin' => 'Administration – Xomiton',
        'neutral' => 'Notes',
    ],
];
