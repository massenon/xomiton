<?php

declare(strict_types=1);

/*
 * Page d'accueil `/` (P4-01 ; prototype_social/index.php, docs/HOME_PAGE_COPY.md, design-system/xomiton/pages/accueil.md).
 * Registre : vous, sauf `questions` (tu : exception « Je me pose des questions », VOICE_AND_TONE.md §3).
 * Promesses requalifiées (Q36) : jamais « 24 h/24 » sans « sous 24 h », jamais « dénonciation » (Q29). L'assistant public
 * n'enregistre rien et ne mène à aucun humain : la « réponse humaine sous 24 h » est rattachée au parcours « Exprimer un
 * besoin » de /signaler (?tab=besoin, prototype signalement.php l.11 ; VOICE_AND_TONE.md §3 règle 6).
 */
return [

    'hero' => [
        'kicker' => 'Espace sûr · confidentiel · bienveillant',
        'title' => 'Xomiton : votre espace safe d’apprentissage et d’appui holistique',
        'lead' => 'Filles et femmes LBTQ+ ? Rejoignez notre communauté bienveillante et accédez à des ressources, trouvez du soutien et faites entendre votre voix en toute sécurité.',
        // Q16 : libellé à trancher par le client (« Rejoignez-nous » ou « Créer un compte ») ; une seule clé à changer.
        'cta_primary' => 'Rejoignez-nous',
        'quote' => '« Enfin un espace où je me sens en sécurité et comprise. »',
        'photo_1_alt' => 'Deux femmes qui se soutiennent',
        'photo_2_alt' => 'Communauté Xomiton',
        'proof_human' => 'Lu sous 24 h par une personne formée',
        'proof_report' => 'Votre signalement a été reçu',
    ],

    'valeurs' => [
        'title' => 'Un soutien à portée de main',
        'intro_1' => 'Un espace d’échange, de soutien et d’actions pour votre bien-être.',
        'intro_2' => 'Nous répondons à toutes vos questions, gratuitement et en toute confidentialité.',
        'items' => [
            ['icon' => '02.svg', 'alt' => 'Icône de ressources', 'title' => 'Thématiques & ressources', 'text' => 'Explorez nos fiches d’informations complètes sur les droits humains, la lutte contre les VBG, la santé sexuelle et reproductive et bien d’autres sujets essentiels.'],
            ['icon' => '07.svg', 'alt' => 'Icône de communauté', 'title' => 'Réseau et communautés', 'text' => 'Connectez-vous avec d’autres membres, partagez vos expériences et bâtissez des liens solides au sein de nos groupes de discussion thématiques et sécurisés.'],
            ['icon' => '01.svg', 'alt' => 'Icône de sécurité', 'title' => 'Confidentialité et sécurité', 'text' => 'Vos échanges sont protégés. Signalez les abus en toute confiance, votre anonymat est garanti et nos conseiller·ères sont disponibles pour vous écouter et vous assister.'],
        ],
    ],

    // Registre tu pour tout le bloc, cartes services comprises (exception conservée du prototype, VOICE_AND_TONE.md §3
    // règles 1 et 7 : un seul registre par écran ; le prototype vouvoyait les trois cartes, écart consigné en Q53).
    'questions' => [
        'title' => 'Je me pose des questions',
        'text' => 'Tu as une question sur l’orientation sexuelle et l’identité de genre, les VBG, la SSR ou les droits humains en général ? Tu es lesbienne, bi, trans, queer, intersexe ou en questionnement ?',
        'report_title' => 'Je veux témoigner ou signaler un abus',
        'report_text' => 'Tu as vécu ou été témoin de violences ou de discrimination ? Signale-nous ton cas pour que nous puissions t’assister et t’accompagner.',
        'report_img_alt' => 'Signaler un abus',
        'services' => [
            ['icon' => '03.svg', 'title' => 'Chat d’écoute et d’assistance', 'text' => 'Pose tes questions à l’assistant, en ligne 24 h/24 ; pour une réponse humaine sous 24 h, exprime un besoin : un conseil, une orientation ou simplement une oreille attentive. Ton bien-être est notre priorité.', 'cta' => 'Exprimer un besoin'],
            ['icon' => '09.svg', 'title' => 'Actualités et événements', 'text' => 'Reste informé·e des dernières nouvelles, des campagnes de sensibilisation, des ateliers et des événements organisés par et pour la communauté.'],
            // Texte du prototype conservé (il décrit la FAQ) ; correction proposée au plan §7, à valider par le client.
            ['icon' => '06.svg', 'title' => 'Forums de discussion et groupes de soutien', 'text' => 'Trouve rapidement des réponses aux questions fréquentes sur tes droits, les démarches à suivre et les ressources disponibles sur Xomiton.'],
        ],
    ],

    'reseau' => [
        'eyebrow' => 'Rejoignez-nous maintenant',
        'title' => 'Échangez, partagez, grandissez ensemble',
        'text' => 'Partagez vos expériences, participez à des discussions enrichissantes, et contribuez à faire de Xomiton un espace d’accompagnement puissant. Chaque voix compte pour bâtir un avenir plus juste et inclusif.',
        'features' => [
            ['icon' => 'newspaper', 'title' => 'Fil d’actualité', 'text' => 'Publiez, commentez, partagez sondages, témoignages et ressources.'],
            ['icon' => 'chat-dots', 'title' => 'Chat Messenger', 'text' => 'Messages privés et conversations de groupe en temps réel.'],
            ['icon' => 'people', 'title' => 'Groupes & pages', 'text' => 'Cercles thématiques, pages d’organisations partenaires.'],
            ['icon' => 'calendar-event', 'title' => 'Événements', 'text' => 'Ateliers, webinaires, dépistages, self-défense.'],
            ['icon' => 'chat-square-text', 'title' => 'Forums', 'text' => 'Parler librement, droits, santé, sororité.'],
            ['icon' => 'person-plus', 'title' => 'Qui suivre', 'text' => 'Suggestions bienveillantes et invitations.'],
        ],
        'mock_b_alt' => 'Aperçu animé : fil d’actualité, fiche thématique et parcours',
        'mock_c_alt' => 'Aperçu animé : discussion dans le cercle sororité',
    ],

    'ressources' => [
        'eyebrow' => 'Ressources & thématiques',
        'title' => 'Apprendre pour mieux se protéger et décider',
        'text' => 'Fiches claires et fiables sur les violences, la santé, le droit, la sexualité et l’autonomie, rédigées pour vous.',
        'cover_alt' => 'Couverture de la fiche : :title',
        'default_category' => 'Thème',
        'empty' => 'Les fiches thématiques arrivent bientôt.',
    ],

    'alerte' => [
        'kicker' => 'Alerte & signalement',
        'title_1' => 'En danger ? Besoin d’aide ?',
        'title_2' => 'Alertez en quelques secondes.',
        'text' => 'Signalement, expression d’un besoin ou SOS avec votre position GPS : votre message est routé automatiquement vers les bonnes personnes de l’annuaire (point focal, juriste, psychologue…).',
        'proof_anonymous' => 'Signalement 100 % anonyme possible',
        'proof_location' => 'Localisation partagée uniquement avec votre accord',
        // Maquette décorative (aria-hidden) : données fictives.
        'mock_title' => 'Alerte SOS',
        'mock_sos' => 'SOS',
        'mock_position' => 'Position : Cotonou, Littoral',
        'mock_routing' => 'Envoi vers : point focal · psychologue · juriste',
    ],

    'experts' => [
        'title_1' => 'Nos expert·es sont',
        'title_2' => 'prêt·es à vous aider',
        'text' => 'Nous disposons d’expert·es juridiques, psychologiques et gynécologiques, avec des approches thérapeutiques adaptées à vos besoins.',
        'arg_1_title' => 'Besoin de parler à quelqu’un ?',
        'arg_1_text' => 'Assistant en ligne 24 h/24, réponse humaine sous 24 h quand vous exprimez un besoin.',
        'arg_1_cta' => 'Exprimer un besoin',
        'arg_2_title' => 'Conseils personnalisés',
        'arg_2_text' => 'C’est anonyme et entièrement gratuit. Si vous avez besoin de parler à quelqu’un, nous sommes là pour vous écouter et vous aider à vous sentir mieux.',
        'mock_alt' => 'Aperçu animé de l’assistant Xomiton : question, réponse, mise en relation avec un·e conseiller·ère',
    ],

    'do_glegbe' => [
        'logo_alt' => 'Logo Do Glegbe : goutte de sang avec symbole de genre et lune',
        'logo_title' => 'Do Glegbe – Gestion de l’hygiène menstruelle',
        'badge' => 'Droit fondamental',
        'eyebrow' => 'Do Glegbe · La gestion de l’hygiène menstruelle',
        'title' => 'Avoir ses règles est un simple fait biologique qui constitue trop souvent un obstacle à la santé, à la dignité et au respect des droits des filles et des femmes…',
        'text' => 'Une bonne gestion de l’hygiène menstruelle est un droit humain fondamental, indispensable à la santé, au bien-être et à l’égalité des genres. Elle permet de :',
        'benefits' => [
            ['icon' => 'shield-plus', 'text' => 'Prévenir les infections'],
            ['icon' => 'people', 'text' => 'Assurer la participation sociale'],
            ['icon' => 'emoji-smile', 'text' => 'Renforcer la confiance en soi'],
            ['icon' => 'chat-heart', 'text' => 'Lutter contre la stigmatisation'],
        ],
        'cta_rules' => 'Fiches sur les règles',
    ],

    'faq' => [
        'eyebrow' => 'Questions fréquentes',
        'title' => 'Vous vous demandez…',
    ],

    'cta' => [
        'title' => 'Prête à nous rejoindre ?',
        'text' => 'Créez votre compte Xomiton et accédez instantanément à un réseau d’entraide et d’information conçu pour vous.',
        // Variante membre connecté·e (P4-01 « CTA selon connexion » ; le prototype n'en a pas) : à valider par le client.
        'title_member' => 'Votre espace vous attend',
        'text_member' => 'Retrouvez votre fil, vos groupes, vos messages et vos ressources.',
        'free' => 'Gratuit',
        'secure' => '100 % sécurisé',
    ],

];
