<?php

declare(strict_types=1);

/*
 * FAQ (prototype_social/data/faqs.json, source unique retenue par le dictionnaire) : [question, réponse, publiée].
 * Les catégories du centre d'aide viennent de x_help_categories() (prototype_social/inc/x_public.php) :
 * slug => [libellé, icône, couleur] ; l'affectation par index reproduit x_faq_cat().
 * Typographie harmonisée (P4-01, VOICE_AND_TONE.md §5) : apostrophes ’, espaces insécables, « allié·es » ; la réponse sur
 * le chat requalifie la promesse « 24 h/24 » (Q36 : réponse humaine sous 24 h). Les six premières questions sont celles
 * de la page d'accueil (Q16 : 5–6 questions, réponses ≤ 60 mots) ; ContentSeeder retrouve aussi l'ancienne graphie.
 */
return [

    'categories' => [
        'compte' => ['Compte & inscription', 'bi-person-circle', 'primary'],
        'signal' => ['Signalement & urgences', 'bi-megaphone', 'danger'],
        'soutien' => ['Soutien & consultations', 'bi-heart-pulse', 'success'],
        'communaute' => ['Communauté & événements', 'bi-people', 'info'],
    ],

    'category_by_index' => ['compte', 'compte', 'signal', 'signal', 'signal', 'soutien', 'soutien', 'communaute', 'communaute', 'compte'],

    'slug_by_index' => [
        'qu-est-ce-que-xomiton', 'qui-peut-s-inscrire', 'abus-a-signaler', 'apres-mon-signalement', 'porter-plainte-police',
        'chat-d-assistance', 'consultations-specialisees', 'participer-evenement-atelier', 'espace-sur-lbtq', 'protection-des-donnees',
    ],

    'rows' => [
        ['Qu’est-ce que Xomiton ?', 'Xomiton est une plateforme numérique sécurisée destinée aux femmes, aux filles et aux communautés LBTQ+ au Bénin : un espace d’apprentissage, de soutien et de communauté bienveillante.', true],
        ['Qui peut s’inscrire sur Xomiton ?', 'Toute personne s’identifiant comme femme, fille ou faisant partie de la communauté LBTQ+. Les allié·es peuvent rejoindre l’espace sous condition de respect de la charte.', true],
        ['Quel type d’abus puis-je signaler ?', 'Toute forme de VBG : violence physique, psychologique, sexuelle, économique, harcèlement, chantage, violences en ligne, discrimination.', true],
        ['Que se passe-t-il après mon signalement ?', 'Il est examiné en toute confidentialité par une équipe formée, puis orienté vers le bon interlocuteur de l’annuaire (psychologue, juriste, point focal).', true],
        ['Dois-je porter plainte à la police ?', 'Non. Signaler sur Xomiton est une démarche indépendante : nous vous écoutons et vous orientons, vous décidez de la suite.', true],
        ['Comment fonctionne le chat d’assistance ?', 'L’assistant automatique répond 24 h/24 aux questions sur les VBG, la sexualité et les droits ; pour une réponse humaine sous 24 h, exprimez un besoin depuis la page Signaler. En cas de danger, il vous oriente vers le bouton SOS.', true],
        ['Comment accéder aux consultations spécialisées ?', 'Depuis l’annuaire : psychologues, juristes et gynécologues partenaires, avec leurs disponibilités.', true],
        ['Comment participer à un événement ou un atelier ?', 'Rendez-vous dans « Événements », choisissez une activité et confirmez votre participation.', true],
        ['Xomiton est-il un espace sûr pour les personnes LBTQ+ ?', 'Oui. Tolérance zéro contre l’homophobie, la transphobie et le harcèlement ; modération active et signalement anonyme.', true],
        ['Mes données sont-elles protégées ?', 'Chiffrement, pseudonymes, signalement anonyme possible et consentement explicite pour tout partage de localisation.', true],
    ],

];
