<?php
/** Données de référence Xomiton (contenu éditorial du prototype). Source : base actuelle (thématiques, FAQ, contacts). */

function x_avatar($i = 0) {
    $a = ['avatar1.png', 'avatar2.png', 'avatar3.png', 'avatar4.png', 'personnage-1.png', 'personnage-2.png'];
    return img($a[$i % count($a)]);
}

function x_thematique_cats() {
    return [
        'vbg'    => ['Violences basées sur le genre', 'bi-shield-exclamation', 'danger'],
        'regles' => ['Hygiène menstruelle', 'bi-droplet-half', 'danger'],
        'mental' => ['Santé mentale', 'bi-heart-pulse', 'primary'],
        'droits' => ['Droits & lois', 'bi-bank', 'info'],
        'esc'    => ['Éducation complète à la sexualité', 'bi-book', 'success'],
        'corps'  => ['Autonomie & autonomisation', 'bi-person-check', 'warning'],
    ];
}

function x_thematiques() {
    $img = ['hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg', 'hero-04.jpg', 'hero-03.jpg', 'hero-05.jpg', 'hero-07.jpg'];
    $rows = [
        [1, 'vbg', "Qu'est-ce que la Violence Basée sur le Genre (VBG) ?", "Définition, origines et formes de violences dirigées contre une personne en raison de son genre ou de son orientation."],
        [2, 'vbg', "Les différentes formes de VBG : reconnaître les signes", "Physique, psychologique, sexuelle, économique, numérique : apprenez à repérer les signaux d'alerte."],
        [3, 'droits', "Le cadre légal au Bénin : ce que dit la loi", "Loi n°2011-26 du 9 janvier 2012 portant prévention et répression des violences faites aux femmes."],
        [4, 'vbg', "Les violences correctives et le chantage : savoir dire stop", "Comprendre le chantage à l'outing, les « thérapies » imposées et les recours possibles."],
        [5, 'vbg', "Comment aider une amie victime de violence ?", "Écouter sans juger, ne pas minimiser, orienter vers les bonnes personnes."],
        [6, 'droits', "La loi au Bénin : quels sont vos droits en cas de violence ?", "Droit à la protection, à l'assistance médicale, juridique et à l'hébergement d'urgence."],
        [7, 'vbg', "Le cycle de la violence : comprendre pour mieux s'en sortir", "Tension, agression, justification, lune de miel : un schéma qui se répète."],
        [8, 'vbg', "Reconnaître les 5 formes de VBG : au-delà des coups", "Violences physiques, psychologiques, sexuelles, économiques et institutionnelles."],
        [9, 'regles', "Quelle protection hygiénique est faite pour moi ?", "Serviettes, tampons, cups, culottes menstruelles : avantages, limites et budget."],
        [10, 'regles', "SOS douleurs de règles : astuces naturelles et signaux d'alerte", "Chaleur, mouvement, plantes… et quand consulter."],
        [11, 'regles', "Mythes et réalités sur les règles : brisons les tabous !", "Non, les règles ne rendent pas « impure » : démêlons le vrai du faux."],
        [12, 'regles', "Guide de l'hygiène menstruelle pour les hommes trans et personnes non-binaires", "Gérer ses règles en préservant sa sécurité et son confort."],
        [13, 'mental', "Anxiété, stress, tristesse : comment reconnaître les signes et agir ?", "Repères pour identifier un mal-être et premiers gestes d'apaisement."],
        [14, 'mental', "5 techniques de respiration faciles pour calmer une crise d'angoisse", "Cohérence cardiaque, respiration carrée, ancrage : exercices guidés."],
        [15, 'mental', "L'impact de la discrimination sur la santé mentale des personnes LBTQ+", "Stress minoritaire, isolement, et ressources pour se protéger."],
        [16, 'mental', "Le guide pour dire « non » sans culpabiliser et poser ses limites", "Phrases-types et mises en situation pour s'affirmer."],
        [17, 'vbg', "Le harcèlement à l'école ou au travail : comment le prouver et le signaler ?", "Conserver les preuves, saisir la hiérarchie, les autorités compétentes."],
        [18, 'droits', "Le mariage forcé n'est pas une fatalité : connaître ses droits et les aides", "Textes protecteurs, structures d'accueil et accompagnement."],
        [19, 'droits', "Porter plainte : guide étape par étape pour les victimes de violence", "De la préparation du dossier à l'audition, avec un accompagnement."],
        [20, 'droits', "Droits des personnes LBTQ+ au Bénin : ce que dit la loi", "Cadre juridique, protections existantes et zones de vigilance."],
        [21, 'esc', "Le consentement expliqué : si ce n'est pas un « oui » enthousiaste, c'est un « non »", "Libre, éclairé, réversible, spécifique."],
        [22, 'esc', "Guide complet des méthodes de contraception disponibles au Bénin", "Pilule, implant, DIU, préservatifs : choisir avec un·e professionnel·le."],
        [23, 'esc', "IST/VIH : prévention, dépistage (où le faire ?) et traitement", "Se protéger, se tester gratuitement et accéder au traitement."],
        [24, 'esc', "Anatomie du plaisir féminin : à la découverte de son propre corps", "Connaître son corps pour mieux le respecter."],
        [25, 'esc', "Sexualités lesbiennes, bi et queer : pratiques, plaisir et sécurité", "Santé sexuelle sans stigmatisation."],
        [26, 'corps', "Mon corps, mes règles : le pouvoir de décider pour soi", "Autonomie corporelle et choix éclairés."],
        [27, 'corps', "L'indépendance financière : 5 étapes pour gérer son budget", "Épargne, budget, activités génératrices de revenus."],
        [28, 'corps', "Comment construire sa confiance en soi : 3 exercices pratiques à faire chaque jour", "Petites habitudes, grands effets."],
        [29, 'corps', "La sororité comme arme : l'importance du réseau et de l'entraide", "Pourquoi la solidarité entre pairs protège."],
    ];
    $out = [];
    foreach ($rows as $i => $r) {
        $out[] = ['id' => $r[0], 'cat' => $r[1], 'title' => $r[2], 'summary' => $r[3], 'image' => $img[$i % count($img)], 'cover' => 'covers/th-' . $r[0] . '.jpg', 'minutes' => 3 + ($i % 6)];
    }
    return $out;
}
function x_thematique($id) { foreach (x_thematiques() as $t) if ($t['id'] == $id) return $t; return null; }

