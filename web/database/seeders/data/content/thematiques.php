<?php

declare(strict_types=1);

/*
 * Les 29 thématiques du prototype (prototype_social/inc/data.php : x_thematiques()).
 * Chaque ligne : [id prototype, slug de catégorie, titre, résumé]. L'image et la couverture sont
 * attribuées comme dans le prototype : image = images[i % 8], couverture = covers/th-<id>.jpg,
 * temps de lecture = 3 + (i % 6).
 */
return [

    'images' => ['hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg', 'hero-04.jpg', 'hero-03.jpg', 'hero-05.jpg', 'hero-07.jpg'],

    'rows' => [
        [1, 'vbg', "Qu'est-ce que la Violence Basée sur le Genre (VBG) ?", 'Définition, origines et formes de violences dirigées contre une personne en raison de son genre ou de son orientation.'],
        [2, 'vbg', 'Les différentes formes de VBG : reconnaître les signes', "Physique, psychologique, sexuelle, économique, numérique : apprenez à repérer les signaux d'alerte."],
        [3, 'droits', 'Le cadre légal au Bénin : ce que dit la loi', 'Loi n°2011-26 du 9 janvier 2012 portant prévention et répression des violences faites aux femmes.'],
        [4, 'vbg', 'Les violences correctives et le chantage : savoir dire stop', "Comprendre le chantage à l'outing, les « thérapies » imposées et les recours possibles."],
        [5, 'vbg', 'Comment aider une amie victime de violence ?', 'Écouter sans juger, ne pas minimiser, orienter vers les bonnes personnes.'],
        [6, 'droits', 'La loi au Bénin : quels sont vos droits en cas de violence ?', "Droit à la protection, à l'assistance médicale, juridique et à l'hébergement d'urgence."],
        [7, 'vbg', "Le cycle de la violence : comprendre pour mieux s'en sortir", 'Tension, agression, justification, lune de miel : un schéma qui se répète.'],
        [8, 'vbg', 'Reconnaître les 5 formes de VBG : au-delà des coups', 'Violences physiques, psychologiques, sexuelles, économiques et institutionnelles.'],
        [9, 'regles', 'Quelle protection hygiénique est faite pour moi ?', 'Serviettes, tampons, cups, culottes menstruelles : avantages, limites et budget.'],
        [10, 'regles', "SOS douleurs de règles : astuces naturelles et signaux d'alerte", 'Chaleur, mouvement, plantes… et quand consulter.'],
        [11, 'regles', 'Mythes et réalités sur les règles : brisons les tabous !', 'Non, les règles ne rendent pas « impure » : démêlons le vrai du faux.'],
        [12, 'regles', "Guide de l'hygiène menstruelle pour les hommes trans et personnes non-binaires", 'Gérer ses règles en préservant sa sécurité et son confort.'],
        [13, 'mental', 'Anxiété, stress, tristesse : comment reconnaître les signes et agir ?', "Repères pour identifier un mal-être et premiers gestes d'apaisement."],
        [14, 'mental', "5 techniques de respiration faciles pour calmer une crise d'angoisse", 'Cohérence cardiaque, respiration carrée, ancrage : exercices guidés.'],
        [15, 'mental', "L'impact de la discrimination sur la santé mentale des personnes LBTQ+", 'Stress minoritaire, isolement, et ressources pour se protéger.'],
        [16, 'mental', 'Le guide pour dire « non » sans culpabiliser et poser ses limites', "Phrases-types et mises en situation pour s'affirmer."],
        [17, 'vbg', "Le harcèlement à l'école ou au travail : comment le prouver et le signaler ?", 'Conserver les preuves, saisir la hiérarchie, les autorités compétentes.'],
        [18, 'droits', "Le mariage forcé n'est pas une fatalité : connaître ses droits et les aides", "Textes protecteurs, structures d'accueil et accompagnement."],
        [19, 'droits', 'Porter plainte : guide étape par étape pour les victimes de violence', "De la préparation du dossier à l'audition, avec un accompagnement."],
        [20, 'droits', 'Droits des personnes LBTQ+ au Bénin : ce que dit la loi', 'Cadre juridique, protections existantes et zones de vigilance.'],
        [21, 'esc', "Le consentement expliqué : si ce n'est pas un « oui » enthousiaste, c'est un « non »", 'Libre, éclairé, réversible, spécifique.'],
        [22, 'esc', 'Guide complet des méthodes de contraception disponibles au Bénin', 'Pilule, implant, DIU, préservatifs : choisir avec un·e professionnel·le.'],
        [23, 'esc', 'IST/VIH : prévention, dépistage (où le faire ?) et traitement', 'Se protéger, se tester gratuitement et accéder au traitement.'],
        [24, 'esc', 'Anatomie du plaisir féminin : à la découverte de son propre corps', 'Connaître son corps pour mieux le respecter.'],
        [25, 'esc', 'Sexualités lesbiennes, bi et queer : pratiques, plaisir et sécurité', 'Santé sexuelle sans stigmatisation.'],
        [26, 'corps', 'Mon corps, mes règles : le pouvoir de décider pour soi', 'Autonomie corporelle et choix éclairés.'],
        [27, 'corps', "L'indépendance financière : 5 étapes pour gérer son budget", 'Épargne, budget, activités génératrices de revenus.'],
        [28, 'corps', 'Comment construire sa confiance en soi : 3 exercices pratiques à faire chaque jour', 'Petites habitudes, grands effets.'],
        [29, 'corps', "La sororité comme arme : l'importance du réseau et de l'entraide", 'Pourquoi la solidarité entre pairs protège.'],
    ],

];
