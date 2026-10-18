<?php

declare(strict_types=1);

/*
 * Centre d'aide `/aide`, `/aide/{slug}` (P4-05 ; prototype_social/help.php, help-details.php). Registre : vous (site
 * public institutionnel, VOICE_AND_TONE.md §3). Les questions et réponses viennent de la table `faqs` (catégories
 * `faq`) ; « À savoir » (`$more` du prototype) par slug de catégorie, harmonisé : « signalement » au lieu de
 * « dénonciation » (Q29), assistant requalifié (Q36), règle réelle du mot de passe (:min caractères, PasswordStrength).
 */
return [

    'title' => 'Centre d’aide',
    'og_alt' => 'Centre d’aide Xomiton : questions fréquentes sur le compte, le signalement, le soutien et la communauté',
    'meta_description_filler' => 'Toutes les réponses du centre d’aide Xomiton, gratuites et confidentielles.',

    // Liste (help.php)
    'hero_title' => 'Comment pouvons-nous vous aider ?',
    'search_label' => 'Rechercher dans l’aide',
    'search_placeholder' => 'Rechercher : signalement, données, SOS…',
    'search_submit' => 'Chercher',
    'categories_title' => 'Catégories de l’aide',
    'category_count' => '{1} :count article|[2,*] :count articles',
    'list_title' => 'Questions fréquentes',
    'results_count' => '{0} Aucune question trouvée.|{1} 1 question trouvée.|[2,*] :count questions trouvées.',
    'clear_filters' => 'Effacer les filtres',
    'read_more' => 'Lire la suite',
    'read_more_context' => ' : « :question »',
    'empty_title' => 'Aucun résultat pour « :query ».',
    'empty_text' => 'Essayez un autre mot ou contactez l’équipe.',
    'empty_contact' => 'Exprimer un besoin',
    'empty_none' => 'Les questions fréquentes arrivent bientôt.',
    // CTA secondaire de l'Aide (CONTENT_STRATEGY.md §1 : « Créer un compte »), discret, pour les visiteur·ses seulement.
    'account_text' => 'Pas encore de compte ? Rejoignez la communauté pour échanger dans les groupes et participer aux événements.',

    // Bloc « en danger » (help.php l.71-74) et colonne de l'article (help-details.php l.52-55)
    'danger_title' => 'Vous êtes en danger ?',
    'danger_text' => 'Ne cherchez pas dans l’aide : agissez maintenant.',
    'danger_sos' => 'SOS',
    'danger_contacts' => 'Contacts d’urgence',
    'danger_report' => 'Signaler',
    'now_title' => 'Besoin d’aide maintenant ?',
    'now_report' => 'Signalement',

    // Article (help-details.php)
    'know_title' => 'À savoir',
    'related_title' => 'Autres questions',
    'feedback' => [
        'question' => 'Cet article vous a-t-il aidé·e ?',
        'yes' => 'Oui',
        'no' => 'Non',
        'thanks_yes' => 'Merci pour votre retour !',
        'thanks_no' => 'Merci, nous allons améliorer cet article.',
        'failed' => 'Votre avis n’a pas pu être envoyé. Réessayez dans un instant.',
        'invalid' => 'Choisissez « Oui » ou « Non ».',
    ],

    'know' => [
        'compte' => [
            'Créez votre compte avec un pseudonyme : aucun nom réel n’est exigé.',
            'Protégez-le avec un mot de passe fort : :min caractères au moins, avec des majuscules, des minuscules et des chiffres.',
            'Vous pouvez à tout moment exporter ou supprimer vos données depuis Paramètres.',
        ],
        'signal' => [
            'Choisissez le type : signaler une violence, exprimer un besoin ou SOS en cas de danger immédiat.',
            'Le signalement peut être totalement anonyme ; vous recevez un code de suivi.',
            'Il est transmis aux personnes de l’annuaire concernées par votre besoin et votre zone.',
            'Hors connexion, il est conservé sur votre appareil puis envoyé automatiquement.',
        ],
        'soutien' => [
            'Consultez l’annuaire pour trouver psychologues, juristes et points focaux.',
            'L’assistant automatique répond 24 h/24 et vous oriente vers le bouton SOS en cas de danger ; pour une réponse humaine sous 24 h, exprimez un besoin depuis la page Signaler.',
            'Les pair·es éducateur·rices peuvent vous accompagner dans vos démarches.',
        ],
        'communaute' => [
            'Rejoignez des groupes, pages et forums selon vos centres d’intérêt.',
            'Confirmez votre participation aux événements depuis leur fiche.',
            'Signalez tout comportement contraire à la charte de bienveillance.',
        ],
    ],

];