function x_faq() {
    return [
        ["Qu'est-ce que Xomiton ?", "Xomiton est une plateforme numérique sécurisée destinée aux femmes, aux filles et aux communautés LBTQ+ au Bénin : un espace d'apprentissage, de soutien et de communauté bienveillante."],
        ["Qui peut s'inscrire sur Xomiton ?", "Toute personne s'identifiant comme femme, fille ou faisant partie de la communauté LBTQ+. Les alliés peuvent rejoindre l'espace sous condition de respect de la charte."],
        ["Quel type d'abus puis-je signaler ?", "Toute forme de VBG : violence physique, psychologique, sexuelle, économique, harcèlement, chantage, violences en ligne, discrimination."],
        ["Que se passe-t-il après mon signalement ?", "Il est examiné en toute confidentialité par une équipe formée, puis orienté vers le bon interlocuteur de l'annuaire (psychologue, juriste, point focal)."],
        ["Dois-je porter plainte à la police ?", "Non. Signaler sur Xomiton est une démarche indépendante : nous vous écoutons et vous orientons, vous décidez de la suite."],
        ["Comment fonctionne le chat d'assistance ?", "L'assistant répond 24h/24 aux questions sur les VBG, la sexualité et les droits. En cas de danger, il vous oriente vers un humain et le bouton SOS."],
        ["Comment accéder aux consultations spécialisées ?", "Depuis l'annuaire : psychologues, juristes et gynécologues partenaires, avec leurs disponibilités."],
        ["Comment participer à un événement ou un atelier ?", "Rendez-vous dans « Événements », choisissez une activité et confirmez votre participation."],
        ["Xomiton est-il un espace sûr pour les personnes LBTQ+ ?", "Oui. Tolérance zéro contre l'homophobie, la transphobie et le harcèlement ; modération active et signalement anonyme."],
        ["Mes données sont-elles protégées ?", "Chiffrement, pseudonymes, signalement anonyme possible et consentement explicite pour tout partage de localisation."],
    ];
}

