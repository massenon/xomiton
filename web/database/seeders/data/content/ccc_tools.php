<?php

declare(strict_types=1);

/*
 * Kit CCC numérisé (prototype_social/inc/data.php : x_ccc_manuel(), x_ccc_boites(), x_ccc_bd()).
 * - manuel : [clé, titre, chapitres, durée en minutes] ;
 * - boites : [titre, image, consigne d'animation] (clé générée bi1…bi6) ;
 * - bd : [clé, titre, image, légendes des planches].
 */
return [

    'manuel' => [
        ['m1', 'Module 1 – Être pair animateur·rice', ['Rôle, posture et limites', 'Écoute active et non-jugement', 'Confidentialité et consentement', 'Prendre soin de soi'], 45],
        ['m2', 'Module 2 – Comprendre les VBG / VDH', ['Définitions et formes de violences', 'Cycle de la violence', 'Facteurs de risque dans les populations clés', 'Signes à repérer'], 60],
        ['m3', 'Module 3 – Santé sexuelle & reproductive', ['Corps, plaisir et consentement', 'Contraception et dépistage IST/VIH', 'Hygiène menstruelle', 'Réduction des risques'], 60],
        ['m4', 'Module 4 – Orienter et référer', ["Utiliser l'annuaire dynamique", 'Remplir une fiche de référence', 'Alerte, dénonciation, SOS', 'Suivi des survivant·es'], 50],
        ['m5', 'Module 5 – Animer une séance', ['Boîtes à images : conduire une discussion', 'Bandes dessinées SSR : jeux de rôle', 'Gérer un groupe difficile', "Évaluer l'activité"], 60],
    ],

    'boites' => [
        ['Le consentement', 'hero-09.jpg', "Montrez l'image : « Que se passe-t-il ? Que ressent chaque personne ? Comment dire non ? »"],
        ['Reconnaître le harcèlement', 'hero-10.jpg', 'Laissez le groupe nommer les signaux, puis classez-les du plus subtil au plus grave.'],
        ['Les réseaux de soutien', 'hero-12.jpg', "Qui puis-je appeler ? Associez chaque personne de l'image à un service de l'annuaire."],
        ['Violence numérique', '6033855.jpg', 'Que faire quand une photo est diffusée sans accord ? Mettez en scène les bons réflexes.'],
        ['Prendre la parole', 'hero-04.jpg', "Chacun·e complète la phrase « Dans mon espace sûr, j'ai besoin de… »."],
        ["Stop à l'homophobie", '3995814.jpg', "Analysez l'affiche : message, émotions, alternatives pour répondre à un propos haineux."],
    ],

    'bd' => [
        ['bd1', 'Léa et le « non »', 'hero-09.jpg', ["Léa est invitée chez un ami… Elle se sent mal à l'aise.", "Elle dit « non » clairement ; l'ami insiste.", 'Elle quitte la pièce et appelle une pair animatrice.', "Elle découvre qu'elle peut parler sans être jugée."]],
        ['bd2', 'Message reçu, que faire ?', 'hero-10.jpg', ["Amina reçoit une photo d'elle partagée sans accord.", 'Elle ne répond pas, fait des captures.', 'Elle utilise « Signaler » sur Xomiton.', "Un juriste de l'annuaire la rappelle dans la journée."]],
        ['bd3', 'Protège-toi, protège-nous', 'hero-12.jpg', ['Au centre de dépistage, Fatou hésite.', 'Une pair animatrice lui explique le test, gratuit et confidentiel.', 'Fatou obtient son résultat et un suivi adapté.', 'Elle invite à son tour une amie.']],
    ],

];
