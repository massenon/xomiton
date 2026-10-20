<?php

declare(strict_types=1);

/*
 * Les 8 modèles d'e-mails du prototype (prototype_social/data/email_templates.json), dans l'ordre du fichier, plus les
 * templates système `mot-de-passe-modifie` (P1-09, P2-05), `compte-supprime` et `export-donnees` (P6-06). Le template de réinitialisation applique Q8 c) : code OTP
 * `reset` et lien magique dans le même e-mail, sans durée fixe « 60 minutes » (elle suit `xomiton.otp.ttl_minutes`).
 * `category` est le libellé du prototype (converti par TemplateCategory::fromPrototype) ; `system` = template
 * système au slug immuable (RG-10). Q36 : les e-mails adressés aux membres (compte, OTP, bienvenue, validation,
 * rappel) tutoient ; `alerte-routee` (intervenant·es) et `newsletter-confirmation` (public) vouvoient. Les variables {{...}} sont détectées par EmailTemplate::extractVariables().
 */
return [
    [
        // Q8 c) : un seul e-mail portant le code OTP `reset` et un lien magique signé, valables le temps du code (P2-05).
        'name' => 'Réinitialisation du mot de passe',
        'slug' => 'reinitialisation-mot-de-passe',
        'category' => 'Authentification',
        'system' => true,
        'subject' => '{{site_name}} : ton code pour choisir un nouveau mot de passe',
        'preheader' => 'Code {{otp_code}}, valable {{otp_expiry}}.',
        'body' => '<h2>Bonjour {{user_first_name}},</h2><p>Tu as demandé à réinitialiser ton mot de passe {{site_name}}. Voici ton code :</p><h1>{{otp_code}}</h1><p>Tu peux aussi cliquer sur ce lien, qui vaut le code :</p><p><a href="{{reset_link}}" rel="noopener noreferrer" target="_blank"><strong>Choisir un nouveau mot de passe</strong></a></p><p>Code et lien sont valables {{otp_expiry}}. Si tu n’es pas à l’origine de cette demande, ignore ce message : ton mot de passe reste inchangé.</p>',
        'active' => true,
    ],
    [
        'name' => 'Activation du compte',
        'slug' => 'activation-compte',
        'category' => 'Authentification',
        'system' => true,
        'subject' => 'Active ton compte {{site_name}}',
        'preheader' => 'Une dernière étape avant de rejoindre la communauté.',
        'body' => '<h2>Bienvenue {{user_first_name}} 💜</h2><p>Merci de rejoindre {{site_name}}. Pour activer ton compte, confirme ton adresse e-mail :</p><p><a href="{{activation_link}}" rel="noopener noreferrer" target="_blank"><strong>Activer mon compte</strong></a></p><p>Ton pseudonyme : <strong>{{user_pseudo}}</strong>. Tu pourras compléter ton profil plus tard, à ton rythme.</p>',
        'active' => true,
    ],
    [
        'name' => 'Code de vérification (OTP)',
        'slug' => 'code-verification',
        'category' => 'Authentification',
        'system' => true,
        'subject' => 'Ton code {{site_name}} : {{otp_code}}',
        'preheader' => 'Code valable {{otp_expiry}}.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Voici ton code de vérification :</p><h1>{{otp_code}}</h1><p>Il est valable {{otp_expiry}}. Ne le communique à personne, même à une personne qui dit faire partie de l’équipe {{site_name}}.</p>',
        'active' => true,
    ],
    [
        'name' => 'Bienvenue',
        'slug' => 'bienvenue',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Bienvenue dans ton espace safe, {{user_first_name}}',
        'preheader' => 'Tes premiers pas sur Xomiton.',
        'body' => '<h2>Bienvenue {{user_first_name}} !</h2><p>Ton compte est actif. Voici trois idées pour commencer :</p><ul><li>Explore les thématiques et les ressources.</li><li>Présente-toi dans le fil, sous pseudonyme si tu le souhaites.</li><li>Enregistre les contacts d’urgence de ta zone.</li></ul><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank">Me connecter</a></p>',
        'active' => true,
    ],
    [
        'name' => 'Compte validé par l’équipe',
        'slug' => 'compte-valide',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Ton compte {{site_name}} est validé',
        'preheader' => 'L’espace pair t’est ouvert.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Bonne nouvelle : l’équipe {{site_name}} a validé ton inscription. L’espace pair et la collecte te sont désormais ouverts.</p><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank"><strong>Me connecter</strong></a></p>',
        'active' => true,
    ],
    [
        'name' => 'Alerte routée vers un·e intervenant·e',
        'slug' => 'alerte-routee',
        'category' => 'Alertes',
        'system' => true,
        'subject' => '[{{site_name}}] Nouvelle alerte {{alert_reference}} — {{alert_zone}}',
        'preheader' => 'Une alerte vous a été attribuée.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>L’alerte <strong>{{alert_reference}}</strong> ({{alert_zone}}) vous a été attribuée par le routage automatique.</p><p>Merci de la prendre en charge depuis votre espace dans les meilleurs délais.</p><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank">Ouvrir mon espace</a></p>',
        'active' => true,
    ],
    [
        'name' => 'Rappel d’événement',
        'slug' => 'rappel-evenement',
        'category' => 'Communauté',
        'system' => false,
        'subject' => 'Rappel : {{event_title}}',
        'preheader' => '{{event_date}}',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Petit rappel : <strong>{{event_title}}</strong> a lieu {{event_date}}. Viens avec une amie !</p>',
        'active' => true,
    ],
    [
        'name' => 'Confirmation d’inscription à la newsletter',
        'slug' => 'newsletter-confirmation',
        'category' => 'Newsletter',
        'system' => true,
        'subject' => 'Confirmez votre inscription à la newsletter {{site_name}}',
        'preheader' => 'Un clic pour confirmer.',
        'body' => '<p>Bonjour,</p><p>Merci de votre intérêt pour la newsletter {{site_name}}. Confirmez votre inscription :</p><p><a href="{{activation_link}}" rel="noopener noreferrer" target="_blank"><strong>Confirmer mon inscription</strong></a></p><p>Vous pourrez vous désinscrire à tout moment : <a href="{{unsubscribe_link}}" rel="noopener noreferrer" target="_blank">se désinscrire</a>.</p>',
        'active' => true,
    ],
    [
        // Template système ajouté par P1-09 / P2-05 : confirmation après réinitialisation ou changement du mot de passe.
        'name' => 'Mot de passe modifié',
        'slug' => 'mot-de-passe-modifie',
        'category' => 'Authentification',
        'system' => true,
        'subject' => '{{site_name}} : ton mot de passe a été modifié',
        'preheader' => 'Si ce n’est pas toi, écris-nous tout de suite.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Le mot de passe de ton compte {{site_name}} vient d’être modifié. Par sécurité, toutes tes sessions ont été fermées : reconnecte-toi avec ton nouveau mot de passe.</p><p><a href="{{login_link}}" rel="noopener noreferrer" target="_blank"><strong>Me connecter</strong></a></p><p>Si tu n’es pas à l’origine de ce changement, écris-nous sans attendre à <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>',
        'active' => true,
    ],
    [
        // Lot api-me (P6-06) : confirmation de la suppression du compte (DELETE me), effacement différé de {{deletion_days}} jours.
        'name' => 'Compte supprimé',
        'slug' => 'compte-supprime',
        'category' => 'Authentification',
        'system' => true,
        'subject' => '{{site_name}} : ton compte est supprimé',
        'preheader' => 'Tes données seront effacées le {{deletion_date}}.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Ton compte {{site_name}} (<strong>{{user_pseudo}}</strong>) vient d’être supprimé. Tu es déconnecté·e de tous tes appareils et tu ne recevras plus de notifications.</p><p>Tes données personnelles seront effacées le {{deletion_date}} (sous {{deletion_days}} jours). Tes signalements anonymes déjà transmis restent suivis par l’équipe.</p><p>Tu n’es pas à l’origine de cette suppression, ou tu changes d’avis avant cette date ? Écris-nous à <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>',
        'active' => true,
    ],
    [
        // Lot api-me (P6-06) : lien de téléchargement de l'export RGPD (POST me/export), valable {{export_expiry}}.
        'name' => 'Export de mes données',
        'slug' => 'export-donnees',
        'category' => 'Authentification',
        'system' => true,
        'subject' => '{{site_name}} : ton export de données est prêt',
        'preheader' => 'Lien valable {{export_expiry}}.',
        'body' => '<p>Bonjour {{user_first_name}},</p><p>Tu as demandé une copie de tes données {{site_name}}. Ton fichier est prêt :</p><p><a href="{{export_link}}" rel="noopener noreferrer" target="_blank"><strong>Télécharger mes données</strong></a></p><p>Ce lien est valable {{export_expiry}} et ne fonctionne que connecté·e à ton compte. Le fichier contient des informations personnelles : garde-le en lieu sûr.</p><p>Si tu n’es pas à l’origine de cette demande, change ton mot de passe et écris-nous à <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>',
        'active' => true,
    ],
    [
        // Lot admin-indicateurs-rapports (P10-07) : envoi du fichier d'un rapport généré (planification ou destinataires de
        // l'assistant), en pièce jointe. Adressé à l'équipe et aux partenaires : vouvoiement (administration).
        'name' => 'Rapport généré',
        'slug' => 'rapport-genere',
        'category' => 'Autre',
        'system' => true,
        'subject' => '{{site_name}} : {{report_title}}',
        'preheader' => 'Période {{report_period}}, format {{report_format}}.',
        'body' => '<p>Bonjour,</p><p>Veuillez trouver ci-joint le rapport <strong>{{report_title}}</strong> pour la période {{report_period}} ({{report_format}}).</p><p>Ce document contient des chiffres issus de la plateforme {{site_name}}. Les effectifs inférieurs à 5 y sont masqués. Merci de le conserver en lieu sûr et de ne pas le transférer.</p><p>Une question sur ce rapport ? Écrivez-nous à <a href="mailto:{{support_email}}">{{support_email}}</a>.</p>',
        'active' => true,
    ],
];