function x_violence_categories() {
    return [
        ['physique', 'Violence physique', 'bi-hand-index-thumb', 'Coups, brûlures, séquestration, agressions.'],
        ['psychologique', 'Violence psychologique', 'bi-chat-square-heart', "Insultes, humiliations, menaces, chantage à l'outing."],
        ['sexuelle', 'Violence sexuelle', 'bi-exclamation-octagon', 'Viol, attouchements, « viols correctifs », exploitation.'],
        ['economique', 'Violence économique', 'bi-cash-coin', 'Privation de ressources, contrôle financier, exclusion.'],
        ['numerique', 'Violence en ligne', 'bi-phone', "Cyberharcèlement, diffusion d'images, doxxing, outing."],
    ];
}

function x_needs() {
    return ['soutien_psychologique' => 'Soutien psychologique', 'soutien_juridique' => 'Soutien juridique', 'soutien_medical' => 'Prise en charge médicale',
        'hebergement' => "Hébergement d'urgence", 'accompagnement' => 'Accompagnement (police, tribunal)', 'securite' => 'Mise en sécurité', 'autre' => 'Autre besoin'];
}

function x_zones() {
    return ['Alibori' => ['Kandi', 'Malanville', 'Banikoara'], 'Atacora' => ['Natitingou', 'Tanguiéta'], 'Atlantique' => ['Abomey-Calavi', 'Ouidah', 'Allada'],
        'Borgou' => ['Parakou', 'Nikki', 'Tchaourou'], 'Collines' => ['Dassa-Zoumè', 'Savalou', 'Savè'], 'Couffo' => ['Aplahoué', 'Dogbo'],
        'Donga' => ['Djougou', 'Bassila'], 'Littoral' => ['Cotonou'], 'Mono' => ['Lokossa', 'Grand-Popo'], 'Ouémé' => ['Porto-Novo', 'Sèmè-Podji', 'Adjarra'],
        'Plateau' => ['Pobè', 'Kétou'], 'Zou' => ['Abomey', 'Bohicon', 'Covè']];
}
function x_populations() { return ['Femmes cis', 'Femmes lesbiennes', 'Femmes bisexuelles', 'Personnes trans / non-binaires', 'Travailleuses du sexe', 'Femmes vivant avec le VIH', 'Jeunes filles (15-24)']; }
function x_age_ranges() { return ['10-14', '15-17', '18-24', '25-34', '35-49', '50+']; }
function x_sexes() { return ['Femme', 'Homme', 'Autre / non-binaire']; }
function x_roles_annuaire() { return ['Point focal', 'Juriste', 'Psychologue', 'Gynécologue', 'Responsable de réseau', 'Pair animateur·rice', 'Travailleur·se social·e']; }

function x_social_gateways() {
    return [['Facebook', 'bi-facebook', '#1877F2'], ['TikTok', 'bi-tiktok', '#000000'], ['Roméo', 'bi-heart-fill', '#E4007C'],
            ['GanXo', 'bi-chat-heart-fill', '#7A2BBE'], ['Telegram', 'bi-telegram', '#229ED9'], ['Tinder', 'bi-fire', '#FF5864']];
}

function x_lois() {
    return [
        ['loi-2011-26', 'Loi n°2011-26 du 9 janvier 2012', 'Prévention et répression des violences faites aux femmes', "Pénalise le harcèlement sexuel, les violences conjugales, le viol, le mariage forcé et les mutilations génitales féminines.", 'Violences'],
        ['code-personnes', 'Code des personnes et de la famille (2004)', 'Droits des personnes et de la famille', "Égalité des époux, consentement au mariage, protection de l'enfant, droit successoral égalitaire.", 'Famille'],
        ['code-penal', 'Code pénal béninois (2018)', 'Infractions et sanctions', "Qualification pénale des violences, de l'agression sexuelle, du viol et du harcèlement ; circonstances aggravantes.", 'Pénal'],
        ['loi-sante-repro', 'Loi n°2003-04 sur la santé sexuelle et de la reproduction', 'Santé sexuelle et reproductive', "Droit à l'information, à la contraception, aux soins et à la confidentialité médicale.", 'Santé'],
        ['loi-vih', 'Loi n°2005-31 sur la prévention du VIH', 'Prise en charge et non-discrimination', "Interdit la discrimination des personnes vivant avec le VIH ; garantit confidentialité et accès aux soins.", 'Santé'],
        ['charte-africaine', "Charte africaine des droits de l'homme et des peuples", 'Droits fondamentaux', "Dignité, égalité devant la loi, liberté, intégrité physique ; protocole de Maputo.", 'International'],
        ['constitution', 'Constitution du 11 décembre 1990', 'Droits fondamentaux', "Art. 26 : égalité de tous devant la loi, sans distinction de sexe ; l'État protège la famille et la femme.", 'Constitution'],
    ];
}

