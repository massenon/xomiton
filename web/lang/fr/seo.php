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
        'violences' => [
            'title' => 'Comprendre les violences : formes, signes et cycle – Xomiton',
            'description' => 'Violences physiques, psychologiques, sexuelles, économiques ou en ligne : repérer les signes, comprendre le cycle et faire le point en toute confidentialité.',
        ],
        'lois' => [
            'title' => 'Lois et codes du Bénin : connaître vos droits – Xomiton',
            'description' => 'Loi sur les violences faites aux femmes, Code pénal, Code de la famille, santé, VIH : résumés clairs et version simple pour connaître et défendre vos droits.',
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
        // Aucun numéro en dur (P4-06, Q62) : `:number` = premier numéro administré (`emergency_numbers`) ; variantes
        // `*_plain` quand aucun numéro n'est renseigné.
        'urgences' => [
            'title' => 'Contacts d’urgence au Bénin : :number, écoute, juriste – Xomiton',
            'title_plain' => 'Contacts d’urgence au Bénin : écoute, juriste – Xomiton',
            'description' => 'Numéro d’urgence :number, ligne d’écoute, psychologues et juristes joignables, lieux d’accueil et plan de sécurité à imprimer : agir vite, en sécurité.',
            'description_plain' => 'Numéros d’urgence, ligne d’écoute, psychologues et juristes joignables, lieux d’accueil et plan de sécurité à imprimer : agir vite, en sécurité.',
        ],
        'connexion' => [
            'title' => 'Se connecter : retrouver mon fil, mes messages – Xomiton',
            'description' => 'Connecte-toi à ton espace Xomiton avec ton e-mail ou ton pseudonyme et ton mot de passe : retrouve ton fil, tes messages, tes groupes et tes ressources.',
        ],
        'inscription' => [
            'title' => 'Créer un compte : rejoindre la communauté – Xomiton',
            'description' => 'Crée ton compte en une étape, avec un pseudonyme et un mot de passe : ton vrai nom n’est jamais demandé. Communauté bienveillante et soutien confidentiel.',
        ],
        'actualites' => [
            'title' => 'Actualités de la communauté et des partenaires – Xomiton',
            'description' => 'Articles et nouvelles de l’équipe Xomiton au Bénin : droits, santé, sécurité et vie de la communauté, expliqués simplement pour agir en confiance.',
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
        // Lot auth-ecrans (P4-09) : tunnel d'accès hors connexion et inscription (tu, VOICE_AND_TONE §3).
        'forgot_password' => 'Mot de passe oublié – Xomiton',
        'reset_password' => 'Nouveau mot de passe – Xomiton',
        'register_closed' => 'Inscriptions momentanément fermées – Xomiton',
        'register_step' => 'Créer mon compte, étape :current sur :total – Xomiton',
        'search' => 'Résultats de recherche – Xomiton',
        'thanks' => 'Merci – Xomiton',
        'member' => 'Mon espace – Xomiton',
        'admin' => 'Administration – Xomiton',
        'neutral' => 'Notes',
    ],
];
