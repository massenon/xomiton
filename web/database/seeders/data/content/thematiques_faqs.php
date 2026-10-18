<?php

declare(strict_types=1);

/*
 * FAQ des fiches thématiques (P4-02) : trois questions par catégorie de thématique, rattachées par `faqs.category_id`
 * (décision par défaut, plan §7 : une FAQ appartient aux fiches de sa catégorie ; 3 à 5 questions par fiche, sinon ni
 * section ni FAQPage). Textes dérivés de prototype_social/inc/x_thematiques_content.php, registre vous, réponses ≤ 60 mots,
 * typographie française (VOICE_AND_TONE.md §6) et formulations épicènes (§4). À valider par l'équipe éditoriale avant la mise
 * en production (plan §7) : ThematiqueFaqSeeder ne les publie que hors production.
 * Format : slug de catégorie => [[question, réponse, anciennes formulations de la question (facultatif)], …].
 */
return [

    'vbg' => [
        ['Qu’est-ce qu’une violence basée sur le genre ?', 'C’est tout acte de violence exercé contre une personne en raison de son sexe, de son identité de genre ou de son orientation sexuelle. Elle peut être physique, psychologique, sexuelle, économique ou numérique, à la maison, à l’école, au travail ou en ligne.'],
        ['Que faire si je suis en danger immédiat ?', 'Éloignez-vous si vous le pouvez, puis utilisez le bouton SOS ou appelez un contact d’urgence (117, 118). Ensuite, parlez à une personne de confiance ou à un point focal de l’annuaire.'],
        ['Dois-je porter plainte pour recevoir de l’aide ?', 'Non. Porter plainte n’est pas obligatoire pour recevoir écoute et accompagnement. Vous décidez du rythme et de la suite à donner ; un·e juriste ou un·e psychologue de l’annuaire peut faire le point avec vous.', ['Dois-je porter plainte pour être aidée ?']],
    ],

    'regles' => [
        ['Comment choisir ma protection hygiénique ?', 'Serviettes jetables ou lavables, tampons, cup, culotte menstruelle : choisissez selon votre confort, votre budget et votre accès à l’eau. Changez de protection toutes les 4 à 6 heures et lavez-vous les mains avant et après.'],
        ['Quand consulter pour mes règles ?', 'Consultez en cas de douleurs invalidantes, de saignements très abondants ou prolongés, d’absence prolongée de règles, de fièvre ou de mauvaise odeur inhabituelle avec une protection interne. Un·e professionnel·le de santé vous écoutera sans jugement.'],
        ['Les règles sont-elles une « impureté » ?', 'Non. Les règles sont un processus physiologique normal. En parler réduit la honte et les risques sanitaires ; disposer d’informations fiables et de protections accessibles est un droit.', ['Les règles rendent-elles « impure » ?']],
    ],

    'mental' => [
        ['Comment reconnaître un mal-être qui mérite de l’aide ?', 'Stress, tristesse, anxiété ou fatigue émotionnelle sont des réactions humaines. Quand elles durent, s’intensifient ou vous empêchent d’accomplir vos activités, parlez-en à une personne de confiance ou à un·e psychologue de l’annuaire.'],
        ['Que faire pendant une crise d’angoisse ?', 'Pratiquez une respiration lente : inspirez 4 secondes, expirez 6 secondes pendant 5 minutes. L’ancrage dans le présent, un mouvement doux et un lieu calme aident aussi. Ces gestes n’empêchent pas de demander un accompagnement.'],
        ['J’ai des idées noires : qui contacter ?', 'Parlez-en sans attendre à une personne de confiance ou à un·e psychologue de l’annuaire. En cas de danger immédiat pour vous-même, appelez les urgences ou déclenchez une alerte SOS. Vous méritez une écoute sans jugement.'],
    ],

    'droits' => [
        ['Quels textes protègent contre les violences au Bénin ?', 'La Constitution du 11 décembre 1990, le Code pénal, le Code des personnes et de la famille et la loi n° 2011-26 du 9 janvier 2012 portant prévention et répression des violences faites aux femmes garantissent protection, intégrité, santé et accès à la justice.'],
        ['Comment préparer une plainte ?', 'Notez les faits (dates, lieux, témoins), conservez les preuves sans les modifier, obtenez un certificat médical si nécessaire et prenez conseil auprès d’un·e juriste de l’annuaire avant de déposer plainte. Faites-vous accompagner lors des démarches.'],
        ['Les personnes LBTQ+ sont-elles protégées par la loi ?', 'Les protections contre les violences, le chantage, le harcèlement et les atteintes à la vie privée s’appliquent à toutes et tous. Un·e juriste de l’annuaire peut évaluer votre situation en toute confidentialité avant toute démarche.'],
    ],

    'esc' => [
        ['Qu’est-ce qu’un consentement valable ?', 'Un consentement est libre, éclairé, spécifique et réversible : il peut être retiré à tout moment. Le silence, la peur, l’ivresse ou une relation passée ne valent pas un « oui ».'],
        ['Où faire un dépistage VIH/IST ?', 'Dans les centres de santé partenaires, de façon confidentielle et souvent gratuite. Un dépistage régulier est un geste de soin ; l’équipe Xomiton peut vous orienter vers un·e professionnel·le sans stigmatisation.'],
        ['Que faire après un rapport non protégé ?', 'Consultez rapidement un centre de santé : traitement d’urgence, contraception d’urgence et dépistage sont possibles. Les services de santé doivent respecter votre confidentialité.'],
    ],

    'corps' => [
        ['Comment commencer à gérer mon budget ?', 'Tenez un petit budget (entrées, dépenses, épargne), mettez de côté une somme même modeste chaque semaine et fixez-vous un objectif simple pour les trois prochains mois. L’indépendance financière protège de la dépendance.'],
        ['Comment renforcer ma confiance en moi ?', 'Par de petites habitudes : un objectif réaliste, célébrer ses réussites, apprendre à dire non et s’entourer de personnes bienveillantes. La confiance se construit par la pratique.'],
        ['Pourquoi rejoindre un groupe ou un réseau ?', 'La solidarité entre femmes et personnes LBTQ+ démultiplie les efforts : cercles d’épargne, entraide, mentorat, informations fiables. Les groupes et événements Xomiton permettent de ne jamais avancer sans soutien.'],
    ],

];
