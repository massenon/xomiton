<?php

declare(strict_types=1);

/*
 * Do Glegbe `/do-glegbe` (P4-04 ; prototype_social/do-glegbe.php). Registre : vous (site public institutionnel,
 * VOICE_AND_TONE.md §3). « touxtes » du prototype remplacé par une formulation épicène (VOICE §4). Fiches : thématiques
 * publiées de la catégorie Do Glegbe (`xomiton.content.do_glegbe_category`) ; quiz vrai / faux 100 % dans le navigateur,
 * rien n'est enregistré (plan §7).
 */
return [

    'title' => 'Do Glegbe',
    'headline' => 'Do Glegbe – Gestion de l’hygiène menstruelle',

    // Bannière (prototype l.22-36)
    'hero' => [
        'badge' => 'Gestion de l’hygiène menstruelle',
        'lead' => 'Votre guide complet pour une gestion saine et digne de l’hygiène menstruelle.',
        'sheets' => 'Explorer les fiches',
        'workshops' => 'Voir les ateliers',
        'logo_alt' => 'Logo Do Glegbe : goutte de sang avec symbole de genre et lune',
        'logo_title' => 'Do Glegbe',
    ],

    // Qu'est-ce que (prototype l.38-44)
    'mission' => [
        'eyebrow' => 'Notre mission',
        'title' => 'Qu’est-ce que « Do Glegbe » ?',
        'lead' => 'Avoir ses règles est un simple fait biologique qui constitue trop souvent un obstacle à la santé, à la dignité et au respect des droits des filles et des femmes…',
        'text' => '« Do Glegbe » est une initiative dédiée à l’autonomisation des personnes menstruées. Notre mission est de briser les tabous et de fournir les informations, les compétences, les ressources, le soutien social et le matériel nécessaires pour gérer sa santé menstruelle en toute confiance et dignité.',
    ],

    // Pourquoi (prototype l.46-61)
    'why' => [
        'eyebrow' => 'Un droit humain fondamental',
        'title' => 'Pourquoi la gestion de l’hygiène menstruelle est-elle essentielle ?',
        'intro' => 'Une bonne gestion de l’hygiène menstruelle est indispensable à la santé, au bien-être et à l’égalité des genres. Elle permet de :',
        'benefits' => [
            ['icon' => 'shield-plus', 'title' => 'Prévenir les infections', 'text' => 'Utiliser des protections propres et les changer régulièrement est crucial pour éviter les infections de l’appareil reproducteur.'],
            ['icon' => 'people', 'title' => 'Assurer la participation sociale', 'text' => 'Avoir accès à des protections et à des installations sanitaires adéquates permet de poursuivre ses études, son travail et ses activités sociales sans interruption.'],
            ['icon' => 'emoji-smile', 'title' => 'Renforcer la confiance en soi', 'text' => 'Comprendre son corps et savoir gérer ses menstruations contribue à une meilleure estime de soi et à un plus grand bien-être psychologique.'],
            ['icon' => 'chat-heart', 'title' => 'Lutter contre la stigmatisation', 'text' => 'Parler ouvertement des menstruations aide à déconstruire les mythes et les tabous qui entraînent discrimination et exclusion.'],
        ],
    ],

    // Services (prototype l.63-80)
    'services' => [
        'eyebrow' => 'Nos services et ressources',
        'title' => 'À travers Do Glegbe, Xomiton s’engage à vous fournir',
        'items' => [
            'sheets' => ['title' => 'Informations fiables', 'text' => 'Des articles et guides sur le cycle menstruel, les différents types de protections (serviettes, tampons, coupes, culottes menstruelles) et la gestion de la douleur.', 'cta' => 'Lire les fiches'],
            'workshops' => ['title' => 'Compétences pratiques', 'text' => 'Des ateliers (en ligne et en présentiel) pour apprendre à utiliser et entretenir les protections réutilisables, et pour mieux comprendre son corps.', 'cta' => 'Voir les ateliers'],
            'groups' => ['title' => 'Soutien social', 'text' => 'Des groupes de discussion sécurisés pour partager ses expériences, poser des questions sans jugement et briser le sentiment d’isolement.', 'cta' => 'Rejoindre un groupe'],
        ],
        'members_only' => 'réservé aux membres',
    ],

    // Fiches (MODULE_SPECIFICATIONS.md §A : « fiches, quiz »)
    'sheets' => [
        'title' => 'Fiches sur les règles',
        'intro' => 'Des réponses simples et vérifiées, à lire à votre rythme.',
        'all' => 'Toutes les fiches sur les règles',
    ],

    // Quiz vrai / faux (MODULE_SPECIFICATIONS.md §A), corrigé visible sans JavaScript (details)
    'quiz' => [
        'eyebrow' => 'Quiz',
        'title' => 'Vrai ou faux ? Testez vos connaissances',
        'intro' => ':count affirmations sur les règles. Vos réponses restent sur cet écran : rien n’est enregistré.',
        'statement_label' => 'Affirmation :number sur :total',
        'true' => 'Vrai',
        'false' => 'Faux',
        'show_answer' => 'Voir la réponse',
        'correct' => 'Bonne réponse.',
        'wrong' => 'Pas tout à fait.',
        'score' => 'Votre score : :score sur :total.',
        'restart' => 'Recommencer le quiz',
        'items' => [
            ['statement' => 'Les règles sont une impureté.', 'answer' => false, 'explanation' => 'Faux. Les règles sont un phénomène biologique naturel : elles ne salissent pas, ne rendent personne impur·e et n’empêchent ni d’aller à l’école, ni de cuisiner, ni de voir ses proches.'],
            ['statement' => 'On peut se laver normalement pendant ses règles.', 'answer' => true, 'explanation' => 'Vrai. Une toilette quotidienne à l’eau propre, avec un savon doux pour l’extérieur, est recommandée. Évitez les douches vaginales (lavage à l’intérieur du vagin), qui déséquilibrent la flore.'],
            ['statement' => 'Une serviette ou un tampon peut se garder toute la journée.', 'answer' => false, 'explanation' => 'Faux. Changez de serviette toutes les 4 à 6 heures environ, et de tampon au plus tard au bout de 8 heures, même si le flux est faible : cela prévient les infections et le syndrome du choc toxique.'],
            ['statement' => 'Des règles si douloureuses qu’elles empêchent d’aller en cours ou au travail, c’est normal : il faut les supporter.', 'answer' => false, 'explanation' => 'Faux. Une douleur qui vous empêche de vivre normalement n’est pas une fatalité. Parlez-en à un·e professionnel·le de santé : elle peut être soulagée et révèle parfois une maladie comme l’endométriose.'],
            ['statement' => 'Les protections réutilisables (coupe menstruelle, serviettes lavables) sont sûres si elles sont bien entretenues.', 'answer' => true, 'explanation' => 'Vrai. Lavées à l’eau propre et au savon, rincées puis séchées au soleil, elles sont sûres et économiques ; la coupe se stérilise à l’eau bouillante entre deux cycles.'],
        ],
    ],

    // Engagez-vous (prototype l.82-93)
    'engage' => [
        'title' => 'Engagez-vous avec nous',
        'text' => 'La santé menstruelle est l’affaire de tout le monde. Explorez nos ressources, participez à nos événements et rejoignez la conversation.',
        'quote' => 'Ensemble, créons un monde où les menstruations ne sont plus un obstacle, mais une partie naturelle et saine de la vie.',
        'thematiques' => 'Explorer les thématiques liées',
        'question' => 'Poser une question',
        'help' => 'Besoin d’aide ?',
    ],

    'schema_section' => 'Hygiène menstruelle',
    'schema_description' => 'Une bonne gestion de l’hygiène menstruelle est un droit humain fondamental, indispensable à la santé, au bien-être et à l’égalité des genres.',
];
