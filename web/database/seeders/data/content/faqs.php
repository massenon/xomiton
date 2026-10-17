<?php

declare(strict_types=1);

/*
 * FAQ (prototype_social/data/faqs.json, source unique retenue par le dictionnaire) : [question, réponse, publiée].
 * Les catégories du centre d'aide viennent de x_help_categories() (prototype_social/inc/x_public.php) :
 * slug => [libellé, icône, couleur] ; l'affectation par index reproduit x_faq_cat().
 */
return [

    'categories' => [
        'compte' => ['Compte & inscription', 'bi-person-circle', 'primary'],
        'signal' => ['Signalement & urgences', 'bi-megaphone', 'danger'],
        'soutien' => ['Soutien & consultations', 'bi-heart-pulse', 'success'],
        'communaute' => ['Communauté & événements', 'bi-people', 'info'],
    ],

    'category_by_index' => ['compte', 'compte', 'signal', 'signal', 'signal', 'soutien', 'soutien', 'communaute', 'communaute', 'compte'],

    'rows' => [
        ["Qu'est-ce que Xomiton ?", "Xomiton est une plateforme numérique sécurisée destinée aux femmes, aux filles et aux communautés LBTQ+ au Bénin : un espace d'apprentissage, de soutien et de communauté bienveillante.", true],
        ["Qui peut s'inscrire sur Xomiton ?", "Toute personne s'identifiant comme femme, fille ou faisant partie de la communauté LBTQ+. Les alliés peuvent rejoindre l'espace sous condition de respect de la charte.", true],
        ["Quel type d'abus puis-je signaler ?", 'Toute forme de VBG : violence physique, psychologique, sexuelle, économique, harcèlement, chantage, violences en ligne, discrimination.', true],
        ['Que se passe-t-il après mon signalement ?', "Il est examiné en toute confidentialité par une équipe formée, puis orienté vers le bon interlocuteur de l'annuaire (psychologue, juriste, point focal).", true],
        ['Dois-je porter plainte à la police ?', 'Non. Signaler sur Xomiton est une démarche indépendante : nous vous écoutons et vous orientons, vous décidez de la suite.', true],
        ["Comment fonctionne le chat d'assistance ?", "L'assistant répond 24h/24 aux questions sur les VBG, la sexualité et les droits. En cas de danger, il vous oriente vers un humain et le bouton SOS.", true],
        ['Comment accéder aux consultations spécialisées ?', "Depuis l'annuaire : psychologues, juristes et gynécologues partenaires, avec leurs disponibilités.", true],
        ['Comment participer à un événement ou un atelier ?', 'Rendez-vous dans « Événements », choisissez une activité et confirmez votre participation.', true],
        ['Xomiton est-il un espace sûr pour les personnes LBTQ+ ?', "Oui. Tolérance zéro contre l'homophobie, la transphobie et le harcèlement ; modération active et signalement anonyme.", true],
        ['Mes données sont-elles protégées ?', 'Chiffrement, pseudonymes, signalement anonyme possible et consentement explicite pour tout partage de localisation.', true],
    ],

];
