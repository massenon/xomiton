<?php

declare(strict_types=1);

/*
 * Charte, confidentialité et conditions `/confidentialite` (P4-08 ; prototype_social/privacy-and-terms.php). Registre :
 * vous (pages légales, VOICE_AND_TONE.md §3) ; la charte reste à la première personne, comme un engagement (« je
 * m’engage »). Textes du prototype harmonisés (allié·es ; signalement sans compte ni IP avec recontact facultatif, lié
 * au compte et au pseudonyme hors anonymat, AlertRecorder::author, P5-04 ; IP et navigateur conservés par `consents`,
 * `otp_codes`, `audit_logs` et `sessions`) : à faire valider par le client et son conseil juridique (plan §7). Changer
 * un texte = changer `xomiton.content.legal_updated_at`.
 */
return [

    'title' => 'Charte, confidentialité et conditions',
    'updated' => 'Dernière mise à jour : :date · version :version',
    'nav_label' => 'Sections de la page',
    'full_privacy' => 'Lire la politique de confidentialité complète',
    'full_terms' => 'Lire les conditions d’utilisation complètes',
    'delete_account' => 'Supprimer mon compte et mes données',

    'sections' => [
        'charte' => 'Charte de bienveillance',
        'confidentialite' => 'Confidentialité et données',
        'conditions' => 'Conditions d’utilisation',
        'signalement' => 'Signalement et modération',
    ],

    'charte' => [
        'intro' => 'Xomiton est un espace sûr. En l’utilisant, je m’engage à :',
        'items' => [
            'respecter chaque personne, quelle que soit son orientation sexuelle, son identité de genre, son âge, son origine ou sa situation ;',
            'ne tenir aucun propos homophobe, transphobe, sexiste, violent ou discriminatoire ;',
            'ne jamais révéler l’identité, l’orientation ou les informations privées d’une autre personne (pas d’« outing ») ;',
            'écouter sans juger et garder confidentiel ce qui est partagé dans les groupes ;',
            'ne pas utiliser la plateforme pour du harcèlement, du chantage, de la publicité ou des contenus illégaux.',
        ],
        'notice' => 'Tout manquement peut entraîner un avertissement, une suspension ou une exclusion décidés par l’équipe de modération.',
    ],

    'privacy' => [
        ['Données collectées', 'Pseudonyme, adresse e-mail, zone géographique et, si vous le choisissez, des informations de profil. Aucun nom réel n’est exigé. Pour la sécurité des comptes, l’adresse IP et le navigateur utilisé sont aussi conservés avec vos consentements, les codes de vérification, le journal d’audit et la liste de vos appareils connectés.'],
        ['Signaler', 'Sans compte, ni identifiant ni adresse IP : seuls le contenu de votre signalement, son code de suivi et, si vous le choisissez, un moyen de vous recontacter sont enregistrés. Connecté·e, vous pouvez rester anonyme ; sinon, le signalement est lié à votre compte et à votre pseudonyme.'],
        ['Localisation GPS', 'Partagée uniquement quand vous déclenchez un SOS ou l’autorisez explicitement ; jamais en arrière-plan.'],
        ['Hors connexion', 'Les saisies en attente sont stockées sur votre appareil, puis transmises de façon sécurisée à la synchronisation.'],
        ['Vos droits', 'Accès, rectification, export et suppression de vos données depuis Paramètres, ou sur demande à l’équipe.'],
        ['Partage', 'Vos informations ne sont jamais vendues. Elles ne sont transmises qu’aux personnes de l’annuaire que vous ciblez.'],
    ],

    'conditions' => [
        ['Accès.', 'Xomiton s’adresse aux filles et femmes, aux personnes LBTQ+ et à leurs allié·es respectueux·ses. Les inscriptions peuvent être ouvertes ou suspendues par l’administration.'],
        ['Compte.', 'Vous êtes responsable de la confidentialité de votre mot de passe. Utilisez un mot de passe fort.'],
        ['Contenus.', 'Vous restez propriétaire de ce que vous publiez et nous autorisez à l’afficher dans la plateforme selon vos réglages de visibilité.'],
        ['Information, pas avis médical ou juridique.', 'Les ressources et l’assistant informent mais ne remplacent pas un·e professionnel·le. En cas de danger, utilisez le bouton SOS ou les contacts d’urgence.'],
        ['Disponibilité.', 'La plateforme est mise à jour périodiquement ; des interruptions brèves peuvent survenir.'],
    ],

    'signalement' => [
        'text' => 'Vous pouvez signaler un contenu ou une personne à tout moment. L’équipe examine chaque signalement dans la confidentialité et peut vous orienter vers un·e professionnel·le de l’annuaire.',
        'report' => 'Signaler',
        'help' => 'Centre d’aide',
        'home' => 'Retour à l’accueil',
    ],

];