/* Kit CCC (Communication pour le Changement de Comportement) numérisé */
function x_ccc_manuel() {
    return [
        ['m1', 'Module 1 – Être pair animateur·rice', ['Rôle, posture et limites', 'Écoute active et non-jugement', 'Confidentialité et consentement', 'Prendre soin de soi'], 45],
        ['m2', 'Module 2 – Comprendre les VBG / VDH', ['Définitions et formes de violences', 'Cycle de la violence', 'Facteurs de risque dans les populations clés', 'Signes à repérer'], 60],
        ['m3', 'Module 3 – Santé sexuelle & reproductive', ['Corps, plaisir et consentement', 'Contraception et dépistage IST/VIH', 'Hygiène menstruelle', 'Réduction des risques'], 60],
        ['m4', 'Module 4 – Orienter et référer', ["Utiliser l'annuaire dynamique", 'Remplir une fiche de référence', 'Alerte, dénonciation, SOS', 'Suivi des survivant·es'], 50],
        ['m5', 'Module 5 – Animer une séance', ['Boîtes à images : conduire une discussion', 'Bandes dessinées SSR : jeux de rôle', 'Gérer un groupe difficile', "Évaluer l'activité"], 60],
    ];
}
function x_ccc_boites() {
    return [
        ['Le consentement', 'hero-09.jpg', "Montrez l'image : « Que se passe-t-il ? Que ressent chaque personne ? Comment dire non ? »"],
        ['Reconnaître le harcèlement', 'hero-10.jpg', 'Laissez le groupe nommer les signaux, puis classez-les du plus subtil au plus grave.'],
        ['Les réseaux de soutien', 'hero-12.jpg', "Qui puis-je appeler ? Associez chaque personne de l'image à un service de l'annuaire."],
        ['Violence numérique', '6033855.jpg', 'Que faire quand une photo est diffusée sans accord ? Mettez en scène les bons réflexes.'],
        ['Prendre la parole', 'hero-04.jpg', "Chacun·e complète la phrase « Dans mon espace sûr, j'ai besoin de… »."],
        ["Stop à l'homophobie", '3995814.jpg', "Analysez l'affiche : message, émotions, alternatives pour répondre à un propos haineux."],
    ];
}
function x_ccc_bd() {
    return [
        ['bd1', 'Léa et le « non »', 'hero-09.jpg', ["Léa est invitée chez un ami… Elle se sent mal à l'aise.", "Elle dit « non » clairement ; l'ami insiste.", "Elle quitte la pièce et appelle une pair animatrice.", "Elle découvre qu'elle peut parler sans être jugée."]],
        ['bd2', 'Message reçu, que faire ?', 'hero-10.jpg', ["Amina reçoit une photo d'elle partagée sans accord.", "Elle ne répond pas, fait des captures.", "Elle utilise « Signaler » sur Xomiton.", "Un juriste de l'annuaire la rappelle dans la journée."]],
        ['bd3', 'Protège-toi, protège-nous', 'hero-12.jpg', ["Au centre de dépistage, Fatou hésite.", "Une pair animatrice lui explique le test, gratuit et confidentiel.", "Fatou obtient son résultat et un suivi adapté.", "Elle invite à son tour une amie."]],
    ];
}

function x_forums() {
    return [
        ['f1', 'Parler librement', "Espace d'échange général, sans jugement.", 'bi-chat-heart', 128, 962],
        ['f2', 'Droits & démarches', 'Questions juridiques, plaintes, recours.', 'bi-bank', 54, 311],
        ['f3', 'Santé & bien-être', 'Santé mentale, santé sexuelle, hygiène menstruelle.', 'bi-heart-pulse', 97, 640],
        ['f4', 'Sororité & entraide', 'Cercles de solidarité, témoignages positifs.', 'bi-people', 76, 480],
        ['f5', 'Autonomisation', 'Emploi, finance, formations.', 'bi-graph-up-arrow', 41, 205],
    ];
}

/** Couverture de marque d'un groupe (gr-1…gr-6 ; gr-default pour les groupes créés par les membres). */
function x_group_cover($g) {
    $id = (int)($g['id'] ?? 0);
    return 'covers/' . (($id >= 1 && $id <= 6) ? 'gr-' . $id : 'gr-default') . '.jpg';
}
